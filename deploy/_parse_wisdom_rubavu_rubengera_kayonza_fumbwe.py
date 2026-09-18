"""Parse Rubavu, Rubengera, Kayonza, and Fumbwe into separate import JSON files."""
from __future__ import annotations

import json
import re
import shutil
import zipfile
from io import BytesIO
from pathlib import Path
from xml.etree import ElementTree as ET

import openpyxl
import xlrd
from PIL import Image

SCHOOL_ROOTS = {
    "rubavu": Path(r"C:\methode\15 Wisdoms\13.Wisdom Rubavu"),
    "rubengera": Path(r"C:\methode\15 Wisdoms\7.WISDOMSCHOOL RUBENGERA"),
    "kayonza": Path(r"C:\methode\15 Wisdoms\2.Wisdom Kayonza"),
    "fumbwe": Path(r"C:\methode\15 Wisdoms\14.Wisdom Fumbwe"),
}
DEPLOY = Path(r"C:\xampp7\htdocs\Xander-school\deploy")


def clean(value: object) -> str:
    return re.sub(r"\s+", " ", str(value or "")).strip(" \t:-.")


def split_name(value: str) -> tuple[str, str]:
    parts = clean(value).split(" ")
    if not parts or parts == [""]:
        return "", ""
    if len(parts) == 1:
        return parts[0], ""
    return parts[0], " ".join(parts[1:])


def compact(value: str) -> str:
    return re.sub(r"[^A-Z0-9]", "", value.upper())


def clean_phone(value: object) -> str:
    text = clean(value).replace("O", "0").replace("o", "0")
    if "/" in text:
        text = text.split("/")[0]
    digits = re.sub(r"[^0-9]", "", text)
    if len(digits) == 9 and digits.startswith("7"):
        digits = "0" + digits
    if len(digits) == 11 and digits.startswith("07"):
        digits = digits[:10]
    return digits[:20]


def clean_email(value: object) -> str:
    text = clean(value).lower().replace(" ", "")
    if not text or text in {"-", "n/a", "na"}:
        return ""
    text = text.replace("gmal.com", "gmail.com").replace("gmai.com", "gmail.com")
    text = text.replace(".@mail.com", "@gmail.com").replace("@mail.com", "@gmail.com")
    text = re.sub(r"@(\d+)@gmail\.com$", r"\1@gmail.com", text)
    text = text.replace("@@", "@")
    if "gmail" in text and "@" not in text:
        text = re.sub(r"gmail\.com$", "@gmail.com", text)
    return text[:100]


def clean_sex(value: object) -> str:
    text = clean(value).upper()
    if text in {"M", "MALE", "BOY"}:
        return "M"
    if text in {"F", "FEMALE", "GIRL"}:
        return "F"
    return "U"


def normalize_position(value: str) -> str:
    compact_text = re.sub(r"[^A-Z]", "", clean(value).upper())
    if "DEPUTY" in compact_text:
        return "DEPUTY HEAD TEACHER"
    if "HEAD" in compact_text:
        return "HEAD TEACHER"
    if "DOS" in compact_text:
        return "DOS"
    if "ACCOUNTANT" in compact_text or "BURSAR" in compact_text or "COMPTABLE" in compact_text:
        return "ACCOUNTANT"
    return "TEACHER"


def staff_row(name: str, position: str, phone: str = "", email: str = "", photo: str = "") -> dict[str, str]:
    fname, lname = split_name(name)
    return {
        "full_name": clean(name),
        "fname": fname,
        "lname": lname,
        "position": normalize_position(position),
        "phone": clean_phone(phone),
        "email": clean_email(email),
        "photo": photo,
    }


def student_row(class_label: str, name: str, sex: str = "U", sheet: str = "") -> dict[str, str]:
    fname, lname = split_name(name)
    return {
        "class_label": class_label,
        "sheet": sheet,
        "full_name": clean(name),
        "fname": fname,
        "lname": lname,
        "sex": clean_sex(sex),
    }


