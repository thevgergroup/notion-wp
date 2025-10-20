# Notion--WordPress Sync: Options, Features, Gaps, and How to Build a Two-Way Plugin

## 1. Existing Options to Sync Notion with WordPress

There are several ways to integrate Notion content into a WordPress
site, ranging from dedicated plugins to external services and DIY
solutions:

-   **WP Sync for Notion (WordPress Plugin)** -- A plugin by WP Connect
    that imports Notion content into WordPress. It can sync a Notion
    page or database to WordPress posts/pages, mapping fields and
    content via the Notion
    API[\[1\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=With%20our%20Notion%20to%20WordPress,multiple%20advanced%20features%20is%20available)[\[2\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,pages).
    This plugin eliminates the need for Zapier or Make by directly
    connecting WordPress to Notion. (Free and Pro versions available --
    details below.)

-   **Content Importer for Notion (WordPress Plugin)** -- An open-source
    plugin by Patrick Chang that pulls content from a Notion
    **database** into
    WordPress[\[3\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=Content%20Importer%20for%20Notion%20is,styles%20in%20the%20WordPress%20admin).
    It fetches pages from a specified Notion database and stores them as
    a custom post type in WordPress (for caching), allowing you to embed
    each page via
    shortcode[\[4\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=,easy%20style%20and%20custom%20CSS)[\[5\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=1,Styles).
    This tool supports many block types (text, headings, lists, toggles,
    callouts, simple tables, etc.) and **uploads images** from Notion
    into the WordPress Media
    Library[\[6\]](https://github.com/pchang78/notion-content#:~:text=,directly%20to%20Wordpress%20Media%20Library).
    It is completely free
    (GPL-licensed)[\[7\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=License).

-   **Notion to WordPress Services (No-Plugin SaaS)** -- Third-party
    services can act as a bridge between Notion and WordPress:

-   **Cloudpress** -- A SaaS that exports Notion pages (or databases) to
    WordPress via the WordPress REST API (no plugin required). It
    preserves formatting (headings, **bold/italic**, lists, tables,
    **code blocks**,
    etc.)[\[8\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Preserve%20formatting)
    and uploads images to the WP media library (with options to
    resize/compress)[\[9\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Resize%20and%20compress%20images).
    Cloudpress converts Notion embeds into proper Gutenberg embed
    blocks[\[10\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Supports%20Notion%20embeds)
    and even supports mapping Notion database properties to WP fields
    (including custom fields and SEO
    metadata)[\[11\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Export%20Notion%20Database)[\[12\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Export%20custom%20Gutenberg%20blocks).
    This is a paid service (14-day free trial for 5 documents).

-   **Notionto (Notion to WP)** -- A newer service (launched mid-2025)
    that offers syncing of Notion pages or databases to WordPress. You
    sign up on their platform and install a companion "Notion to WP"
    plugin on your site, which uses a connection key to receive
    content[\[13\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=Step%204,to%20connect%20your%20WordPress%20website)[\[14\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=,wp.zip%29%2C%20and%20then%20install%20it).
    Notionto offers a **free plan** for trying it
    out[\[15\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=Step%201,Notion%20to%20WP),
    with presumably paid tiers for higher usage. It similarly uses the
    Notion API under the hood to push content to WordPress.

-   **Cloud Automation Tools** -- You can use **Zapier**, **Make.com
    (Integromat)**, or **Uncanny Automator** to connect Notion and
    WordPress. For example, some users have used Notion's API combined
    with **WP All Import** to feed content into WordPress (WP All Import
    can consume a JSON feed or
    webhook)[\[16\]](https://www.reddit.com/r/Notion/comments/13gbnxz/how_notion_page_sync_to_wordpress/#:~:text=%E2%80%A2%20%202y%20ago).
    These solutions can sync basic content or form data (e.g. sending
    WordPress form entries to a Notion
    database[\[17\]](https://wp-umbrella.com/blog/wp-connect/#:~:text=There%20are%20three%20solutions%20for,integrating%20Notion%20and%20WordPress)[\[18\]](https://wp-umbrella.com/blog/wp-connect/#:~:text=3%20Add,Forms%20to%20Notion)),
    but for rich page content they may require custom setup and often
    won't handle complex formatting or images out of the box.

-   **Notion as a Published Site (Not WordPress)** -- *(For context)*
    Some people avoid WordPress entirely and use tools like
    **Super.so**, **Potion**, **Simple.ink**, **Notaku** etc., which
    host your Notion pages as a website with added navigation and
    styling. You mentioned using Super.so to proxy Notion -- this indeed
    provides better navigation and theming on top of Notion's content,
    but it can be expensive. These aren't WordPress solutions, but they
    illustrate the demand for turning Notion into a website. The focus
    below will remain on WordPress-based approaches.

## 2. Features & Pricing of Notion--WP Integration Solutions

Each solution has different capabilities and pricing models:

-   **WP Sync for Notion (by WP Connect)**: The **Free version**
    (available on WP.org) supports one Notion **page** connection
    (databases require Pro), and manual or limited scheduled
    syncs[\[19\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=You%20can%20try%20the%20free,in%20your%20WordPress%20admin%E2%86%92Plugins%E2%86%92Add%20New).
    It can import content as actual WP Posts/Pages (or a custom post
    type) and preserve major formatting: **paragraphs, headings, lists,
    quotes, tables, toggles, callouts, separators, images, videos,
    files, and
    columns**[\[20\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Spotify%20%26%20Loom%20links%20too).
    Free version limitations include *only one connection*, *pages only
    (no database sync)*, no custom post types, no custom fields mapping,
    and only basic sync frequency
    options[\[19\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=You%20can%20try%20the%20free,in%20your%20WordPress%20admin%E2%86%92Plugins%E2%86%92Add%20New)[\[21\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=The%20free%20version%20has%20the,following%20limitations).
    The **Pro version** (paid) unlocks **unlimited connections**,
    **database sync** support, custom post types, custom field mapping,
    and advanced sync triggers (e.g. instant updates via webhooks and
    scheduled
    sync)[\[22\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=match%20at%20L295%20You%20can,Support%2C%20and%20all%20Sync%20strategies)[\[23\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=You%20can%20access%20more%20features,Support%2C%20and%20all%20Sync%20strategies).
    There is also a **Pro+ tier** which adds integration with Advanced
    Custom Fields and SEO plugins (Yoast, RankMath,
    etc.)[\[24\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=Notion%20WP%20Sync%20Pro%2B).
    Essentially, the paid version offers "full features, including Pages
    and Database Sync... Shortcodes support (Gutenberg block), CPT,
    Custom Fields, and all sync
    strategies"[\[23\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=You%20can%20access%20more%20features,Support%2C%20and%20all%20Sync%20strategies)[\[25\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=including%20Pages%20and%20Database%20Sync,Support%2C%20and%20all%20Sync%20strategies).
    Pricing is typically annual (they have Single site and Team
    licenses)[\[22\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=match%20at%20L295%20You%20can,Support%2C%20and%20all%20Sync%20strategies).

*Capabilities:* When using WP Sync, you can map Notion database
properties to WordPress fields (title, date, categories, tags, excerpt,
etc.), which is very useful for
blogs[\[26\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,support%20in%20Pro%2B%20version)[\[27\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=%28cover%2C%20icon%2C%20%E2%80%A6%29%20,block%20or%20use%20shortcodes%20for).
The plugin supports either importing content as actual WP posts/pages or
using a Gutenberg block/shortcode to embed Notion content within an
existing page (the block/shortcode method is mainly in
Pro)[\[27\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=%28cover%2C%20icon%2C%20%E2%80%A6%29%20,block%20or%20use%20shortcodes%20for)[\[28\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Creating%20your%20shortcode%20content%20will,can%20be%20used%20by%20developers).
It also supports choosing a "sync strategy" (e.g. add only, add &
update, or full add/update/delete mirroring) and can trigger sync
manually, on a schedule, or instantly via webhooks (the webhook instant
sync is
Pro-only)[\[29\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Syncing%20Issues)[\[30\]](https://ps.w.org/wp-sync-for-notion/assets/screenshot-3.png?rev=2875744#:~:text=).
*Cost:* Free for basic use; Pro starts around \~\$79/year (based on
similar WP Connect pricing) -- the user described it as "ridiculously
expensive," so presumably the Pro version might feel pricey if only
needed for one site.

-   **Content Importer for Notion (Patrick Chang)**: This plugin is
    **100% free** and open-source. It focuses on pulling pages from a
    Notion **database**. Key features include generating WordPress
    shortcodes for each Notion page so you can embed them, and allowing
    custom CSS styling for Notion elements via the WP admin
    UI[\[4\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=,easy%20style%20and%20custom%20CSS)[\[5\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=1,Styles).
    It caches content locally (stored as a custom post type
    `notion_content`) to avoid excessive API
    calls[\[31\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=,style%20and%20custom%20CSS%20management)[\[32\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=This%20plugin%20uses%20the%20Notion,or%20all%20pages%20at%20once).
    Supported blocks cover most standard content: headings,
    **bullet/numbered lists**, to-do checkboxes, quotes, callouts,
    toggles (collapsibles), dividers, simple tables, and **images**.
    Notably, images from Notion are **imported into the WordPress media
    library**
    automatically[\[6\]](https://github.com/pchang78/notion-content#:~:text=,directly%20to%20Wordpress%20Media%20Library).
    It also supports basic column layouts from Notion (columns are
    converted into HTML/CSS
    columns)[\[33\]](https://github.com/pchang78/notion-content#:~:text=Notion%20Columns).
    You can refresh all content or individual pages on demand, or set up
    an auto-refresh interval using WP-Cron (e.g. every 15 minutes,
    hourly, daily,
    etc.)[\[34\]](https://github.com/pchang78/notion-content#:~:text=Automatic%20Content%20Refresh).
    *There is no paid tier* -- all features are included for free, but
    being a newer plugin, it may not have a polished UI or advanced
    field mapping like WP Sync for Notion. It's best suited if your
    content lives in a Notion database (for example, a database of
    lessons or blog posts).

-   **Cloudpress:** Cloudpress is a **subscription service** (with a
    free trial) rather than a plugin. It is quite feature-rich in terms
    of content fidelity:

-   It **preserves formatting** accurately: headings, styling (bold,
    italics, etc.), lists, tables, and even code blocks are retained in
    the
    export[\[8\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Preserve%20formatting).

-   It can **export a whole Notion database** of articles in one go,
    mapping properties to WP fields (including custom fields/ACF and SEO
    fields)[\[11\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Export%20Notion%20Database).

-   All images are **uploaded to WordPress** (with options to
    auto-compress or resize for
    performance)[\[9\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Resize%20and%20compress%20images).
    Alt text and captions from Notion are preserved for
    SEO[\[35\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20SEO%20Optimize%20images).

-   It converts Notion embeds (YouTube, Twitter, etc.) into proper
    Gutenberg embed blocks in
    WordPress[\[10\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Supports%20Notion%20embeds),
    which many plugins don't handle.

-   It offers a choice to output content as **Gutenberg blocks** or as
    Classic editor
    HTML[\[36\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Gutenberg%20or%20Classic).
    In Gutenberg mode, Cloudpress will translate Notion blocks into
    native WordPress block types where
    possible[\[36\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Gutenberg%20or%20Classic).

-   Advanced features: "Raw Content Block" to inject custom Gutenberg
    blocks (useful if you want to insert a special block like a signup
    form via a placeholder in
    Notion)[\[12\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Export%20custom%20Gutenberg%20blocks),
    bulk export and even an automation mode (via webhook triggers +
    Notion automations) for one-click publishing multiple
    pages[\[37\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Automate%20your%20exports).

-   *Pricing:* Cloudpress typically charges a monthly or annual fee
    based on number of exports. The site mentions a 14-day free trial (5
    documents)[\[38\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Do%20you%20have%20a%20free,trial).
    After that, it's a paid service (the exact price depends on plan,
    but it is aimed at businesses/teams managing content).

-   **Notionto (Notion to WP)**: Notionto appears to combine a cloud
    service with a WP plugin. There's a **free plan** to test
    it[\[15\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=Step%201,Notion%20to%20WP),
    and likely paid plans for higher usage, but detailed pricing isn't
    published in the docs we have. In terms of capabilities, it
    advertises syncing Notion pages or databases to your own WordPress
    site in a few
    steps[\[39\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=The%20tools%20we%20will%20be,using%20are)[\[40\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=,Databases%20to%20your%20WordPress%20website).
    The workflow suggests it can take Notion content and publish it to
    WordPress similar to WP Sync. Since it requires installing their
    plugin and using a "Connection
    Key"[\[13\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=Step%204,to%20connect%20your%20WordPress%20website)[\[14\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=,wp.zip%29%2C%20and%20then%20install%20it),
    the heavy lifting is probably done on their servers. We can infer it
    supports images and basic blocks as well (though we'd need their
    feature list for specifics). Notionto is a newcomer, and its selling
    point might be affordability or simplicity (free for basic use).

-   **DIY with Notion API and WordPress**: For completeness, one option
    is coding a custom integration using the **Notion API** and
    WordPress's REST API or PHP. This requires programming, but gives
    full control. For example, one could write a script to query Notion
    for pages and then use WordPress's XML-RPC or REST API to create
    posts. Some users on Reddit suggested using WP All Import or
    Make.com to catch a JSON feed from
    Notion[\[16\]](https://www.reddit.com/r/Notion/comments/13gbnxz/how_notion_page_sync_to_wordpress/#:~:text=%E2%80%A2%20%202y%20ago)
    -- however, there's no native Notion JSON feed, so you'd have to
    generate one (perhaps by a custom script or using Notion's API via a
    service). This approach is labor-intensive and typically only used
    if existing plugins don't meet needs.

**Summary of Free/Paid:** If you need a completely free solution,
**Content Importer for Notion** is free and supports images, but it
requires using a Notion database and shortcodes for content. **WP Sync
for Notion** has a free version with limitations (pages only, one
connection). Full syncing including databases and advanced features will
require purchasing **WP Sync Pro** or using an external service like
Cloudpress or Notionto (which have subscription costs). The exact gap in
pricing can be significant -- e.g., WP Sync Pro Single-site license cost
vs. Cloudpress subscription -- but all the robust options do have a
price. Many users initially try the free Notion share or Super.so (as
you did) and then look for a cheaper self-hosted route; the plugins can
indeed enable that, but the most complete ones are commercial.

## 3. Gaps in Current Solutions (What Users Are Looking For)

Despite the tools above, there are **notable feature gaps** that users
often ask for. Based on documentation, support forums, and user
feedback, these are the main limitations and missing features:

-   **Bi-Directional Sync (Two-Way Editing):** Nearly all current
    solutions are **one-way** -- Notion content is pushed to WordPress,
    but edits made in WordPress do *not* sync back to Notion. For
    example, WP Sync for Notion explicitly states it's a **one-way sync
    from Notion to WordPress
    only**[\[41\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=%E2%9A%A0%EF%B8%8F%20IMPORTANT%20NOTE).
    If someone updates the post in WordPress, those changes are not
    reflected in Notion. Many users want Notion to be the single source
    of truth (which is why one-way is acceptable), but some have asked
    for two-way sync so that WordPress changes or new WP posts could
    update/create corresponding Notion pages. Currently, two-way sync is
    a gap -- implementing this is complex and thus not offered in
    existing plugins.

-   **Internal Links & Navigation Structure:** When Notion pages contain
    links to other Notion pages, or you have a hierarchy of pages
    (parent pages with subpages), the existing tools do not elegantly
    handle that for website navigation:

-   **Internal page links** in Notion are usually not converted to
    WordPress equivalents. WP Sync's docs confirm that internal links
    won't automatically convert to WP page
    URLs[\[42\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Checkboxes).
    This means if Page A in Notion linked to Page B, after syncing, the
    link might still point to Notion (or be a broken reference) unless
    manually fixed. Users desire a solution where links between Notion
    pages become internal links on the WordPress site. This is a current
    gap -- one needs to manually adjust links or rely on workarounds
    (like using a Notion database relation to map links, which is not
    straightforward).

-   **Navigation Menus / Hierarchy:** Notion's sidebar hierarchy (pages
    and sub-pages) does not automatically become a menu in WordPress.
    The plugins will import pages/posts but **do not auto-create
    WordPress menus or site navigation**. Users often want their Notion
    page structure mirrored as a site structure. Currently, you must
    manually arrange pages or use a custom approach. For instance, WP
    Sync can include child pages in a sync if you choose a parent and
    toggle "include
    children"[\[43\]](https://ps.w.org/wp-sync-for-notion/assets/screenshot-1.png?rev=2875744#:~:text=),
    but it will just import those as separate WP pages (it doesn't
    generate a menu). So, providing a way to map Notion's page tree to
    WordPress menus (or parent/child pages in WP) is a feature gap that
    people would appreciate.

-   **Support for All Notion Block Types:** Notion is very rich in
    content types, and not everything is supported in exports:

-   **Embeds and Widgets:** If you embed a widget in Notion (like an
    Indify widget, or a Google Map, or any embed block), most plugins
    will either skip it or import it as a static image (or not at all).
    In a support thread, a user noted an Indify widget didn't appear on
    the WordPress
    side[\[44\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=None%20of%20the%20dates%20appear,indify%29%20which%20doesn%E2%80%99t%20appear)[\[45\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=I%E2%80%99d%20also%20like%20to%20mention,of%20widgets%20in%20our%20integration)
    -- the plugin author confirmed they *do not support embedding
    third-party widgets* in the current
    integration[\[45\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=I%E2%80%99d%20also%20like%20to%20mention,of%20widgets%20in%20our%20integration).
    Similarly, Notion's **web Bookmark block** (link previews with an
    image) is not supported by WP
    Sync[\[46\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Calendar%20view),
    so those just won't show up (users reported "bookmarks"
    missing[\[47\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=I%E2%80%99m%20having%20the%20same%20issue,fine%20but%20not%20the%20bookmarks)[\[48\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=Hi%2C)).

-   **To-do Checkboxes:** Notion's checkbox lists (to-do lists) might
    not all carry over. WP Sync's docs list "Task lists" and
    "Checkboxes" as not supported
    yet[\[49\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Here%20is%20what%20is%20not,supported%20by%20our%20plugin%20yet)[\[50\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Checkboxes).
    (The Content Importer plugin *does* support to-do checkboxes as it
    lists "To Do" blocks support, but WP Sync only recently added
    partial support.)

-   **Synced Blocks and Buttons:** Newer Notion features like **synced
    blocks** (blocks that mirror content across pages) and the
    **"Button" block** are not supported in
    exports[\[51\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,AI%20block).
    They will likely be ignored or flattened.

-   **Database Views:** If a Notion page contains an inline database or
    a board/calendar view, those are not exported by these tools (they
    typically only pull the raw content of pages or entries, not the
    rendered database view). WP Sync only supports table view of a
    database and not
    others[\[46\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Calendar%20view).

-   **Equations (Math)** and **mentions/reminders** are usually not
    handled. WP Sync notes that the Table of Contents block, math
    equations, page mentions, and reminders are not
    supported[\[52\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Equation%20%E2%80%93%20In%20this%20block).

-   **Code Blocks:** Interestingly, WP Sync's documentation did not list
    code blocks in supported content. It's possible that code blocks are
    treated as plain text or not handled specially. (Cloudpress
    explicitly preserves code
    blocks[\[8\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Preserve%20formatting),
    indicating that was a needed feature.) If your content includes
    programming examples, current plugins might not format them as a
    proper code block in WP. This is a gap if you need nicely formatted
    code snippets.

*In summary:* Most tools support **basic rich text** and common media
(images, bulleted lists, headings, etc.). But users looking to bring
over more interactive or advanced Notion blocks (embedded content,
charts, Notion widgets, etc.) often find those are omitted. There is
active interest in broader block support, and some of these gaps will
likely be addressed as plugins evolve (for example, WP Sync recently
added toggle and callout support after initially lacking
them[\[53\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Image)[\[54\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=In%20Pages)).

-   **Images and Files Handling:** Many early Notion-to-WordPress
    solutions failed to properly handle images (for example, some
    automations would bring over text but leave images out or just
    hotlink to the Notion-hosted image URL). Modern solutions like WP
    Sync and Content Importer do fetch images, but the **gap was** that
    not all tools did this well. The plugin you found ("WP Notion sync")
    does mention image support in its feature
    list[\[55\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,in%20Notion%20or%20customize%20it)[\[56\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,Pro%20Version),
    so it likely downloads images. Content Importer explicitly uploads
    Notion images to the WP
    library[\[6\]](https://github.com/pchang78/notion-content#:~:text=,directly%20to%20Wordpress%20Media%20Library).
    Cloudpress also brings in images with compression
    options[\[9\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Resize%20and%20compress%20images).
    So image support has improved. If any gap remains, it might be
    handling of **Notion file attachments** (non-image files) -- WP Sync
    supports "Files"
    blocks[\[57\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Columns),
    presumably downloading them, but it's something to verify. Users
    definitely want images to come through, so any solution that doesn't
    do images would be considered incomplete.

-   **Real-Time Sync and Scheduling:** Users often ask "can it sync
    immediately when I update Notion?" -- Webhooks are the ideal
    solution for instant updates, but not all tools support them.
    Notion's API recently introduced webhooks for page changes. WP Sync
    Pro uses webhooks to offer near-instant sync (Pro version has
    *"Instant via webhook"*
    trigger[\[30\]](https://ps.w.org/wp-sync-for-notion/assets/screenshot-3.png?rev=2875744#:~:text=)).
    Content Importer doesn't have webhooks; you'd rely on cron intervals
    or manual refresh. So a gap for some is the lack of instantaneous
    update in free tools -- they may have to wait for a cron job or hit
    a sync button.

-   **Complex Field Mapping in Free Solutions:** WP Sync's free version
    cannot map all custom fields or taxonomies (those are Pro
    features)[\[26\]\[58\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,support%20in%20Pro%2B%20version).
    So if a user needs to map a Notion property (say "Course Category")
    to a WordPress taxonomy or custom field, they need Pro or another
    tool. Content Importer is more simplistic and doesn't map metadata
    at all (aside from page title and content). This means **managing
    SEO fields, post excerpts, slugs, etc. from Notion** is only
    supported in the paid tiers (WP Sync Pro+ can sync SEO meta to
    Yoast/RankMath[\[24\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=Notion%20WP%20Sync%20Pro%2B),
    Cloudpress can map properties to meta
    fields[\[11\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Export%20Notion%20Database)).
    Many users desire the ability to do *everything* from Notion --
    including setting the URL slug, meta description, featured image,
    etc. -- which is partially a gap unless you pay for a top-tier
    solution.

In short, **people are looking for** a more seamless integration that
covers: true two-way sync, automatic internal link conversion and menu
generation for Notion's page structure, support for *all* Notion block
types (or at least graceful handling of unsupported ones), and a
low-cost or free solution that doesn't skimp on images or databases.
These gaps hint at opportunities for improvement in a new plugin.

## 4. Notion API Capabilities Relevant to Sync

To understand what a sync tool can do, it's important to know what the
**Notion API** offers (as of 2025):

-   **Access to Pages and Blocks:** The Notion API allows you to
    *retrieve the content of any page* that your integration has access
    to. This is done by fetching the page's **blocks** (via
    `retrieve block children`
    endpoint)[\[32\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=This%20plugin%20uses%20the%20Notion,or%20all%20pages%20at%20once)[\[59\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=This%20plugin%20uses%20the%20following,the%20title%20of%20a%20page).
    Each paragraph, heading, image, list item, etc., comes through as a
    block object in JSON. The API supports a wide range of block types
    (paragraph, headings, lists, to-dos, toggle, quote, callout, code,
    image, video, embed, etc.), each with its content and formatting
    attributes[\[60\]](https://developers.notion.com/reference/block#:~:text=A%20block%20object%20represents%20a,that%20you%20can%20interact).
    A sync tool will use this to reconstruct the page in WordPress.

-   **Images and Files:** When the API returns an **image block** or
    file, it typically provides a URL to the file (often an Amazon S3
    URL). These URLs can be accessed by the integration to download the
    image. However, they are time-limited URLs. The Notion API also has
    a newer file **upload**
    endpoint[\[61\]](https://developers.notion.com/reference/webhooks#:~:text=,Search)
    which can be used to upload files *to Notion* (for WP→Notion
    direction). For Notion→WP, the plugin can fetch the image file from
    the given URL and then upload it into WordPress (most plugins do
    this so that images live on your WordPress server). The Notion API's
    **Files & Media property** type is supported, so you can also get
    images that are part of a database property (like a cover image
    property)[\[62\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Supported%20properties%20).

-   **Databases and Querying:** The API can list and filter **database
    entries**. For a given database (identified by its ID), you can
    query all pages (rows) in that database with pagination. Each page's
    properties (title, tags, etc.) can be retrieved via
    `retrieve a page` or included in the query results. This is how
    tools sync a batch of blog posts or lessons: query the database to
    get all item IDs and basic fields, then retrieve each page's blocks
    for content. You can also filter or sort via the API if needed
    (e.g., only get published
    items)[\[63\]](https://developers.notion.com/reference/webhooks#:~:text=,List%20data%20source%20templates%20get).
    Notion API exposes **property types** like rich text, dates, select,
    multi-select, number, etc., which correspond to what you can map to
    WordPress
    fields[\[62\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Supported%20properties%20).

-   **Creating and Updating Content:** The API is **bi-directional** in
    the sense that an integration can also **create new pages**, update
    page properties, and append or update blocks. For example, there are
    endpoints to create a page in a database (to add a new
    entry)[\[64\]](https://developers.notion.com/reference/webhooks#:~:text=,Update%20a%20database%20patch),
    and to update page properties (e.g., mark something
    published)[\[65\]](https://developers.notion.com/reference/webhooks#:~:text=,Data%20sources)[\[66\]](https://developers.notion.com/reference/webhooks#:~:text=,48).
    Blocks can be appended to a page (to add new content blocks) or
    existing blocks
    updated/deleted[\[67\]](https://developers.notion.com/reference/webhooks#:~:text=,Retrieve%20a%20page%20get)[\[68\]](https://developers.notion.com/reference/webhooks#:~:text=,Databases).
    This means a WordPress plugin *could* push changes back to Notion
    (for instance, if a WP post was edited, the plugin could use the
    Notion API to find the corresponding block and update the text). The
    ability is there, though implementing it requires careful mapping.

-   **Hierarchy and Child Pages:** Notion pages can have sub-pages. In
    the API, a sub-page inside another page appears as a **block of
    type** `child_page` (or in a newer API version, some pages are just
    listed via parent relationships). The API will give you the ID of
    the child page. A sync tool can recursively retrieve child pages if
    needed. This is how a plugin might offer "include children pages"
    (by traversing down the page tree). However, the API doesn't provide
    a single call to get an entire hierarchy; you have to retrieve page
    content and then identify child-page blocks and fetch them one by
    one.

-   **Webhooks (Real-Time Notifications):** The Notion API recently
    introduced **webhooks** that let an integration subscribe to events
    like page content updated, page created,
    etc.[\[69\]](https://developers.notion.com/reference/webhooks#:~:text=Webhooks%20let%20your%20integration%20receive,in%20sync%20with%20user%20activity)[\[70\]](https://developers.notion.com/reference/webhooks#:~:text=Let%E2%80%99s%20walk%20through%20an%20example,from%20start%20to%20finish).
    When something changes in Notion, Notion can send an HTTP POST to a
    webhook URL. This is extremely useful for instant sync: the
    WordPress plugin (or its cloud service) can listen for these events
    and then trigger a fetch of the updated content. However, using
    webhooks requires that the Notion integration is set up with a
    webhook subscription (in Notion's developer settings) and that the
    workspace is on a plan that allows webhooks (Notion's webhooks
    require a paid plan for the workspace, according to Notion's
    documentation[\[71\]](https://www.notion.com/help/webhook-actions#:~:text=Webhook%20actions%20%E2%80%93%20Notion%20Help,are%20available%20on%20paid%20plans)).
    WP Sync Pro likely handles this under the hood (the user would set
    up the integration's webhook to point to a WP endpoint). If webhooks
    aren't used, the alternative is polling (scheduled checks). The
    API's rate limits are generous for most use-cases (currently \~50
    requests per second per integration), but excessive polling is not
    ideal, hence webhooks are better for near-real-time updates.

-   **Search:** The API provides a search endpoint to find pages by
    title[\[72\]](https://developers.notion.com/reference/webhooks#:~:text=,your%20token%27s%20bot%20user%20get),
    which could be used if a plugin needs to lookup a page by name. But
    more often, one would store the mappings of Notion page IDs to
    WordPress posts in a local database.

In summary, the Notion API is quite capable for building a sync plugin:
you can **read content, create/update content, retrieve all necessary
data (including images and files), and get notified of changes**. The
main limitations are with certain Notion-only features (mentioned
earlier, like some block types or formulas which don't have a
straightforward representation via API). But for text content
management, the API covers the bases. Any WordPress plugin we build
would heavily utilize these endpoints (list database entries, get page
content blocks, download images, etc. and conversely create/update pages
for two-way sync).

## 5. Building a Bi-Directional Notion--WordPress Sync Plugin

To build a WordPress plugin that offers **bi-directional syncing** with
Notion (primarily Notion → WP, but also WP → Notion), we would need to
address several key requirements and challenges:

### Content Retrieval and Storage (Notion → WordPress)

-   **Using the Notion API Integration:** First, the plugin needs to
    connect to Notion via an **Internal Integration Token** (a key from
    the user's Notion account). With that token and the appropriate
    share permissions on pages/databases, the plugin can query pages.
    The user would input this token in the plugin settings and select
    which Notion content to sync (could be one or more top-level pages
    or a database). For example, in WP Sync for Notion, the user selects
    either a database or a page and can opt to include child
    pages[\[43\]](https://ps.w.org/wp-sync-for-notion/assets/screenshot-1.png?rev=2875744#:~:text=).
    We'd implement a similar connection setup.

-   **Fetching Pages/Databases:** Depending on user choice, the plugin
    should either:

-   **Pull a Notion Database**: fetch all pages (rows) from a Notion
    database and sync each as a WordPress post (or any post type). This
    involves using `query database` (or the newer `databases/query` or
    `data sources` API) to get the list of page IDs, then retrieving
    each page's content blocks. We'd also retrieve each page's
    properties to map things like title, tags, date, etc.

-   **Pull a Notion Page (and Sub-Pages)**: retrieve a single page's
    content blocks via `retrieve block children`. If "include sub-pages"
    is enabled, recursively fetch any child pages (blocks of type
    child_page or linked pages) and import them, preserving the
    hierarchy. The plugin might create corresponding WP pages with
    parent/child relationships to mirror Notion's structure.

-   **Mapping Notion Blocks to WordPress Blocks/HTML:** This is one of
    the most important parts. We need to convert Notion's content format
    into WordPress post content. There are two approaches:

-   **Render as HTML**: Simpler route -- convert each Notion block to an
    HTML snippet and concatenate for the post_content. For example, a
    Notion heading block becomes `<h2>Heading text</h2>`, a paragraph
    becomes `<p>…</p>`, a bulleted list becomes `<ul><li>…</li></ul>`,
    an image block becomes an `<img src="...">` tag, etc. This is
    relatively straightforward and ensures the content *displays*
    correctly on the site. Both WP Sync and Content Importer essentially
    do this (Content Importer stores the content in a custom post type
    and gives you a shortcode that outputs pre-built HTML).

-   **Gutenberg Block Conversion**: More advanced -- programmatically
    create equivalent Gutenberg blocks for the Notion content.
    WordPress's block editor stores content in HTML comments for complex
    blocks, which can be tricky to generate. However, many Notion
    elements correspond to core WP blocks:

    -   Notion paragraph, headings, lists, quotes can map to the
        **Paragraph**, **Heading (H1/H2/H3)**, **List**, **Quote**
        blocks in Gutenberg.
    -   Notion images could map to the **Image block** in WP (with the
        image file added to media and then referenced by ID in the
        block's comment format).
    -   A Notion toggle could be mapped to a custom block or perhaps a
        core **Details/Disclosure** block if available (there isn't a
        default, so we might create a custom "Toggle" Gutenberg block).
    -   Notion callout might map to a custom "Callout" block (some
        themes or plugins have one, or we define one that simply styles
        a box with an icon).
    -   For code blocks, map to the core **Code block** in WP.
    -   For to-do lists, possibly map to a custom block that renders
        checkboxes (WordPress core doesn't have a checkbox list block by
        default).
    -   Embeds: If the Notion block is an embed link (YouTube, etc.),
        WordPress will often auto-embed if you put the URL in an embed
        block or even paragraph. We might detect known embed URLs and
        use the corresponding core embed block (e.g.,
        `<!-- wp:embed {"url":"https://www.youtube.com/watch?v=..."} /-->`).

<!-- -->

-   Converting to native blocks would make the content fully editable in
    WordPress's visual editor, which is a nice plus. It's more complex
    to implement, but tools like Cloudpress demonstrate it's feasible
    (they *"convert to the correct Gutenberg blocks to ensure it is
    rendered
    correctly"*[\[36\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Gutenberg%20or%20Classic)).
    We might start by converting the basics and treat unsupported ones
    as raw HTML or static content.

<!-- -->

-   **Images Handling:** The plugin must support images **seamlessly**:

-   When encountering an Image block from Notion, use the file URL to
    download the image (the plugin should probably do this server-side).
    Then import it into the WordPress Media Library (using WordPress
    functions to create media attachments). Content Importer, for
    instance, does exactly this, uploading images to WP
    media[\[6\]](https://github.com/pchang78/notion-content#:~:text=,directly%20to%20Wordpress%20Media%20Library).

-   After importing, replace the image URL in the content with the local
    WordPress image URL (or better, reference by attachment ID if using
    a Gutenberg Image block). This ensures images load quickly from your
    server and remain available.

-   We should also carry over the **image captions or alt text** if
    present in Notion. Notion doesn't have a formal caption field on
    images, but users sometimes put a caption as a sub-block (Notion's
    UI shows a caption field in the image block details). The API might
    provide that text, which we could map to the image's caption in WP.

-   Ensure that multiple syncs don't duplicate images. We might store a
    mapping of Notion image block IDs to the WP attachment ID to avoid
    creating a new file each sync if the image hasn't changed.

-   **Other Media:** For **files** (PDFs, docs) attached in Notion, we'd
    do similarly: download and add to WP media, and link to them in the
    post content. For **videos**, if Notion has a video block that's a
    YouTube/Vimeo link, we can embed as mentioned. If it's an uploaded
    video file, we might upload it to WP as well, but that could be
    heavy -- perhaps we'd embed via a video block with the Notion S3 URL
    for convenience (or encourage users to use streaming platforms for
    videos).

-   **Custom Blocks or Unsupported Blocks:** We should decide how to
    handle any Notion block that doesn't have a direct equivalent. A
    flexible approach (as you imagined) is to allow **mapping of Notion
    block types to custom WordPress blocks**. For example, if someone
    has a special block type (say a **callout** with a specific style,
    or a third-party widget embed code), we might:

-   Provide a filter or extension mechanism in our plugin where
    developers can register a handler for a Notion block type. E.g., "If
    block type X is encountered, use this callback to generate WP
    content for it."

-   Alternatively, for simpler use, we could map unknown blocks to a
    generic **HTML block** in Gutenberg containing whatever content we
    got. Or we wrap it in a \<div\> with a class indicating the type, so
    at least it's not lost entirely.

-   In practice, WP Sync plugin chose to **not support** certain blocks
    at all (they just get
    omitted)[\[73\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Checkboxes),
    which isn't ideal for users. A better plugin would at least preserve
    the content in some form (even if just as plain text or a note that
    "This part of content isn't supported"). We can improve here by
    capturing anything we don't explicitly handle and outputting it in a
    readable way.

-   For **buttons or other interactive blocks**, maybe we can render
    them as HTML links or styled divs to approximate the look.

-   **Mapping Notion Properties to WP Fields:** To truly sync content,
    especially from a Notion database, we must map metadata:

-   Identify important properties in Notion: title, published date,
    tags/categories, authors, etc.

-   Map these to WordPress fields: The **post title** comes from the
    Notion page title
    property[\[62\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Supported%20properties%20).
    The **post date** could come from a Date property. Categories/Tags
    in WP could be mapped from a Select or Multi-select property (for
    example, a multi-select "Topics" in Notion could map to WP tags). We
    can let the user configure these mappings in the plugin UI (similar
    to WP Sync's field mapping
    interface[\[74\]](https://ps.w.org/wp-sync-for-notion/assets/screenshot-2.png?rev=2875744#:~:text=)).

-   If we want to support custom fields or SEO data: e.g., a text
    property in Notion for "Meta Description" could map to an SEO
    plugin's meta field. This is advanced but doable (WP Sync Pro+ does
    it for
    Yoast/RankMath[\[24\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=Notion%20WP%20Sync%20Pro%2B)).
    Our plugin could integrate with popular SEO plugins to update their
    meta values from Notion.

-   **Featured Image**: If users want a cover image, Notion pages have a
    "Cover" image and an "Icon". The API provides those if set. We could
    use the Cover image as the WordPress featured image for the post
    (downloading it like other
    images)[\[75\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,content%20%E2%80%93%20See%20%2093).
    This is a nice touch for blog posts.

-   **Navigation/Hierarchy to Menus:** To address that gap, our plugin
    can offer an option to **auto-generate a menu** in WordPress that
    reflects the Notion page structure. Implementation:

-   If a user syncs a top-level page and its children, we know the
    parent-child relationships (Notion child pages vs parent page).
    After importing, we have WP pages with hierarchical relationships
    (we can set the parent page in WordPress to mirror Notion's).
    WordPress supports hierarchical pages and you can manually create
    menus from them.

-   The plugin could programmatically create or update a **WP Navigation
    Menu** (using WP's menu APIs) named, say, "Notion Site Menu", and
    add each imported page to it in the correct nested order. This way,
    the user instantly gets a menu they can assign to their theme. We'd
    update this menu on each sync (adding new pages, removing deleted
    ones).

-   Alternatively, if the site uses a page hierarchy for navigation
    (some themes just use parent/child pages for menus), ensuring the
    parent field is set might suffice. But explicitly managing a menu
    gives more control (so you can exclude certain pages if needed).

-   We'd also need to handle internal links: after importing pages, go
    through the content and replace any `sperity.notion.site/xyz...` or
    notion links with the proper WP permalink if that page was synced.
    We can maintain a map of Notion page IDs to WP URLs to accomplish
    this during the sync process.

### Sync from WordPress to Notion (WP → Notion)

This is the secondary direction, but if we aim to support it, here's
what we need:

-   **Identify Correspondence:** Every WordPress post or page that came
    from Notion should "know" its origin Notion page ID. We will store
    the Notion Page ID (and maybe the Notion last update timestamp) in a
    hidden custom field on the WordPress post. This way, if the
    WordPress content is edited, we know which Notion page to update.
    Similarly, if a user creates a *new* post in WordPress and wants it
    in Notion, we might allow specifying a target Notion database or
    parent page to create it under.

-   **Push Updates:** When a WP post is saved (and marked as eligible
    for syncing back), our plugin can take the WP content and translate
    it **back into Notion block objects**. This is essentially the
    reverse mapping of the import:

-   We'd parse the WP post content (which might be in Gutenberg blocks
    JSON or HTML) and break it into block structures. If we preserved an
    internal representation or kept the Notion blocks somewhere, it
    might be easier, but let's assume we have to read the WP content.

-   For standard blocks: paragraph text becomes a Notion paragraph block
    (with rich text spans for bold/italic as needed), headings become
    heading blocks, etc. This is possible by scanning the HTML tags or
    block comments.

-   For images: we know the image URL or attachment ID in WP, we could
    upload the image to Notion. The Notion API's file upload flow
    requires an accessible URL or an actual file upload via their AWS S3
    endpoints[\[61\]\[76\]](https://developers.notion.com/reference/webhooks#:~:text=,Search).
    We can use the WP image's URL to create an image block in Notion
    (Notion will either hotlink to it or more properly, we use Create
    Block with type image and give the external URL, then Notion will
    handle it).

-   We also update the Notion page properties if needed (e.g., if the
    title changed or tags changed in WP, we send those via
    `update page`).

-   **Conflict Resolution:** One tricky aspect -- what if someone edits
    in both places before a sync? To keep it simple, we might enforce a
    rule that **Notion is the source of truth**, and WP→Notion sync is
    only manual or on request. Perhaps the plugin could have a "Push to
    Notion" button for each post, rather than automatic two-way merge.
    That way, a user consciously decides to overwrite the Notion page
    with the WP changes (or vice versa). This avoids complex merge
    conflicts.

-   Alternatively, implement last-edited timestamp checks: Notion
    provides a `last_edited_time` for pages. We can compare that with
    the WP post's last modified time and decide which one wins or warn
    the user of a conflict.

-   **New Content from WP:** If the plugin supports creating new Notion
    entries from WordPress (less common scenario, but possible for
    bi-directional completeness), the user would create a WP post and
    perhaps select "Publish to Notion" specifying a target Notion
    database or parent page. The plugin would then call the Notion API
    to **create a new page** with the content. This is similar to how
    some editorial teams might prefer writing in WP but want an archive
    in Notion -- but given our primary use-case, it might not be heavily
    used.

-   **Webhooks for WP→Notion:** While Notion's webhooks notify changes
    from the Notion side, WordPress's side could use its own hooks. We
    can hook into `save_post` in WordPress to trigger an update to
    Notion whenever a synced post is updated. We should probably provide
    a setting to enable or disable this, to avoid unintentional
    overwrites.

### Additional Considerations

-   **Authentication & Security:** Storing the Notion token in WP --
    ensure it's stored securely (not plaintext visible to all admins
    ideally, maybe in wp-config or database with encryption). Also, the
    plugin needs to handle timeouts or API errors gracefully (e.g., if
    Notion API is down or rate-limited, queue syncs).

-   **Scaling and Performance:** For large content (hundreds of pages or
    large databases), we might need to use batching and background
    processes. Notion's API has pagination limits (e.g., 100 entries per
    query) so we'd loop. Also, uploading many images could be slow; we
    might use asynchronous requests or WP Cron to chunk the sync job to
    avoid PHP
    timeouts[\[77\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Server%20Timeout).

-   **User Interface:** Create an admin UI where the user can:

-   Connect their Notion integration (enter token).

-   Choose content to sync (maybe list their Notion pages/databases via
    the API's search).

-   Map fields (drag-and-drop or dropdown to map Notion properties to WP
    fields, similar to WP Sync's "Field Mapping"
    screen[\[74\]](https://ps.w.org/wp-sync-for-notion/assets/screenshot-2.png?rev=2875744#:~:text=)).

-   View sync status/logs (e.g., last sync time, any errors). Provide
    buttons to "Sync Now" manually.

-   Possibly a list of synced items with options to push updates or view
    in Notion.

-   Settings for sync frequency (off, interval, webhook) and what to do
    on deletes (e.g., if a page is removed in Notion, should we trash it
    in WP?).

-   Because navigation/menus are involved, maybe an option "Auto-update
    menu X with synced pages".

-   **Testing on Real Content:** We'd want to test the plugin with
    typical content: a Notion space with a main page and subpages, a
    Notion database of blog posts with different property types, etc.,
    to ensure our mappings cover real-world use. Ensuring images,
    formatting, and links come through as expected will be crucial for
    user satisfaction.

Building such a plugin is non-trivial, but absolutely possible with
Notion's API. The result would be a tool that lets users write in Notion
(enjoying its UX and collaboration) and publish to a WordPress site
(taking advantage of WP's theming, navigation, plugins, and SEO
control). By addressing the gaps of current solutions -- notably
navigation, internal links, broader block support, and optional two-way
sync -- our plugin could fill a niche for those who found existing
options too limiting or too costly.

**In summary**, a bi-directional Notion--WordPress plugin needs to:
leverage the Notion API for full content and media sync, map Notion
constructs to WordPress equivalents (with extensibility for custom
mappings), handle media and internal links intelligently, and implement
a safe two-way update mechanism. With these components in place, one
could manage content in either Notion or WordPress and have it reflected
on the other side (primarily using Notion as the master source, but not
strictly one-way). This would empower users to use Notion as a true
headless CMS for WordPress, with support for images, databases, menus,
and more, without the current limitations of existing integrations.

**Sources:**

-   Notion WP Sync plugin description and
    documentation[\[1\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=With%20our%20Notion%20to%20WordPress,multiple%20advanced%20features%20is%20available)[\[78\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,in%20Notion%20or%20customize%20it)[\[20\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Spotify%20%26%20Loom%20links%20too)[\[42\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Checkboxes)
-   Content Importer for Notion plugin
    description[\[4\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=,easy%20style%20and%20custom%20CSS)[\[6\]](https://github.com/pchang78/notion-content#:~:text=,directly%20to%20Wordpress%20Media%20Library)
-   WPMayor review of Notion WP Sync Pro (features and
    pricing)[\[22\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=match%20at%20L295%20You%20can,Support%2C%20and%20all%20Sync%20strategies)[\[23\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=You%20can%20access%20more%20features,Support%2C%20and%20all%20Sync%20strategies)
-   Cloudpress Notion-to-WordPress integration page
    (capabilities)[\[8\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Preserve%20formatting)[\[9\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Resize%20and%20compress%20images)[\[10\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Supports%20Notion%20embeds)
-   WP Connect documentation and support forum (supported vs unsupported
    features)[\[79\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Not%20Supported%20fields%2C%20Content%20Blocks,Views)[\[52\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Equation%20%E2%80%93%20In%20this%20block)[\[47\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=I%E2%80%99m%20having%20the%20same%20issue,fine%20but%20not%20the%20bookmarks)[\[48\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=Hi%2C)
-   Notion API documentation (webhooks and data
    retrieval)[\[69\]](https://developers.notion.com/reference/webhooks#:~:text=Webhooks%20let%20your%20integration%20receive,in%20sync%20with%20user%20activity)[\[32\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=This%20plugin%20uses%20the%20Notion,or%20all%20pages%20at%20once)

[\[1\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=With%20our%20Notion%20to%20WordPress,multiple%20advanced%20features%20is%20available)
[\[2\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,pages)
[\[26\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,support%20in%20Pro%2B%20version)
[\[27\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=%28cover%2C%20icon%2C%20%E2%80%A6%29%20,block%20or%20use%20shortcodes%20for)
[\[55\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,in%20Notion%20or%20customize%20it)
[\[56\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,Pro%20Version)
[\[58\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,support%20in%20Pro%2B%20version)
[\[78\]](https://wordpress.org/plugins/wp-sync-for-notion/#:~:text=,in%20Notion%20or%20customize%20it)
WP Sync for Notion -- Notion to WordPress -- WordPress plugin \|
WordPress.org

<https://wordpress.org/plugins/wp-sync-for-notion/>

[\[3\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=Content%20Importer%20for%20Notion%20is,styles%20in%20the%20WordPress%20admin)
[\[4\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=,easy%20style%20and%20custom%20CSS)
[\[5\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=1,Styles)
[\[7\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=License)
[\[31\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=,style%20and%20custom%20CSS%20management)
[\[32\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=This%20plugin%20uses%20the%20Notion,or%20all%20pages%20at%20once)
[\[59\]](https://wordpress.org/plugins/content-importer-for-notion/#:~:text=This%20plugin%20uses%20the%20following,the%20title%20of%20a%20page)
Content Importer for Notion -- WordPress plugin \| WordPress.org

<https://wordpress.org/plugins/content-importer-for-notion/>

[\[6\]](https://github.com/pchang78/notion-content#:~:text=,directly%20to%20Wordpress%20Media%20Library)
[\[33\]](https://github.com/pchang78/notion-content#:~:text=Notion%20Columns)
[\[34\]](https://github.com/pchang78/notion-content#:~:text=Automatic%20Content%20Refresh)
GitHub - pchang78/notion-content

<https://github.com/pchang78/notion-content>

[\[8\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Preserve%20formatting)
[\[9\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Resize%20and%20compress%20images)
[\[10\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Supports%20Notion%20embeds)
[\[11\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Export%20Notion%20Database)
[\[12\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Export%20custom%20Gutenberg%20blocks)
[\[35\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20SEO%20Optimize%20images)
[\[36\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Gutenberg%20or%20Classic)
[\[37\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Image%3A%20Automate%20your%20exports)
[\[38\]](https://www.usecloudpress.com/integrations/export-notion-to-wordpress#:~:text=Do%20you%20have%20a%20free,trial)
Export content from Notion to WordPress • Cloudpress

<https://www.usecloudpress.com/integrations/export-notion-to-wordpress>

[\[13\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=Step%204,to%20connect%20your%20WordPress%20website)
[\[14\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=,wp.zip%29%2C%20and%20then%20install%20it)
[\[15\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=Step%201,Notion%20to%20WP)
[\[39\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=The%20tools%20we%20will%20be,using%20are)
[\[40\]](https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/#:~:text=,Databases%20to%20your%20WordPress%20website)
How to use Notion to WP? -- Notionto

<https://wordpress.notionto.com/2025/06/24/how-to-use-notion-to-wp/>

[\[16\]](https://www.reddit.com/r/Notion/comments/13gbnxz/how_notion_page_sync_to_wordpress/#:~:text=%E2%80%A2%20%202y%20ago)
How Notion page sync to Wordpress? : r/Notion

<https://www.reddit.com/r/Notion/comments/13gbnxz/how_notion_page_sync_to_wordpress/>

[\[17\]](https://wp-umbrella.com/blog/wp-connect/#:~:text=There%20are%20three%20solutions%20for,integrating%20Notion%20and%20WordPress)
[\[18\]](https://wp-umbrella.com/blog/wp-connect/#:~:text=3%20Add,Forms%20to%20Notion)
How to Integrate Notion with WordPress

<https://wp-umbrella.com/blog/wp-connect/>

[\[19\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=You%20can%20try%20the%20free,in%20your%20WordPress%20admin%E2%86%92Plugins%E2%86%92Add%20New)
[\[21\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=The%20free%20version%20has%20the,following%20limitations)
[\[22\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=match%20at%20L295%20You%20can,Support%2C%20and%20all%20Sync%20strategies)
[\[23\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=You%20can%20access%20more%20features,Support%2C%20and%20all%20Sync%20strategies)
[\[24\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=Notion%20WP%20Sync%20Pro%2B)
[\[25\]](https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/#:~:text=including%20Pages%20and%20Database%20Sync,Support%2C%20and%20all%20Sync%20strategies)
Notion WP Sync Pro: Connecting WordPress and Notion

<https://wpmayor.com/notion-wp-sync-pro-connecting-wordpress-and-notion/>

[\[20\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Spotify%20%26%20Loom%20links%20too)
[\[28\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Creating%20your%20shortcode%20content%20will,can%20be%20used%20by%20developers)
[\[29\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Syncing%20Issues)
[\[41\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=%E2%9A%A0%EF%B8%8F%20IMPORTANT%20NOTE)
[\[42\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Checkboxes)
[\[46\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Calendar%20view)
[\[49\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Here%20is%20what%20is%20not,supported%20by%20our%20plugin%20yet)
[\[50\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Checkboxes)
[\[51\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,AI%20block)
[\[52\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Equation%20%E2%80%93%20In%20this%20block)
[\[53\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Image)
[\[54\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=In%20Pages)
[\[57\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Columns)
[\[62\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Supported%20properties%20)
[\[73\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,Checkboxes)
[\[75\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=,content%20%E2%80%93%20See%20%2093)
[\[77\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Server%20Timeout)
[\[79\]](https://wpconnect.co/notion-wp-sync-documentation/#:~:text=Not%20Supported%20fields%2C%20Content%20Blocks,Views)
Notion WP Sync Documentation: Notion To WordPress

<https://wpconnect.co/notion-wp-sync-documentation/>

[\[30\]](https://ps.w.org/wp-sync-for-notion/assets/screenshot-3.png?rev=2875744#:~:text=)
ps.w.org

<https://ps.w.org/wp-sync-for-notion/assets/screenshot-3.png?rev=2875744>

[\[43\]](https://ps.w.org/wp-sync-for-notion/assets/screenshot-1.png?rev=2875744#:~:text=)
ps.w.org

<https://ps.w.org/wp-sync-for-notion/assets/screenshot-1.png?rev=2875744>

[\[44\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=None%20of%20the%20dates%20appear,indify%29%20which%20doesn%E2%80%99t%20appear)
[\[45\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=I%E2%80%99d%20also%20like%20to%20mention,of%20widgets%20in%20our%20integration)
[\[47\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=I%E2%80%99m%20having%20the%20same%20issue,fine%20but%20not%20the%20bookmarks)
[\[48\]](https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/#:~:text=Hi%2C)
Some features don't appear (child pages, links...) \| WordPress.org

<https://wordpress.org/support/topic/some-features-dont-appear-child-pages-links/>

[\[60\]](https://developers.notion.com/reference/block#:~:text=A%20block%20object%20represents%20a,that%20you%20can%20interact)
Block - Notion API

<https://developers.notion.com/reference/block>

[\[61\]](https://developers.notion.com/reference/webhooks#:~:text=,Search)
[\[63\]](https://developers.notion.com/reference/webhooks#:~:text=,List%20data%20source%20templates%20get)
[\[64\]](https://developers.notion.com/reference/webhooks#:~:text=,Update%20a%20database%20patch)
[\[65\]](https://developers.notion.com/reference/webhooks#:~:text=,Data%20sources)
[\[66\]](https://developers.notion.com/reference/webhooks#:~:text=,48)
[\[67\]](https://developers.notion.com/reference/webhooks#:~:text=,Retrieve%20a%20page%20get)
[\[68\]](https://developers.notion.com/reference/webhooks#:~:text=,Databases)
[\[69\]](https://developers.notion.com/reference/webhooks#:~:text=Webhooks%20let%20your%20integration%20receive,in%20sync%20with%20user%20activity)
[\[70\]](https://developers.notion.com/reference/webhooks#:~:text=Let%E2%80%99s%20walk%20through%20an%20example,from%20start%20to%20finish)
[\[72\]](https://developers.notion.com/reference/webhooks#:~:text=,your%20token%27s%20bot%20user%20get)
[\[76\]](https://developers.notion.com/reference/webhooks#:~:text=,Search)
Webhooks

<https://developers.notion.com/reference/webhooks>

[\[71\]](https://www.notion.com/help/webhook-actions#:~:text=Webhook%20actions%20%E2%80%93%20Notion%20Help,are%20available%20on%20paid%20plans)
Webhook actions -- Notion Help Center

<https://www.notion.com/help/webhook-actions>

[\[74\]](https://ps.w.org/wp-sync-for-notion/assets/screenshot-2.png?rev=2875744#:~:text=)
ps.w.org

<https://ps.w.org/wp-sync-for-notion/assets/screenshot-2.png?rev=2875744>
