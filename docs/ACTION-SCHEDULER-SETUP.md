# Action Scheduler Setup Guide

## Automated Setup (Phase 4+)

**Good news!** Starting with Phase 4, automated cron is built into the Docker environment.

When you start the Docker containers with `make up`, the system automatically:
1. Installs WP-CLI in the WordPress container
2. Sets up a system cron job to run every minute
3. Processes the Action Scheduler queue automatically

**No manual intervention needed!** The queue processes in the background every 60 seconds.

### Verify Automated Cron

Check that automated cron is working:

```bash
# View cron execution log
make cron-log

# Check Action Scheduler queue status
make cron-status
```

The cron log will show WP-Cron running every minute. Once WordPress is installed, you should see successful queue processing.

---

## Background: How Action Scheduler Works

**Action Scheduler uses WP-Cron** (configured in `ActionSchedulerConfig.php`), which traditionally **triggers on page loads**.

### Why Syncs Worked in Earlier Phases

When you were actively using the admin interface:
1. You'd trigger a sync from the admin panel
2. Navigate to other pages, check status, refresh
3. **Each page load triggered WP-Cron**
4. Action Scheduler processed tasks in the background
5. Syncs completed successfully!

### Why Manual Processing Was Needed

During development/testing:
- Less active admin browsing
- Fewer page loads = fewer WP-Cron triggers
- Tasks pile up in the queue waiting for page loads
- You notice tasks aren't processing

**This is actually working as designed for WordPress!** WP-Cron is "pseudo-cron" that piggybacks on page visits.

### The Solution: Automated System Cron

Phase 4+ includes automated system cron in Docker, eliminating the need for manual queue processing during development.

### Secondary Issue (Docker Environment)

Additionally, WP-Cron spawn (the HTTP self-request) fails in Docker:
- From inside the WordPress Docker container
- WP-Cron tries to connect to `http://phase4.localtest.me`
- This hostname doesn't resolve from inside the container
- WP-Cron spawn fails with "Could not connect to server"

However, because we have `action_scheduler_allow_async_request_runner` set to `false`, Action Scheduler processes the queue **directly during page loads** rather than via spawn, so this doesn't block processing.

## Manual Queue Processing (Optional)

While automated cron handles queue processing, you can still manually process the queue if needed:

### Manual Commands

```bash
# Check queue status
make cron-status

# View automated cron log
make cron-log

# Manually process queue once
make cron

# Process 10 times to clear backlog
make cron-loop

# Reset stuck actions
make cron-reset
```

**When to use manual commands:**
- Testing queue processing immediately
- Debugging queue issues
- Clearing a large backlog quickly

### Alternative Approaches (For Reference)

#### Option A: Just Use the Admin

Browse the WordPress admin after triggering syncs:
1. Trigger a sync from Notion Sync admin page
2. Navigate to other pages (Dashboard, Posts, etc.)
3. Each page load processes the queue

**Note:** With automated cron (Phase 4+), this is no longer necessary.

#### Option B: Host Machine Cron (Production)

For production environments, set up a cron job on your host machine:

```bash
# Add to crontab (crontab -e)
* * * * * cd /path/to/project && make wp ARGS="cron event run --due-now" >> /tmp/wp-cron.log 2>&1
```

#### Option C: Disable WP-Cron (Advanced)

For production with system cron:

1. Add to `.env`:
   ```bash
   WORDPRESS_CONFIG_EXTRA="define('DISABLE_WP_CRON', true);"
   ```

2. Set up system cron (see Option B)

## Verification

### Verify Automated Cron is Running

```bash
# Check cron execution log - should show activity every minute
make cron-log

# Verify cron daemon is running in container
docker exec notionwp_phase4_wp ps aux | grep cron

# View the cron job configuration
docker exec notionwp_phase4_wp cat /etc/cron.d/wordpress-cron
```

### Verify Queue Processing

Once WordPress is installed:

```bash
# Check Action Scheduler queue status
make cron-status

# Check for pending actions
make wp ARGS="db query 'SELECT action_id, hook, status, scheduled_date_gmt FROM wp_actionscheduler_actions ORDER BY scheduled_date_gmt DESC LIMIT 10'"

# Wait 60 seconds and check again - pending should decrease automatically
sleep 60
make cron-status
```

## Monitoring

View Action Scheduler logs in WordPress admin:
- Go to: **Tools → Action Scheduler**
- Check the **Pending**, **In-Progress**, **Complete**, and **Failed** tabs

Or via WP-CLI:

```bash
# Count actions by status
make wp ARGS="db query 'SELECT status, COUNT(*) as count FROM wp_actionscheduler_actions GROUP BY status'"
```

## Troubleshooting

### Actions stuck "in-progress"

This happens when an action starts but never completes. To reset:

```bash
# Mark stuck actions as failed (they'll auto-retry if configured)
make wp ARGS="db query \"UPDATE wp_actionscheduler_actions SET status='failed' WHERE status='in-progress' AND scheduled_date_gmt < DATE_SUB(NOW(), INTERVAL 1 HOUR)\""
```

### Queue not processing

1. Verify WP-Cron is registered:
   ```bash
   make wp ARGS="cron event list" | grep action_scheduler
   ```

2. Manually trigger:
   ```bash
   make wp ARGS="cron event run action_scheduler_run_queue"
   ```

3. Check for errors:
   ```bash
   make logs-errors
   ```

### Performance Issues

If queue processing is slow:

1. Check timeout setting:
   ```bash
   make wp ARGS="eval 'echo apply_filters(\"action_scheduler_timeout_period\", 300);'"
   ```
   Should return `600` (10 minutes)

2. Verify async runner is disabled:
   ```bash
   make wp ARGS="eval 'echo apply_filters(\"action_scheduler_allow_async_request_runner\", true) ? \"ENABLED\" : \"DISABLED\";'"
   ```
   Should return `DISABLED`

## Implementation Details (Phase 4+)

The automated cron system consists of three components:

### 1. Setup Script (`docker/config/setup-cron.sh`)

Runs when the WordPress container starts:
- Installs system cron daemon
- Installs WP-CLI if not present
- Creates `/etc/cron.d/wordpress-cron` configuration
- Starts cron daemon in background
- Creates `/var/log/wp-cron.log` for monitoring

### 2. Docker Compose Integration (`docker/compose.yml`)

The WordPress service:
- Mounts `setup-cron.sh` into the container
- Executes the script on startup (before Apache)
- Runs both cron daemon and Apache in the same container

### 3. Cron Job Configuration

The cron job (`/etc/cron.d/wordpress-cron`):
```bash
* * * * * www-data cd /var/www/html && wp cron event run --due-now --path=/var/www/html --quiet >> /var/log/wp-cron.log 2>&1
```

Runs every minute as `www-data` user to process all due WP-Cron events.

## Best Practices

1. **Development:** Automated cron (Phase 4+) handles queue processing automatically
2. **Production:** Use host machine cron or disable WP-Cron for better reliability
3. **Monitor the queue** regularly via WordPress admin or `make cron-status`
4. **Check logs** when syncs don't complete (`make cron-log`)
5. **Clear failed actions** periodically to prevent bloat
6. **Test with manual triggers** for immediate processing (`make cron`)

## Additional Resources

- [Action Scheduler Documentation](https://actionscheduler.org/)
- [WordPress Cron Best Practices](https://developer.wordpress.org/plugins/cron/)
- [WP-CLI Cron Commands](https://developer.wordpress.org/cli/commands/cron/)
