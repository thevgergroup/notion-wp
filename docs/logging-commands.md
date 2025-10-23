# Logging Commands Reference

This document describes the logging commands available in the Makefile for debugging and monitoring the Notion-WordPress sync plugin.

## Quick Reference

```bash
make logs-php            # View PHP error logs (last 100 lines)
make logs-errors         # View all error logs (last 100 lines)
make logs-perf           # View performance logs (last 100 lines)
make logs-perf-summary   # View performance summary only
make logs-sync           # View sync-related logs (last 100 lines)
make logs-live           # Live tail all logs with color filtering
```

## Detailed Command Descriptions

### `make logs-php`
**Purpose**: View PHP error logs from the WordPress container.

**Shows**:
- PHP errors and warnings
- `error_log()` output
- PHP notices

**Example Output**:
```
[php:notice] [pid 828:tid 828] PageSyncScheduler: Successfully synced page 2654dac9...
[php:notice] ImageConverter: Failed to convert image block...
```

**When to use**: Debugging PHP errors, checking error_log statements.

---

### `make logs-errors`
**Purpose**: View all error messages, warnings, and failures.

**Shows**:
- Errors from all sources
- Warnings
- Failed operations

**Example Output**:
```
ImageDownloader: Attempt 1/3 failed for https://... Invalid MIME type: image/tiff
[php:error] Fatal error in file...
```

**When to use**: Quick overview of what's failing in the system.

---

### `make logs-perf`
**Purpose**: View performance profiling logs.

**Shows**:
- Individual operation timings
- Memory usage
- Call counts

**Example Output**:
```
[PERF] api_get_page: 0.229s, 0 B memory
[PERF] fetch_page_blocks: 1.027s, 2 MB memory
[PERF] convert_block_image: 1.710s, 6 MB memory
```

**When to use**: Identifying slow operations, profiling performance.

---

### `make logs-perf-summary`
**Purpose**: View aggregated performance summaries from sync operations.

**Shows**:
- Total time per operation
- Average time per operation
- Call counts
- Memory usage
- Operations sorted by total time (slowest first)

**Example Output**:
```
[PERF SUMMARY] Sync Complete: 75424b1c... -> Post 39:
================================================================================
  sync_page_total                    | Total: 18.574s | Avg: 18.574s | Calls: 1
  convert_blocks                     | Total: 16.690s | Avg: 16.690s | Calls: 1
  convert_block_image                | Total: 16.681s | Avg: 4.170s  | Calls: 4
  fetch_page_blocks                  | Total: 1.027s  | Avg: 1.027s  | Calls: 1
  ...
================================================================================
  TOTAL: 55.088s | Memory: 24 MB
================================================================================
```

**When to use**: Analyzing overall sync performance, identifying bottlenecks.

---

### `make logs-sync`
**Purpose**: View sync-related logs only.

**Shows**:
- PageSyncScheduler messages
- NotionSync operations
- ImageConverter activity
- ImageDownloader status

**Example Output**:
```
PageSyncScheduler: Scheduled 19 pages for batch page_sync_68f9aa516be1c1.28750623
PageSyncScheduler: Successfully synced page 2654dac9... (post 61)
ImageConverter: Failed to convert image block 2644dac9... Invalid MIME type
```

**When to use**: Tracking sync operations, debugging sync failures.

---

### `make logs-live`
**Purpose**: Live tail all important logs with filtering.

**Shows** (in real-time):
- Performance logs
- PHP errors
- Sync operations
- All NotionSync-related activity

**Example Output**:
```
[PERF] convert_block_paragraph: 0.000s, 0 B memory
PageSyncScheduler: Successfully synced page...
[php:notice] ImageDownloader: Attempt 1/3 failed...
```

**When to use**:
- Monitoring sync operations in real-time
- Watching performance during active syncs
- Debugging issues as they happen

**Note**: Press CTRL+C to exit.

---

## Common Use Cases

### Debugging a Slow Sync

```bash
# 1. Trigger a sync (via admin UI or WP-CLI)
# 2. Watch it in real-time
make logs-live

# 3. After completion, view the summary
make logs-perf-summary
```

### Finding Why a Sync Failed

```bash
# Check for errors
make logs-errors

# Check sync-specific logs
make logs-sync

# Check PHP errors
make logs-php
```

### Performance Analysis

```bash
# View all performance measurements
make logs-perf

# View aggregated summary
make logs-perf-summary
```

### Continuous Monitoring

```bash
# Live tail with auto-filtering
make logs-live
```

## Performance Log Interpretation

### Understanding the Performance Summary

The performance summary shows operations sorted by **total time** (slowest first):

```
convert_block_image | Total: 16.681s | Avg: 4.170s | Calls: 4 | Memory: 6 MB
```

- **Total**: Total cumulative time for all calls
- **Avg**: Average time per call (Total / Calls)
- **Calls**: Number of times this operation was called
- **Memory**: Memory allocated during these operations

### Identifying Bottlenecks

Look for operations with:
1. **High total time** - Primary bottleneck
2. **High average time** - Slow per-operation
3. **Many calls** - Frequent operations (optimization candidates)

### Example Analysis

```
convert_block_image                | Total: 16.681s | Avg: 4.170s  | Calls: 4  ⚠️ SLOW
fetch_page_blocks                  | Total: 1.027s  | Avg: 1.027s  | Calls: 1
convert_block_paragraph            | Total: 0.000s  | Avg: 0.000s  | Calls: 36 ✅ FAST
```

**Interpretation**:
- Image conversion is the bottleneck (89.8% of total time)
- Text blocks are very efficient
- Focus optimization efforts on image handling

## Tips

1. **Log Retention**: Logs are stored in Docker and rotate automatically. The commands show the most recent 100-2000 lines.

2. **No Logs Found**: If you see "No X logs found in recent logs", either:
   - The operation hasn't run recently
   - The logs have rotated out
   - Increase the `--tail` value in the Makefile

3. **Combining Commands**: Pipe to other tools for analysis:
   ```bash
   make logs-perf | grep "convert_block" | wc -l  # Count block conversions
   ```

4. **Timing**: Run performance analysis immediately after a sync for best results.

5. **Color Output**: The terminal colors help distinguish:
   - Cyan: Command names
   - Yellow: Warnings/no results
   - Normal: Log content

## Related Documentation

- [Performance Analysis](./performance-analysis.md) - Comprehensive performance study
- [Makefile](../Makefile) - Full command reference
