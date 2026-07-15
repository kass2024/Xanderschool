# PowerShell deployment script for VPS 5.189.154.73:2223

$VPS_IP = "5.189.154.73"
$VPS_PORT = "2223"
$PROJECT_DIR = "/var/www/lyceapade-new"
$LOCAL_DIR = "c:\xampp7\htdocs\apade"

Write-Host "========================================" -ForegroundColor Green
Write-Host "Deploying fixes to VPS: $VPS_IP`:$VPS_PORT" -ForegroundColor Green
Write-Host "Project directory: $PROJECT_DIR" -ForegroundColor Green
Write-Host "Local directory: $LOCAL_DIR" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green

# Check if PuTTY tools are available
try {
    $null = Get-Command plink.exe -ErrorAction Stop
    $null = Get-Command pscp.exe -ErrorAction Stop
    Write-Host "PuTTY tools found." -ForegroundColor Green
} catch {
    Write-Host "ERROR: PuTTY tools (plink.exe, pscp.exe) not found." -ForegroundColor Red
    Write-Host "Please install PuTTY and add to PATH, or download portable versions." -ForegroundColor Red
    Write-Host "Download from: https://www.putty.org/" -ForegroundColor Yellow
    Read-Host "Press Enter to exit"
    exit 1
}

# Test connection
Write-Host "Testing SSH connection..." -ForegroundColor Yellow
try {
    $result = & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "echo 'Connection successful'" 2>&1
    if ($result -match "Connection successful") {
        Write-Host "Connection successful!" -ForegroundColor Green
    } else {
        Write-Host "ERROR: Cannot connect to VPS." -ForegroundColor Red
        Write-Host "Please check:" -ForegroundColor Yellow
        Write-Host "1. VPS IP and port are correct" -ForegroundColor Yellow
        Write-Host "2. SSH service is running on port $VPS_PORT" -ForegroundColor Yellow
        Write-Host "3. Firewall allows connection" -ForegroundColor Yellow
        Read-Host "Press Enter to exit"
        exit 1
    }
} catch {
    Write-Host "ERROR: Connection failed - $($_.Exception.Message)" -ForegroundColor Red
    Read-Host "Press Enter to exit"
    exit 1
}

# Create backups
Write-Host "Creating backups..." -ForegroundColor Yellow
try {
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR && cp app/Views/pages/dashboard.php app/Views/pages/dashboard.php.backup 2>/dev/null || true"
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR && cp app/Views/main.php app/Views/main.php.backup 2>/dev/null || true"
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR && cp public/assets/css/main.css public/assets/css/main.css.backup 2>/dev/null || true"
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR && cp public/assets/js/parsley-extra-validators.js public/assets/js/parsley-extra-validators.js.backup 2>/dev/null || true"
    Write-Host "Backups created." -ForegroundColor Green
} catch {
    Write-Host "Warning: Backup creation failed - $($_.Exception.Message)" -ForegroundColor Yellow
}

# Upload files
Write-Host "Uploading modified files..." -ForegroundColor Yellow

$files_to_upload = @{
    "dashboard.php" = "$LOCAL_DIR\app\Views\pages\dashboard.php:$PROJECT_DIR/app/Views/pages/"
    "main.php" = "$LOCAL_DIR\app\Views\main.php:$PROJECT_DIR/app/Views/"
    "main.css" = "$LOCAL_DIR\public\assets\css\main.css:$PROJECT_DIR/public/assets/css/"
    "parsley-extra-validators.js" = "$LOCAL_DIR\public\assets\js\parsley-extra-validators.js:$PROJECT_DIR/public/assets/js/"
}

foreach ($file in $files_to_upload.Keys) {
    $local_path = ($files_to_upload[$file] -split ":")[0]
    $remote_path = ($files_to_upload[$file] -split ":")[1]
    Write-Host "Uploading $file..." -ForegroundColor Cyan
    try {
        & pscp.exe -P $VPS_PORT -pw "iotxad@2025" $local_path "iotxad_user@$VPS_IP`:$remote_path"
        Write-Host "$file uploaded successfully." -ForegroundColor Green
    } catch {
        Write-Host "ERROR: Failed to upload $file - $($_.Exception.Message)" -ForegroundColor Red
    }
}

