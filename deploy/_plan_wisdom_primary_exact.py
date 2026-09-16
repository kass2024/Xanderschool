#!/usr/bin/env python3
"""Plan exact Excel primary lists: keep matches, lock extras, create remaining Excel names."""
from __future__ import annotations

import json
import re
from collections import Counter, defaultdict
from difflib import SequenceMatcher
from pathlib import Path

EXCEL = Path(r"C:\xampp7\htdocs\Xander-school\deploy\_primary_list_2026_parsed.json")
LIVE = Path(r"C:\xampp7\htdocs\Xander-school\deploy\_wisdom_primary_live.json")
OUT = Path(r"C:\xampp7\htdocs\Xander-school\deploy\_primary_exact_plan.json")


def compact(s: str) -> str:
    s = (s or "").upper().replace("É", "E").replace("È", "E").replace("Ê", "E").replace("À", "A")
    s = s.replace("’", "").replace("'", "").replace("`", "")
    return re.sub(r"[^A-Z]", "", s)


def tokens(s: str) -> list[str]:
    s = (s or "").upper().replace("É", "E").replace("È", "E").replace("Ê", "E")
    return re.findall(r"[A-Z]+", s)


def class_key(level: str, stream: str) -> str:
    level = (level or "").strip().upper()
    stream = (stream or "").strip().upper()
    if level == "P1":
        return "P1"
    return f"{level}{stream}" if stream else level


def sheet_level(sheet: str) -> str:
    m = re.match(r"(P[1-6])", (sheet or "").upper())
    return m.group(1) if m else ""


def level_num(level: str) -> int:
    m = re.search(r"(\d)", level or "")
    return int(m.group(1)) if m else 0


def ratio(a: str, b: str) -> float:
    if not a or not b:
        return 0.0
    return SequenceMatcher(None, a, b).ratio()


def split_name(full: str) -> tuple[str, str]:
    parts = re.sub(r"\s+", " ", (full or "").strip()).split(" ")
    if not parts:
        return "", ""
    if len(parts) == 1:
        return parts[0], ""
    return parts[0], " ".join(parts[1:])


def live_rows() -> list[dict]:
    rows = []
    for r in json.loads(LIVE.read_text(encoding="utf-8"))["students"]:
        if str(r.get("rec_status") or "") not in ("1", "1.0"):
            continue
        if str(r.get("status") or "") not in ("1", "2"):
            continue
        r = dict(r)
        r["id"] = int(r["id"])
        r["ckey"] = class_key(r.get("level_name", ""), r.get("stream", ""))
        r["full"] = " ".join(x for x in [(r.get("fname") or "").strip(), (r.get("lname") or "").strip()] if x)
        r["photo"] = (r.get("photo") or "").strip()
        rows.append(r)
    return rows


def take_best(src: dict, cands: list[dict], used: set[int], min_ratio: float, same_family: bool) -> dict | None:
    want_c = compact(src["name"])
    want_t = tokens(src["name"])
    best = None
    for cand in cands:
        if cand["id"] in used:
            continue
        got_c = compact(cand["full"])
        r = 1.0 if want_c and want_c == got_c else ratio(want_c, got_c)
        if same_family:
            got_t = tokens(cand["full"])
            if not (want_t and got_t and (want_t[0] == got_t[0] or ratio(want_t[0], got_t[0]) >= 0.84)):
                continue
            if r < min_ratio:
                continue
        elif r < min_ratio and want_c != got_c:
            continue
        if best is None or r > best[0]:
            best = (r, cand)
    return None if best is None else best[1]


def pack_keep(src: dict, cand: dict, how: str, score: float) -> dict:
    nf, nl = split_name(src["name"])
    mode_map = {"boarding": "0", "day": "1"}
    return {
        "sheet": src["sheet"].upper(),
        "excel": src["name"],
        "excel_status": src["status"],
        "id": cand["id"],
        "regno": cand["regno"],
        "old": cand["full"],
        "old_class": cand["ckey"],
        "old_class_id": int(cand["class_id"]),
        "new_fname": nf,
        "new_lname": nl,
        "want_mode": mode_map.get(src["status"], ""),
        "photo": cand["photo"],
        "how": how,
        "score": round(score * 100, 1),
        "class_change": cand["ckey"] != src["sheet"].upper(),
    }


