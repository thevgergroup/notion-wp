# WordPress.org Graphics Assets Specifications

**Plugin:** Notion Sync
**Date:** 2025-11-02
**Status:** Required for WordPress.org submission

---

## Required Assets

WordPress.org requires the following graphics for your plugin listing:

### 1. Plugin Icon (Required)

**Purpose:** Displayed in plugin search results and on the plugin page.

**Specifications:**
- **Size:** 256x256 pixels (PNG or JPG)
- **Alternative:** 128x128 pixels (for low-DPI displays)
- **File names:**
  - `icon-256x256.png` (or .jpg)
  - `icon-128x128.png` (or .jpg)
- **Location:** `/plugin/assets/` directory
- **Format:** PNG (preferred for transparency) or JPG
- **File size:** Keep under 1MB

**Design Guidelines:**
- Simple, recognizable icon
- Works well at small sizes (64x64 display size)
- Should represent sync/connection between Notion and WordPress
- Consider using Notion's color scheme (#000000, #FFFFFF) or WordPress blue (#0073AA)
- Avoid text (icon should be visual only)

### 2. Plugin Banner (Required)

**Purpose:** Displayed at the top of your plugin page on WordPress.org.

**Specifications:**
- **High-DPI:** 1544x500 pixels
- **Standard:** 772x250 pixels
- **File names:**
  - `banner-1544x500.png` (or .jpg)
  - `banner-772x250.png` (or .jpg)
- **Location:** `/plugin/assets/` directory
- **Format:** PNG or JPG
- **File size:** Keep under 2MB each

**Design Guidelines:**
- Include plugin name "Notion Sync"
- Visual representation of Notion ↔ WordPress sync
- Professional, clean design
- Text should be readable at both sizes
- Consider gradient or solid background with overlaid graphics
- Safe area: Keep important content away from edges (50px margin)

---

## Design Concepts