def write_payload(slug: str, school: dict, students: list, staff: list, photos: list) -> None:
    by_class: dict[str, int] = {}
    for student in students:
        by_class[student["class_label"]] = by_class.get(student["class_label"], 0) + 1
    payload = {"school": school, "classes": students, "staff": staff, "photos": photos, "skipped": []}
    out = DEPLOY / f"_wisdom_{slug}_school_information.json"
    out.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({"school": slug, "students": len(students), "staff": len(staff), "photos": len(photos), "by_class": by_class, "head": school.get("head_teacher")}, indent=2))
    for row in staff:
        print(" -", row["full_name"], "|", row["position"], "|", row["phone"], "|", row["email"], "|", row.get("photo", ""))
    print("Wrote", out)


def photo_dir(slug: str) -> Path:
    path = DEPLOY / f"_wisdom_{slug}_staff_photos"
    if path.exists():
        shutil.rmtree(path)
    path.mkdir(parents=True)
    return path


def ahash(data: bytes, size: int = 8) -> str:
    image = Image.open(BytesIO(data)).convert("L").resize((size, size))
    pixels = list(image.getdata())
    avg = sum(pixels) / max(len(pixels), 1)
    return "".join("1" if pixel > avg else "0" for pixel in pixels)


def hamming(a: str, b: str) -> int:
    return sum(x != y for x, y in zip(a, b))


def save_photo(folder: Path, index: int, name: str, data: bytes) -> str:
    slug = re.sub(r"[^A-Za-z0-9]+", "_", name).strip("_") or f"staff{index}"
    filename = f"{index:02d}_{slug}.jpg"
    image = Image.open(BytesIO(data)).convert("RGB")
    image.save(folder / filename, "JPEG", quality=92)
    return filename


def extract_xls_images(path: Path) -> list[bytes]:
    """Pull embedded PNG/JPEG from a BIFF8 .xls Workbook stream.

    Excel splits blips across MSODRAWING/CONTINUE records, so a raw file scan
    cannot open the bytes. Concatenate those records first, then walk PNG chunks.
    """
    try:
        import olefile
    except ImportError:
        raise SystemExit("olefile is required to extract Rubavu .xls photos")

    ole = olefile.OleFileIO(str(path))
    data = ole.openstream("Workbook").read()
    parts: list[bytes] = []
    pos = 0
    while pos + 4 <= len(data):
        rtype = int.from_bytes(data[pos : pos + 2], "little")
        rlen = int.from_bytes(data[pos + 2 : pos + 4], "little")
        if rlen > 8224:
            pos += 1
            continue
        if rtype in (0x00EB, 0x003C):  # MSODRAWING, CONTINUE
            parts.append(data[pos + 4 : pos + 4 + rlen])
        pos += 4 + rlen
    blob = b"".join(parts)
    png_sig = b"\x89PNG" + bytes([13, 10, 26, 10])
    images: list[bytes] = []
    idx = 0
    while True:
        jpeg_at = blob.find(b"\xff\xd8\xff", idx)
        png_at = blob.find(png_sig, idx)
        cands = [item for item in (jpeg_at, png_at) if item >= 0]
        if not cands:
            break
        start = min(cands)
        picture = b""
        if start == png_at:
            cursor = start + 8
            while cursor + 8 <= len(blob):
                length = int.from_bytes(blob[cursor : cursor + 4], "big")
                ctype = blob[cursor + 4 : cursor + 8]
                if length < 0 or length > 20_000_000 or cursor + 12 + length > len(blob):
                    break
                cursor += 12 + length
                if ctype == b"IEND":
                    picture = blob[start:cursor]
                    break
        else:
            end = blob.find(b"\xff\xd9", start)
            picture = blob[start : end + 2] if end > start else b""
        if picture:
            try:
                image = Image.open(BytesIO(picture))
                image.load()
                if min(image.size) >= 200:
                    images.append(picture)
            except Exception:
                pass
        idx = start + 4
    return images


