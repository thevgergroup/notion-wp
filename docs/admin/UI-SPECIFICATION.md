# UI Specification: Menu Meta Box

## Visual Design Reference

### Color Palette

```css
/* WordPress Admin Colors */
--primary-blue:      #2271b1;  /* Buttons, links, icons */
--primary-blue-dark: #135e96;  /* Hover states */
--success-green:     #00a32a;  /* Success messages, badges */
--success-green-bg:  #d5f5e3;  /* Success badge background */
--success-green-text:#00712e;  /* Success badge text */
--error-red:         #d63638;  /* Error messages */
--error-red-bg:      #fef0f0;  /* Error message background */
--text-dark:         #1d2327;  /* Primary text */
--text-medium:       #50575e;  /* Secondary text */
--text-light:        #646970;  /* Tertiary text, descriptions */
--border-gray:       #c3c4c7;  /* Borders, dividers */
--bg-light:          #f0f0f1;  /* Code backgrounds */
--bg-white:          #ffffff;  /* Container backgrounds */
```

### Typography

```css
/* Font Stack */
font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto,
             Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;

/* Font Sizes */
--font-size-large:   16px;  /* Headings, emphasis */
--font-size-base:    13px;  /* Body text, labels */
--font-size-small:   12px;  /* Descriptions, help text */
--font-size-tiny:    11px;  /* Code, metadata */

/* Line Heights */
--line-height-base:  1.5;   /* Body text */
--line-height-tight: 1.3;   /* Headings */

/* Font Weights */
--font-weight-normal: 400;  /* Body text */
--font-weight-bold:   600;  /* Labels, headings */
```

### Spacing Scale

```css
/* Consistent spacing units */
--space-xs:  4px;   /* Tight spacing, icon gaps */
--space-sm:  8px;   /* Small gaps, padding */
--space-md:  12px;  /* Default spacing */
--space-lg:  16px;  /* Section spacing */
--space-xl:  20px;  /* Large gaps */

/* Component Spacing */
padding: 12px;           /* Meta box container */
margin-bottom: 16px;     /* Section spacing */
gap: 12px;              /* Flex/grid gaps */
```

### Component Dimensions

```css
/* Buttons */
min-height: 32px;       /* Desktop buttons */
min-height: 44px;       /* Mobile buttons (touch-friendly) */
padding: 8px 16px;      /* Button padding */

/* Borders */
border-width: 1px;      /* Standard borders */
border-radius: 4px;     /* Rounded corners */

/* Icons */
font-size: 16px;        /* Dashicons size */
margin-right: 4px;      /* Icon spacing */

/* Focus Outlines */
outline: 2px solid #2271b1;  /* Focus indicator */
outline-offset: 2px;          /* Space from element */
```

## Meta Box Layout

### Visual Structure

```
┌───────────────────────────────────────┐
│ 🔄 Notion Menu Sync          [Meta Box Header]
├───────────────────────────────────────┤
│ ┌─────────────────────────────────┐   │
│ │ Last Synced: 2 minutes ago      │   │ [Stats Section]
│ │ Synced Items: 19 of 21          │   │
│ └─────────────────────────────────┘   │
│                                        │
│ ┌─────────────────────────────────┐   │
│ │ [🔄 Sync from Notion Now]       │   │ [Action Section]
│ └─────────────────────────────────┘   │
│                                        │
│ ┌─────────────────────────────────┐   │
│ │ [Success/Error Messages]        │   │ [Messages Section]
│ └─────────────────────────────────┘   │
│                                        │
│ ┌─────────────────────────────────┐   │
│ │ ℹ️ Notion-synced items show 🔄  │   │ [Help Section]
│ │    icon. Toggle "Prevent        │   │
│ │    Updates" to keep manual      │   │
│ │    changes.                      │   │
│ └─────────────────────────────────┘   │
└───────────────────────────────────────┘
```

### Measurement Details

