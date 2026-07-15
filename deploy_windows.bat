@echo off
REM Windows batch script for deployment
REM Usage: deploy_windows.bat [vps_ip]

set VPS_IP=%1
if "%VPS_IP%"=="" set VPS_IP=your_vps_ip

set PROJECT_DIR=/var/www/lyceapade-new
set LOCAL_DIR=c:\xampp7\htdocs\apade

echo Deploying fixes to VPS: %VPS_IP%
echo Project directory: %PROJECT_DIR%
echo Local directory: %LOCAL_DIR%

REM Create backup on VPS
echo Creating backups...
plink iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && cp app/Views/pages/dashboard.php app/Views/pages/dashboard.php.backup && cp app/Views/main.php app/Views/main.php.backup && cp public/assets/css/main.css public/assets/css/main.css.backup && cp public/assets/js/parsley-extra-validators.js public/assets/js/parsley-extra-validators.js.backup"

REM Upload modified files
echo Uploading modified files...
pscp -pw iotxad@2025 "%LOCAL_DIR%\app\Views\pages\dashboard.php" iotxad_user@%VPS_IP%:%PROJECT_DIR%/app/Views/pages/
pscp -pw iotxad@2025 "%LOCAL_DIR%\app\Views\main.php" iotxad_user@%VPS_IP%:%PROJECT_DIR%/app/Views/
pscp -pw iotxad@2025 "%LOCAL_DIR%\public\assets\css\main.css" iotxad_user@%VPS_IP%:%PROJECT_DIR%/public/assets/css/
pscp -pw iotxad@2025 "%LOCAL_DIR%\public\assets\js\parsley-extra-validators.js" iotxad_user@%VPS_IP%:%PROJECT_DIR%/public/assets/js/

REM Create fonts directory and upload fonts
echo Creating fonts directory and uploading fonts...
plink iotxad_user@%VPS_IP% -pw iotxad@2025 "mkdir -p %PROJECT_DIR%/public/assets/css/assets/fonts"
pscp -r -pw iotxad@2025 "%LOCAL_DIR%\public\assets\css\assets\fonts\*" iotxad_user@%VPS_IP%:%PROJECT_DIR%/public/assets/css/assets/fonts/

REM Set correct permissions
echo Setting permissions...
plink iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && sudo chown -R www-data:www-data app/Views/ && sudo chown -R www-data:www-data public/assets/ && sudo find app/Views/ -type f -exec chmod 644 {} \; && sudo find public/assets/ -type f -exec chmod 644 {} \; && sudo find public/assets/ -type d -exec chmod 755 {} \;"

REM Create placeholder profile image
echo Creating placeholder profile image...
plink iotxad_user@%VPS_IP% -pw iotxad@2025 "cd %PROJECT_DIR% && if [ ! -f public/assets/images/profile/69cb742350f35.png ]; then mkdir -p public/assets/images/profile/ && cp public/assets/images/no_image.jpg public/assets/images/profile/69cb742350f35.png; fi"

echo Deployment completed!
echo Test your dashboard at: http://your_domain.com/dashboard
echo Check browser console for any remaining errors.
pause
