# Phase 0: Proof of Concept (MANDATORY GATEKEEPING)

**Status:** Not Started
**Duration:** 3-5 days
**Complexity:** S (Small)
**Version Target:** v0.1-dev

## 🎯 Goal

Prove that authentication works and users can connect their Notion account. **This phase is about showing visible results FAST, not building infrastructure.**

**User Story:** "As a WordPress admin, I can enter my Notion API token, see my workspaces, and verify the connection works."

## ✅ Success Criteria (Gatekeeping)

**DO NOT PROCEED to Phase 1 until ALL criteria are met:**

- [ ] Non-technical user can enter API token and see success message
- [ ] Settings page displays user's Notion workspaces/pages
- [ ] Error messages are helpful (e.g., "Invalid token" not "Error 401")
- [ ] User can disconnect and try again cleanly
- [ ] All linting passes (WPCS, ESLint, PHPStan level 5)
- [ ] Zero PHP warnings or JavaScript console errors
- [ ] UI works on mobile devices (responsive)
- [ ] **Can be demoed to a non-developer in under 2 minutes**

## 📋 Dependencies

**None** - This is the first phase.

**Infrastructure Already Ready (from setup):**
- ✅ Docker environment with worktree support
- ✅ Linting configuration (phpcs, eslint, stylelint, pre-commit hooks)
- ✅ Makefile commands
- ✅ Project structure documented

## 🔀 Parallel Work Streams

### Stream 1: Authentication System (Main Priority)
**Worktree:** `phase-0-auth`
**Duration:** 3-4 days
**Files Created:** 3-4 files, all <500 lines

**What Users See:**
- Settings page at **WP Admin > Notion Sync**
- Input field for Notion API token
- "Connect to Notion" button
- Success message showing workspace name
- List of accessible pages (read-only)
- "Disconnect" button

**Technical Implementation:**

**File 1:** `plugin/src/Admin/SettingsPage.php` (<300 lines)
```php
<?php
namespace NotionSync\Admin;

class SettingsPage {
    public function register(): void {
        add_menu_page(
            __( 'Notion Sync', 'notion-wp' ),
            __( 'Notion Sync', 'notion-wp' ),
            'manage_options',
            'notion-sync',
            [ $this, 'render' ],
            'dashicons-cloud'
        );

        add_action( 'admin_post_notion_sync_connect', [ $this, 'handle_connect' ] );
        add_action( 'admin_post_notion_sync_disconnect', [ $this, 'handle_disconnect' ] );
    }

    public function render(): void {
        // Load template
        require NOTION_SYNC_PATH . 'plugin/templates/admin/settings.php';
    }

    public function handle_connect(): void {
        // Verify nonce
        // Sanitize token
        // Test connection
        // Save token
        // Redirect with success/error message
    }

    public function handle_disconnect(): void {
        // Verify nonce
        // Delete token
        // Redirect
    }
}
```

**File 2:** `plugin/src/API/NotionClient.php` (<400 lines)
```php
<?php
namespace NotionSync\API;

class NotionClient {
    private string $token;
    private string $base_url = 'https://api.notion.com/v1';

    public function __construct( string $token ) {
        $this->token = $token;
    }

    public function test_connection(): bool {
        // GET /users/me
        // Return true if 200, false otherwise
    }

    public function get_workspace_info(): array {
        // GET /users/me
        // Return workspace name and user info
    }

    public function list_pages( int $limit = 10 ): array {
        // POST /search with query
        // Return simplified list of pages
    }

    private function request( string $method, string $endpoint, array $body = [] ): array {
        // wp_remote_request wrapper
        // Error handling
        // JSON decode
    }
}
```

**File 3:** `plugin/templates/admin/settings.php` (<200 lines)
```php
<?php
// Template for settings page
// Shows connection form if not connected
// Shows workspace info + pages if connected
// All output escaped
```

