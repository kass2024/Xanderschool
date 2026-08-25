#!/usr/bin/env python3
"""HeyStar LAN helper: instant CLOCK IN/OUT overlay, local clock rules, offline queue.

Clocks are decided here the same way as Xander (one IN per day, 5 minutes before OUT,
later looks overwrite OUT). Overlay does not wait for the internet. HeyStar stores
records on the device and retries upload when the network is back.
"""
from __future__ import annotations

import json
import math
import os
import struct
import subprocess
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import wave
from base64 import b64encode
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

VPS = os.environ.get(
    "HEYSTAR_WEB", "https://schoolmis.xanderglobalacademy.com"
).rstrip("/")
DEVICE_IP = os.environ.get("HEYSTAR_IP", "10.151.53.95")
DEVICE_PORT = int(os.environ.get("HEYSTAR_PORT", "8090"))
PASSWORD = os.environ.get("HEYSTAR_PASSWORD", "123456")
LISTEN = os.environ.get("HEYSTAR_RELAY_HOST", "0.0.0.0")
PORT = int(os.environ.get("HEYSTAR_RELAY_PORT", "8787"))
SCHOOL_ID = os.environ.get("HEYSTAR_SCHOOL_ID", "27")
OUT_AFTER_IN = 300
STATE_FILE = Path(__file__).with_name("heystar_clock_state.json")
QUEUE_FILE = Path(__file__).with_name("heystar_upload_queue.jsonl")
BEEP_OK = Path(__file__).with_name("heystar_ok.wav")
BEEP_BAD = Path(__file__).with_name("heystar_bad.wav")
REMOTE_OK = "/data/local/tmp/heystar_ok.wav"
REMOTE_BAD = "/data/local/tmp/heystar_bad.wav"

state_lock = threading.Lock()
cgi_lock = threading.Lock()
clock_state: dict = {}


def device_cgi(path: str, body, timeout: int = 8):
    data = json.dumps(body).encode("utf-8")
    auth = "Basic " + b64encode(f"admin:{PASSWORD}".encode()).decode()
    req = urllib.request.Request(
        f"http://{DEVICE_IP}:{DEVICE_PORT}/cgi-bin/js/{path.lstrip('/')}",
        data=data,
        method="POST",
        headers={
            "Content-Type": "application/json; charset=UTF-8",
            "Authorization": auth,
        },
    )
    with cgi_lock:
        with urllib.request.urlopen(req, timeout=timeout) as resp:
            return json.loads(resp.read().decode("utf-8", "replace"))


# Same SMDT unmute the card app uses (ISmdtManagerNew on smdtserver).
AUDIO_SH = (
    "service call smdtserver 216 i32 0;"
    "service call smdtserver 108 i32 0;"
    "service call smdtserver 102 i32 15;"
    "service call smdtserver 104 i32 0 i32 15;"
    "am broadcast -n com.xander.student/.SoundBridgeReceiver -a com.xander.student.SOUND_PREP;"
    "tinymix 0 SPK;"
    "settings put system volume_music 15;"
    "settings put system volume_ring 7;"
    "settings put system volume_alarm 7;"
    "settings put global dock_audio_media_enabled 0"
)


def speaker_on() -> None:
    try:
        subprocess.run(
            ["adb", "shell", AUDIO_SH],
            timeout=10,
            capture_output=True,
        )
    except Exception:
        try:
            subprocess.run(
                ["adb", "shell", "tinymix", "0", "RING_SPK"],
                timeout=8,
                capture_output=True,
            )
        except Exception:
            pass


def keep_speaker_loud() -> None:
    while True:
        speaker_on()
        time.sleep(20)


def write_beep(path: Path, freq: float, seconds: float = 0.45) -> None:
    sr = 44100
    n = int(sr * seconds)
    with wave.open(str(path), "w") as wav:
        wav.setnchannels(2)
        wav.setsampwidth(2)
        wav.setframerate(sr)
        frames = bytearray()
        fade = 900
        for i in range(n):
            env = 1.0
            if i < fade:
                env = i / fade
            elif i > n - fade:
                env = max(0.0, (n - i) / fade)
            sample = int(0.95 * 32767 * env * math.sin(2 * math.pi * freq * i / sr))
            frames += struct.pack("<hh", sample, sample)
        wav.writeframes(bytes(frames))


