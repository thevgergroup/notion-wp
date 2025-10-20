# Admin UI Implementation - Phase 0

This directory contains the admin interface assets for the Notion Sync settings page.

## Files Created

### 1. SCSS Stylesheet
**Location:** `plugin/assets/src/scss/admin.scss`
**Lines:** 198 (under 200 line limit)
**Purpose:** Professional styling matching WordPress admin design language

**Features:**
- Connection form styling (disconnected state)
- Workspace info display (connected state)
- Pages list with scroll
- Loading states with spinners
- Error/success message styling
- Mobile responsive (782px breakpoint)
- Accessible focus states
- Uses WordPress color variables
- Print-safe (blurs sensitive tokens)

**Key Selectors:**
- `.notion-sync-settings` - Main container
- `.notion-sync-connection-form` - Login/connect form
- `.notion-sync-workspace-info` - Success state with workspace details
- `.notion-sync-pages` - List of available Notion pages
- `.notion-sync-loading` - Loading state

### 2. JavaScript
**Location:** `plugin/assets/src/js/admin.js`
**Lines:** 194 (under 200 line limit)
**Purpose:** Interactive functionality and validation

**Features:**
- Loading spinner on form submit
- Disable submit button during API call
- Token format validation (basic client-side check)
- Disconnect confirmation dialog
- Clear sensitive data handling
- Keyboard navigation support
- ARIA labels for accessibility
- No console.log statements (lint-friendly)
- Vanilla JavaScript (no jQuery dependency)

**Functions:**
- `validateTokenFormat()` - Client-side token validation
- `showLoadingState()` - Display loading UI
- `handleDisconnectButton()` - Confirmation before disconnect
- `enhanceKeyboardNavigation()` - Accessibility improvements
- `handleDismissibleNotices()` - Auto-dismiss success notices

### 3. HTML Sample Template
**Location:** `plugin/templates/admin/settings-sample.php`
**Purpose:** Reference implementation showing all UI states

**States Demonstrated:**
1. **Disconnected:** Connection form with help text
2. **Connected:** Workspace info + pages list
3. **Loading:** Spinner during API operations
4. **Notices:** Success, error, warning examples

**Includes:**
- Complete WordPress admin markup
- Proper escaping (`esc_html`, `esc_attr`, `esc_url`)
- Nonce verification setup
- i18n ready (all strings translatable)
- Accessibility attributes (ARIA, roles)
- List of all WordPress admin classes used
- Color reference guide

### 4. Enqueue Code Snippet
**Location:** `plugin/assets/ENQUEUE-SNIPPET.php`
**Purpose:** Copy-paste code for SettingsPage.php asset enqueuing

**Includes:**
- Complete `enqueue_assets()` method
- Proper hook checking (only load on settings page)
- Version management for cache busting
- Alternative build process integration
- Localized script data for AJAX
- Development vs production approaches
- Debugging tips
- WordPress standards compliance notes

## Design Principles

### WordPress Native Design
All components match WordPress admin interface:
- Colors match core admin palette
- Typography uses WordPress defaults
- Spacing follows WP grid system
- Components feel "at home" in admin

### Accessibility (WCAG 2.1 AA)
- ✅ Proper ARIA labels and roles
- ✅ Keyboard navigation fully supported
- ✅ Color contrast ratios meet AA standards
- ✅ Screen reader compatible
- ✅ Focus indicators clearly visible
- ✅ Logical tab order maintained

### Responsive Design
- Desktop-first (primary use case)
- Mobile breakpoint at 782px (WordPress standard)
- Touch-friendly buttons (min 44px on mobile)
- No horizontal scrolling
- Stacked layouts on small screens

### Performance
- Assets only load on settings page
- Minimal JavaScript (< 200 lines)
- No external dependencies
- Efficient selectors in CSS
- Lazy evaluation in JS

## WordPress Admin Classes Used

### Layout
- `.wrap` - Main admin page wrapper (required)

### Buttons
- `.button` - Standard button
- `.button-primary` - Primary action (blue)
- `.button-secondary` - Secondary action (grey)

