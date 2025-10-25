# Action Scheduler Setup Guide

## Problem

Action Scheduler tasks are not processing automatically in the Phase 4 development environment.

**Symptoms:**
- Pending actions stuck in "pending" status
- "In progress" actions never complete
- Sync operations don't run automatically

## Root Cause (And Why It Worked Before!)

**Action Scheduler uses WP-Cron** (configured in `ActionSchedulerConfig.php`), which means it **triggers on page loads**.

### Why Syncs Worked in Earlier Phases

When you were actively using the admin interface:
1. You'd trigger a sync from the admin panel
2. Navigate to other pages, check status, refresh
3. **Each page load triggered WP-Cron**
4. Action Scheduler processed tasks in the background
5. Syncs completed successfully!

### Why It's Not Working in Phase 4

During development/testing:
- Less active admin browsing
- Fewer page loads = fewer WP-Cron triggers
- Tasks pile up in the queue waiting for page loads
- You notice tasks aren't processing

**This is actually working as designed for WordPress!** WP-Cron is "pseudo-cron" that piggybacks on page visits.

### Secondary Issue (Docker Environment)

Additionally, WP-Cron spawn (the HTTP self-request) fails in Docker:
- From inside the WordPress Docker container
- WP-Cron tries to connect to `http://phase4.localtest.me`
- This hostname doesn't resolve from inside the container
- WP-Cron spawn fails with "Could not connect to server"

However, because we have `action_scheduler_allow_async_request_runner` set to `false`, Action Scheduler processes the queue **directly during page loads** rather than via spawn, so this doesn't block processing.

## Solutions

### Option 0: Just Use the Admin! (Simplest)

**This is how it worked before!** Simply browse the WordPress admin after triggering syncs:

1. Trigger a sync from Notion Sync admin page
2. Navigate to other pages (Dashboard, Posts, etc.)
3. Check back on Databases page
4. Refresh as needed

**Each page load processes the queue!** This is the normal WordPress way and requires no additional setup.

### Option 1: Manual Queue Processing (Development Workflow)

Use the new Makefile commands for more control:

```bash
# Check queue status
make cron-status

# Process queue once
make cron

# Process 10 times to clear backlog
make cron-loop

# Reset stuck actions
make cron-reset
```

**When to use:** After triggering syncs via WP-CLI or when developing without browsing admin.

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