### Concept 1: Icon Merge
**Icon:** Notion logo + WordPress logo merged with sync arrows
**Banner:** Same concept with "Notion Sync" text overlay
**Color scheme:** Black (#000000) and WordPress blue (#0073AA)

### Concept 2: Database Connection
**Icon:** Database/grid icon with bidirectional arrows
**Banner:** Notion grid transitioning to WordPress posts
**Color scheme:** Notion black and WordPress blue with white accents

### Concept 3: Minimal Modern
**Icon:** Simple "N" and "W" with sync symbol
**Banner:** Minimal text "Notion Sync for WordPress" with subtle arrow graphics
**Color scheme:** Monochrome with accent color

---

## Brand Guidelines

### Notion Branding
- **Colors:** Black (#000000), White (#FFFFFF)
- **Fonts:** Inter (if showing Notion UI)
- **Logo usage:** Check Notion's brand guidelines before using their logo
- **Alternative:** Use abstract representation (grid, database icon)

### WordPress Branding
- **Primary color:** #0073AA (blue)
- **Secondary:** #23282D (dark gray)
- **Fonts:** Open Sans, system fonts
- **Logo usage:** WordPress logo allowed for "works with WordPress" context

---

## Tools for Creating Assets

### Online Tools (Free)
1. **Canva** - https://canva.com
   - Templates for social media graphics
   - Easy drag-and-drop interface
   - Free tier available

2. **Figma** - https://figma.com
   - Professional design tool
   - Free for personal use
   - Export at exact dimensions

3. **Photopea** - https://photopea.com
   - Free Photoshop alternative
   - Works in browser
   - Supports PSD files

### Design Software (Paid)
1. **Adobe Photoshop** - Industry standard
2. **Adobe Illustrator** - For vector graphics
3. **Sketch** - Mac-only design tool
4. **Affinity Designer** - One-time purchase alternative

### Icon Libraries (for inspiration/components)
- **Heroicons** - https://heroicons.com
- **Font Awesome** - https://fontawesome.com
- **Material Icons** - https://fonts.google.com/icons
- **Feather Icons** - https://feathericons.com

---

## File Checklist

Before uploading to WordPress.org:

- [ ] `icon-256x256.png` (256x256 pixels, PNG/JPG, <1MB)
- [ ] `icon-128x128.png` (128x128 pixels, PNG/JPG, <1MB)
- [ ] `banner-1544x500.png` (1544x500 pixels, PNG/JPG, <2MB)
- [ ] `banner-772x250.png` (772x250 pixels, PNG/JPG, <2MB)
- [ ] All files optimized (TinyPNG, ImageOptim, etc.)
- [ ] All files placed in `/plugin/assets/` directory
- [ ] Files committed to repository

---

## WordPress.org Upload Process

Assets are uploaded separately from the plugin ZIP:

1. **Access SVN repository** (after plugin is approved)
   ```bash
   svn co https://plugins.svn.wordpress.org/notion-sync
   cd notion-sync
   ```

2. **Add assets to `/assets` directory**
   ```bash
   svn add assets/icon-256x256.png
   svn add assets/icon-128x128.png
   svn add assets/banner-1544x500.png
   svn add assets/banner-772x250.png
   ```

3. **Commit to SVN**
   ```bash
   svn ci -m "Add plugin icon and banner"
   ```

4. **Assets appear on WordPress.org within minutes**

---

## Examples from WordPress.org

### Well-Designed Plugin Icons
- **Yoast SEO** - Simple "Y" mark, recognizable brand color
- **Akismet** - Clean logo, works at small sizes
- **WooCommerce** - Iconic "W" with shopping bag
- **Jetpack** - Jet icon, WordPress colors

### Well-Designed Plugin Banners
- **WooCommerce** - Product imagery with clear branding
- **Elementor** - Gradient background with logo and tagline
- **Contact Form 7** - Simple, professional design
- **Advanced Custom Fields** - Clean typography with accent color

---

## Quick Start Guide

### Option 1: Use Canva (Easiest)

1. **Create Icon:**
   - Go to Canva.com
   - Create custom size: 256x256
   - Design your icon with shapes/text
   - Export as PNG (transparent background)
   - Resize to 128x128 for second version

2. **Create Banner:**
   - Create custom size: 1544x500
   - Add background, text "Notion Sync", graphics
   - Export as PNG/JPG
   - Resize to 772x250 for second version

### Option 2: Hire a Designer

**Budget:** $50-200 for professional graphics

**Platforms:**
- **Fiverr** - https://fiverr.com (search "WordPress plugin icon")
- **Upwork** - https://upwork.com (post a job)
- **99designs** - https://99designs.com (design contest)

**Brief to provide:**
- Plugin name: Notion Sync
- Purpose: Syncs content between Notion and WordPress
- Colors: Notion black/white + WordPress blue
- Deliverables: 4 files (icon x2 sizes, banner x2 sizes)
- Format: PNG with transparency (icon), PNG/JPG (banner)

### Option 3: Use Placeholders (Temporary)

For initial submission, you can use simple text-based graphics:
- Icon: Black square with white "NS" text
- Banner: Gradient background with "Notion Sync" text

**These work but are less appealing to users.**

---

## Optimization Tips

Before committing assets:

1. **Compress images:**
   - https://tinypng.com (PNG compression)
   - https://squoosh.app (all formats)
   - ImageOptim (Mac app)

2. **Test at different sizes:**
   - View icon at 64x64 (actual display size)
   - View banner at 772x250 (most common size)
   - Ensure text is readable

3. **Check accessibility:**
   - Sufficient contrast for text
   - Icon recognizable in grayscale
   - No reliance on color alone

---

## Current Status

**POT File:** ✅ Complete (304 strings extracted)

**Graphics Assets:**
- [ ] Icon 256x256
- [ ] Icon 128x128
- [ ] Banner 1544x500
- [ ] Banner 772x250

**Next Steps:**
1. Create graphics using one of the methods above
2. Save to `/plugin/assets/` directory
3. Optimize file sizes
4. Commit to repository
5. Continue with WordPress.org submission

---

## References

- **WordPress.org Plugin Assets:** https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
- **WordPress.org Guidelines:** https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/
- **Brand Guidelines (Notion):** https://www.notion.so/brand
- **WordPress Colors:** https://make.wordpress.org/design/handbook/design-guide/foundations/colors/