**File 4:** `plugin/src/Admin/AdminNotices.php` (<150 lines)
```php
<?php
namespace NotionSync\Admin;

class AdminNotices {
    public function show_success( string $message ): void {
        // WordPress admin notice (success)
    }

    public function show_error( string $message ): void {
        // WordPress admin notice (error)
    }
}
```

**Tasks:**
1. ✅ Create settings menu page (Day 1)
2. ✅ Build connection form with nonce (Day 1)
3. ✅ Implement NotionClient API wrapper (Day 2)
4. ✅ Test connection and fetch workspace info (Day 2)
5. ✅ Display workspace name and pages list (Day 3)
6. ✅ Add disconnect functionality (Day 3)
7. ✅ Polish error messages and UI (Day 4)
8. ✅ Test on mobile (Day 4)

**Definition of Done:**
- [ ] Can connect with valid token in <30 seconds
- [ ] Invalid token shows helpful error
- [ ] See workspace name and 5+ pages
- [ ] Disconnect button works
- [ ] All output escaped (security)
- [ ] All input sanitized (security)
- [ ] Nonces verified on all actions
- [ ] Responsive on mobile

---

### Stream 2: Development Environment (Supporting)
**Worktree:** `phase-0-linting` (or work in main)
**Duration:** 1-2 days
**Note:** Much of this is already done from project setup

**What This Provides:**
- All code must pass linting before commit
- Pre-commit hooks block bad code
- CI/CD runs on every push

**Tasks:**
1. ✅ Verify linting config works (Day 1)
2. ✅ Install dependencies: `composer install && npm install` (Day 1)
3. ✅ Test pre-commit hooks (Day 1)
4. ✅ Verify GitHub Actions run (Day 2)
5. ✅ Document linting workflow (Day 2)

**Files to Verify/Adjust:**
- `phpcs.xml.dist` - Ensure 500-line limit enforced
- `phpstan.neon` - WordPress stubs loaded
- `.eslintrc.json` - No console.log allowed
- `.husky/pre-commit` - All linters run

**Definition of Done:**
- [ ] `composer lint` passes on all code
- [ ] `npm run lint` passes on all code
- [ ] Pre-commit hook blocks bad commits
- [ ] CI passes on GitHub
- [ ] VS Code shows inline lint errors

---

### Stream 3: Basic Admin UI (Design)
**Worktree:** `phase-0-admin-ui`
**Duration:** 2-3 days
**Files:** CSS and JS for admin interface

**What Users See:**
- Clean, modern admin interface
- WordPress-native styling
- Loading states during API calls
- Helpful empty states

**Technical Implementation:**

**File 1:** `plugin/assets/src/scss/admin.scss` (<200 lines)
```scss
.notion-sync-settings {
  max-width: 800px;

  .connection-form {
    background: white;
    padding: 20px;
    border: 1px solid #ddd;
    border-radius: 4px;
  }

  .token-input {
    width: 100%;
    font-family: monospace;
  }

  .workspace-info {
    // Success state styles
  }

  @media (max-width: 782px) {
    // Mobile responsive
  }
}
```

**File 2:** `plugin/assets/src/js/admin.js` (<200 lines)
```javascript
// Show loading spinner during connection test
// Clear sensitive data on disconnect
// Validate token format before submit
// Accessible keyboard navigation
```

**Tasks:**
1. ✅ Design connection form layout (Day 1)
2. ✅ Add loading states (Day 2)
3. ✅ Style workspace info display (Day 2)
4. ✅ Test mobile responsiveness (Day 3)
5. ✅ Add helpful empty states (Day 3)

**Definition of Done:**
- [ ] Looks professional (matches WordPress admin)
- [ ] Loading spinner during API calls
- [ ] Works on phones/tablets
- [ ] Keyboard accessible
- [ ] No JavaScript errors in console

---

### Stream 4: Documentation (Can run anytime)
**Worktree:** Can work directly in main
**Duration:** 1-2 days

**What This Provides:**
- Users know how to get started
- Developers know how to contribute

**Files to Create/Update:**