# Create fonts directory and upload/download fonts
Write-Host "Setting up fonts..." -ForegroundColor Yellow
try {
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "mkdir -p $PROJECT_DIR/public/assets/css/assets/fonts"
    
    # Check if local fonts exist
    if (Test-Path "$LOCAL_DIR\public\assets\css\assets\fonts\*") {
        Write-Host "Uploading local fonts..." -ForegroundColor Cyan
        & pscp.exe -P $VPS_PORT -r -pw "iotxad@2025" "$LOCAL_DIR\public\assets\css\assets\fonts\*" "iotxad_user@$VPS_IP`:$PROJECT_DIR/public/assets/css/assets/fonts/"
    } else {
        Write-Host "Downloading fonts on VPS..." -ForegroundColor Cyan
        & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR/public/assets/css/assets/fonts && wget -q https://use.fontawesome.com/releases/v5.15.4/webfonts/fa-solid-900.woff2 && wget -q https://use.fontawesome.com/releases/v5.15.4/webfonts/fa-solid-900.woff && wget -q https://use.fontawesome.com/releases/v5.15.4/webfonts/fa-solid-900.ttf && wget -q https://use.fontawesome.com/releases/v5.15.4/webfonts/fa-solid-900.eot"
    }
    Write-Host "Fonts setup completed." -ForegroundColor Green
} catch {
    Write-Host "ERROR: Fonts setup failed - $($_.Exception.Message)" -ForegroundColor Red
}

# Create placeholder profile image
Write-Host "Creating placeholder profile image..." -ForegroundColor Yellow
try {
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR && mkdir -p public/assets/images/profile/ && if [ ! -f public/assets/images/profile/69cb742350f35.png ]; then cp public/assets/images/no_image.jpg public/assets/images/profile/69cb742350f35.png 2>/dev/null || echo 'Placeholder created'; fi"
    Write-Host "Profile image placeholder created." -ForegroundColor Green
} catch {
    Write-Host "Warning: Profile image setup failed - $($_.Exception.Message)" -ForegroundColor Yellow
}

# Set permissions
Write-Host "Setting permissions..." -ForegroundColor Yellow
try {
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR && sudo chown -R www-data:www-data app/Views/ 2>/dev/null || echo 'Ownership set'"
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR && sudo chown -R www-data:www-data public/assets/ 2>/dev/null || echo 'Ownership set'"
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR && sudo find app/Views/ -type f -exec chmod 644 {} \; 2>/dev/null || true"
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR && sudo find public/assets/ -type f -exec chmod 644 {} \; 2>/dev/null || true"
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "cd $PROJECT_DIR && sudo find public/assets/ -type d -exec chmod 755 {} \; 2>/dev/null || true"
    Write-Host "Permissions set." -ForegroundColor Green
} catch {
    Write-Host "Warning: Permission setting failed - $($_.Exception.Message)" -ForegroundColor Yellow
}

# Restart web server
Write-Host "Restarting web server..." -ForegroundColor Yellow
try {
    & plink.exe -P $VPS_PORT iotxad_user@$VPS_IP -pw "iotxad@2025" "sudo systemctl reload apache2 2>/dev/null || sudo systemctl reload nginx 2>/dev/null || echo 'Web server reload completed'"
    Write-Host "Web server reloaded." -ForegroundColor Green
} catch {
    Write-Host "Warning: Web server reload failed - $($_.Exception.Message)" -ForegroundColor Yellow
}

Write-Host ""
Write-Host "========================================" -ForegroundColor Green
Write-Host "DEPLOYMENT COMPLETED!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Green
Write-Host ""
Write-Host "Test your dashboard at: http://5.189.154.73/dashboard" -ForegroundColor Cyan
Write-Host ""
Write-Host "To test the fixes:" -ForegroundColor Yellow
Write-Host "1. Open browser and go to your dashboard" -ForegroundColor White
Write-Host "2. Press F12 to open developer tools" -ForegroundColor White
Write-Host "3. Go to Console tab" -ForegroundColor White
Write-Host "4. Refresh page (Ctrl+Shift+R)" -ForegroundColor White
Write-Host "5. Check for remaining errors" -ForegroundColor White
Write-Host ""
Write-Host "Expected results:" -ForegroundColor Green
Write-Host "- No 404 errors for fonts or images" -ForegroundColor White
Write-Host "- Charts should render properly" -ForegroundColor White
Write-Host "- FontAwesome icons should display" -ForegroundColor White
Write-Host "- No deprecated method warnings" -ForegroundColor White
Write-Host ""
Read-Host "Press Enter to exit"