```css
/* Meta Box Container */
.notion-menu-sync-meta-box {
    padding: 12px;
    background: #ffffff;
    border: 1px solid #c3c4c7;
    border-radius: 4px;
}

/* Stats Section */
.notion-sync-stats {
    margin-bottom: 16px;
}

.notion-sync-stats p {
    margin: 0 0 12px;
    font-size: 13px;
    line-height: 1.5;
}

.notion-sync-stats strong {
    display: inline-block;
    margin-bottom: 2px;
    color: #1d2327;
    font-weight: 600;
}

.notion-sync-stats time {
    color: #2271b1;
    font-size: 13px;
}

/* Action Button */
.notion-sync-actions .button-primary {
    width: 100%;
    min-height: 32px;
    padding: 8px 16px;
    background: #2271b1;
    border-color: #2271b1;
    color: #ffffff;
    font-size: 13px;
    text-align: center;
}

.notion-sync-actions .button-primary .dashicons {
    font-size: 16px;
    vertical-align: middle;
    margin-right: 4px;
}

/* Messages Container */
#notion-menu-sync-messages {
    margin: 12px 0;
    min-height: 20px;
}

#notion-menu-sync-messages .notice {
    margin: 5px 0;
    padding: 8px 12px;
}

/* Help Text */
.notion-sync-help {
    padding-top: 12px;
    border-top: 1px solid #dcdcde;
}

.notion-sync-help .description {
    font-size: 12px;
    line-height: 1.5;
    color: #646970;
}

.notion-sync-help .dashicons {
    color: #2271b1;
    font-size: 16px;
    vertical-align: middle;
    margin-right: 4px;
}
```

## Menu Item Fields Layout

### Visual Structure (Expanded Item)

```
Menu Item Title 🔄
────────────────────────────────────
Navigation Label: [Test Page]
Title Attribute:  [____________]
CSS Classes:      [____________]
────────────────────────────────────
[✓] Notion Sync
    This item is synced from Notion

    Notion Page ID: abc123def456

    [ ] Prevent Notion Updates
        When checked, this item will not
        be updated during Notion sync.
────────────────────────────────────
[Move] [Remove] [Cancel]
```

### Measurement Details

```css
/* Notion Sync Fields Container */
.field-notion-sync,
.field-notion-page-id,
.field-notion-override {
    padding: 10px 0;
    border-top: 1px solid #f0f0f1;
}

/* Sync Status Badge */
.notion-sync-indicator {
    display: inline-block;
    padding: 4px 8px;
    background: #d5f5e3;
    color: #00712e;
    border: 1px solid #00a32a;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

/* Page ID Display */
.notion-page-id-display {
    display: inline-block;
    padding: 4px 8px;
    background: #f0f0f1;
    border: 1px solid #c3c4c7;
    border-radius: 3px;
    font-family: "Menlo", "Monaco", "Consolas", monospace;
    font-size: 11px;
    color: #1d2327;
    word-break: break-all;
}

/* Override Checkbox */
.field-notion-override input[type="checkbox"] {
    margin-right: 6px;
    width: 16px;
    height: 16px;
}

.field-notion-override label {
    display: flex;
    align-items: center;
    font-weight: 600;
    cursor: pointer;
}

.field-notion-override .description {
    display: block;
    margin-top: 4px;
    margin-left: 24px;
    color: #646970;
    font-size: 12px;
    font-style: italic;
}
```

## State Variations

### Loading State

```
┌───────────────────────────────────────┐
│ 🔄 Notion Menu Sync                   │
├───────────────────────────────────────┤
│ Last Synced: 2 minutes ago            │
│ Synced Items: 19 of 21                │
│                                        │
│ [⟳ Syncing...]                        │ ← Button disabled
│    (spinning icon)                    │
│                                        │
│ ℹ️ Please wait...                     │
└───────────────────────────────────────┘
```

**CSS:**
```css
.button-primary.updating-message {
    opacity: 0.7;
    cursor: not-allowed;
}

.button-primary.updating-message .dashicons {
    animation: rotation 1s infinite linear;
}

@keyframes rotation {
    from { transform: rotate(0deg); }
    to { transform: rotate(359deg); }
}
```

### Success State

```
┌───────────────────────────────────────┐
│ 🔄 Notion Menu Sync                   │
├───────────────────────────────────────┤
│ Last Synced: Just now                 │ ← Updated
│ Synced Items: 21 of 21                │ ← Updated
│                                        │
│ [🔄 Sync from Notion Now]             │
│                                        │
│ ┌─────────────────────────────────┐   │
│ │ ✓ Menu "Notion Navigation"      │   │ ← Success message
│ │   updated with 21 items.        │   │
│ │   View & assign menu.           │   │
│ └─────────────────────────────────┘   │
│                                        │
│ ℹ️ Notion-synced items show 🔄 icon  │
└───────────────────────────────────────┘
```

