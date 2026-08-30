# Xander School Desktop

Offline Windows app for Xander School. It runs the full existing school UI locally with **SQLite** (no MySQL install), then **auto-syncs** with the live server when the network is back.

Folder: `desktop-app/` (same Electron + electron-vite + electron-builder + better-sqlite3 stack as Xander AI IDE).

## What you get

- Native `.exe` (NSIS installer + portable)
- Local PHP server serving the real school system
- SQLite database in the user data folder
- Background pull/push against `https://schoolmis.xanderglobalacademy.com` (URL is configurable)
- Two-way sync: local inserts/updates/deletes queue offline, upload when online, and full syncs reconcile server-side deletions
- Local data is isolated per Windows user and the saved password is protected with Windows-backed Electron encryption
- Works fully while offline; queued changes upload later

## Dev

Requires Node 20+ and PHP (`C:\xampp7\php\php.exe` or `npm run fetch-php`).

```powershell
cd C:\xampp7\htdocs\Xander-school\desktop-app
npm install
npm run dev
```

Sign in with a staff account from the live school. The first login downloads that school’s data into SQLite.

## Build .exe

```powershell
cd C:\xampp7\htdocs\Xander-school\desktop-app
powershell -ExecutionPolicy Bypass -File .\build-exe.ps1
```

Output:

- `release/Xander School-Setup-1.0.4.exe`
- `release/Xander School-Portable-1.0.4.exe`

The server must already expose `/api/desktop/*` (deployed with this repo) so the first download and later sync can run. The build bundles portable PHP, so the installed app does not require XAMPP.
