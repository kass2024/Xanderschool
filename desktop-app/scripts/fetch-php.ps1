$ErrorActionPreference = 'Stop'
$dest = Split-Path -Parent $PSScriptRoot
$phpDir = Join-Path $dest 'php-runtime'
New-Item -ItemType Directory -Force -Path $phpDir | Out-Null

$zip = Join-Path $phpDir 'php-win.zip'
$urls = @(
  'https://windows.php.net/downloads/releases/latest/php-8.2-nts-Win32-vs16-x64-latest.zip',
  'https://windows.php.net/downloads/releases/php-8.2.33-nts-Win32-vs16-x64.zip',
  'https://windows.php.net/downloads/releases/php-8.3.33-nts-Win32-vs16-x64.zip'
)

Write-Host 'Downloading portable PHP...'
$ok = $false
foreach ($url in $urls) {
  try {
    Write-Host "Trying $url"
    Invoke-WebRequest -Uri $url -OutFile $zip -UseBasicParsing
    $ok = $true
    break
  } catch {
    Write-Host "  failed: $($_.Exception.Message)"
  }
}
if (-not $ok) {
  throw 'Could not download PHP. The desktop app can still use C:\xampp7\php\php.exe for local runs.'
}

Write-Host 'Extracting...'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$tmp = Join-Path $phpDir '_extract'
if (Test-Path $tmp) { Remove-Item $tmp -Recurse -Force }
New-Item -ItemType Directory -Path $tmp | Out-Null
[System.IO.Compression.ZipFile]::ExtractToDirectory($zip, $tmp)

$phpExe = Get-ChildItem -Path $tmp -Filter php.exe -Recurse | Select-Object -First 1
if (-not $phpExe) { throw 'php.exe missing from archive' }
$srcDir = $phpExe.Directory.FullName
Get-ChildItem $srcDir | ForEach-Object {
  $target = Join-Path $phpDir $_.Name
  if ($_.Name -eq 'php.ini') { return }
  Copy-Item $_.FullName $target -Recurse -Force
}
Remove-Item $tmp -Recurse -Force
Remove-Item $zip -Force
Write-Host "PHP ready at $phpDir\php.exe"
