# Phase 0 Test Plan - Notion Sync for WordPress

**Version:** 1.0
**Date:** 2025-10-19
**Phase:** Phase 0 - Proof of Concept
**Test Coordinator:** Development Team
**Status:** Ready for Testing

---

## Executive Summary

This document outlines the comprehensive testing strategy for Phase 0 of the Notion Sync plugin. Phase 0 focuses exclusively on authentication and connection verification. All tests must pass before proceeding to the gatekeeping demo.

### Test Objectives

1. Verify users can connect to Notion successfully
2. Ensure error handling is clear and helpful
3. Validate security measures (nonces, sanitization, escaping)
4. Confirm UI works across devices and browsers
5. Check code quality meets all standards

### Success Criteria

- All functional tests pass (100%)
- All security tests pass (100%)
- Zero linting errors
- Works on 3+ browsers and 3+ devices
- Non-developer can complete connection in < 2 minutes

---

## Test Environment Setup

### Required Test Environments

1. **Local Development** - Docker WordPress environment
2. **Staging Server** - WordPress 6.0+ with PHP 8.0+
3. **Mobile Devices** - iPhone and Android for responsive testing

### Test Data Requirements

#### Valid Notion Tokens

- **Valid Token 1:** Fresh internal integration token (create new)
- **Valid Token 2:** Token with multiple shared pages (5-10 pages)
- **Valid Token 3:** Token with no shared pages (for empty state)

#### Invalid Test Data

- **Invalid Token 1:** Random string not starting with `secret_`
- **Invalid Token 2:** Expired/revoked token
- **Invalid Token 3:** Token with typo (modified valid token)
- **Malicious Input:** SQL injection attempts, XSS payloads

### Browser Matrix

| Browser       | Version | Operating System | Priority |
| ------------- | ------- | ---------------- | -------- |
| Chrome        | Latest  | macOS/Windows    | High     |
| Firefox       | Latest  | macOS/Windows    | High     |
| Safari        | Latest  | macOS            | High     |
| Edge          | Latest  | Windows          | Medium   |
| Safari Mobile | Latest  | iOS              | High     |
| Chrome Mobile | Latest  | Android          | High     |

### Device Matrix

| Device Type    | Screen Size | Priority |
| -------------- | ----------- | -------- |
| Desktop        | 1920x1080   | High     |
| Laptop         | 1366x768    | High     |
| Tablet         | 768x1024    | Medium   |
| Mobile (Large) | 414x896     | High     |
| Mobile (Small) | 375x667     | High     |

---

## Functional Test Cases

### TC-F001: Initial Page Load

**Objective:** Verify settings page loads correctly when not connected

**Prerequisites:** Plugin activated, no existing Notion connection

**Steps:**

1. Navigate to WordPress Admin > Notion Sync
2. Observe page content

**Expected Results:**

- Page loads without errors
- Heading displays "Notion Sync"
- Connection form is visible
- Instructions are clear and numbered
- Link to Notion integrations page present
- Token input field visible (type="password")
- "Connect to Notion" button visible
- No PHP warnings in debug log
- No JavaScript console errors

**Pass Criteria:** All expected results met

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Browser:
Issues Found:
```

---

### TC-F002: Valid Token Connection

**Objective:** User can connect with a valid Notion token

**Prerequisites:**

- Valid Notion internal integration token ready
- At least 1 page shared with integration

**Steps:**

1. Navigate to Notion Sync settings page
2. Paste valid token into "Notion API Token" field
3. Click "Connect to Notion" button
4. Wait for page to reload

**Expected Results:**

- Page redirects with success message
- Green success notice appears
- Message includes workspace name
- Connection status shows "Connected to Notion"
- Green checkmark icon displayed
- Workspace name shown in info table
- Integration name displayed
- Integration ID displayed (as code)
- "Disconnect" button visible
- List of accessible pages appears (if pages shared)
- Loading spinner appears during connection (before redirect)

**Pass Criteria:** All expected results met, connection completes in < 10 seconds

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Browser:
Connection Time:
Issues Found:
```

---

### TC-F003: Invalid Token Format

**Objective:** Clear error for tokens not starting with "secret\_"

**Prerequisites:** Settings page loaded, not connected

**Steps:**

1. Enter invalid token: `invalid_token_123`
2. Click "Connect to Notion"
3. Observe error message

