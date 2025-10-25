# Action Scheduler Setup Guide

## Problem

Action Scheduler tasks are not processing automatically because WP-Cron cannot make HTTP requests from inside the Docker container to trigger itself.

**Symptoms:**
- Pending actions stuck in "pending" status
- "In progress" actions never complete
- Sync operations don't run automatically

## Root Cause

From inside the WordPress Docker container:
- WP-Cron tries to connect to `http://phase4.localtest.me`
- This hostname doesn't resolve from inside the container
- WP-Cron spawn fails with "Could not connect to server"
- Action Scheduler queue runner never executes

## Solutions

### Option 1: Manual Queue Processing (Quick Fix)

Run this command to manually process the queue:

```bash
make wp ARGS="cron event run action_scheduler_run_queue"
```

To process multiple times (recommended):

```bash
# Run 10 times to clear backlog
for i in {1..10}; do make wp ARGS="cron event run action_scheduler_run_queue"; sleep 1; done
```

### Option 2: System Cron Job (Recommended for Production)

Set up a real cron job on your host machine to trigger WP-Cron:

**On macOS/Linux:**

1. Open crontab:
   ```bash
   crontab -e
   ```

2. Add this line (replace path with your actual path):
   ```bash
   * * * * * cd /Users/patrick/Projects/thevgergroup/notion-wp/worktrees/phase-4-advanced-blocks && make wp ARGS="cron event run --due-now" >> /tmp/wp-cron.log 2>&1
   ```

3. Save and exit. Cron will now run every minute.

### Option 3: Disable WP-Cron, Use System Cron (Alternative)

For better reliability, disable WordPress's built-in cron and use system cron:

1. Add to `.env`:
   ```bash
   WORDPRESS_CONFIG_EXTRA="define('DISABLE_WP_CRON', true);"
   ```

2. Restart containers:
   ```bash
   make down && make up
   ```

3. Set up system cron (see Option 2)

### Option 4: Development Workaround (During Active Development)

When actively developing and testing syncs, trigger cron after each operation:

```bash
# After triggering a sync
make wp ARGS="cron event run action_scheduler_run_queue"
```

Or create an alias:

```bash
# Add to ~/.bashrc or ~/.zshrc
alias wp-cron="cd /Users/patrick/Projects/thevgergroup/notion-wp/worktrees/phase-4-advanced-blocks && make wp ARGS='cron event run action_scheduler_run_queue'"
```

Then just run: `wp-cron`

## Verification

Check if cron is working:

```bash
# Check for pending actions
make wp ARGS="db query 'SELECT action_id, hook, status, scheduled_date_gmt FROM wp_actionscheduler_actions ORDER BY scheduled_date_gmt DESC LIMIT 10'"

# Run cron
make wp ARGS="cron event run action_scheduler_run_queue"

# Check again - pending should have decreased
make wp ARGS="db query 'SELECT action_id, hook, status, scheduled_date_gmt FROM wp_actionscheduler_actions ORDER BY scheduled_date_gmt DESC LIMIT 10'"
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

## Best Practices

1. **Use system cron** for production environments
2. **Monitor the queue** regularly via WordPress admin
3. **Check logs** when syncs don't complete
4. **Clear failed actions** periodically to prevent bloat
5. **Test with manual triggers** before relying on automatic processing

## Additional Resources

- [Action Scheduler Documentation](https://actionscheduler.org/)
- [WordPress Cron Best Practices](https://developer.wordpress.org/plugins/cron/)
- [WP-CLI Cron Commands](https://developer.wordpress.org/cli/commands/cron/)