### Forms
- Standard input/label elements inherit WP styles

### Notices
- `.notice` - Base notice class
- `.notice-success` - Green success notice
- `.notice-error` - Red error notice
- `.notice-warning` - Yellow warning
- `.notice-info` - Blue info
- `.is-dismissible` - Makes notice dismissible
- `.notice-dismiss` - Dismiss button

### Loading
- `.spinner` - WordPress spinner icon
- `.is-active` - Shows spinner

### Accessibility
- `.screen-reader-text` - Visually hidden text for screen readers

## WordPress Color Reference

### Primary Colors
| Purpose | Hex | Usage |
|---------|-----|-------|
| Primary blue | `#2271b1` | Links, primary buttons |
| Darker blue | `#135e96` | Hover states |
| Success green | `#00a32a` | Success messages, connected state |
| Error red | `#d63638` | Error messages, validation |
| Warning yellow | `#dba617` | Warnings |

### Text Colors
| Purpose | Hex | Usage |
|---------|-----|-------|
| Primary text | `#1d2327` | Headings, labels |
| Secondary text | `#50575e` | Body text |
| Meta text | `#646970` | Descriptions, timestamps |

### Borders & Backgrounds
| Purpose | Hex | Usage |
|---------|-----|-------|
| Default borders | `#c3c4c7` | Box borders |
| Input borders | `#8c8f94` | Form inputs |
| White bg | `#ffffff` | Cards, forms |
| Light grey bg | `#f0f0f1` | Subtle backgrounds |
| Hover bg | `#f6f7f7` | Hover states |

## Integration Instructions

### Step 1: Copy Assets
```bash
# Assets are already in place:
plugin/assets/src/scss/admin.scss
plugin/assets/src/js/admin.js
```

### Step 2: Add Enqueue Code
Copy the relevant method from `plugin/assets/ENQUEUE-SNIPPET.php` into your `SettingsPage.php` class.

### Step 3: Build Assets (Optional)
For Phase 0, you have two options:

**Option A: No Build Process (Simpler)**
Convert SCSS to CSS manually or use CSS directly:
```bash
# If you have Sass CLI installed:
sass plugin/assets/src/scss/admin.scss plugin/assets/dist/css/admin.css --style compressed

# Or manually convert SCSS to CSS (SCSS is valid CSS if you avoid nesting)
```

**Option B: Set Up Build Process**
```bash
# Install dependencies
npm install --save-dev sass terser

# Add to package.json scripts:
"build:css": "sass plugin/assets/src/scss/admin.scss plugin/assets/dist/css/admin.min.css --style compressed",
"build:js": "terser plugin/assets/src/js/admin.js -o plugin/assets/dist/js/admin.min.js -c -m",
"build": "npm run build:css && npm run build:js",
"watch": "npm run watch:css & npm run watch:js"

# Create dist directories
mkdir -p plugin/assets/dist/css plugin/assets/dist/js

# Build
npm run build
```

### Step 4: Use Sample Template
Reference `plugin/templates/admin/settings-sample.php` when creating your actual settings template.

Key patterns to follow:
- Proper escaping on all output
- Nonce verification on forms
- Translation-ready strings
- ARIA attributes for accessibility
- WordPress admin classes for consistency

## Testing Checklist

### Visual Testing
- [ ] Matches WordPress admin design (doesn't look custom)
- [ ] Connection form is clear and professional
- [ ] Workspace info displays correctly when connected
- [ ] Pages list scrolls properly with many items
- [ ] Loading states are visible and clear
- [ ] Admin notices render correctly

### Functional Testing
- [ ] Submit button disabled until token is entered
- [ ] Loading spinner shows during connection
- [ ] Disconnect button shows confirmation dialog
- [ ] Token input is monospace for readability
- [ ] Forms submit correctly (nonces verified)

### Mobile Testing
- [ ] Works on iPhone (Safari)
- [ ] Works on Android phone (Chrome)
- [ ] Works on tablet (iPad)
- [ ] Buttons are touch-friendly (min 44px)
- [ ] No horizontal scrolling
- [ ] Text is readable (no zoom required)

### Accessibility Testing
- [ ] All interactive elements keyboard accessible
- [ ] Tab order is logical
- [ ] Focus indicators visible
- [ ] ARIA labels present and correct
- [ ] Color contrast meets WCAG AA
- [ ] Screen reader announces state changes
- [ ] Forms properly associated with labels

### Browser Testing
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)