**Expected Results:**

- Error notice appears (red)
- Error message: "Invalid token format. Notion API tokens should start with 'secret\_'."
- User remains on settings page (no redirect)
- Token field is cleared or preserved (check which is better UX)
- No connection is saved

**Pass Criteria:** Error message is clear and actionable

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Error Message Received:
Is error message helpful? Y/N
Issues Found:
```

---

### TC-F004: Empty Token Submission

**Objective:** Prevent submission with empty token

**Prerequisites:** Settings page loaded, not connected

**Steps:**

1. Leave token field empty
2. Try to click "Connect to Notion"

**Expected Results:**

- HTML5 validation prevents submission (field marked `required`)
- Browser shows "Please fill out this field" message
- OR JavaScript prevents submission with inline error
- No network request made
- No PHP processing occurs

**Pass Criteria:** Empty submission blocked before reaching server

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Validation Method Used:
Issues Found:
```

---

### TC-F005: Invalid/Revoked Token

**Objective:** Handle authentication failure gracefully

**Prerequisites:** Token that has been revoked or is invalid

**Steps:**

1. Paste invalid/revoked token
2. Click "Connect to Notion"
3. Observe error

**Expected Results:**

- Error notice appears
- Message: "Authentication failed. Please check that your API token is correct and has not been revoked."
- No connection saved
- User can try again
- No system errors or crashes

**Pass Criteria:** Error is user-friendly, not technical

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Actual Error Message:
Issues Found:
```

---

### TC-F006: Network Timeout

**Objective:** Handle network failures gracefully

**Prerequisites:**

- Ability to simulate network issues
- OR test on slow/unreliable connection

**Steps:**

1. Simulate network timeout (disconnect WiFi briefly, or use browser dev tools)
2. Try to connect with valid token
3. Observe error handling

**Expected Results:**

- Error message appears within 30 seconds (timeout limit)
- Message indicates network issue, not user error
- Suggestion to try again
- No fatal errors or white screens
- User can retry without refreshing page

**Pass Criteria:** Graceful failure, clear messaging

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Timeout After (seconds):
Error Message:
Issues Found:
```

---

### TC-F007: View Accessible Pages

**Objective:** Display list of pages user has shared with integration

**Prerequisites:**

- Valid connection established
- 5-10 pages shared with integration in Notion

**Steps:**

1. Connect to Notion (from TC-F002)
2. Scroll to "Accessible Pages" section
3. Verify page list

**Expected Results:**

- Section heading "Accessible Pages" visible
- Table with columns: Page Title, Last Edited, Actions
- At least 5 pages listed
- Page titles accurate (match Notion)
- Last edited times show relative format ("2 hours ago")
- "View in Notion" button for each page
- Links open in new tab
- Links work (go to correct Notion page)
- Empty state message if no pages shared

**Pass Criteria:** All pages display correctly with accurate data

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Number of Pages Displayed:
Data Accuracy: Y/N
Issues Found:
```

---

### TC-F008: Empty Pages List

**Objective:** Show helpful message when no pages shared

**Prerequisites:**

- Valid connection
- Integration has NO pages shared with it

**Steps:**

1. Connect with token that has no shared pages
2. Observe pages section

**Expected Results:**

- "No Pages Found" heading displayed
- Explanation: "No pages are currently accessible by this integration."
- Instructions on how to share pages (4-step list)
- No broken UI or empty tables
- Instructions are clear and actionable

**Pass Criteria:** User understands what to do next

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Are instructions clear? Y/N
Issues Found:
```

---

### TC-F009: Disconnect Functionality

**Objective:** User can disconnect and clear connection

**Prerequisites:** Valid connection established

**Steps:**

1. Click "Disconnect" button
2. Confirm in dialog (if present)
3. Wait for page reload

**Expected Results:**

- Confirmation dialog appears: "Are you sure you want to disconnect from Notion? This will remove your API token."
- If user clicks OK:
    - Page reloads
    - Success message: "Successfully disconnected from Notion."
    - Connection form shown again (disconnected state)
    - Token cleared from database
    - Workspace info cleared
    - No cached data remains
- If user clicks Cancel:
    - Dialog closes
    - Connection remains intact

