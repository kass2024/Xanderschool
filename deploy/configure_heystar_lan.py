#!/usr/bin/env python3
"""Configure a LAN HeyStar terminal for Xander staff attendance.

Green LED on match, red LED when not found. Upload URLs include school_id.
Default school: WISDOM SCHOOL RWANDA (id 27).
"""
from __future__ import annotations

import json
import os
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from base64 import b64encode

DEVICE_IP = os.environ.get("HEYSTAR_IP", "10.151.53.95")
DEVICE_PORT = os.environ.get("HEYSTAR_PORT", "8090")
PASSWORD = os.environ.get("HEYSTAR_PASSWORD", "123456")
SCHOOL_ID = int(os.environ.get("HEYSTAR_SCHOOL_ID", "27"))
WEB_BASE = os.environ.get(
    "HEYSTAR_WEB", "https://schoolmis.xanderglobalacademy.com"
).rstrip("/")
TIMEOUT = 60


def auth_header(password: str) -> str:
    token = b64encode(f"admin:{password}".encode()).decode()
    return f"Basic {token}"


def device_post(path: str, body, password: str = PASSWORD):
    url = f"http://{DEVICE_IP}:{DEVICE_PORT}/cgi-bin/js/{path.lstrip('/')}"
    data = json.dumps(body).encode("utf-8")
    last = {"code": "ERR", "msg": "no attempt"}
    for attempt in range(4):
        req = urllib.request.Request(
            url,
            data=data,
            method="POST",
            headers={
                "Content-Type": "application/json; charset=UTF-8",
                "Authorization": auth_header(password),
            },
        )
        try:
            with urllib.request.urlopen(req, timeout=TIMEOUT) as resp:
                raw = resp.read().decode("utf-8", "replace")
            parsed = json.loads(raw)
            return parsed
        except urllib.error.HTTPError as e:
            raw = e.read().decode("utf-8", "replace")
            last = {"code": "ERR", "msg": f"HTTP {e.code} {raw[:180]}"}
        except Exception as e:
            last = {"code": "ERR", "msg": str(e)}
        time.sleep(1.5 * (attempt + 1))
    return last


def authed(res: dict) -> bool:
    return str(res.get("code") or "") not in ("401", "ERR")


def ok(res: dict) -> bool:
    return str(res.get("code") or "") == "000"


def web_post(path: str, fields: dict) -> dict:
    data = urllib.parse.urlencode(fields).encode()
    req = urllib.request.Request(f"{WEB_BASE}{path}", data=data, method="POST")
    with urllib.request.urlopen(req, timeout=90) as resp:
        return json.loads(resp.read().decode("utf-8", "replace"))


