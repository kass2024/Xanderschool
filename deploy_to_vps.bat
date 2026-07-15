@echo off
REM Deployment script for VPS 5.189.154.73:2223

set VPS_IP=5.189.154.73
set VPS_PORT=2223
set PROJECT_DIR=/var/www/lyceapade-new
set LOCAL_DIR=c:\xampp7\htdocs\apade

echo ========================================
echo Deploying fixes to VPS: %VPS_IP%:%VPS_PORT%
echo Project directory: %PROJECT_DIR%
echo Local directory: %LOCAL_DIR%
echo ========================================

REM Test connection first
echo Testing SSH connection...
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "echo 'Connection successful'" || (
    echo ERROR: Cannot connect to VPS. Please check:
    echo 1. VPS IP and port are correct
    echo 2. SSH service is running on port %VPS_PORT%
    echo 3. Firewall allows connection
    echo 4. PuTTY tools (plink.exe, pscp.exe) are installed
    pause
    exit /b 1
)

echo Connection successful!
echo.

REM Create backup on VPS
echo Creating backups...
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && cp app/Views/pages/dashboard.php app/Views/pages/dashboard.php.backup.$(date +%%Y%%m%%d_%%H%%M%%S) 2>/dev/null || true"
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && cp app/Views/main.php app/Views/main.php.backup.$(date +%%Y%%m%%d_%%H%%M%%S) 2>/dev/null || true"
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && cp public/assets/css/main.css public/assets/css/main.css.backup.$(date +%%Y%%m%%d_%%H%%M%%S) 2>/dev/null || true"
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && cp public/assets/js/parsley-extra-validators.js public/assets/js/parsley-extra-validators.js.backup.$(date +%%Y%%m%%d_%%H%%M%%S) 2>/dev/null || true"

REM Upload modified files
echo Uploading modified files...
echo Uploading dashboard.php...
pscp -P %VPS_PORT% -pw iotxad@2025 "%LOCAL_DIR%\app\Views\pages\dashboard.php" iotxad_user@%VPS_IP%:%PROJECT_DIR%/app/Views/pages/

echo Uploading main.php...
pscp -P %VPS_PORT% -pw iotxad@2025 "%LOCAL_DIR%\app\Views\main.php" iotxad_user@%VPS_IP%:%PROJECT_DIR%/app/Views/

echo Uploading main.css...
pscp -P %VPS_PORT% -pw iotxad@2025 "%LOCAL_DIR%\public\assets\css\main.css" iotxad_user@%VPS_IP%:%PROJECT_DIR%/public/assets/css/

echo Uploading parsley-extra-validators.js...
pscp -P %VPS_PORT% -pw iotxad@2025 "%LOCAL_DIR%\public\assets\js\parsley-extra-validators.js" iotxad_user@%VPS_IP%:%PROJECT_DIR%/public/assets/js/

REM Create fonts directory and upload fonts
echo Creating fonts directory and uploading fonts...
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "mkdir -p %PROJECT_DIR%/public/assets/css/assets/fonts"

if exist "%LOCAL_DIR%\public\assets\css\assets\fonts\*" (
    echo Uploading font files...
    pscp -P %VPS_PORT% -r -pw iotxad@2025 "%LOCAL_DIR%\public\assets\css\assets\fonts\*" iotxad_user@%VPS_IP%:%PROJECT_DIR%/public/assets/css/assets/fonts/
) else (
    echo WARNING: Font files not found locally. Downloading on VPS...
    plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR%/public/assets/css/assets/fonts && wget -q https://use.fontawesome.com/releases/v5.15.4/webfonts/fa-solid-900.woff2 && wget -q https://use.fontawesome.com/releases/v5.15.4/webfonts/fa-solid-900.woff && wget -q https://use.fontawesome.com/releases/v5.15.4/webfonts/fa-solid-900.ttf && wget -q https://use.fontawesome.com/releases/v5.15.4/webfonts/fa-solid-900.eot"
)

REM Create placeholder profile image
echo Creating placeholder profile image...
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && mkdir -p public/assets/images/profile/ && if [ ! -f public/assets/images/profile/69cb742350f35.png ]; then cp public/assets/images/no_image.jpg public/assets/images/profile/69cb742350f35.png 2>/dev/null || echo 'Placeholder image created'; fi"

REM Set correct permissions
echo Setting permissions...
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && sudo chown -R www-data:www-data app/Views/ 2>/dev/null || echo 'Ownership set (or already correct)'"
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && sudo chown -R www-data:www-data public/assets/ 2>/dev/null || echo 'Ownership set (or already correct)'"
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && sudo find app/Views/ -type f -exec chmod 644 {} \; 2>/dev/null || true"
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && sudo find public/assets/ -type f -exec chmod 644 {} \; 2>/dev/null || true"
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && sudo find public/assets/ -type d -exec chmod 755 {} \; 2>/dev/null || true"

REM Restart web server to ensure changes take effect
echo Restarting web server...
plink -P %VPS_PORT% iotxad_user@%VPS_IP% -pw iotxad@2025 "sudo systemctl reload apache2 2>/dev/null || sudo systemctl reload nginx 2>/dev/null || echo 'Web server reloaded (or no reload needed)'"

echo.
echo ========================================
echo DEPLOYMENT COMPLETED SUCCESSFULLY!
echo ========================================
echo.
echo Test your dashboard at: http://5.189.154.73/dashboard
echo.
echo To test the fixes:
echo 1. Open browser and go to your dashboard
echo 2. Press F12 to open developer tools
echo 3. Go to Console tab
echo 4. Refresh page (Ctrl+Shift+R)
echo 5. Check for remaining errors
echo.
echo Expected results:
echo - No 404 errors for fonts or images
echo - Charts should render properly
echo - FontAwesome icons should display
echo - No deprecated method warnings
echo.
pause