**Pass Criteria:** Clean disconnection, all data removed

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Confirmation Dialog Present? Y/N
Issues Found:
```

---

### TC-F010: Reconnect After Disconnect

**Objective:** User can disconnect and reconnect cleanly

**Prerequisites:** Valid token available

**Steps:**

1. Establish connection (TC-F002)
2. Disconnect (TC-F009)
3. Reconnect with same token
4. Verify connection

**Expected Results:**

- Connection succeeds
- Workspace info matches previous connection
- Pages list repopulates
- No duplicate data
- No errors about existing connection
- Cached data refreshed

**Pass Criteria:** Clean reconnect, no issues

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Issues Found:
```

---

### TC-F011: Reconnect With Different Token

**Objective:** User can switch to different Notion workspace

**Prerequisites:** Two different valid Notion tokens

**Steps:**

1. Connect with Token A
2. Note workspace name
3. Disconnect
4. Connect with Token B
5. Verify workspace info

**Expected Results:**

- Previous workspace info completely replaced
- New workspace name shown
- New pages list shown
- No mixing of data from Token A and Token B
- Clean state transition

**Pass Criteria:** Workspace switch is clean

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Issues Found:
```

---

### TC-F012: Workspace Info Caching

**Objective:** Verify workspace info is cached appropriately

**Prerequisites:** Valid connection

**Steps:**

1. Connect to Notion
2. Note page load time
3. Refresh the settings page
4. Note page load time
5. Wait 1 hour
6. Refresh again

**Expected Results:**

- First load: API call made, data fetched
- Subsequent refreshes within 1 hour: Cached data used (faster load)
- After 1 hour: Fresh API call made (cache expired)
- No stale data displayed
- Cache invalidated on disconnect
- Cache invalidated on new connection

**Pass Criteria:** Caching works, improves performance

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
First Load Time:
Cached Load Time:
After Cache Expiry:
Issues Found:
```

---

## Security Test Cases

### TC-S001: Nonce Verification - Connect

**Objective:** Ensure connect action verifies nonce

**Prerequisites:** Settings page loaded

**Steps:**

1. Open browser dev tools > Network tab
2. Inspect connect form HTML
3. Verify nonce field present
4. Submit form
5. Attempt to replay request without valid nonce

**Expected Results:**

- Form includes hidden input `notion_sync_connect_nonce`
- Nonce value is unique per page load
- Request without valid nonce fails with security error
- Error message: "Security check failed. Please try again."
- HTTP 403 response
- No connection saved

**Pass Criteria:** Nonce properly verified, replay attacks blocked

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Nonce Field Present? Y/N
Replay Attack Blocked? Y/N
Issues Found:
```

---

### TC-S002: Nonce Verification - Disconnect

**Objective:** Ensure disconnect action verifies nonce

**Prerequisites:** Valid connection

**Steps:**

1. Inspect disconnect form
2. Verify nonce present
3. Attempt to submit without nonce or with invalid nonce

**Expected Results:**

- Form includes `notion_sync_disconnect_nonce`
- Invalid nonce rejected
- Security error displayed
- Connection NOT disconnected

**Pass Criteria:** Disconnect properly secured

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Issues Found:
```

---

### TC-S003: Capability Checks

**Objective:** Only admins can access settings and submit forms

**Prerequisites:**

- Admin user
- Editor user (or lower role)

**Steps:**

1. Log in as Editor
2. Try to access `/wp-admin/admin.php?page=notion-sync`
3. Try to submit connect form (if accessible via direct POST)
4. Log in as Admin
5. Verify access granted

**Expected Results:**

- Non-admin users: Access denied (403)
- Error: "You do not have sufficient permissions to access this page."
- Form submissions from non-admins rejected
- Admin users: Full access

**Pass Criteria:** Proper capability checks (`manage_options`)

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Non-admin Access Blocked? Y/N
Admin Access Granted? Y/N
Issues Found:
```

---

### TC-S004: Input Sanitization

**Objective:** All user input is properly sanitized

**Prerequisites:** Settings page

**Steps:**

1. Enter token with spaces: `  secret_test123  `
2. Enter token with special characters: `secret_<script>alert('xss')</script>`
3. Submit each and check database

**Expected Results:**

- Spaces trimmed from token before storage
- Special characters properly sanitized
- No XSS payloads stored
- Tokens processed through `sanitize_text_field()`
- No unsanitized $\_POST data used directly

**Pass Criteria:** All input sanitized, no XSS possible

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Sanitization Functions Used:
Issues Found:
```