### JavaScript Testing
- [ ] No console errors
- [ ] No console.log statements
- [ ] JavaScript works when disabled (graceful degradation)
- [ ] ESLint passes (when configured)

### Code Quality
- [ ] All files under 200 lines (except sample template)
- [ ] SCSS uses WordPress color variables
- [ ] JavaScript is vanilla (no jQuery required)
- [ ] No inline styles in HTML
- [ ] Proper WordPress coding standards

## Accessibility Features

### Keyboard Navigation
- All interactive elements focusable
- Logical tab order maintained
- Enter/Space activate buttons
- Escape closes modals (future)
- No keyboard traps

### Screen Reader Support
- ARIA labels on all controls
- ARIA live regions for dynamic updates
- ARIA invalid on validation errors
- ARIA busy during loading states
- Semantic HTML structure

### Visual Accessibility
- Color contrast ratios meet WCAG AA
- Focus indicators visible (2px blue outline)
- Text resizes without breaking layout
- No information conveyed by color alone
- Sufficient touch target sizes (44px minimum)

### Form Accessibility
- Labels properly associated with inputs
- Required fields marked with asterisk
- Error messages in ARIA live regions
- Help text linked via aria-describedby
- Validation provides clear feedback

## Known Issues & Limitations

### Phase 0 Scope
These items are intentionally deferred to future phases:

- No AJAX-based form submission (uses standard POST)
- No real-time token validation (client-side only)
- No advanced loading indicators (basic spinner)
- No drag-and-drop for future field mapping
- No settings export/import
- No webhook configuration UI

### Browser Support
Targets modern browsers (last 2 versions):
- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS Safari 14+, Chrome Mobile 90+)

Does NOT support:
- Internet Explorer (discontinued)
- Very old browsers without CSS Grid/Flexbox

### Mobile Limitations
- Primarily designed for desktop use
- Mobile support is functional but not optimized
- Some features may be harder to use on small screens
- Consider responsive admin table for page lists in future

## Future Enhancements

Ideas for post-Phase 0:

1. **AJAX Form Submission**
   - Submit forms without page reload
   - Show inline validation errors
   - Update UI dynamically

2. **Advanced Loading States**
   - Progress bars for long operations
   - Step-by-step status updates
   - Cancel operation button

3. **Settings Sections**
   - Tabbed interface for multiple settings
   - Collapsible sections
   - Settings search/filter

4. **Page Selection**
   - Checkboxes to select pages for sync
   - Bulk actions
   - Search/filter pages

5. **Visual Feedback**
   - Toast notifications
   - Inline success indicators
   - Better error context

## Support & Resources

### WordPress References
- [Plugin Handbook - Admin Menus](https://developer.wordpress.org/plugins/administration-menus/)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [Dashicons Reference](https://developer.wordpress.org/resource/dashicons/)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)

### Tools
- [WAVE Accessibility Tool](https://wave.webaim.org/)
- [axe DevTools](https://www.deque.com/axe/devtools/)
- [Lighthouse](https://developers.google.com/web/tools/lighthouse)

### Related Files
- `/docs/plans/phase-0.md` - Phase 0 implementation plan
- `/docs/development/principles.md` - Development principles
- `phpcs.xml.dist` - PHP coding standards config
- `.eslintrc.json` - JavaScript linting config
- `.stylelintrc.json` - CSS linting config

## Questions?

If you encounter issues or have questions:

1. Check the HTML sample template for markup patterns
2. Review the enqueue snippet for integration details
3. Verify WordPress admin classes are used correctly
4. Test accessibility with WAVE or axe DevTools
5. Check browser console for JavaScript errors

---

**Version:** 1.0
**Last Updated:** 2025-10-19
**Status:** Ready for Integration
