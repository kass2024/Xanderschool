"""Parse the Kayonza school-information workbook into import JSON.

The workbook contains school metadata, one row of class columns, and a
separate staff table.  It is intentionally kept as a small, deterministic
parser so the PHP importer can be rerun safely.
"""
from __future__ import annotations

import json
import re
from pathlib import Path

import openpyxl


SOURCE = Path(r"C:\Users\user\Downloads\school information.xlsx")
OUTPUT = Path(r"C:\xampp7\htdocs\Xander-school\deploy\_wisdom_kayonza_school_information.json")


def clean(value: object) -> str:
    return re.sub(r"\s+", " ", str(value or "")).strip()


def split_name(value: str) -> tuple[str, str]:
    parts = clean(value).split(" ")
    if not parts:
        return "", ""
    if len(parts) == 1:
        return parts[0], ""
    return parts[0], " ".join(parts[1:])


def field_value(value: object) -> str:
    text = clean(value)
    return text.split(":", 1)[1].strip() if ":" in text else text


def main() -> None:
    workbook = openpyxl.load_workbook(SOURCE, data_only=True, read_only=True)
    sheet = workbook.active
    rows = [list(row) for row in sheet.iter_rows(values_only=True)]

    metadata: dict[str, str] = {}
    for row in rows[:12]:
        text = clean(row[0] if row else "")
        if "." not in text or ":" not in text:
            continue
        key, value = text.split(":", 1)
        metadata[re.sub(r"^\d+\.", "", key).strip().lower()] = value.strip()

    class_header_index = next(
        index
        for index, row in enumerate(rows)
        if clean(row[0] if row else "").lower() == "n0"
    )
    class_headers = [clean(value).upper() for value in rows[class_header_index]]
    classes: list[dict[str, str]] = []
    for row in rows[class_header_index + 1 :]:
        number = clean(row[0] if row else "")
        if not number.isdigit():
            if classes:
                break
            continue
        for column, label in enumerate(class_headers[1:], start=1):
            name = clean(row[column] if column < len(row) else "")
            if not name:
                continue
            fname, lname = split_name(name)
            classes.append(
                {
                    "class_label": label,
                    "full_name": name,
                    "fname": fname,
                    "lname": lname,
                }
            )

    staff_header_index = next(
        index
        for index, row in enumerate(rows)
        if [clean(value).upper() for value in row[:4]]
        == ["NO", "NAMES", "TEL", "POSITION"]
    )
    staff: list[dict[str, str]] = []
    for row in rows[staff_header_index + 1 :]:
        number = clean(row[0] if row else "")
        name = clean(row[1] if len(row) > 1 else "")
        if not number.isdigit() or not name:
            continue
        fname, lname = split_name(name)
        staff.append(
            {
                "full_name": name,
                "fname": fname,
                "lname": lname,
                "phone": clean(row[2] if len(row) > 2 else ""),
                "position": clean(row[3] if len(row) > 3 else ""),
            }
        )

    result = {
        "school": {
            "name": metadata.get("school name", ""),
            "director": metadata.get("director name", ""),
            "phones": metadata.get("school phone number", ""),
            "email": metadata.get("school email", ""),
            "motto": metadata.get("school moto", ""),
            "slogan": metadata.get("school moto and sloga", ""),
            "accountant": metadata.get("accountant name", ""),
            "accountant_phone": metadata.get("accountant phone number", ""),
            "accountant_email": metadata.get("accountant email", ""),
        },
        "classes": classes,
        "staff": staff,
    }
    OUTPUT.write_text(json.dumps(result, ensure_ascii=False, indent=2), encoding="utf-8")
    counts: dict[str, int] = {}
    for row in classes:
        counts[row["class_label"]] = counts.get(row["class_label"], 0) + 1
    print(json.dumps({"classes": counts, "students": len(classes), "staff": len(staff)}, indent=2))
    print(f"wrote {OUTPUT}")


if __name__ == "__main__":
    main()