---

### TC-S005: Output Escaping

**Objective:** All dynamic output is properly escaped

**Prerequisites:** Valid connection with workspace name containing special characters

**Steps:**

1. Review template file source code
2. Check all dynamic outputs use escaping functions
3. Test with workspace name: `Test <script>alert('xss')</script> Workspace`

**Expected Results:**

- Workspace names: `esc_html()`
- URLs: `esc_url()`
- Attributes: `esc_attr()`
- HTML snippets: `wp_kses()` with allowed tags
- No raw `echo` of user data
- Special characters displayed safely (not executed)

**Pass Criteria:** 100% of dynamic output escaped

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Unescaped Outputs Found: (list)
Issues Found:
```

---

### TC-S006: XSS Prevention

**Objective:** Plugin resistant to XSS attacks

**Prerequisites:** Settings page

**Test Payloads:**

```
secret_<script>alert('XSS')</script>
secret_"><img src=x onerror=alert('XSS')>
secret_';alert('XSS');//
secret_<svg/onload=alert('XSS')>
```

**Steps:**

1. Submit each payload as token
2. Check if JavaScript executes
3. Check database storage
4. Check display on settings page

**Expected Results:**

- No JavaScript executes
- Payloads rendered as text (visible but not executed)
- Proper HTML entity encoding
- No alert boxes appear
- Console shows no XSS warnings

**Pass Criteria:** All XSS attempts blocked

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Payloads Tested:
Any Executed? Y/N (If yes, CRITICAL)
Issues Found:
```

---

### TC-S007: CSRF Protection

**Objective:** Actions protected against CSRF

**Prerequisites:** Valid connection

**Steps:**

1. Create malicious form on different site:

```html
<form action="http://your-wp-site.test/wp-admin/admin-post.php" method="POST">
	<input type="hidden" name="action" value="notion_sync_disconnect" />
	<input type="submit" value="Click Me" />
</form>
```

2. Submit form while logged in to WordPress
3. Check if disconnection occurs

**Expected Results:**

- Request fails (no valid nonce)
- User remains connected
- Error displayed
- Action not executed

**Pass Criteria:** CSRF attacks blocked

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
CSRF Blocked? Y/N
Issues Found:
```

---

### TC-S008: SQL Injection Prevention

**Objective:** No SQL injection vulnerabilities

**Prerequisites:** Settings page

**Note:** Plugin doesn't use custom SQL queries, relies on WordPress options API

**Steps:**

1. Review code for any custom SQL
2. Verify all database operations use WordPress functions
3. Test with SQL injection payloads in token field (should have no effect)

**Expected Results:**

- All database operations via `update_option()`, `get_option()`, `delete_option()`
- No raw SQL queries
- WordPress handles sanitization internally
- Injection attempts harmless

**Pass Criteria:** No custom SQL, WordPress API used exclusively

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Custom SQL Found? Y/N (list if yes)
Issues Found:
```

---

### TC-S009: Secure Token Storage

**Objective:** Token stored securely in database

**Prerequisites:** Valid connection

**Steps:**

1. Connect with valid token
2. Check database table `wp_options`
3. Query: `SELECT * FROM wp_options WHERE option_name = 'notion_wp_token'`
4. Verify token storage

**Expected Results:**

- Token stored in `wp_options` table
- Option name: `notion_wp_token`
- Token value stored as-is (plaintext in DB is acceptable for API tokens)
- Token NOT displayed in admin UI after saving
- Token NOT in page HTML source
- Token NOT in JavaScript variables
- Only accessible to admin users

**Pass Criteria:** Token not exposed in UI or source code

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Token Visible in HTML? Y/N
Token in JS Variables? Y/N
Issues Found:
```

---

### TC-S010: Token Not Logged

**Objective:** Ensure tokens don't appear in logs

**Prerequisites:** Debug logging enabled

**Steps:**

1. Enable WordPress debug logging
2. Connect with token
3. Check debug.log file
4. Disconnect
5. Check logs again

**Expected Results:**

- Token value NOT in debug.log
- API calls logged without full token (e.g., "secret\_\*\*\*")
- Error messages don't include token
- Stack traces safe

**Pass Criteria:** Token never logged in plaintext

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Token Found in Logs? Y/N (If yes, CRITICAL)
Issues Found:
```

---

