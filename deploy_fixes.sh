#!/bin/bash

# Deployment script for dashboard fixes
# Usage: ./deploy_fixes.sh [vps_ip]

VPS_IP=${1:-"your_vps_ip"}
PROJECT_DIR="/var/www/lyceapade-new"
LOCAL_DIR="c:/xampp7/htdocs/apade"

echo "Deploying fixes to VPS: $VPS_IP"
echo "Project directory: $PROJECT_DIR"
echo "Local directory: $LOCAL_DIR"

# Create backup on VPS
echo "Creating backups..."
ssh iotxad_user@$VPS_IP "cd $PROJECT_DIR && cp app/Views/pages/dashboard.php app/Views/pages/dashboard.php.backup && cp app/Views/main.php app/Views/main.php.backup && cp public/assets/css/main.css public/assets/css/main.css.backup && cp public/assets/js/parsley-extra-validators.js public/assets/js/parsley-extra-validators.js.backup"

# Upload modified files
echo "Uploading modified files..."
scp "$LOCAL_DIR/app/Views/pages/dashboard.php" iotxad_user@$VPS_IP:$PROJECT_DIR/app/Views/pages/
scp "$LOCAL_DIR/app/Views/main.php" iotxad_user@$VPS_IP:$PROJECT_DIR/app/Views/
scp "$LOCAL_DIR/public/assets/css/main.css" iotxad_user@$VPS_IP:$PROJECT_DIR/public/assets/css/
scp "$LOCAL_DIR/public/assets/js/parsley-extra-validators.js" iotxad_user@$VPS_IP:$PROJECT_DIR/public/assets/js/

# Create fonts directory and upload fonts
echo "Creating fonts directory and uploading fonts..."
ssh iotxad_user@$VPS_IP "mkdir -p $PROJECT_DIR/public/assets/css/assets/fonts"
scp -r "$LOCAL_DIR/public/assets/css/assets/fonts/"* iotxad_user@$VPS_IP:$PROJECT_DIR/public/assets/css/assets/fonts/

# Set correct permissions
echo "Setting permissions..."
ssh iotxad_user@$VPS_IP "
cd $PROJECT_DIR
sudo chown -R www-data:www-data app/Views/
sudo chown -R www-data:www-data public/assets/
sudo find app/Views/ -type f -exec chmod 644 {} \;
sudo find public/assets/ -type f -exec chmod 644 {} \;
sudo find public/assets/ -type d -exec chmod 755 {} \;
"

# Create placeholder profile image if it doesn't exist
echo "Creating placeholder profile image..."
ssh iotxad_user@$VPS_IP "
cd $PROJECT_DIR
if [ ! -f public/assets/images/profile/69cb742350f35.png ]; then
    mkdir -p public/assets/images/profile/
    cp public/assets/images/no_image.jpg public/assets/images/profile/69cb742350f35.png
fi
"

echo "Deployment completed!"
echo "Test your dashboard at: http://your_domain.com/dashboard"
echo "Check browser console for any remaining errors."