def ensure_beeps() -> None:
    write_beep(BEEP_OK, 880, 0.4)
    write_beep(BEEP_BAD, 220, 0.55)
    try:
        subprocess.run(["adb", "push", str(BEEP_OK), REMOTE_OK], timeout=15, capture_output=True)
        subprocess.run(["adb", "push", str(BEEP_BAD), REMOTE_BAD], timeout=15, capture_output=True)
        print("beeps pushed")
    except Exception as exc:
        print("beep push", exc)


def play_beep(kind: str) -> None:
    action = "com.xander.student.SOUND_OK" if kind == "ok" else "com.xander.student.SOUND_FAIL"

    def _run() -> None:
        try:
            subprocess.run(["adb", "shell", AUDIO_SH], timeout=12, capture_output=True)
            r = subprocess.run(
                [
                    "adb",
                    "shell",
                    "am",
                    "broadcast",
                    "-n",
                    "com.xander.student/.SoundBridgeReceiver",
                    "-a",
                    action,
                ],
                timeout=8,
                capture_output=True,
                text=True,
            )
            print("beep", kind, (r.stdout or r.stderr or "").strip())
        except Exception as exc:
            print("beep", kind, exc)

    threading.Thread(target=_run, daemon=True).start()


def today() -> str:
    return time.strftime("%Y-%m-%d")


def load_state() -> None:
    global clock_state
    try:
        raw = json.loads(STATE_FILE.read_text(encoding="utf-8"))
        if isinstance(raw, dict):
            clock_state = raw
    except Exception:
        clock_state = {}


def save_state() -> None:
    try:
        STATE_FILE.write_text(json.dumps(clock_state), encoding="utf-8")
    except Exception as exc:
        print("state save", exc)


def decide(sn: str, ts: int, name: str) -> tuple[str, bool]:
    """Return (IN|OUT, already_waiting). Mutates clock_state."""
    day = today()
    st = clock_state.get(sn) or {}
    if st.get("day") != day:
        st = {"day": day, "time_in": 0, "time_out": 0, "name": name}
    if not st.get("time_in"):
        st["time_in"] = ts
        st["time_out"] = 0
        st["name"] = name
        clock_state[sn] = st
        return "IN", False
    if not st.get("time_out") and int(st["time_in"]) + OUT_AFTER_IN > ts:
        st["name"] = name
        clock_state[sn] = st
        return "IN", True
    st["time_out"] = ts
    st["name"] = name
    clock_state[sn] = st
    return "OUT", False


def announce(status: str) -> None:
    status = "OUT" if str(status).upper() == "OUT" else "IN"
    label = "CLOCK OUT" if status == "OUT" else "CLOCK IN"
    speaker_on()
    play_beep("ok")
    content = json.dumps({"displayContent": label})
    try:
        device_cgi("device/output", {"type": 1}, timeout=1.5)
        device_cgi("device/output", {"type": 4, "content": content}, timeout=1.5)
        print("announce", label)
    except Exception as exc:
        print("announce fail", exc)


def fetch_records(start_ms: int, end_ms: int, length: int, order: int):
    j = device_cgi(
        "record/findList",
        {
            "startTime": start_ms,
            "endTime": end_ms,
            "index": 1,
            "length": length,
            "order": order,
            "recordType": 1,
        },
        timeout=10,
    )
    rows = j.get("data") or []
    if isinstance(rows, dict):
        rows = [rows]
    return rows


def record_ts(row: dict) -> int:
    t = int(row.get("createTime") or row.get("recordTime") or 0)
    if t > 20000000000:
        t = int(t / 1000)
    return t if t > 1000000000 else int(time.time())


