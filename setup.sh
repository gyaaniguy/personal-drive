#!/bin/bash

# Exit on error
set -e
# Check dependencies
REQUIRED_TOOLS=(npm node php composer)
for tool in "${REQUIRED_TOOLS[@]}"; do
    if ! command -v $tool &>/dev/null; then
        echo "Error: $tool is not installed. Please install it first."
        exit 1
    fi
done


# Default values
CURRENT_USER=$(whoami)
WEB_GROUP="www-data"

# Function to ask for user input
ask_for_value() {
    local prompt="$1"
    local default_value="$2"
    local user_value

    read -p "$prompt [$default_value]: " user_value
    echo "${user_value:-$default_value}"
}

# echo "You can change these values if needed."
# Ask the user to confirm or change the web server user and group
CURRENT_USER=$(ask_for_value "Enter the owner user for app files" "$CURRENT_USER")
WEB_GROUP=$(ask_for_value "Enter the web server group" "$WEB_GROUP")

if [ -f .env ]; then
    echo "Existing .env found - keeping it (APP_KEY preserved)."
else
    echo "Setting up environment file..."
    cp .env.example .env

    # Ask for APP_URL
    APP_URL=$(ask_for_value "Enter the application URL (leave empty to skip)" "")
    if [ -n "$APP_URL" ]; then
        sed -i "s|^APP_URL=.*|APP_URL=$APP_URL|" .env
    fi
fi

echo "Installing composer dependencies..."
composer install --no-interaction --prefer-dist

echo "Installing npm dependencies..."
npm install && npm run build


if grep -q '^APP_KEY=..*$' .env; then
    echo "APP_KEY already set - keeping it."
else
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Set permissions
echo "Attempting to change ownership to $CURRENT_USER:$WEB_GROUP..."
if sudo chown -R $CURRENT_USER:$WEB_GROUP storage bootstrap/cache database 2>/dev/null; then
    echo "Ownership changed successfully."
else
    echo "Could not change owner. Insufficient permissions. Please fix manually."
fi

echo "Setting directory permissions..."
if sudo chmod -R 775 storage bootstrap/cache database 2>/dev/null; then
    echo "Permissions updated successfully."
else
    echo "Could not change permissions. Please update manually."
fi


echo "Clearing config cache..."
php artisan config:clear

echo "Setup complete!"

