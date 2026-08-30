# Build the Windows EXE

Same packaging path as Xander AI IDE (`electron-vite` then `electron-builder`).

## One command

```powershell
cd C:\xampp7\htdocs\Xander-school\desktop-app
powershell -ExecutionPolicy Bypass -File .\build-exe.ps1
```

## Manual

```powershell
npm install
npm run fetch-php
npm run dist:win
```

Install Visual Studio Build Tools (Desktop C++) if `better-sqlite3` fails to rebuild for Electron.

Outputs land in `desktop-app/release/`.