def poll_device_records() -> None:
    load_state()
    last_id = 0
    while last_id == 0:
        try:
            now_ms = int(time.time() * 1000)
            start = now_ms - 86400000
            rows = fetch_records(start, now_ms + 60000, 20, 0)
            with state_lock:
                for row in rows:
                    sn = str(row.get("personSn") or "")
                    rid = int(row.get("id") or 0)
                    last_id = max(last_id, rid)
                    if sn.startswith("T"):
                        decide(sn, record_ts(row), str(row.get("personName") or ""))
                save_state()
            if last_id == 0:
                last_id = 1
            print("poll start last_id", last_id, "staff", len(clock_state))
        except Exception as exc:
            print("poll init", exc)
            time.sleep(4)

    while True:
        time.sleep(0.7)
        try:
            now_ms = int(time.time() * 1000)
            rows = fetch_records(now_ms - 180000, now_ms + 60000, 8, 0)
            batch = []
            max_id = last_id
            for row in rows:
                rid = int(row.get("id") or 0)
                if rid <= last_id:
                    continue
                max_id = max(max_id, rid)
                batch.append(row)
            last_id = max_id
            batch.sort(key=lambda r: int(r.get("id") or 0))
            for row in batch:
                sn = str(row.get("personSn") or "")
                stranger = int(row.get("strangerFlag") or 0) == 1 or int(row.get("resultFlag") or 1) == 2
                if stranger or not sn.startswith("T"):
                    play_beep("bad")
                    print("announce RED")
                    continue
                name = str(row.get("personName") or "")
                with state_lock:
                    status, already = decide(sn, record_ts(row), name)
                    now = time.time()
                    st = clock_state.get(sn) or {}
                    last = float(st.get("last_announce") or 0)
                    if already or (now - last) < 8:
                        save_state()
                        continue
                    st["last_announce"] = now
                    clock_state[sn] = st
                    save_state()
                announce(status)
        except Exception as exc:
            print("poll", exc)


def queue_payload(query: str, ctype: str, raw: bytes) -> None:
    try:
        with QUEUE_FILE.open("a", encoding="utf-8") as fh:
            fh.write(json.dumps({
                "query": query,
                "ctype": ctype,
                "raw": raw.decode("utf-8", "replace"),
                "ts": int(time.time()),
            }) + "\n")
    except Exception as exc:
        print("queue write", exc)


def flush_queue() -> None:
    if not QUEUE_FILE.exists():
        return
    lines = QUEUE_FILE.read_text(encoding="utf-8").splitlines()
    keep = []
    for line in lines:
        if not line.strip():
            continue
        try:
            item = json.loads(line)
            dest = f"{VPS}/api/heystar_record"
            if item.get("query"):
                dest += "?" + item["query"]
            raw = str(item.get("raw") or "").encode("utf-8")
            req = urllib.request.Request(
                dest,
                data=raw,
                method="POST",
                headers={"Content-Type": item.get("ctype") or "application/json"},
            )
            with urllib.request.urlopen(req, timeout=20) as resp:
                resp.read()
        except Exception:
            keep.append(line)
    QUEUE_FILE.write_text("\n".join(keep) + ("\n" if keep else ""), encoding="utf-8")


def retry_uploads() -> None:
    while True:
        time.sleep(20)
        try:
            flush_queue()
        except Exception as exc:
            print("retry", exc)


def vps_get(path: str, timeout: int = 20):
    url = VPS + path
    req = urllib.request.Request(url, method="GET")
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return json.loads(resp.read().decode("utf-8", "replace"))


def vps_post(path: str, body: dict, timeout: int = 20):
    data = json.dumps(body).encode("utf-8")
    req = urllib.request.Request(
        path if path.startswith("http") else (VPS + path),
        data=data,
        method="POST",
        headers={"Content-Type": "application/json"},
    )
    with urllib.request.urlopen(req, timeout=timeout) as resp:
        return json.loads(resp.read().decode("utf-8", "replace"))