**CSS:**
```css
.notice-success {
    border-left: 4px solid #00a32a;
    background: #d5f5e3;
    padding: 8px 12px;
}

.notice-success p {
    color: #00712e;
    margin: 0.5em 0;
}
```

### Error State

```
┌───────────────────────────────────────┐
│ 🔄 Notion Menu Sync                   │
├───────────────────────────────────────┤
│ Last Synced: 2 minutes ago            │
│ Synced Items: 19 of 21                │
│                                        │
│ [🔄 Sync from Notion Now]             │
│                                        │
│ ┌─────────────────────────────────┐   │
│ │ ✗ Menu sync failed: No root     │   │ ← Error message
│ │   pages found. Please sync      │   │
│ │   some pages from Notion first. │   │
│ └─────────────────────────────────┘   │
│                                        │
│ ℹ️ Notion-synced items show 🔄 icon  │
└───────────────────────────────────────┘
```

**CSS:**
```css
.notice-error {
    border-left: 4px solid #d63638;
    background: #fef0f0;
    padding: 8px 12px;
}

.notice-error p {
    color: #b32d2e;
    margin: 0.5em 0;
}
```

### Empty State (No Menu Selected)

```
┌───────────────────────────────────────┐
│ 🔄 Notion Menu Sync                   │
├───────────────────────────────────────┤
│                                        │
│ Select or create a menu to view       │
│ sync status.                           │
│                                        │
└───────────────────────────────────────┘
```

## Icon System

### Sync Icon (🔄)

**Usage:** Visual indicator for Notion-synced items