def docx_images_in_order(path: Path, min_size: int = 12000) -> list[bytes]:
    with zipfile.ZipFile(path) as zf:
        rels = ET.fromstring(zf.read("word/_rels/document.xml.rels"))
        rid = {}
        for rel in rels:
            if "image" not in (rel.attrib.get("Type") or ""):
                continue
            target = rel.attrib.get("Target", "")
            if target.startswith("../"):
                media = "word/" + target.replace("../", "")
            elif not target.startswith("word"):
                media = "word/" + target
            else:
                media = target
            rid[rel.attrib["Id"]] = media.replace("word/word/", "word/")
        xml = zf.read("word/document.xml").decode("utf-8")
        needle = "r:embed=\""
        seen = []
        start = 0
        blobs: list[bytes] = []
        while True:
            pos = xml.find(needle, start)
            if pos < 0:
                break
            end = xml.find("\"", pos + len(needle))
            embed = xml[pos + len(needle) : end]
            start = end + 1
            media = rid.get(embed)
            if not media or media in seen or media not in zf.namelist():
                continue
            data = zf.read(media)
            if len(data) < min_size or media.endswith(".emf"):
                continue
            seen.append(media)
            blobs.append(data)
        return blobs


def parse_rubavu() -> None:
    root = SCHOOL_ROOTS["rubavu"]
    xls = next(p for p in root.iterdir() if p.suffix.lower() == ".xls" and not p.name.startswith("~$"))
    if "RUBAVU" not in str(root).upper() and "RUBVU" not in xls.name.upper():
        raise SystemExit("Refusing Rubavu parse")
    wb = xlrd.open_workbook(str(xls))
    sheet_map = {"N1": "BABY CLASS", "N2": "MIDDLE CLASS", "N3": "TOP CLASS", "P1": "P1", "P2": "P2", "P3": "P3", "P4": "P4", "P5": "P5", "P6": "P6"}
    students: list[dict[str, str]] = []
    for name in wb.sheet_names():
        label = sheet_map.get(name.strip().upper())
        if not label:
            continue
        sh = wb.sheet_by_name(name)
        for r in range(sh.nrows):
            number = clean(sh.cell_value(r, 0) if sh.ncols else "")
            if number.endswith(".0"):
                number = number[:-2]
            student_name = clean(sh.cell_value(r, 1) if sh.ncols > 1 else "")
            sex = sh.cell_value(r, 2) if sh.ncols > 2 else ""
            if not number.isdigit() or not re.search(r"[A-Za-z]", student_name):
                continue
            if student_name.upper() in {"NAMES", "STUDENT NAME"}:
                continue
            students.append(student_row(label, student_name, str(sex), name))
    staff: list[dict[str, str]] = []
    sh = wb.sheet_by_name("STAFF")
    started = False
    for r in range(sh.nrows):
        cells = [clean(sh.cell_value(r, c)) for c in range(sh.ncols)]
        joined = " ".join(cells).upper()
        if not started:
            if "POSITION" in joined and "TELEPHONE" in joined:
                started = True
            continue
        number = cells[0].replace(".0", "")
        name = cells[1] if len(cells) > 1 else ""
        if not re.search(r"[A-Za-z]", name):
            continue
        staff.append(staff_row(name, cells[2] if len(cells) > 2 else "TEACHER", cells[3] if len(cells) > 3 else "", cells[4] if len(cells) > 4 else ""))
    folder = photo_dir("rubavu")
    photos = []
    for index, blob in enumerate(extract_xls_images(xls)[: len(staff)], start=1):
        person = staff[index - 1]
        filename = save_photo(folder, index, person["full_name"], blob)
        person["photo"] = filename
        photos.append({"file": filename, "name_hint": person["full_name"]})
    head = next((row["full_name"] for row in staff if row["position"] == "HEAD TEACHER"), "")
    write_payload(
        "rubavu",
        {"name": "WISDOM SCHOOL RUBAVU", "acronym": "WIS-RUB", "slogan": "FEARING GOD IS KNOWLEDGE", "academic_year": "2026-2027", "term": "Term I", "head_teacher": head},
        students,
        staff,
        photos,
    )