**File 1:** `plugin/README.md` (User-focused)
```markdown
# Notion Sync for WordPress

## Installation

1. Download plugin
2. Upload to WordPress
3. Activate

## Setup

1. Create Notion Integration at https://www.notion.com/my-integrations
2. Copy the "Internal Integration Token"
3. In WordPress, go to Notion Sync > Settings
4. Paste token and click Connect
5. Share your Notion pages with the integration
```

**File 2:** `docs/getting-started.md` (Detailed setup)
- Step-by-step with screenshots (placeholders for now)
- How to get Notion API token
- How to share pages with integration
- Troubleshooting common issues

**File 3:** `docs/development/phase-0-checklist.md`
- Daily checklist for Phase 0 tasks
- Testing checklist
- Pre-launch checklist

**Definition of Done:**
- [ ] User can set up plugin without asking questions
- [ ] Screenshots show key steps (or placeholders)
- [ ] Troubleshooting section covers common errors
- [ ] Developer docs explain code structure

---

## 📦 Deliverables

### Visible to Users (What They Can Do)
- ✅ Navigate to **WP Admin > Notion Sync**
- ✅ Enter Notion API token
- ✅ Click "Connect to Notion"
- ✅ See their workspace name
- ✅ See list of accessible Notion pages (read-only)
- ✅ Click "Disconnect" to remove connection

### Technical (What We Built)
- ✅ `plugin/src/Admin/SettingsPage.php` - Settings page registration
- ✅ `plugin/src/API/NotionClient.php` - Notion API wrapper
- ✅ `plugin/templates/admin/settings.php` - UI template
- ✅ `plugin/assets/dist/css/admin.min.css` - Compiled styles
- ✅ `plugin/assets/dist/js/admin.min.js` - Compiled JavaScript
- ✅ Documentation for users and developers
- ✅ All linting passing
- ✅ Pre-commit hooks working

