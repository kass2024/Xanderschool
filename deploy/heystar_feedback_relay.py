#!/usr/bin/env python3
"""LAN relay: HeyStar record upload -> Xander VPS, then show/speak IN or OUT on the terminal.

The VPS cannot reach the terminal's private IP, so this must run on the school LAN.
Keep this process running during school hours.
"""
from __future__ import annotations

import json
import os
import urllib.error
import urllib.parse
import urllib.request
from base64 import b64encode
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

VPS = os.environ.get(
    "HEYSTAR_WEB", "https://schoolmis.xanderglobalacademy.com"
).rstrip("/")
DEVICE_IP = os.environ.get("HEYSTAR_IP", "10.151.53.95")
PASSWORD = os.environ.get("HEYSTAR_PASSWORD", "123456")
LISTEN = os.environ.get("HEYSTAR_RELAY_HOST", "0.0.0.0")
PORT = int(os.environ.get("HEYSTAR_RELAY_PORT", "8787"))


def announce(name: str, status: str) -> None:
    status = "OUT" if str(status).upper() == "OUT" else "IN"
    safe = " ".join("".join(ch if ch.isalnum() or ch == " " else " " for ch in (name or "")).split())
    line = " ".join(part for part in (safe, status) if part) or status
    content = json.dumps({"ttsContent": line, "displayContent": line})
    body = json.dumps({"type": 4, "content": content}).encode("utf-8")
    auth = "Basic " + b64encode(f"admin:{PASSWORD}".encode()).decode()
    req = urllib.request.Request(
        f"http://{DEVICE_IP}:8090/cgi-bin/js/device/output",
        data=body,
        method="POST",
        headers={
            "Content-Type": "application/json; charset=UTF-8",
            "Authorization": auth,
        },
    )
    try:
        with urllib.request.urlopen(req, timeout=2) as resp:
            resp.read()
    except Exception as exc:
        print("announce fail", exc)


class Handler(BaseHTTPRequestHandler):
    def log_message(self, fmt: str, *args) -> None:
        print("relay", self.address_string(), fmt % args)

    def do_POST(self) -> None:
        parsed = urllib.parse.urlparse(self.path)
        length = int(self.headers.get("Content-Length") or 0)
        raw = self.rfile.read(length) if length else b""
        ctype = self.headers.get("Content-Type") or "application/json; charset=UTF-8"
        dest = f"{VPS}/api/heystar_record"
        if parsed.query:
            dest = dest + "?" + parsed.query
        req = urllib.request.Request(
            dest,
            data=raw,
            method="POST",
            headers={"Content-Type": ctype},
        )
        try:
            with urllib.request.urlopen(req, timeout=25) as resp:
                out = resp.read()
                code = resp.status
        except urllib.error.HTTPError as exc:
            out = exc.read()
            code = exc.code
        except Exception as exc:
            out = json.dumps({"result": 1, "code": "000", "msg": str(exc)}).encode()
            code = 200

        status = ""
        name = ""
        try:
            payload = json.loads(out.decode("utf-8", "replace"))
            status = str(payload.get("status") or "")
            name = str((payload.get("person") or {}).get("name") or "")
            if not name:
                line = str(payload.get("displayContent") or payload.get("ttsContent") or "")
                name = line.replace(" IN", "").replace(" OUT", "").strip()
        except Exception:
            payload = {}

        if status in ("IN", "OUT") and not payload.get("already"):
            announce(name, status)

        self.send_response(code if 200 <= code < 600 else 200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(out)))
        self.end_headers()
        self.wfile.write(out)


def main() -> int:
    httpd = ThreadingHTTPServer((LISTEN, PORT), Handler)
    print("heystar feedback relay", f"http://{LISTEN}:{PORT}/record", "->", VPS, "device", DEVICE_IP)
    httpd.serve_forever()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