**Display:**
- Prepended to menu item title
- Font size: 14px
- Color: Inherits from title (usually #1d2327)
- Spacing: 4px right margin

**CSS:**
```css
.notion-sync-icon {
    display: inline-block;
    font-size: 14px;
    margin-right: 4px;
}
```

### Dashicons

**Update Icon:** `dashicons-update`
- Used in sync button
- Rotates during loading state

**Info Icon:** `dashicons-info`
- Used in help text
- Color: #2271b1 (primary blue)

**CSS:**
```css
.dashicons-update {
    font-size: 16px;
    vertical-align: middle;
}

.dashicons-update.spinning {
    animation: rotation 1s infinite linear;
}
```

## Interactive States

### Button States

**Normal:**
```css
background: #2271b1;
border-color: #2271b1;
color: #ffffff;
cursor: pointer;
```

**Hover:**
```css
background: #135e96;
border-color: #135e96;
```

**Focus:**
```css
outline: 2px solid #2271b1;
outline-offset: 2px;
box-shadow: none;
```

**Active:**
```css
background: #0e4a72;
border-color: #0e4a72;
```

**Disabled:**
```css
opacity: 0.6;
cursor: not-allowed;
background: #2271b1; /* no color change */
```

### Checkbox States

**Unchecked:**
```css
border: 1px solid #8c8f94;
background: #ffffff;
```

**Checked:**
```css
border: 1px solid #2271b1;
background: #2271b1;
/* WordPress handles checkmark */
```

**Focus:**
```css
outline: 2px solid #2271b1;
outline-offset: 2px;
```

**Disabled:**
```css
opacity: 0.5;
cursor: not-allowed;
```

## Mobile Responsive Design

### Breakpoint: 782px

**Meta Box Changes:**

```css
@media screen and (max-width: 782px) {
    .notion-menu-sync-meta-box {
        padding: 10px;
    }

    .notion-sync-actions .button-primary {
        min-height: 44px;    /* Touch-friendly */
        font-size: 14px;     /* Larger text */
        padding: 10px 16px;
    }

    .notion-sync-stats p,
    .notion-sync-help .description {
        font-size: 14px;     /* More readable */
    }

    /* Increase tap target for help icon */
    .notion-sync-help .dashicons {
        font-size: 18px;
    }
}
```

**Menu Item Changes:**

```css
@media screen and (max-width: 782px) {
    .field-notion-override input[type="checkbox"] {
        width: 20px;         /* Larger tap target */
        height: 20px;
    }

    .notion-page-id-display {
        font-size: 12px;     /* More readable */
        padding: 6px 10px;
    }
}
```

## Animation Specifications

### Rotation Animation (Sync Button)

```css
@keyframes rotation {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(359deg);
    }
}

/* Applied to spinning dashicon */
.dashicons.spinning {
    animation: rotation 1s infinite linear;
}
```

**Properties:**
- Duration: 1 second per rotation
- Timing: Linear (constant speed)
- Iteration: Infinite (while syncing)
- Transform origin: Center

### Fade In (Messages)

```css
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.notice {
    animation: fadeIn 0.3s ease-out;
}
```

**Properties:**
- Duration: 300ms
- Timing: Ease-out (smooth deceleration)
- Effect: Fade in + slide down

## Accessibility Annotations

### Focus Order

```
1. Sync button
2. Success/error message (if present)
3. Menu items (WordPress native order)
4. Per-item: Override checkbox
```

### ARIA Attributes

**Sync Button:**
```html
<button
    id="notion-sync-menu-button"
    aria-label="Sync menu from Notion now"
    aria-describedby="notion-sync-help-text">
    Sync from Notion Now
</button>
```

**Messages Container:**
```html
<div
    id="notion-menu-sync-messages"
    role="status"
    aria-live="polite"
    aria-atomic="true">
    <!-- Messages appear here -->
</div>
```

**Override Checkbox:**
```html
<input
    type="checkbox"
    id="notion-override-123"
    aria-describedby="notion-override-help-123">
<span id="notion-override-help-123" class="description">
    When checked, this item will not be updated during Notion sync.
</span>
```

### Screen Reader Text

**Hidden but announced:**
```css
.screen-reader-text {
    border: 0;
    clip: rect(1px, 1px, 1px, 1px);
    clip-path: inset(50%);
    height: 1px;
    margin: -1px;
    overflow: hidden;
    padding: 0;
    position: absolute;
    width: 1px;
    word-wrap: normal !important;
}
```

## Print Styles

```css
@media print {
    /* Hide interactive elements */
    .notion-sync-actions,
    #notion-menu-sync-messages {
        display: none;
    }

    /* Ensure readable text */
    .notion-sync-stats p,
    .notion-sync-help .description {
        color: #000000;
    }

    /* Remove shadows and borders */
    .notion-menu-sync-meta-box {
        border: 1px solid #000000;
        box-shadow: none;
    }
}
```

## Browser-Specific Considerations

### Safari (macOS/iOS)
- Use `-webkit-appearance: none` for custom checkbox styling
- Test rubber-band scrolling on meta box
- Verify smooth animations (use `transform` not `position`)

### Firefox
- Test outline rendering (may differ from Chrome)
- Verify animation performance
- Check flexbox behavior

### Edge/IE11 (if supporting)
- Provide fallback for `gap` property
- Test CSS Grid layouts
- Verify focus outlines

## Design Tokens Summary

```json
{
    "colors": {
        "primary": "#2271b1",
        "success": "#00a32a",
        "error": "#d63638",
        "text": "#1d2327",
        "textSecondary": "#646970",
        "border": "#c3c4c7",
        "background": "#ffffff"
    },
    "spacing": {
        "xs": "4px",
        "sm": "8px",
        "md": "12px",
        "lg": "16px",
        "xl": "20px"
    },
    "typography": {
        "fontFamily": "system-ui, -apple-system, sans-serif",
        "fontSize": {
            "small": "12px",
            "base": "13px",
            "large": "16px"
        },
        "fontWeight": {
            "normal": 400,
            "bold": 600
        },
        "lineHeight": {
            "tight": 1.3,
            "base": 1.5
        }
    },
    "borders": {
        "radius": "4px",
        "width": "1px"
    },
    "shadows": {
        "focus": "0 0 0 2px #2271b1"
    },
    "animation": {
        "duration": {
            "fast": "150ms",
            "base": "300ms",
            "slow": "600ms"
        },
        "easing": {
            "linear": "linear",
            "ease": "ease-out"
        }
    }
}
```

---

**Design Version:** 1.0
**Last Updated:** 2025-10-29
**Design System:** WordPress Admin UI
**Accessibility Standard:** WCAG 2.1 AA
