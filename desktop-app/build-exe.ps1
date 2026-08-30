Write-Host "Building Xander School Desktop..." -ForegroundColor Green
Set-Location $PSScriptRoot

if (-not (Test-Path ".\php-runtime\php.exe")) {
  Write-Host "Fetching portable PHP..." -ForegroundColor Yellow
  powershell -ExecutionPolicy Bypass -File .\scripts\fetch-php.ps1
}

if (-not (Test-Path ".\node_modules")) {
  Write-Host "Installing dependencies..." -ForegroundColor Yellow
  npm install
}

Write-Host "Compiling..." -ForegroundColor Yellow
npm run build

Write-Host "Packaging Windows installer + portable EXE..." -ForegroundColor Yellow
npx electron-builder --win

Write-Host ""
Write-Host "Done. Files are in desktop-app\release\" -ForegroundColor Cyan
Write-Host "  Xander School-Setup-1.0.0.exe     (installer)" -ForegroundColor White
Write-Host "  Xander School-Portable-1.0.0.exe   (no install)" -ForegroundColor White