## UI/UX Test Cases

### TC-U001: Mobile Responsiveness (iPhone)

**Objective:** Settings page works on iPhone

**Prerequisites:** iPhone or iOS simulator

**Steps:**

1. Access settings page on iPhone (375x667)
2. Test connection flow
3. View workspace info
4. Scroll through pages list

**Expected Results:**

- Page renders without horizontal scrolling
- Form elements sized appropriately for touch
- Button min-height: 44px (Apple touch target)
- Token input full width
- Text readable without zooming
- No overlapping elements
- Instructions remain clear
- Tables stack or scroll horizontally

**Pass Criteria:** Fully functional on mobile, no usability issues

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Device/Simulator:
Screen Size:
Issues Found:
```

---

### TC-U002: Mobile Responsiveness (Android)

**Objective:** Settings page works on Android

**Prerequisites:** Android device or simulator

**Steps:**

1. Access settings page on Android device
2. Test full connection workflow
3. Test disconnect
4. Verify all interactions work

**Expected Results:**

- Similar to TC-U001
- Touch targets adequate
- Forms functional
- No rendering issues
- Keyboard doesn't break layout

**Pass Criteria:** Works on Android as well as iPhone

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Device:
Android Version:
Issues Found:
```

---

### TC-U003: Tablet Responsiveness

**Objective:** Verify layout on tablets (768x1024)

**Prerequisites:** Tablet or simulator

**Steps:**

1. Access settings page
2. Test in portrait and landscape
3. Verify layout adapts

**Expected Results:**

- Layout optimized for tablet width
- Not just stretched mobile view
- Not cramped desktop view
- Good use of screen space

**Pass Criteria:** Professional appearance on tablets

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Device:
Issues Found:
```

---

### TC-U004: Keyboard Navigation

**Objective:** All interactive elements keyboard accessible

**Prerequisites:** Settings page in browser

**Steps:**

1. Press Tab key repeatedly
2. Navigate through all interactive elements
3. Submit form using keyboard only
4. Dismiss notices with keyboard

**Expected Results:**

- Tab order logical (top to bottom, left to right)
- Focus indicators visible on all elements
- Can reach all buttons, links, inputs
- Enter key submits forms
- Escape key closes modals/dialogs
- No keyboard traps

**Pass Criteria:** Fully keyboard accessible

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
All Elements Reachable? Y/N
Focus Indicators Visible? Y/N
Issues Found:
```

---

### TC-U005: Loading States

**Objective:** User feedback during async operations

**Prerequisites:** Settings page

**Steps:**

1. Click "Connect to Notion"
2. Observe button during API call
3. Test on slow connection if possible

**Expected Results:**

- Button shows loading spinner
- Button text changes to "Connecting..."
- Button disabled during load
- User can't double-submit
- Loading completes within 10 seconds
- Clear indication of processing

**Pass Criteria:** Loading states clear and functional

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Loading Indicator Present? Y/N
Button Disabled? Y/N
Issues Found:
```

---

### TC-U006: Error Message Clarity

**Objective:** Error messages are helpful to non-technical users

**Prerequisites:** Settings page

**Steps:**

1. Trigger each error scenario (invalid token, network error, etc.)
2. Read error messages
3. Assess clarity

**Expected Results:**

- Errors in plain language (not technical jargon)
- No HTTP codes shown (e.g., "Error 401")
- Specific guidance on what to do
- No stack traces visible to users
- Error color-coded (red)
- Dismissible notices

**Pass Criteria:** Non-developer understands all errors

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Error Messages Tested:
1.
2.
3.
Are they helpful? Y/N
Issues Found:
```

---

### TC-U007: Success Message Clarity

**Objective:** Success feedback is encouraging and clear

**Prerequisites:** Valid token

**Steps:**

1. Connect successfully
2. Disconnect successfully
3. Read success messages

**Expected Results:**

- Green success notices
- Positive language
- Includes workspace name (for connection)
- Dismissible
- Auto-dismiss after 5 seconds (optional)

