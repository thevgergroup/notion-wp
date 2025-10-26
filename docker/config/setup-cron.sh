#!/bin/bash
#
# Setup WP-Cron for WordPress in Docker
#
# This script sets up a system cron job to trigger WordPress cron
# every minute, which is necessary for Action Scheduler to process
# background tasks reliably in development.
#

set -e

echo "Setting up automated WP-Cron..."

# Install cron if not present
if ! command -v cron &> /dev/null; then
    echo "Installing cron..."
    apt-get update && apt-get install -y cron
fi

# Install WP-CLI if not present
if ! command -v wp &> /dev/null; then
    echo "Installing WP-CLI..."
    curl -sS -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x wp-cli.phar
    mv wp-cli.phar /usr/local/bin/wp
fi

# Wait for WordPress to be fully initialized
echo "Waiting for WordPress to be ready..."
sleep 15

# Create cron job file
cat > /etc/cron.d/wordpress-cron << 'EOF'
# Run WP-Cron every minute to process Action Scheduler queue
# This is for development only - production should use system cron or disable WP-Cron
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

# Process WP-Cron events every minute
* * * * * www-data cd /var/www/html && wp cron event run --due-now --path=/var/www/html --quiet >> /var/log/wp-cron.log 2>&1

EOF

# Set proper permissions
chmod 0644 /etc/cron.d/wordpress-cron

# Create log file with proper permissions
touch /var/log/wp-cron.log
chown www-data:www-data /var/log/wp-cron.log

# Start cron daemon in foreground (for Docker)
echo "Starting cron daemon..."
cron -f &

echo "✓ WP-Cron automation setup complete"
echo "Action Scheduler queue will process automatically every minute"