def parse_rubengera() -> None:
    root = SCHOOL_ROOTS["rubengera"]
    class_files = {
        "NUSERY ONE.xlsx": "BABY CLASS",
        "NUSERY TWO.xlsx": "MIDDLE CLASS",
        "NUSERY THREE.xlsx": "TOP CLASS",
        "PRIMARY 1.xlsx": "P1",
        "PRIMARY 2.xlsx": "P2",
        "PRIMARY 3.xlsx": "P3",
        "PRIMARY 4.xlsx": "P4",
        "PRIMARY 5.xlsx": "P5",
        "PRIMARY 6.xlsx": "P6",
    }
    students: list[dict[str, str]] = []
    for filename, label in class_files.items():
        path = root / "LIST PER CLASS" / filename
        wb = openpyxl.load_workbook(path, data_only=True, read_only=True)
        ws = wb[wb.sheetnames[0]]
        started = False
        for row in ws.iter_rows(values_only=True):
            cells = [clean(cell) for cell in (row or ())]
            joined = " ".join(cells).upper()
            if not started:
                if "STUDENT NAME" in joined or ("NO" in joined and "NAME" in joined):
                    started = True
                continue
            number = cells[0] if cells else ""
            name = cells[1] if len(cells) > 1 else ""
            if not number.isdigit() or not name:
                continue
            students.append(student_row(label, name, cells[2] if len(cells) > 2 else "U", filename))
        wb.close()
    staff_xlsx = root / "STAFF" / "WISDOM SCHOOL RUBENGERA STAFF.xlsx"
    wb = openpyxl.load_workbook(staff_xlsx, data_only=True, read_only=True)
    ws = wb[wb.sheetnames[0]]
    staff: list[dict[str, str]] = []
    started = False
    for row in ws.iter_rows(values_only=True):
        cells = [clean(cell) for cell in (row or ())]
        joined = " ".join(cells).upper()
        if not started:
            if "NAME" in joined and "POSITION" in joined:
                started = True
            continue
        number = cells[0] if cells else ""
        name = cells[1] if len(cells) > 1 else ""
        if not number.isdigit() and not (name and re.search(r"[A-Za-z]", name)):
            continue
        if not name:
            continue
        staff.append(staff_row(name, cells[2] if len(cells) > 2 else "TEACHER", cells[3] if len(cells) > 3 else "", cells[4] if len(cells) > 4 else ""))
    wb.close()
    caption_names = [
        "SINDAYIHEBA FABIEN",
        "UWURUKUNDO EDUARD",
        "NYIRAMUTUZO JENIPHER",
        "MUKAKABANDA VIOLETTE",
        "NIYOMUGABO STEVEN",
        "ELISHA MUSHINGI RUBIN",
        "NIYITEGEKA EMMANUEL",
        "ISINGIZWE LOUANGE",
        "NIYONSENGA HONORINE",
        "NIYONZIMA JULIUS",
    ]
    folder = photo_dir("rubengera")
    photos = []
    images = docx_images_in_order(root / "STAFF PICTURES.docx", min_size=5000)
    used = set()
    for index, (hint, blob) in enumerate(zip(caption_names, images), start=1):
        person = next((row for row in staff if compact(row["full_name"]) == compact(hint) or compact(hint) in compact(row["full_name"]) or compact(row["full_name"]) in compact(hint)), None)
        if not person:
            person = next((row for row in staff if row["full_name"] not in used and len(set(re.findall(r"[A-Z]+", hint.upper())) & set(re.findall(r"[A-Z]+", row["full_name"].upper()))) >= 2), None)
        label = person["full_name"] if person else hint
        filename = save_photo(folder, index, label, blob)
        photos.append({"file": filename, "name_hint": hint})
        if person:
            person["photo"] = filename
            used.add(person["full_name"])
    head = next((row["full_name"] for row in staff if row["position"] == "HEAD TEACHER"), "")
    write_payload(
        "rubengera",
        {"name": "WISDOM SCHOOL RUBENGERA", "acronym": "WIS-RBE", "slogan": "", "academic_year": "2026-2027", "term": "Term I", "head_teacher": head},
        students,
        staff,
        photos,
    )