**Pass Criteria:** Success feels rewarding

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Success Messages Clear? Y/N
Issues Found:
```

---

### TC-U008: WordPress Admin Integration

**Objective:** Plugin fits naturally into WordPress admin

**Prerequisites:** WordPress admin access

**Steps:**

1. Locate plugin in admin menu
2. Check menu icon
3. Verify page styling
4. Compare to other admin pages

**Expected Results:**

- Menu item: "Notion Sync"
- Icon: Cloud dashicon (dashicons-cloud)
- Menu position appropriate (around Settings area)
- Page uses WordPress admin CSS
- Cards look like WordPress cards
- Buttons use WordPress button classes
- Matches WordPress design language

**Pass Criteria:** Looks native to WordPress

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Looks Native? Y/N
Issues Found:
```

---

### TC-U009: Page Load Performance

**Objective:** Settings page loads quickly

**Prerequisites:** Valid connection

**Steps:**

1. Measure initial page load time (disconnected state)
2. Measure connected state load time
3. Measure with 10 pages
4. Measure with 100 pages (if possible)

**Expected Results:**

- Disconnected state: < 2 seconds
- Connected state (cached): < 2 seconds
- Connected state (fresh): < 5 seconds
- Page list renders incrementally or quickly
- No blocking/hanging
- Perceived performance good (loading indicators)

**Pass Criteria:** Load times acceptable, feels fast

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Load Times:
- Disconnected:
- Connected (cached):
- Connected (fresh):
Issues Found:
```

---

### TC-U010: Browser Back/Forward

**Objective:** Browser navigation works correctly

**Prerequisites:** Settings page

**Steps:**

1. Navigate to settings page
2. Connect
3. Click browser back button
4. Click forward button
5. Refresh page

**Expected Results:**

- Back button doesn't break connection
- Forward button works
- Page state consistent
- No duplicate submissions
- Success/error messages don't persist incorrectly

**Pass Criteria:** Browser nav works as expected

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Issues Found:
```

---

## Code Quality Test Cases

### TC-Q001: PHP Linting (PHPCS)

**Objective:** All PHP code passes WordPress Coding Standards

**Prerequisites:** Development environment with composer installed

**Steps:**

1. Run: `composer lint:phpcs`
2. Review output

**Expected Results:**

- Zero errors
- Zero warnings (or only documented exceptions)
- All files scanned
- Exit code 0

**Pass Criteria:** `composer lint:phpcs` passes

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Command Output:**

```
Tester:
Date:
Command: composer lint:phpcs
Exit Code:
Errors:
Warnings:
```

---

### TC-Q002: PHP Static Analysis (PHPStan)

**Objective:** PHP code passes level 5 static analysis

**Prerequisites:** Development environment

**Steps:**

1. Run: `composer lint:phpstan`
2. Review output

**Expected Results:**

- Zero errors at level 5
- All type hints correct
- No undefined variables
- No dead code
- Exit code 0

**Pass Criteria:** `composer lint:phpstan` passes

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Command Output:**

```
Tester:
Date:
Command: composer lint:phpstan
Exit Code:
Errors Found:
```

---

### TC-Q003: JavaScript Linting (ESLint)

**Objective:** JavaScript passes WordPress ESLint rules

**Prerequisites:** Development environment with npm installed

**Steps:**

1. Run: `npm run lint:js`
2. Review output

**Expected Results:**

- Zero errors
- Zero warnings
- No console.log statements
- All functions documented
- Exit code 0

**Pass Criteria:** `npm run lint:js` passes

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Command Output:**

```
Tester:
Date:
Command: npm run lint:js
Exit Code:
Errors:
Warnings:
```

---

### TC-Q004: CSS Linting (Stylelint)

**Objective:** CSS passes WordPress style standards

**Prerequisites:** Development environment

**Steps:**

1. Run: `npm run lint:css`
2. Review output

**Expected Results:**

- Zero errors
- Warnings for !important usage (if any) documented
- Property ordering correct
- No duplicate properties
- Exit code 0

**Pass Criteria:** `npm run lint:css` passes

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Command Output:**

```
Tester:
Date:
Command: npm run lint:css
Exit Code:
Errors:
Warnings:
```

---

### TC-Q005: File Size Limits

**Objective:** No files exceed 500 lines

**Prerequisites:** All source files

**Steps:**

1. Run: `find plugin/src plugin/templates -name "*.php" -exec wc -l {} + | sort -rn`
2. Check JavaScript files: `wc -l plugin/assets/src/js/*.js`
3. Check CSS files: `wc -l plugin/assets/src/css/*.css`

**Expected Results:**

