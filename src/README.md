# Admin Dashboard Source Files

This directory contains the **source files** for the admin sync dashboard Preact component.

## Files

- `sync-dashboard.jsx` - Original source with JSX syntax
- `sync-dashboard.js` - Compiled version (JSX → h() calls)

## Build Process

The `.js` file is already compiled and ready to use. It gets copied to:
```
plugin/assets/build/sync-dashboard.js
```

This file is enqueued by `plugin/src/Admin/SettingsPage.php` to show real-time sync progress.

## Development

If you need to modify the sync dashboard:

1. Edit `sync-dashboard.jsx` (source with JSX)
2. Compile JSX to `.js` using a JSX compiler (babel, esbuild, etc.)
3. Copy compiled `sync-dashboard.js` to `plugin/assets/build/`

Or just edit `sync-dashboard.js` directly if you're comfortable with `h()` syntax.

## Why Not in Plugin Directory?

These are **build sources** kept separate from the plugin distribution. Only the compiled file in `plugin/assets/build/` is needed at runtime.
