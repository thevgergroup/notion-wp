# Getting Started

Complete setup guide for Notion Sync for WordPress.

---

## Step 1: Create a Notion Integration

1. Go to [Notion Integrations](https://www.notion.so/my-integrations)
2. Click **+ New integration**
3. Give it a name (e.g., "WordPress Sync")
4. Select the workspace you want to use
5. Click **Submit**
6. Copy your **Internal Integration Token** (starts with `secret_`)

---

## Step 2: Share Pages with Your Integration

1. Open the Notion page you want to sync
2. Click **Share** in the top right
3. Click **Invite** and select your integration
4. Repeat for all pages you want to sync

> **Tip:** If you share a parent page, all child pages are automatically shared!

---

## Step 3: Connect WordPress to Notion

1. In WordPress, go to **Settings → Notion Sync**
2. Paste your **Integration Token**
3. Click **Test Connection**
4. If successful, you'll see your available pages

---

## Step 4: Select Pages to Sync

1. Check the boxes next to pages you want to sync
2. Click **Sync Selected Pages**
3. The plugin will import your content in the background

---

## Step 5: Add Navigation to Your Site

The plugin automatically creates a WordPress menu from your Notion page hierarchy. You have two options to display it:

### Option A: Use the Navigation Menu (Recommended for most themes)

1. Go to **Appearance → Menus** or **Appearance → Editor** (block themes)
2. Find the menu named **Notion Pages** (auto-created)
3. Assign it to a menu location in your theme or add it to a Navigation block
4. Visit your site to see the navigation

### Option B: Use the Sidebar Pattern (For themes without menu support)

Some themes don't support navigation menus. For these cases, we provide a sidebar pattern:

1. Edit any page/post or template in the Site Editor
2. Click **`+`** → **Patterns** → **"Notion Sync"**
3. Insert **"Notion Navigation Hierarchy"**
4. Your collapsible sidebar navigation appears

**[Complete Pattern Documentation →](features/BLOCK-PATTERNS.md)**

---

## Next Steps

- [Usage Guide](USAGE.md) - Learn how to sync pages and databases
- [FAQ](FAQ.md) - Common questions and troubleshooting
- [Security Guide](SECURITY.md) - Security features and best practices

---

**Need help?** See our [Support](https://github.com/thevgergroup/notion-wp/issues) page.