def main() -> None:
    excel = json.loads(EXCEL.read_text(encoding="utf-8"))["students"]
    live = live_rows()
    by_class = defaultdict(list)
    for r in live:
        by_class[r["ckey"]].append(r)

    used: set[int] = set()
    keep: list[dict] = []
    pending = list(excel)

    def consume(min_ratio: float, same_family: bool, how: str, cross_class: bool) -> None:
        nonlocal pending
        still = []
        for src in pending:
            sheet = src["sheet"].upper()
            if cross_class:
                cands = live
            else:
                cands = by_class.get(sheet, [])
            cand = take_best(src, cands, used, min_ratio, same_family)
            if cand is None:
                still.append(src)
                continue
            if cross_class:
                src_lv = level_num(sheet_level(sheet))
                cand_lv = level_num(cand.get("level_name", ""))
                if abs(src_lv - cand_lv) > 1:
                    still.append(src)
                    continue
            used.add(cand["id"])
            sc = ratio(compact(src["name"]), compact(cand["full"]))
            if compact(src["name"]) == compact(cand["full"]):
                sc = 1.0
            keep.append(pack_keep(src, cand, how, sc))
        pending = still

    consume(1.0, False, "exact-class", False)
    consume(0.92, False, "fuzzy-class", False)

    ALIASES = {
        ("P1", "MUSANGO ALLAN"): "260270328",
        ("P2B", "NZIZA SELAPHIN ARNOLD"): "260270084",
    }
    by_regno = {str(r["regno"]): r for r in live}
    still = []
    for src in pending:
        key = (src["sheet"].upper(), src["name"])
        cand = by_regno.get(ALIASES.get(key, ""))
        if not cand or cand["id"] in used:
            still.append(src)
            continue
        used.add(cand["id"])
        sc = ratio(compact(src["name"]), compact(cand["full"]))
        keep.append(pack_keep(src, cand, "alias", sc if sc else 0.99))
    pending = still

    consume(0.92, False, "adjacent-cross", True)

    extras = [r for r in live if r["id"] not in used]
    create = []
    for src in pending:
        nf, nl = split_name(src["name"])
        mode_map = {"boarding": "0", "day": "1"}
        create.append({
            "sheet": src["sheet"].upper(),
            "excel": src["name"],
            "new_fname": nf,
            "new_lname": nl,
            "want_mode": mode_map.get(src["status"], "1"),
        })

    lock = [{
        "id": r["id"],
        "regno": r["regno"],
        "name": r["full"],
        "class": r["ckey"],
        "photo": r["photo"],
        "old_class_id": int(r["class_id"]),
    } for r in extras]

    moves = [k for k in keep if k["class_change"]]
    names = [k for k in keep if compact(k["old"]) != compact(k["excel"])]
    print("EXCEL", len(excel), "LIVE_ACTIVE", len(live))
    print("KEEP", len(keep), "LOCK", len(lock), "CREATE", len(create), "MOVES", len(moves), "RENAME", len(names))
    print("KEEP_HOW", Counter(k["how"] for k in keep))
    print("KEEP_BY_CLASS", dict(Counter(k["sheet"] for k in keep)))
    print("LOCK_BY_CLASS", dict(Counter(x["class"] for x in lock)))
    print("CREATE_BY_CLASS", dict(Counter(x["sheet"] for x in create)))
    print("\nLAST-CHANCE KEEPS")
    for k in keep:
        if k["how"] not in ("exact-class",):
            print(f"  {k['how']:16} {k['score']:5.1f} {k['old_class']:4}->{k['sheet']:4} {k['old']} => {k['excel']}")
    print("\nCREATE")
    for c in create:
        print(f"  {c['sheet']:4} {c['excel']}")
    print("\nLOCK sample", len(lock))
    for x in lock[:12]:
        print(f"  {x['class']:4} {x['name']} ({x['regno']})")
    if len(lock) > 12:
        print("  ...")
    OUT.write_text(json.dumps({"keep": keep, "lock": lock, "create": create}, indent=2), encoding="utf-8")
    print("WROTE", OUT)


if __name__ == "__main__":
    main()
