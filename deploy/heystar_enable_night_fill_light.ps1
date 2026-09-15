# Accurate HeyStar recognition (night-safe, fewer false matches).
# Does NOT touch registered faces, person list, logo, or school branding.
# Usage:
#   .\deploy\heystar_enable_night_fill_light.ps1
#   .\deploy\heystar_enable_night_fill_light.ps1 -BaseUrl http://10.151.53.95:8090

param(
	[string]$BaseUrl = 'http://127.0.0.1:18090',
	[string]$Password = '123456'
)

$ErrorActionPreference = 'Stop'
if ($BaseUrl -match '127\.0\.0\.1:18090') {
	adb forward tcp:18090 tcp:8090 | Out-Null
}

$base = $BaseUrl.TrimEnd('/') + '/cgi-bin/js'
$dir = Join-Path $env:TEMP 'heystar_accurate_fix'
New-Item -ItemType Directory -Force -Path $dir | Out-Null

function Post-Json([string]$path, [string]$json) {
	$file = Join-Path $dir (($path -replace '[/\\]', '_') + '.json')
	Set-Content -Path $file -Value $json -Encoding Ascii -NoNewline
	Write-Host "=== $path ==="
	curl.exe -s -u "admin:$Password" -H 'Content-Type: application/json; charset=UTF-8' --data-binary "@$file" "$base/$path"
	Write-Host ''
}

# Fill light auto (not always-on). Always-on floods the camera and confuses matches.
Post-Json 'device/setPciConfig' '{"pciLedAlwaysEnable":0,"pciLedColorStranger":1}'
Post-Json 'device/setRecModeConfig' '{"recModeCardEnable":0,"recModeFaceEnable":1,"recModeFingerEnable":0,"recModePalmEnable":0}'
# Strict score + monocular liveness + 1.5m + registered-only (no stranger accept)
Post-Json 'device/setRecConfig' '{"recThreshold1vN":75,"recThreshold1v1":68,"recInterval":3,"recDistance":3,"recRank":2,"recStrangerEnable":0,"recIsStrangerTimes":2,"recStrangerOpenDoor":0,"recMultiplayer":0}'

Write-Host 'Done. Expect code 000 Successful. Faces and logo were not changed.'