- SettingsPage.php: < 500 lines (currently 276)
- NotionClient.php: < 500 lines (currently 313)
- AdminNotices.php: < 500 lines (currently 88)
- settings.php: < 500 lines (currently 298)
- admin.js: < 500 lines (currently 376)
- admin.css: < 500 lines (currently 453)

**Pass Criteria:** All files under 500 lines

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**File Sizes:**

```
Tester:
Date:
Files Over 500 Lines: (none expected)
```

---

### TC-Q006: No PHP Warnings/Errors

**Objective:** Code runs without PHP warnings

**Prerequisites:**

- WordPress with WP_DEBUG enabled
- WP_DEBUG_LOG enabled

**Steps:**

1. Enable debugging in wp-config.php:
    ```php
    define('WP_DEBUG', true);
    define('WP_DEBUG_LOG', true);
    define('WP_DEBUG_DISPLAY', false);
    ```
2. Perform all functional tests
3. Check debug.log

**Expected Results:**

- Zero PHP notices
- Zero PHP warnings
- Zero PHP errors
- Zero deprecated function calls
- Clean debug log

**Pass Criteria:** No PHP errors/warnings

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Debug Log:**

```
Tester:
Date:
Issues in Log: (paste any)
```

---

### TC-Q007: No JavaScript Console Errors

**Objective:** No JavaScript errors in browser console

**Prerequisites:** Settings page in browser

**Steps:**

1. Open browser DevTools > Console
2. Clear console
3. Perform all user actions (connect, disconnect, etc.)
4. Monitor console output

**Expected Results:**

- Zero errors
- Zero warnings
- No deprecated API warnings
- console.warn/console.error acceptable if documented
- No console.log statements (should be removed)

**Pass Criteria:** Clean console

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Console Output:**

```
Tester:
Date:
Browser:
Errors Found: (paste)
```

---

### TC-Q008: Code Documentation

**Objective:** All code properly documented

**Prerequisites:** Source files

**Steps:**

1. Review PHP files for DocBlocks
2. Review JavaScript for JSDoc
3. Check function documentation

**Expected Results:**

- All classes have DocBlocks
- All methods documented with @param and @return
- All functions have description
- Complex logic has inline comments
- No TODO comments remaining (or documented in issues)

**Pass Criteria:** Documentation complete

**Test Result:** [ ] Pass [ ] Fail [ ] Blocked

**Notes:**

```
Tester:
Date:
Undocumented Functions: (list)
TODO Comments Found: (list)
```

---

## Browser Compatibility Matrix

### Testing Checklist

For each browser, run core functional tests (TC-F001 through TC-F012)

| Browser | Version | OS      | TC-F002 | TC-F003 | TC-F007 | TC-F009 | Status   |
| ------- | ------- | ------- | ------- | ------- | ------- | ------- | -------- |
| Chrome  | Latest  | macOS   | [ ]     | [ ]     | [ ]     | [ ]     | [ ] Pass |
| Chrome  | Latest  | Windows | [ ]     | [ ]     | [ ]     | [ ]     | [ ] Pass |
| Firefox | Latest  | macOS   | [ ]     | [ ]     | [ ]     | [ ]     | [ ] Pass |
| Firefox | Latest  | Windows | [ ]     | [ ]     | [ ]     | [ ]     | [ ] Pass |
| Safari  | Latest  | macOS   | [ ]     | [ ]     | [ ]     | [ ]     | [ ] Pass |
| Edge    | Latest  | Windows | [ ]     | [ ]     | [ ]     | [ ]     | [ ] Pass |
| Safari  | Mobile  | iOS     | [ ]     | [ ]     | [ ]     | [ ]     | [ ] Pass |
| Chrome  | Mobile  | Android | [ ]     | [ ]     | [ ]     | [ ]     | [ ] Pass |

**Notes:**

- Focus testing on browsers with "High" priority
- Report any browser-specific issues
- Visual differences acceptable if functional

---

## Test Results Summary

### Completion Tracking

**Test Execution Date:** **\*\***\_**\*\***

**Tester Name:** **\*\***\_**\*\***

