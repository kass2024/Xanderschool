"""
Pre-deploy check for CodeIgniter .env files.
Fails if unquoted values contain spaces (the exact DotEnv.php fatal).
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(r"C:\xampp7\htdocs\Xander-school")
FILES = [
    ROOT / ".env",
    ROOT / "deploy" / "app.env.production",
]


def check_file(path: Path) -> list[str]:
    errors: list[str] = []
    if not path.exists():
        return [f"MISSING: {path}"]

    for i, raw in enumerate(path.read_text(encoding="utf-8", errors="replace").splitlines(), 1):
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip()
        if not value:
            continue
        # Quoted values are fine
        if (value.startswith('"') and value.endswith('"')) or (value.startswith("'") and value.endswith("'")):
            continue
        # Strip inline comments like: value # comment
        if " #" in value:
            value = value.split(" #", 1)[0].strip()
        if re.search(r"\s", value):
            errors.append(f"{path.name}:{i} {key}={value!r}  → wrap in quotes")
    return errors


def main() -> int:
    all_errors: list[str] = []
    for f in FILES:
        errs = check_file(f)
        print(f"CHECK {f}")
        if errs:
            all_errors.extend(errs)
            for e in errs:
                print("  FAIL", e)
        else:
            print("  OK")

    # Quick required keys in production env
    prod = ROOT / "deploy" / "app.env.production"
    text = prod.read_text(encoding="utf-8", errors="replace") if prod.exists() else ""
    required = [
        "SMTP_HOST",
        "SMTP_PORT",
        "SMTP_USERNAME",
        "SMTP_PASSWORD",
        "SMTP_FROM_EMAIL",
        "SMTP_FROM_NAME",
        "SMTP_ENCRYPTION",
        "sms.type",
        "sms.swiftqom.key",
        "database.default.hostname",
        "database.default.database",
        "app.baseURL",
    ]
    print("REQUIRED KEYS (production):")
    for k in required:
        # match KEY = or KEY=
        ok = re.search(rf"(?m)^\s*{re.escape(k)}\s*=", text) is not None
        print(("  OK  " if ok else "  MISS"), k)
        if not ok:
            all_errors.append(f"missing required key: {k}")

    if all_errors:
        print(f"\nPRE-DEPLOY FAILED ({len(all_errors)} issue(s))")
        return 1
    print("\nPRE-DEPLOY PASSED")
    return 0


if __name__ == "__main__":
    sys.exit(main())
