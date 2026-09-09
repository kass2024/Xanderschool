# Enable HeyStar fill light (always on) + night-friendly face detection.
# Does NOT touch registered faces, person list, logo, or school branding.
# Requires: adb connected + forward, or device reachable on LAN :8090
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
$dir = Join-Path $env:TEMP 'heystar_night_fix'
New-Item -ItemType Directory -Force -Path $dir | Out-Null

function Post-Json([string]$path, [string]$json) {
	$file = Join-Path $dir (($path -replace '[/\\]', '_') + '.json')
	Set-Content -Path $file -Value $json -Encoding Ascii -NoNewline
	Write-Host "=== $path ==="
	curl.exe -s -u "admin:$Password" -H 'Content-Type: application/json; charset=UTF-8' --data-binary "@$file" "$base/$path"
	Write-Host ''
}

# Official LAN API: pciLedAlwaysEnable = "Fill light is always on"
Post-Json 'device/setPciConfig' '{"pciLedAlwaysEnable":1,"pciLedColorStranger":1,"pciRelayOut":1,"pciRelayMode":1,"pciRelayDelay":2000}'
Post-Json 'device/setRecModeConfig' '{"recModeCardEnable":0,"recModeFaceEnable":1,"recModeFingerEnable":0,"recModePalmEnable":0}'
# recRank 1 = no liveness (best for dark); threshold slightly lower for night
Post-Json 'device/setRecConfig' '{"recThreshold1vN":55,"recThreshold1v1":50,"recInterval":2,"recDistance":0,"recRank":1,"recStrangerEnable":0}'

Write-Host 'Done. Expect code 000 Successful on each call. Faces and logo were not changed.'
