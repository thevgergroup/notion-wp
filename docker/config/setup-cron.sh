#!/bin/bash
#
# Setup WP-Cron for WordPress in Docker
#
# This script sets up a system cron job to trigger WordPress cron
# every minute, which is necessary for Action Scheduler to process
# background tasks reliably.
#

# Wait for WordPress to be ready
sleep 10

# Add cron job to trigger WP-Cron every minute
# Use wget instead of curl for better reliability in WordPress container
echo "* * * * * www-data cd /var/www/html && /usr/local/bin/wp cron event run --due-now --path=/var/www/html --quiet 2>&1" >> /etc/crontab

# Start cron daemon
cron

echo "WP-Cron setup complete"
