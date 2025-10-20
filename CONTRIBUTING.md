# Contributing to Notion Sync for WordPress

Thank you for your interest in contributing to Notion Sync! This guide will help you get started with development, understand our workflow, and submit high-quality contributions.

## Table of Contents

1. [Code of Conduct](#code-of-conduct)
2. [Getting Started](#getting-started)
3. [Development Environment Setup](#development-environment-setup)
4. [Git Worktree Workflow](#git-worktree-workflow)
5. [Coding Standards](#coding-standards)
6. [Running Linters](#running-linters)
7. [Testing](#testing)
8. [Submitting Pull Requests](#submitting-pull-requests)
9. [Development Principles](#development-principles)
10. [Project Architecture](#project-architecture)

## Code of Conduct

We are committed to providing a welcoming and inclusive environment for all contributors. Please:

- Be respectful and constructive in discussions
- Accept feedback gracefully
- Focus on what's best for the project
- Show empathy towards other contributors

## Getting Started

### Prerequisites

Before contributing, ensure you have:

- **Git 2.5+** (for worktree support)
- **Docker and Docker Compose** (for local WordPress environment)
- **Node.js 18+** (for asset building)
- **Composer** (for PHP dependencies)
- **Basic understanding of**:
    - WordPress plugin development
    - PHP 8.0+
    - Modern JavaScript (ES6+)
    - Notion API basics

### First Steps

1. **Fork the repository** on GitHub
2. **Clone your fork**:
    ```bash
    git clone https://github.com/YOUR-USERNAME/notion-wp.git
    cd notion-wp
    ```
3. **Add upstream remote**:
    ```bash
    git remote add upstream https://github.com/thevgergroup/notion-wp.git
    ```

## Development Environment Setup

This project uses **git worktrees** with isolated Docker environments for parallel development. Each worktree has its own WordPress installation and database.

### Quick Setup

1. **Create your first worktree**:

    ```bash
    ./scripts/setup-worktree.sh main 8080 3306
    ```

    This creates a worktree named `main` with:
    - HTTP port: 8080
    - Database port: 3306
    - URL: http://main.localtest.me

2. **Access WordPress**:
    - Admin: http://main.localtest.me/wp-admin
    - Username: `admin`
    - Password: `admin`

3. **Install dependencies**:
    ```bash
    cd ../main/plugin
    composer install
    npm install
    npm run build
    ```

### Creating Feature Worktrees

For each feature or fix, create a dedicated worktree:

```bash
# Create worktree for your feature
./scripts/setup-worktree.sh feature-my-feature 8081 3307

# Navigate to it
cd ../feature-my-feature

# Start coding!
```

Each worktree is completely isolated:

- Separate WordPress installation
- Separate database
- Unique Docker containers
- Independent .env file

### Common Commands

From any worktree directory:

```bash
make help              # Show all available commands
make up                # Start containers
make down              # Stop containers
make logs              # View logs
make shell             # Access WordPress container
make wp ARGS="..."     # Run WP-CLI commands
make test              # Run tests (when available)
```

## Git Worktree Workflow

### Creating a Feature Branch

1. **Sync with upstream**:

    ```bash
    git fetch upstream
    git checkout main
    git merge upstream/main
    ```

2. **Create feature worktree**:

    ```bash
    ./scripts/setup-worktree.sh feature-block-converter 8082 3308
    cd ../feature-block-converter
    ```

3. **Make changes**:

    ```bash
    # Edit files
    vim plugin/src/Converters/MyConverter.php

    # Check linting
    cd plugin
    composer lint
    npm run lint

    # Test changes in browser
    # Visit http://feature-block-converter.localtest.me
    ```

4. **Commit changes**:

    ```bash
    git add plugin/src/Converters/MyConverter.php
    git commit -m "Add custom block converter for callouts"
    ```

5. **Push to your fork**:

    ```bash
    git push origin feature-block-converter
    ```

6. **Create pull request** on GitHub

### Cleaning Up Worktrees

After your PR is merged:

```bash
# Teardown worktree and delete branch
./scripts/teardown-worktree.sh feature-block-converter --delete-branch
```

## Coding Standards

We follow strict coding standards to maintain code quality and consistency.

### File Size Limits

**Maximum 500 lines per file** (including comments and whitespace)

If you hit this limit:

- Refactor into smaller, focused files
- Split responsibilities
- Extract helper functions

**Exception:** Configuration files only

### PHP Standards

**WordPress Coding Standards (WPCS)**

- Follow https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/
- Use tabs for indentation (not spaces)
- PSR-4 autoloading for namespaces
- PHPDoc comments for all classes and methods

**Type Declarations**

```php
// Always use type declarations (PHP 8.0+)
public function convert(array $block): string {
    // ...
}

// Use nullable types when appropriate
public function getData(): ?array {
    // ...
}
```

**Naming Conventions**

- Classes: `PascalCase` (e.g., `NotionClient`)
- Methods: `snake_case` (e.g., `get_workspace_info`)
- Variables: `snake_case` (e.g., `$notion_token`)
- Constants: `SCREAMING_SNAKE_CASE` (e.g., `NOTION_API_VERSION`)

**Security**

- Escape all output: `esc_html()`, `esc_attr()`, `esc_url()`
- Sanitize all input: `sanitize_text_field()`, etc.
- Verify nonces on form submissions
- Check capabilities: `current_user_can('manage_options')`

### JavaScript Standards

**ESLint with WordPress Preset**

- ES6+ syntax
- No `console.log` in production code
- Use `const` and `let` (not `var`)
- Prettier for formatting

**Example**:

```javascript
// Good
const connectButton = document.querySelector('.connect-button');

connectButton.addEventListener('click', async (event) => {
	event.preventDefault();
	await handleConnection();
});

// Bad
var button = document.querySelector('.connect-button'); // Use const
button.onclick = handleConnection; // Use addEventListener
console.log('clicked'); // No console.log
```

### CSS Standards

**Stylelint with WordPress Config**

- BEM naming convention preferred
- Mobile-first responsive design
- No `!important` unless documented

**Example**:

```scss
.notion-sync {
	&__settings {
		padding: 20px;

		@media (max-width: 782px) {
			padding: 10px;
		}
	}

	&__button {
		background: #2271b1;

		&:hover {
			background: #135e96;
		}
	}
}
```

## Running Linters

Before committing, always run linters to ensure code quality.

### PHP Linting

```bash
cd plugin

# Run all PHP linters
composer lint

# Individual linters
composer lint:phpcs          # Code standards
composer lint:phpstan        # Static analysis

# Auto-fix issues
composer lint:fix            # Fix all auto-fixable issues
composer lint:phpcbf         # Fix coding standards only
```

### JavaScript Linting

```bash
cd plugin

# Run JavaScript linter
npm run lint:js

# Auto-fix issues
npm run lint:js:fix
```

### CSS Linting

```bash
cd plugin

# Run CSS linter
npm run lint:css

# Auto-fix issues
npm run lint:css:fix
```

### Pre-commit Hooks

Pre-commit hooks are configured to run linters automatically:

```bash
# Install hooks (done automatically by setup-worktree.sh)
npm run prepare

# Hooks will run on every commit
git commit -m "Your message"

# If linting fails, commit is blocked
# Fix issues and try again
```

**To bypass hooks** (not recommended):

```bash
git commit --no-verify -m "Emergency fix"
```

## Testing

### Current Testing Setup

Phase 0 testing is primarily manual. Automated tests will be added in future phases.

**Manual Testing Checklist:**

1. Can connect with valid Notion token
2. Invalid token shows clear error
3. Workspace name displays correctly
4. Pages list populates after sharing
5. Disconnect clears connection
6. Can reconnect after disconnect

### Future Testing

As the project grows, we'll add:

- PHPUnit for unit tests
- Integration tests with WordPress
- E2E tests with Playwright
- Visual regression tests

## Submitting Pull Requests

### Before Submitting

- [ ] All linters pass (`composer lint && npm run lint`)
- [ ] Code follows project standards
- [ ] No files exceed 500 lines
- [ ] All output is escaped (security)
- [ ] All input is sanitized (security)
- [ ] Changes tested manually
- [ ] Inline comments explain complex logic
- [ ] No debug code or console.logs

### PR Title Format

Use conventional commit style:

```
feat: Add callout block converter
fix: Resolve token validation error
docs: Update installation guide
style: Fix code formatting in NotionClient
refactor: Extract API request logic
test: Add unit tests for block converter
```

### PR Description Template

```markdown
## Description

Brief description of what this PR does.

## Type of Change

- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Changes Made

- Bullet list of specific changes
- Include file names if helpful

## Testing

- How you tested these changes
- What browsers/devices tested on

## Screenshots (if applicable)

Add screenshots for UI changes

## Checklist

- [ ] Linters pass
- [ ] Code follows standards
- [ ] Security best practices followed
- [ ] Documentation updated
- [ ] No files exceed 500 lines
```

### Review Process

1. **Submit PR** against `main` branch
2. **Automated checks** run (linting, etc.)
3. **Code review** by maintainers
4. **Address feedback** if requested
5. **Approval and merge** by maintainer

### After Your PR is Merged

1. **Sync your fork**:

    ```bash
    git checkout main
    git fetch upstream
    git merge upstream/main
    git push origin main
    ```

2. **Clean up**:

    ```bash
    ./scripts/teardown-worktree.sh your-feature-branch --delete-branch
    ```

3. **Celebrate!** You've contributed to the project.

## Development Principles

This project follows specific development principles. Please review before contributing.

### 1. KISS (Keep It Simple, Stupid)

- Favor simple, readable solutions over clever abstractions
- One file, one responsibility
- Avoid premature optimization
- Question every dependency before adding it

### 2. Small Incremental Changes

- No big bang releases - each phase produces working results
- Full-stack increments (don't build entire backend then defer frontend)
- Every PR should be deployable
- Aim for daily/weekly progress that can be demoed

### 3. File Size Discipline

- **Hard limit: 500 lines per file**
- If you approach this limit, refactor
- Extract helpers, split responsibilities
- Target: 200-300 lines average

### 4. Code Quality Over Speed

- All code must pass linting before commit
- Pre-commit hooks enforce quality
- PHPStan level 5 minimum
- Zero warnings or errors

### 5. Security First

- All output must be escaped
- All input must be sanitized
- Nonces on all forms
- Capability checks on all admin actions

### 6. Progressive Enhancement

- Build simple features first
- Add complexity incrementally
- Edge cases come after main flows
- Defer optimization until needed

## Project Architecture

Understanding the architecture helps you contribute effectively.

### Directory Structure

```
plugin/
├── src/                    # PSR-4 source code (NotionWP\)
│   ├── Admin/              # WordPress admin UI
│   ├── API/                # Notion API client
│   ├── Sync/               # Sync orchestration (Phase 1+)
│   ├── Converters/         # Block converters (Phase 1+)
│   ├── Media/              # Media handling (Phase 3+)
│   ├── Navigation/         # Menu generation (Phase 3+)
│   └── Database/           # Data persistence (Phase 1+)
├── assets/
│   ├── src/                # Source SCSS/JS (committed)
│   └── dist/               # Built CSS/JS (gitignored)
├── templates/              # PHP templates
│   └── admin/              # Admin page templates
├── tests/                  # Test suite (future)
└── config/                 # Runtime config (gitignored per worktree)
```

### Namespace Structure

All PHP code uses PSR-4 autoloading under `NotionWP\` namespace:

```php
namespace NotionWP\Admin;      // Admin classes
namespace NotionWP\API;        // API clients
namespace NotionWP\Converters; // Block converters
```

### Key Classes (Phase 0)

**NotionWP\Admin\SettingsPage**

- Registers admin menu page
- Handles connection form
- Displays workspace info

**NotionWP\API\NotionClient**

- Wraps Notion API requests
- Handles authentication
- Returns parsed responses

**NotionWP\Admin\AdminNotices**

- Shows success/error messages
- Provides user feedback

### Hooks and Filters

We use WordPress hooks extensively. Familiarize yourself with:

**Actions:**

- `admin_menu` - Register admin pages
- `admin_post_{action}` - Handle form submissions
- `admin_enqueue_scripts` - Enqueue assets

**Filters:**

- `notion_wp_api_timeout` - Customize API timeout
- `notion_wp_api_headers` - Modify API headers
- More filters added in future phases

### Adding New Features

**Example: Adding a New Block Converter (Phase 1+)**

1. Create converter class:

    ```php
    // plugin/src/Converters/CalloutConverter.php
    namespace NotionWP\Converters;

    class CalloutConverter implements BlockConverterInterface {
        public function convert(array $notion_block): string {
            // Conversion logic
        }
    }
    ```

2. Keep file under 500 lines
3. Add PHPDoc comments
4. Write tests (when testing is set up)
5. Register via filter

## Common Tasks

### Adding a New Admin Page

```php
// plugin/src/Admin/MyPage.php
namespace NotionWP\Admin;

class MyPage {
    public function register(): void {
        add_menu_page(
            __('My Page', 'notion-wp'),
            __('My Page', 'notion-wp'),
            'manage_options',
            'my-page',
            [$this, 'render']
        );
    }

    public function render(): void {
        require NOTION_WP_PATH . 'templates/admin/my-page.php';
    }
}
```

### Adding a REST Endpoint

```php
// plugin/src/REST/MyController.php
namespace NotionWP\REST;

class MyController {
    public function register_routes(): void {
        register_rest_route('notion-wp/v1', '/my-endpoint', [
            'methods' => 'POST',
            'callback' => [$this, 'handle_request'],
            'permission_callback' => [$this, 'check_permissions'],
        ]);
    }

    public function handle_request(\WP_REST_Request $request): \WP_REST_Response {
        // Handle request
        return rest_ensure_response(['status' => 'success']);
    }

    public function check_permissions(): bool {
        return current_user_can('manage_options');
    }
}
```

### Adding Styles/Scripts

```php
// In your admin class
public function enqueue_assets(): void {
    wp_enqueue_style(
        'notion-wp-admin',
        NOTION_WP_URL . 'assets/dist/css/admin.min.css',
        [],
        NOTION_WP_VERSION
    );

    wp_enqueue_script(
        'notion-wp-admin',
        NOTION_WP_URL . 'assets/dist/js/admin.min.js',
        ['jquery'],
        NOTION_WP_VERSION,
        true
    );
}
```

## Documentation

When adding features, update relevant documentation:

- **User-facing changes:** Update `plugin/README.md`
- **Setup changes:** Update `docs/getting-started.md`
- **API changes:** Update `docs/api/*.md`
- **Code comments:** Add inline PHPDoc

### Documentation Style

- Use clear, non-technical language for user docs
- Include code examples in developer docs
- Provide step-by-step instructions
- Add troubleshooting sections

## Getting Help

### Resources

- [Development Principles](/docs/development/principles.md)
- [Project Architecture](/docs/architecture/ARCHITECTURE-SUMMARY.md)
- [Phase 0 Plan](/docs/plans/phase-0.md)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Notion API Docs](https://developers.notion.com/reference)

### Questions?

- Open a GitHub Discussion for general questions
- Open an Issue for bugs or feature requests
- Tag maintainers in PRs for review

## License

By contributing, you agree that your contributions will be licensed under the same GPL-2.0-or-later license as the project.

## Thank You!

Every contribution, no matter how small, helps make this plugin better. We appreciate your time and effort!

Happy coding!