### Not Built (Deferred to Phase 1+)
- ❌ Actual sync functionality (that's Phase 1)
- ❌ Database schema (not needed yet)
- ❌ Block converters (Phase 1)
- ❌ Media handling (Phase 3)
- ❌ Complex CI/CD workflows (add as needed)
- ❌ Extensive test suites (add with features)

---

## 🚀 Daily Workflow

### Day 1: Foundation
**Morning:**
- Create worktrees: `./scripts/create-worktree.sh phase-0-auth phase-0-auth`
- Start Stream 1: Create `SettingsPage.php` skeleton
- Start Stream 2: Verify linting works

**Afternoon:**
- Build connection form UI
- Add nonce verification
- Test form submission
- **Demo:** Can submit form (even if nothing happens yet)

### Day 2: API Integration
**Morning:**
- Create `NotionClient.php`
- Implement `test_connection()` method
- Handle API errors gracefully

**Afternoon:**
- Implement `get_workspace_info()`
- Display workspace name on success
- **Demo:** Can connect and see workspace name

### Day 3: Page Listing
**Morning:**
- Implement `list_pages()` method
- Display page list in UI
- Add disconnect button

**Afternoon:**
- Polish error messages
- Add loading states
- **Demo:** Full connection flow works

### Day 4: Polish & Testing
**Morning:**
- Test on mobile devices
- Fix responsive issues
- Improve error messages

**Afternoon:**
- Run through full checklist
- Fix any linting issues
- **Demo:** Ready for gatekeeping review

### Day 5: Buffer & Documentation
- Address any issues found
- Complete documentation
- Prepare for Phase 1

---

## ✋ Gatekeeping Review

Before proceeding to Phase 1, schedule a **2-minute demo** with someone who:
- Is NOT a developer
- Has never seen the plugin before
- Can provide honest feedback

**Demo Script:**
1. Show them WordPress admin (30 seconds)
2. Navigate to Notion Sync (15 seconds)
3. Paste API token and connect (45 seconds)
4. Show workspace and pages list (30 seconds)

**Pass Criteria:**
- They understood what happened
- No confusion or questions about errors
- UI felt responsive and professional
- They could repeat it without help

**If demo fails:**
- Document what confused them
- Fix those specific issues
- Schedule another demo
- **DO NOT** proceed to Phase 1

---

## 🔍 Testing Checklist

### Functional Testing
- [ ] Can connect with valid token
- [ ] Invalid token shows clear error
- [ ] Network errors handled gracefully
- [ ] Workspace name displays correctly
- [ ] Page list shows 5+ pages
- [ ] Disconnect clears token
- [ ] Can reconnect after disconnect
- [ ] Nonce verification works on all forms

### Security Testing
- [ ] All output escaped (`esc_html`, `esc_attr`, etc.)
- [ ] All input sanitized (`sanitize_text_field`, etc.)
- [ ] Nonces verified on POST requests
- [ ] Capability checks (`current_user_can('manage_options')`)
- [ ] Token stored securely (not in plain text)
- [ ] No XSS vulnerabilities
- [ ] No SQL injection vectors (none expected yet)

### Code Quality
- [ ] All files under 500 lines
- [ ] `composer lint` passes
- [ ] `npm run lint` passes
- [ ] PHPStan level 5 passes
- [ ] No console errors or warnings
- [ ] No PHP notices or warnings
- [ ] Pre-commit hooks work

### UI/UX Testing
- [ ] Works in Chrome
- [ ] Works in Firefox
- [ ] Works in Safari
- [ ] Works on iPhone
- [ ] Works on Android phone
- [ ] Works on tablet
- [ ] Keyboard navigation works
- [ ] Screen reader friendly (basic test)

---

## 📊 Success Metrics

**Time Metrics:**
- Setup should take user <5 minutes
- Connection should complete <10 seconds
- Page load should be <2 seconds

**Quality Metrics:**
- Zero linting errors
- Zero security vulnerabilities
- 100% of forms have nonce verification
- 100% of output is escaped

**User Metrics:**
- 5/5 test users can connect without help
- Zero confusing error messages reported
- Works on all tested devices

---

## 🚧 Risks & Mitigation

| Risk | Impact | Mitigation |
|------|--------|------------|
| Notion API rate limiting | Medium | Cache workspace info, limit API calls |
| Invalid/expired tokens | High | Clear error messages, test connection flow |
| Network timeouts | Medium | Set reasonable timeout limits (30s) |
| WordPress version compatibility | Low | Test on WP 6.0+ |
| PHP version issues | Low | Require PHP 8.0+, test on 8.0-8.3 |

---

## 📝 Phase 0 Completion Checklist

### Code Complete
- [ ] All 4 work streams merged to main
- [ ] All files under 500 lines
- [ ] Zero linting errors
- [ ] All TODO comments resolved or converted to issues

### Testing Complete
- [ ] All functional tests pass
- [ ] All security checks pass
- [ ] Tested on 3+ devices (desktop, phone, tablet)
- [ ] Tested in 3+ browsers

### Documentation Complete
- [ ] README.md updated
- [ ] Getting started guide written
- [ ] Troubleshooting guide covers common issues
- [ ] Code has inline comments

### Demo Complete
- [ ] 2-minute demo successful with non-developer
- [ ] No confusion during demo
- [ ] Positive feedback received
- [ ] Ready to show stakeholders

### Ready for Phase 1
- [ ] All gatekeeping criteria met
- [ ] No critical bugs
- [ ] No security issues
- [ ] Team confident to proceed

---

## ⏭️ Next Phase Preview

**Phase 1: MVP Core** will build on this foundation:
- Use `NotionClient` to fetch page content
- Convert Notion blocks to WordPress blocks
- Create WordPress posts from Notion pages
- **Requires:** Working authentication from Phase 0

**Do not start Phase 1 until this checklist is 100% complete.**

---

**Document Version:** 2.0 (Aligned with KISS principles)
**Last Updated:** 2025-10-19
**Status:** Ready for Development
