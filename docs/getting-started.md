# Getting Started with Notion Sync for WordPress

This comprehensive guide will walk you through setting up the Notion Sync plugin from scratch. By the end of this guide, you'll have a working connection between your Notion workspace and WordPress site.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Understanding Notion Integrations](#understanding-notion-integrations)
3. [Creating Your Notion Integration](#creating-your-notion-integration)
4. [Installing the WordPress Plugin](#installing-the-wordpress-plugin)
5. [Connecting to Notion](#connecting-to-notion)
6. [Sharing Pages with Your Integration](#sharing-pages-with-your-integration)
7. [Verifying the Connection](#verifying-the-connection)
8. [Troubleshooting Common Issues](#troubleshooting-common-issues)
9. [Next Steps](#next-steps)

## Prerequisites

Before you begin, make sure you have:

### Required

- **WordPress Site** running version 6.0 or higher
- **PHP 8.0 or higher** on your hosting server
- **Notion Account** (free or paid)
- **Administrator access** to your WordPress site
- **Internet connection** for WordPress to communicate with Notion's API

### Recommended

- Basic familiarity with WordPress admin interface
- A test Notion workspace (optional, but recommended for first-time setup)

### How to Check Your WordPress and PHP Version

1. Log into WordPress Admin
2. Go to **Tools > Site Health > Info**
3. Expand **WordPress** section to see WordPress version
4. Expand **Server** section to see PHP version

If you don't meet the requirements, contact your hosting provider for assistance upgrading.

## Understanding Notion Integrations

Before creating an integration, it's helpful to understand what it is and how it works.

### What is a Notion Integration?

A Notion integration is like a "bot user" that can access your Notion workspace on behalf of an external application (in this case, WordPress). Think of it as giving WordPress its own Notion account with limited permissions.

### Types of Integrations

- **Internal Integration** (what we'll use): Private to your workspace, created by you
- **Public Integration**: Shared across workspaces, requires OAuth (not needed for this plugin)

### Security Model

- Integrations can ONLY access pages you explicitly share with them
- They cannot see other pages in your workspace
- You can revoke access anytime by removing the integration from shared pages
- Your integration token is like a password - keep it secret!

## Creating Your Notion Integration

This is the most important step. Follow these instructions carefully.

### Step-by-Step Instructions

1. **Open Notion Integrations Page**
    - Go to https://www.notion.com/my-integrations
    - You'll need to log in if you're not already

2. **Create New Integration**
    - Click the **+ New integration** button
    - You'll see a form with several fields

3. **Configure Your Integration**

    **Name:** Enter a descriptive name
    - Example: "WordPress Sync"
    - Example: "My Blog Sync"
    - This name will appear when you share pages

    **Associated Workspace:** Select your workspace
    - Choose the workspace containing pages you want to sync
    - If you have multiple workspaces, you'll need separate integrations for each

    **Logo:** (Optional)
    - Upload a logo if you want
    - Not required for functionality

    **Integration Type:**
    - Leave as **Internal** (default)
    - Do NOT select "Public"

    **Capabilities:**
    - Leave default capabilities checked:
        - Read content
        - Update content (for future bi-directional sync)
        - Insert content (for future bi-directional sync)
    - Do NOT uncheck "Read content" - this is required!

4. **Submit**
    - Click **Submit** button
    - You'll be taken to the integration details page

5. **Copy Your Token**
    - You'll see an **Internal Integration Token** field
    - The token starts with `secret_` and is very long
    - Click **Show** then **Copy** to copy the entire token
    - Store it somewhere safe temporarily (you'll paste it into WordPress next)

### Important Security Notes

- **Never share your token publicly**
- **Never commit it to version control** (GitHub, GitLab, etc.)
- **Never email it or post it in support forums**
- If your token is compromised, you can regenerate it on the integration details page

### Visual Reference

For a visual guide to creating Notion integrations, refer to Notion's official documentation at https://developers.notion.com/docs/create-a-notion-integration.

## Installing the WordPress Plugin

Now that you have your Notion integration token, let's install the plugin.

### Method 1: Upload via WordPress Admin (Recommended)

1. **Download Plugin**
    - Download the plugin ZIP file from the release page
    - Save it somewhere you can find it

2. **Navigate to Plugin Upload**
    - Log into WordPress Admin
    - Go to **Plugins > Add New**
    - Click **Upload Plugin** (button at top)

3. **Upload and Install**
    - Click **Choose File**
    - Select the downloaded ZIP file
    - Click **Install Now**
    - Wait for upload and installation to complete

4. **Activate**
    - After installation, click **Activate Plugin**
    - You should see a success message

### Method 2: Install via FTP/SFTP

1. **Extract ZIP File**
    - Extract the downloaded ZIP file on your computer
    - You'll see a folder named `notion-sync`

2. **Connect to Your Server**
    - Use an FTP client (FileZilla, Cyberduck, etc.)
    - Connect to your WordPress server

3. **Upload Plugin Folder**
    - Navigate to `/wp-content/plugins/`
    - Upload the entire `notion-sync` folder
    - Wait for upload to complete

4. **Activate in WordPress**
    - Log into WordPress Admin
    - Go to **Plugins > Installed Plugins**
    - Find "Notion Sync for WordPress"
    - Click **Activate**

### Method 3: Install via WP-CLI

If you have command-line access:

```bash
wp plugin install notion-sync.zip --activate
```

### Verification

After activation, you should see:

- "Notion Sync" in your WordPress admin menu (left sidebar)
- No error messages
- Plugin listed as active in **Plugins** page

## Connecting to Notion

Now let's connect WordPress to your Notion workspace.

### Step-by-Step Connection Process

1. **Navigate to Settings**
    - In WordPress Admin, look for **Notion Sync** in the left sidebar
    - Click on it to open the settings page

2. **Enter Your Token**
    - Find the **Notion API Token** field
    - Paste the token you copied from Notion (starts with `secret_`)
    - Make sure there are no extra spaces before or after the token

3. **Connect**
    - Click the **Connect to Notion** button
    - You'll see a loading indicator
    - Wait for the connection to be established (usually 2-10 seconds)

4. **Success!**
    - If successful, you'll see:
        - A green success message
        - Your Notion workspace name
        - An empty page list (we'll fix this next)

### What Happens During Connection?

When you click "Connect to Notion":

1. WordPress sends a test request to Notion API
2. Notion verifies your token
3. Notion returns your workspace information
4. WordPress saves your token securely
5. WordPress displays your workspace name

Your token is encrypted and stored in the WordPress database. It's never displayed again for security reasons.

### Settings Page

![Settings - Connection](images/settings-connection.png)

After successfully connecting, you'll see your workspace information and available pages:

![Settings - Page Selection](images/settings-page-selection.png)

## Sharing Pages with Your Integration

After connecting, you won't see any pages yet. This is normal! You need to explicitly share pages with your integration.

### Why Doesn't My Integration See All Pages?

For security and privacy, Notion integrations start with zero access. You control exactly what the integration can see by sharing specific pages.

### How to Share a Page

1. **Open Notion** in your browser
2. **Navigate to a page** you want to sync to WordPress
3. **Click Share** (button in top right corner)
4. **Click Invite** in the Share modal
5. **Search for your integration**
    - Type the name you gave your integration (e.g., "WordPress Sync")
    - You should see it appear with a small robot icon
6. **Select and Invite**
    - Click on your integration name
    - Click **Invite** to confirm

### Sharing Tips

**Start Small**

- Share just one page first to test
- Once it works, share additional pages

**Share Parent Pages**

- If you share a parent page, all child pages are automatically accessible
- This is useful for syncing entire sections of your workspace

**Share Databases**

- You can share database pages the same way
- All entries in the database become accessible

**Verify Sharing**

- After sharing, you should see your integration listed in the "Shared with" section
- If you don't see it, the sharing didn't work - try again

### Visual Guide

For step-by-step screenshots of sharing pages with integrations in Notion, see [Notion's official guide on authorizing integrations](https://developers.notion.com/docs/authorization).

## Verifying the Connection

Let's make sure everything is working correctly.

### Check in WordPress

1. **Return to WordPress Admin**
    - Go back to **Notion Sync** settings page
2. **Refresh the page**
    - Click your browser's refresh button
    - Or reload the settings page
3. **Look for Your Pages**
    - You should now see a list of pages you shared
    - Each page shows its title and Notion page ID

### What You Should See

**Success Indicators:**

- Green success message
- Workspace name displayed
- List of shared pages (at least one)
- No error messages

**If You Don't See Pages:**

- Wait 30 seconds and refresh again
- Verify sharing in Notion (check "Shared with" section)
- Try sharing a different page
- See troubleshooting section below

### Test Connection Button

Some versions include a "Test Connection" button:

- Click it to verify token is still valid
- Should show a success message
- Useful if you haven't used the plugin in a while

## Troubleshooting Common Issues

### Issue: "Invalid token" Error

**Symptoms:**

- Error message: "Invalid token" or "Unauthorized"
- Connection fails immediately
- Red error notice

**Solutions:**

1. **Verify token format**
    - Token should start with `secret_`
    - Should be 50+ characters long
    - No spaces before or after

2. **Copy token again**
    - Go back to https://www.notion.com/my-integrations
    - Click on your integration
    - Copy the token again (sometimes clipboard issues cause problems)

3. **Check integration status**
    - Make sure integration wasn't deleted
    - Verify it's still in your integrations list

4. **Try regenerating token**
    - In Notion integration settings, regenerate the token
    - Copy the new token
    - Update in WordPress

### Issue: "No pages found"

**Symptoms:**

- Connection succeeds
- Workspace name shows
- Page list is empty or says "No pages shared"

**Solutions:**

1. **Verify sharing**
    - Open page in Notion
    - Click Share
    - Confirm your integration is listed in "Shared with"

2. **Wait and refresh**
    - Wait 30-60 seconds
    - Refresh WordPress page
    - Notion's API may take a moment to update

3. **Share a different page**
    - Try sharing a simple page (not a database)
    - Test if that page appears

4. **Check integration capabilities**
    - Go to Notion integration settings
    - Ensure "Read content" capability is checked

### Issue: Network or Timeout Errors

**Symptoms:**

- "Network error"
- "Could not connect to Notion"
- "Request timeout"
- Connection takes very long then fails

**Solutions:**

1. **Check internet connection**
    - Verify WordPress site can access external sites
    - Test by visiting https://api.notion.com in browser

2. **Hosting restrictions**
    - Some hosts block external API calls
    - Contact hosting support to allow connections to api.notion.com
    - Ask about "outbound HTTPS requests"

3. **Firewall or security plugins**
    - Temporarily disable security plugins
    - Check if a firewall is blocking requests
    - Whitelist api.notion.com

4. **Try again later**
    - Notion API might be temporarily down
    - Check https://status.notion.so for status
    - Wait 15 minutes and retry

### Issue: Token Field Appears Empty

**Symptoms:**

- After saving, token field is blank
- Can't see your saved token

**This is normal!**

- For security, saved tokens are never displayed
- If you see workspace name and pages, token is saved correctly
- To change token, just enter a new one and save

### Issue: "Permission denied" or "Forbidden"

**Symptoms:**

- Error 403 or "Forbidden"
- "You don't have permission"

**Solutions:**

1. **Verify workspace**
    - Make sure you selected correct workspace when creating integration
    - Integration can only access pages in its assigned workspace

2. **Check user permissions**
    - You must be a workspace admin to create integrations
    - Regular members cannot create integrations

3. **Recreate integration**
    - Delete old integration
    - Create new one with correct workspace

### Still Having Issues?

If none of the above solutions work:

1. **Disable then re-enable plugin**
    - Go to Plugins page
    - Deactivate Notion Sync
    - Reactivate it
    - Try connecting again

2. **Check WordPress error logs**
    - Enable WP_DEBUG in wp-config.php
    - Check debug.log for detailed errors
    - Share errors with support

3. **Try different browser**
    - Sometimes browser extensions interfere
    - Test in incognito/private mode

4. **Contact support**
    - Provide WordPress version, PHP version
    - Share exact error message
    - Describe steps you've tried

## Next Steps

Congratulations! You've successfully connected Notion to WordPress.

### What You Can Do Now (Phase 0)

- View your connected workspace name
- See list of accessible Notion pages
- Disconnect and reconnect as needed
- Test with different tokens

### What's Coming Next (Phase 1 and Beyond)

**Phase 1: Content Sync**

- Sync Notion pages to WordPress posts
- Convert Notion blocks to Gutenberg blocks
- Map Notion properties to post fields

**Phase 2: Advanced Features**

- Field mapping configuration
- Multiple database support
- Custom post type targeting

**Phase 3: Media & Navigation**

- Automatic image imports
- Media library integration
- Navigation menu generation

**Phase 4: Bi-directional Sync**

- WordPress to Notion sync
- Real-time webhook updates
- Conflict resolution

### Recommended Next Actions

1. **Explore Notion**
    - Create a test page in Notion
    - Share it with your integration
    - Watch it appear in WordPress

2. **Join Community**
    - Subscribe to updates
    - Follow development progress
    - Share feedback

3. **Read Documentation**
    - Review [plugin/README.md](/plugin/README.md) for feature updates
    - Check [CONTRIBUTING.md](/CONTRIBUTING.md) if you want to contribute code

### Learning Resources

**Notion API Documentation:**

- https://developers.notion.com/docs/getting-started

**WordPress Plugin Development:**

- https://developer.wordpress.org/plugins/

**This Project's Docs:**

- [Technical Architecture](/docs/architecture/ARCHITECTURE-SUMMARY.md)
- [Development Principles](/docs/development/principles.md)

## Feedback and Support

We're actively developing this plugin and would love your feedback!

**Found a bug?**

- Check if it's a known issue
- Report it with steps to reproduce

**Have a feature request?**

- Suggest improvements
- Describe your use case

**Want to contribute?**

- Read [CONTRIBUTING.md](/CONTRIBUTING.md)
- Submit pull requests
- Improve documentation

Thank you for using Notion Sync for WordPress!