def main() -> int:
    print("device", DEVICE_IP, DEVICE_PORT, "school_id", SCHOOL_ID, "web", WEB_BASE)
    ping = device_post("device/setPciConfig", {"pciLedAlwaysEnable": 0, "pciLedColorStranger": 1})
    if not authed(ping):
        alt = os.environ.get("HEYSTAR_PASSWORD_ALT", "HFSecurity")
        print("auth failed with default, retry", alt, ping.get("msg"))
        ping = device_post("device/setPciConfig", {"pciLedAlwaysEnable": 0, "pciLedColorStranger": 1}, alt)
        if not authed(ping):
            print("DEVICE UNREACHABLE", ping)
            return 1
        global PASSWORD
        PASSWORD = alt
    print("pci", ping.get("code"), json.dumps(ping.get("data") or {}, ensure_ascii=False)[:400])

    bootstrap = web_post("/api/device_open_school", {"school_id": str(SCHOOL_ID)})
    if int(bootstrap.get("success") or 0) != 1:
        print("WEB OPEN FAILED", bootstrap)
        return 1
    school = bootstrap.get("school") or {}
    name = (school.get("name") or "WISDOM SCHOOL RWANDA")[:48]
    staff = bootstrap.get("staff") or []
    print("school", school.get("id"), name, "staff", len(staff))

    ui = {
        "uiCompanyName": name,
        "uiShowIp": 0,
        "uiShowSn": 0,
        "uiShowPersonCount": 1,
        "uiScreensaverWait": 90,
    }
    logo_url = str(school.get("logo") or "").strip()
    if logo_url:
        try:
            req = urllib.request.Request(logo_url)
            with urllib.request.urlopen(req, timeout=30) as resp:
                raw = resp.read()
            if 80 < len(raw) < 900000:
                ui["uiCompanyLogo"] = b64encode(raw).decode("ascii")
                print("logo bytes", len(raw))
        except Exception as e:
            print("logo skip", e)

    record_vps = f"{WEB_BASE}/api/heystar_record?school_id={SCHOOL_ID}"
    person = f"{WEB_BASE}/api/heystar_person?school_id={SCHOOL_ID}"
    beat = f"{WEB_BASE}/api/heystar_heartbeat?school_id={SCHOOL_ID}"
    relay = os.environ.get("HEYSTAR_RELAY", "").rstrip("/")
    record = f"{relay}/record?school_id={SCHOOL_ID}" if relay else record_vps
    if relay:
        print("record relay", record, "->", record_vps)


    steps = [
        (
            "device/setUploadUrl",
            [
                {"type": 1, "url": beat},
                {"type": 2, "url": record},
                {"type": 3, "url": person},
            ],
        ),
        (
            "device/setSevConfig",
            {
                "sevUploadDevHeartbeatUrl": beat,
                "sevUploadRecRecordUrl": record,
                "sevUploadRegPersonUrl": person,
                "sevUploadRecSnapshotEnable": 1,
                "sevUploadRecStrangerDataEnable": 0,
            },
        ),
        (
            "device/setPciConfig",
            {
                "pciLedAlwaysEnable": 0,
                "pciLedColorStranger": 1,
                "pciRelayOut": 0,
            },
        ),
        (
            "device/setRecModeConfig",
            {
                "recModeCardEnable": 0,
                "recModeFaceEnable": 1,
                "recModeFingerEnable": 0,
                "recModePalmEnable": 0,
            },
        ),
        (
            "device/setRecConfig",
            {
                "recSucTtsMode": 100,
                "recSucTtsCustom": "{name}",
                "recSucDisplayMode": 100,
                "recSucDisplayCustom": "{name}",
                "recStrangerEnable": 1,
                "recIsStrangerTimes": 2,
                "recStrangerTtsMode": 100,
                "recStrangerTtsCustom": "Not found",
                "recStrangerDisplayMode": 100,
                "recStrangerDisplayCustom": "Not found",
                "recStrangerOpenDoor": 0,
            },
        ),
        (
            "device/setUiConfig",
            ui,
        ),
        (
            "device/setCstConfig",
            {
                "attendance_direction_enable": False,
                "recognize_result_countdown": 5000,
                "evt_show_image_duration": 5000,
            },
        ),
    ]
    for path, body in steps:
        res = device_post(path, body)
        print(path, res.get("code"), res.get("msg"))
        if not ok(res):
            print("  FAIL", res)
        time.sleep(0.8)

    merged = 0
    errors = []
    for p in staff:
        sid = int(p.get("id") or 0)
        nm = (str(p.get("name") or "Person"))[:60]
        if sid <= 0:
            continue
        res = device_post(
            "person/merge",
            {"type": 1, "sn": f"T{sid}", "name": nm, "verifyStyle": 1},
        )
        if ok(res):
            merged += 1
        else:
            errors.append(f"T{sid}: {res.get('msg')}")
    print("merged staff", merged, "errors", errors[:8])
    print("urls", record)
    print("LED: always-on OFF, stranger RED, match uses device green")
    print("camera always ready: Select Direction overlay OFF; first look IN, later looks overwrite OUT")
    print("voice: custom TTS on match; IN/OUT spoken and shown after the Xander clock")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
