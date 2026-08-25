#!/usr/bin/env python3
"""LAN relay: HeyStar record upload -> Xander VPS, then show/speak IN or OUT on the terminal.

The VPS cannot reach the terminal's private IP, so this must run on the school LAN.
Keep this process running during school hours.
"""
from __future__ import annotations

import json
import os
import subprocess
import threading
import time
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
SCHOOL_ID = os.environ.get("HEYSTAR_SCHOOL_ID", "27")


def device_cgi(path: str, body, timeout: int = 8):
    data = json.dumps(body).encode("utf-8")
    auth = "Basic " + b64encode(f"admin:{PASSWORD}".encode()).decode()
    req = urllib.request.Request(
        f"http://{DEVICE_IP}:8090/cgi-bin/js/{path.lstrip('/')}",
        data=data,
        method="POST",
        headers={
            "Content-Type": "application/json; charset=UTF-8",
            "Authorization": auth,
        },
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return json.loads(resp.read().decode("utf-8", "replace"))


def keep_speaker_loud() -> None:
    while True:
        try:
            subprocess.run(
                ["adb", "shell", "tinymix", "0", "SPK"],
                timeout=8,
                capture_output=True,
            )
        except Exception:
            pass
        time.sleep(40)


def announce(name: str, status: str) -> None:
    status = "OUT" if str(status).upper() == "OUT" else "IN"
    label = "CLOCK OUT" if status == "OUT" else "CLOCK IN"
    spoken = "Clock out" if status == "OUT" else "Clock in"
    safe = " ".join("".join(ch if ch.isalnum() or ch == " " else " " for ch in (name or "")).split())
    display = f"{label} {safe}".strip() if safe else label
    content = json.dumps({"ttsContent": spoken, "displayContent": display})
    try:
        device_cgi("device/output", {"type": 4, "content": content}, timeout=2)
        print("announce", display)
    except Exception as exc:
        print("announce fail", exc)


def peek_clock(sn: str) -> dict:
    url = f"{VPS}/api/heystar_last_clock?school_id={SCHOOL_ID}&sn={urllib.parse.quote(sn)}"
    req = urllib.request.Request(url, method="GET")
    with urllib.request.urlopen(req, timeout=8) as resp:
        return json.loads(resp.read().decode("utf-8", "replace"))


def poll_device_records() -> None:
    last_id = 0
    last_shown = {}
    try:
        now_ms = int(time.time() * 1000)
        j = device_cgi(
            "record/findList",
            {
                "startTime": now_ms - 86400000,
                "endTime": now_ms + 60000,
                "index": 1,
                "length": 5,
                "order": 0,
                "recordType": 1,
            },
        )
        rows = j.get("data") or []
        if isinstance(rows, dict):
            rows = [rows]
        for row in rows:
            last_id = max(last_id, int(row.get("id") or 0))
        print("poll start last_id", last_id)
    except Exception as exc:
        print("poll init", exc)

    while True:
        time.sleep(0.8)
        try:
            now_ms = int(time.time() * 1000)
            j = device_cgi(
                "record/findList",
                {
                    "startTime": now_ms - 120000,
                    "endTime": now_ms + 60000,
                    "index": 1,
                    "length": 5,
                    "order": 0,
                    "recordType": 1,
                },
            )
            rows = j.get("data") or []
            if isinstance(rows, dict):
                rows = [rows]
            for row in reversed(rows):
                rid = int(row.get("id") or 0)
                sn = str(row.get("personSn") or "")
                if rid <= last_id or not sn.startswith("T"):
                    continue
                last_id = max(last_id, rid)
                time.sleep(0.4)
                peek = peek_clock(sn)
                status = str(peek.get("status") or "")
                if status not in ("IN", "OUT") or peek.get("already"):
                    continue
                now = time.time()
                prev = last_shown.get(sn)
                if prev and prev[0] == status and (now - prev[1]) < 8:
                    continue
                last_shown[sn] = (status, now)
                announce(str(peek.get("name") or row.get("personName") or ""), status)
        except Exception as exc:
            print("poll", exc)


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
                for token in ("CLOCK OUT", "CLOCK IN", "Clock out", "Clock in", " OUT", " IN"):
                    line = line.replace(token, "")
                name = line.strip()
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
    threading.Thread(target=keep_speaker_loud, daemon=True).start()
    threading.Thread(target=poll_device_records, daemon=True).start()
    httpd = ThreadingHTTPServer((LISTEN, PORT), Handler)
    print("heystar feedback relay", f"http://{LISTEN}:{PORT}/record", "->", VPS, "device", DEVICE_IP)
    httpd.serve_forever()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