def parse_kayonza() -> None:
    root = SCHOOL_ROOTS["kayonza"]
    xlsx = root / "LIST OF STUDENTS 2026-2027 (1).xlsx"
    wb = openpyxl.load_workbook(xlsx, data_only=True)
    ws = wb[wb.sheetnames[0]]
    students: list[dict[str, str]] = []
    current = ""
    class_map = {"N1": "BABY CLASS", "N2": "MIDDLE CLASS", "N3": "TOP CLASS", "P1": "P1", "P2": "P2", "P3": "P3", "P4": "P4", "P5": "P5", "P6": "P6"}
    for row in ws.iter_rows(values_only=True):
        cells = [clean(cell) for cell in (row or ())]
        nonempty = [cell.upper() for cell in cells if cell]
        marker = next((cell for cell in nonempty if cell in class_map), "")
        if marker and not (cells and cells[0].isdigit()):
            current = class_map[marker]
            continue
        number = cells[0] if cells else ""
        name = cells[1] if len(cells) > 1 else ""
        if current and number.isdigit() and re.search(r"[A-Za-z]", name):
            students.append(student_row(current, name, cells[2] if len(cells) > 2 else "U", "INCOME"))
    wb.close()
    texts = []
    with zipfile.ZipFile(root / "Wisdom School Kayonza staff 2026-2027.docx") as zf:
        xml = ET.fromstring(zf.read("word/document.xml"))
        ns = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
        for para in xml.findall(".//w:p", ns):
            text = "".join(node.text or "" for node in para.findall(".//w:t", ns)).strip()
            if text:
                texts.append(text)
    staff: list[dict[str, str]] = []
    i = 0
    while i < len(texts):
        if re.fullmatch(r"\d+\.?", texts[i]) and i + 4 < len(texts):
            staff.append(staff_row(texts[i + 1], texts[i + 2], texts[i + 3], texts[i + 4]))
            i += 5
            continue
        i += 1
    docx_blobs = docx_images_in_order(root / "Wisdom School Kayonza staff 2026-2027.docx", min_size=5000)
    portraits = [blob for blob in docx_blobs if 5000 <= len(blob) <= 40000][:10]
    whatsapp = []
    for path in sorted(root.glob("*.jpeg")):
        whatsapp.append((path.name, path.read_bytes()))
    folder = photo_dir("kayonza")
    photos = []
    used_wa = set()
    for index, (person, thumb) in enumerate(zip(staff, portraits), start=1):
        thumb_hash = ahash(thumb)
        best = None
        best_dist = 99
        for name, blob in whatsapp:
            if name in used_wa:
                continue
            dist = hamming(thumb_hash, ahash(blob))
            if dist < best_dist:
                best_dist = dist
                best = (name, blob)
        data = best[1] if best and best_dist <= 18 else thumb
        if best and best_dist <= 18:
            used_wa.add(best[0])
        filename = save_photo(folder, index, person["full_name"], data)
        person["photo"] = filename
        photos.append({"file": filename, "name_hint": person["full_name"]})
    head = next((row["full_name"] for row in staff if row["position"] == "HEAD TEACHER"), "")
    write_payload(
        "kayonza",
        {"name": "WISDOM SCHOOL KAYONZA", "acronym": "WSY", "slogan": "FEARING GOD IS KNOWLEDGE", "academic_year": "2026-2027", "term": "Term I", "head_teacher": head},
        students,
        staff,
        photos,
    )