def device_persons() -> dict:
    found = {}
    index = 1
    while index <= 30:
        j = device_cgi("person/findList", {"index": index, "length": 40}, timeout=12)
        rows = j.get("data") or []
        if isinstance(rows, dict):
            rows = [rows]
        if not rows:
            break
        for row in rows:
            sn = str(row.get("sn") or "").strip()
            if sn:
                found[sn] = str(row.get("name") or "").strip()
        if len(rows) < 40:
            break
        index += 1
    return found


def apply_voice_config() -> None:
    try:
        device_cgi(
            "device/setPciConfig",
            {
                "pciLedAlwaysEnable": 0,
                "pciLedColorStranger": 1,
                "pciRelayOut": 1,
                "pciRelayMode": 1,
                "pciRelayDelay": 2000,
            },
            timeout=12,
        )
        device_cgi(
            "device/setRecConfig",
            {
                "recRank": 2,
                "recThreshold1vN": 72,
                "recThreshold1v1": 65,
                "recSucTtsMode": 2,
                "recSucDisplayMode": 1,
                "recRecordUploadMode": 2,
                "recRecordSave": 1,
                "recStrangerEnable": 1,
                "recIsStrangerTimes": 1,
                "recStrangerTtsMode": 2,
                "recStrangerDisplayMode": 1,
                "recStrangerOpenDoor": 0,
                "recNoPerTtsMode": 2,
                "recNotBioTtsMode": 2,
                "recNotBioDisplayMode": 1,
            },
            timeout=12,
        )
        print("io+voice: relay ON, monocular liveness, match threshold 72, built-in green/red TTS")
    except Exception as exc:
        print("voice cfg", exc)


def sync_staff_once() -> int:
    payload = vps_get(f"/api/heystar_staff?school_id={SCHOOL_ID}")
    staff = payload.get("staff") or []
    existing = {}
    try:
        existing = device_persons()
    except Exception as exc:
        print("person list", exc)
        return 0
    merged = 0
    for person in staff:
        sn = str(person.get("sn") or "")
        name = str(person.get("name") or "Person")[:60]
        if not sn.startswith("T"):
            continue
        if existing.get(sn) == name:
            continue
        res = device_cgi(
            "person/merge",
            {"type": 1, "sn": sn, "name": name, "verifyStyle": 1},
            timeout=10,
        )
        if str(res.get("code") or "") == "000":
            merged += 1
            print("staff merge", sn, name)
    try:
        vps_post("/api/heystar_staff_synced", {"school_id": int(SCHOOL_ID)})
    except Exception:
        pass
    return merged


def sync_staff_loop() -> None:
    time.sleep(4)
    apply_voice_config()
    while True:
        try:
            n = sync_staff_once()
            print("staff sync merged", n)
        except Exception as exc:
            print("staff sync", exc)
        time.sleep(25)


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
        out = json.dumps({"result": 1, "code": "000"}).encode()
        code = 200
        try:
            req = urllib.request.Request(
                dest,
                data=raw,
                method="POST",
                headers={"Content-Type": ctype},
            )
            with urllib.request.urlopen(req, timeout=12) as resp:
                out = resp.read()
                code = resp.status
        except Exception:
            queue_payload(parsed.query, ctype, raw)
            code = 200
            out = json.dumps({"result": 1, "code": "000"}).encode()

        try:
            payload = json.loads(out.decode("utf-8", "replace"))
        except Exception:
            payload = {}
        status = str(payload.get("status") or "")
        if status in ("IN", "OUT") and not payload.get("already"):
            announce(status)

        self.send_response(code if 200 <= code < 600 else 200)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(out)))
        self.end_headers()
        self.wfile.write(out)


def main() -> int:
    ensure_beeps()
    play_beep("ok")
    threading.Thread(target=keep_speaker_loud, daemon=True).start()
    threading.Thread(target=poll_device_records, daemon=True).start()
    threading.Thread(target=retry_uploads, daemon=True).start()
    threading.Thread(target=sync_staff_loop, daemon=True).start()
    httpd = ThreadingHTTPServer((LISTEN, PORT), Handler)
    print("heystar overlay+offline", f"http://{LISTEN}:{PORT}/record", "device", f"{DEVICE_IP}:{DEVICE_PORT}")
    httpd.serve_forever()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