| Category     | Total Tests | Passed | Failed | Blocked | Pass Rate |
| ------------ | ----------- | ------ | ------ | ------- | --------- |
| Functional   | 12          | \_\_\_ | \_\_\_ | \_\_\_  | \_\_\_%   |
| Security     | 10          | \_\_\_ | \_\_\_ | \_\_\_  | \_\_\_%   |
| UI/UX        | 10          | \_\_\_ | \_\_\_ | \_\_\_  | \_\_\_%   |
| Code Quality | 8           | \_\_\_ | \_\_\_ | \_\_\_  | \_\_\_%   |
| **Total**    | **40**      | \_\_\_ | \_\_\_ | \_\_\_  | \_\_\_%   |

### Pass Criteria for Gatekeeping

- [ ] All functional tests pass (100%)
- [ ] All security tests pass (100%)
- [ ] All code quality tests pass (100%)
- [ ] UI/UX tests: 90%+ pass rate
- [ ] Zero critical bugs
- [ ] Zero security vulnerabilities
- [ ] Works on 3+ browsers
- [ ] Works on 3+ devices (desktop, tablet, mobile)

### Critical Issues Found

| Issue ID | Severity | Description | Test Case | Status |
| -------- | -------- | ----------- | --------- | ------ |
|          |          |             |           |        |
|          |          |             |           |        |
|          |          |             |           |        |

**Severity Levels:**

- **Critical:** Blocks release, must fix
- **High:** Should fix before release
- **Medium:** Can defer to next phase
- **Low:** Nice to have

---

## Bug Reporting Template

When logging bugs, use this format:

```markdown
**Bug ID:** BUG-P0-###
**Test Case:** TC-\_\_\_\_
**Severity:** Critical/High/Medium/Low
**Browser/Device:**
**Reproducible:** Yes/No/Sometimes

**Description:**
[Clear description of the bug]

**Steps to Reproduce:**

1.
2.
3.

**Expected Result:**
[What should happen]

**Actual Result:**
[What actually happened]

**Screenshots/Logs:**
[Attach if applicable]

**Suggested Fix:**
[If known]
```

---

## Regression Testing

If any code changes are made after initial testing:

### Re-test Priority

**High Priority (Must Re-test):**

- Any test case related to changed code
- Security tests
- Core connection flow (TC-F002)

**Medium Priority (Should Re-test):**

- UI tests if styling changed
- Full functional suite

**Low Priority (Spot Check):**

- Browser compatibility
- Code quality (automated)

---

## Test Environment Teardown

After testing completes:

1. [ ] Document all test results in this file
2. [ ] File bugs for all failures
3. [ ] Archive screenshots/logs
4. [ ] Clean up test WordPress installations
5. [ ] Revoke test Notion integrations
6. [ ] Update Phase 0 status document

---

## Sign-off

### Test Completion

**Test Lead:** \***\*\*\*\*\*\*\***\_\***\*\*\*\*\*\*\***
**Signature:** \***\*\*\*\*\*\*\***\_\***\*\*\*\*\*\*\***
**Date:** \***\*\*\*\*\*\*\***\_\***\*\*\*\*\*\*\***

**Result:** [ ] PASS - Ready for Gatekeeping Demo [ ] FAIL - Fixes Required

**Notes:**

```




```

---

## Appendix A: Quick Test Commands

```bash
# Code quality checks
composer lint               # All PHP linting
composer lint:phpcs        # PHPCS only
composer lint:phpstan      # PHPStan only
npm run lint               # All JS/CSS linting
npm run lint:js            # ESLint only
npm run lint:css           # Stylelint only

# File size check
find plugin/src plugin/templates -name "*.php" -exec wc -l {} + | sort -rn

# Check for console.log
grep -r "console.log" plugin/assets/src/js/

# Check for unescaped output
grep -r "echo \$" plugin/src/ plugin/templates/

# WordPress debug mode
tail -f wp-content/debug.log
```

---

## Appendix B: Test Data

### Valid Notion Token Template

```
secret_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX
```

### Test Workspace Names

- Simple: "My Workspace"
- With Special Chars: "Test & Dev <Workspace>"
- Unicode: "Test Workspace 🚀"
- Long: "Very Long Workspace Name That Tests Truncation And Display"

### Test Page Titles

- Simple: "Test Page"
- Empty: "(Untitled)"
- Special: "<script>alert('test')</script>"
- Unicode: "📝 Notes"
- Long: "This Is A Very Long Page Title That Tests How We Handle Long Titles In The UI"

---

**Document Version:** 1.0
**Last Updated:** 2025-10-19
**Next Review:** After Phase 0 testing completion