def parse_fumbwe() -> None:
    root = SCHOOL_ROOTS["fumbwe"]
    xlsx = next(p for p in root.glob("*.xlsx") if not p.name.startswith("~$"))
    sheet_map = {
        "N1": "BABY CLASS",
        "N2": "MIDDLE CLASS",
        "N3": "TOP CLASS",
        "P1": "P1",
        "P1 STUDENTS": "P1",
        "P2 STUDENTS": "P2",
        "P3 STUDENTS": "P3",
        "P4 STUDENTS": "P4",
        "P5 STUDENTS": "P5",
        "P6 STUDENTS": "P6",
    }
    students: list[dict[str, str]] = []
    wb = openpyxl.load_workbook(xlsx, data_only=True, read_only=True)
    for name in wb.sheetnames:
        label = sheet_map.get(name.strip().upper())
        if not label:
            continue
        ws = wb[name]
        for row in ws.iter_rows(values_only=True):
            cells = [clean(cell) for cell in (row or ())]
            number = ""
            student_name = ""
            if cells and cells[0].isdigit():
                number, student_name = cells[0], cells[1] if len(cells) > 1 else ""
            elif len(cells) > 1 and cells[1].isdigit():
                number, student_name = cells[1], cells[2] if len(cells) > 2 else ""
            if not number.isdigit() or not re.search(r"[A-Za-z]", student_name):
                continue
            if student_name.upper() in {"NAME", "NAMES"}:
                continue
            students.append(student_row(label, student_name, "U", name))
    wb.close()
    texts = []
    doc = next(p for p in root.glob("*.docx"))
    with zipfile.ZipFile(doc) as zf:
        xml = ET.fromstring(zf.read("word/document.xml"))
        ns = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
        for para in xml.findall(".//w:p", ns):
            text = "".join(node.text or "" for node in para.findall(".//w:t", ns)).strip()
            if text:
                texts.append(re.sub(r"\s+", " ", text))
    role_re = re.compile(r"^(HEADTEACHER|ACCOUNTANT|TEACHER/DOS|TEACHER)\s*:\s*(.+)$", re.I)

    def first_role_name(raw: str) -> str:
        text = clean(raw)
        text = re.split(r"\s+(?:HEADTEACHER|ACCOUNTANT|TEACHER/DOS|TEACHER)\s*:", text, maxsplit=1)[0]
        text = re.split(r"\s+Tel\s*:", text, maxsplit=1)[0]
        return clean(text)

    def email_fits(name: str, email: str) -> bool:
        local = compact(email.split("@")[0])
        named = compact(name)
        if not local or not named:
            return False
        if local in named or named in local:
            return True
        tokens = [tok for tok in re.findall(r"[A-Z]+", name.upper()) if len(tok) >= 4]
        return any(tok in local or local in tok for tok in tokens)

    unique_blocks: list[dict[str, str]] = []
    seen: set[str] = set()
    all_emails: list[str] = []
    for text in texts:
        all_emails += [clean_email(item) for item in re.findall(r"[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}", text)]
    all_emails = [item for item in all_emails if item]
    i = 0
    while i < len(texts):
        matched = role_re.match(texts[i])
        if not matched:
            i += 1
            continue
        position, name = matched.group(1), first_role_name(matched.group(2))
        key = compact(name)
        if key in seen or len(name) < 4:
            i += 1
            continue
        seen.add(key)
        phones: list[str] = []
        emails: list[str] = []
        j = i + 1
        while j < len(texts):
            nxt = role_re.match(texts[j])
            if nxt:
                nxt_name = first_role_name(nxt.group(2))
                nxt_key = compact(nxt_name)
                if nxt_key and nxt_key != key:
                    break
            phones += re.findall(r"0?\s*7[\d\s]{7,12}", texts[j])
            emails += [clean_email(item) for item in re.findall(r"[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}", texts[j])]
            j += 1
        unique_blocks.append({"name": name, "position": position, "phone": phones[0] if phones else "", "emails": [item for item in emails if item]})
        i += 1
    used_emails: set[str] = set()
    staff: list[dict[str, str]] = []
    for block in unique_blocks:
        email = next((item for item in block["emails"] if item not in used_emails and email_fits(block["name"], item)), "")
        if not email:
            email = next((item for item in all_emails if item not in used_emails and email_fits(block["name"], item)), "")
        if email:
            used_emails.add(email)
        phone = block["phone"]
        if not phone and email:
            local = email.split("@")[0].lower()
            for text in texts:
                if local not in text.lower().replace(" ", ""):
                    continue
                found = re.findall(r"0?\s*7[\d\s]{7,12}", text)
                if found:
                    phone = found[-1]
                    break
        staff.append(staff_row(block["name"], block["position"], phone, email))
    folder = photo_dir("fumbwe")
    photos = []
    images = [blob for blob in docx_images_in_order(doc, min_size=40000) if len(blob) >= 40000]
    for index, blob in enumerate(images[: len(staff)], start=1):
        person = staff[index - 1]
        filename = save_photo(folder, index, person["full_name"], blob)
        person["photo"] = filename
        photos.append({"file": filename, "name_hint": person["full_name"]})
    head = next((row["full_name"] for row in staff if row["position"] == "HEAD TEACHER"), "")
    write_payload(
        "fumbwe",
        {"name": "WISDOM SCHOOL FUMBWE", "acronym": "WIS-FUM", "slogan": "FEARING GOD IS KNOWLEDGE", "academic_year": "2026-2027", "term": "Term I", "head_teacher": head},
        students,
        staff,
        photos,
    )


def main() -> int:
    parse_rubavu()
    parse_rubengera()
    parse_kayonza()
    parse_fumbwe()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
