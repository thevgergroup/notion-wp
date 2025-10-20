# NotionClient API Documentation

The `NotionClient` class provides a clean interface for interacting with the Notion API. It handles authentication, request formatting, error handling, and response parsing.

**Namespace:** `NotionWP\API`
**File:** `plugin/src/API/NotionClient.php`
**Since:** v0.1-dev (Phase 0)

## Table of Contents

1. [Overview](#overview)
2. [Class Reference](#class-reference)
3. [Constructor](#constructor)
4. [Public Methods](#public-methods)
5. [Error Handling](#error-handling)
6. [Usage Examples](#usage-examples)
7. [Extending the Client](#extending-the-client)

## Overview

The `NotionClient` class wraps the Notion REST API v1, providing:

- Automatic authentication header injection
- WordPress-native HTTP request handling via `wp_remote_request()`
- Standardized error handling
- JSON encoding/decoding
- Type-safe return values

### Design Principles

- **Thin wrapper:** Minimal abstraction over Notion API
- **WordPress integration:** Uses WordPress HTTP API
- **Error transparency:** Returns clear error messages
- **Type safety:** Uses PHP 8.0+ type declarations
- **Testability:** All dependencies injectable

## Class Reference

```php
namespace NotionWP\API;

class NotionClient {
    private string $token;
    private string $base_url = 'https://api.notion.com/v1';
    private string $api_version = '2022-06-28';

    public function __construct(string $token);
    public function test_connection(): bool;
    public function get_workspace_info(): array;
    public function list_pages(int $limit = 10): array;
    private function request(string $method, string $endpoint, array $body = []): array;
}
```

## Constructor

### `__construct(string $token)`

Creates a new NotionClient instance with the provided API token.

**Parameters:**
- `$token` (string, required): Notion Internal Integration Token (starts with `secret_`)

**Returns:** `NotionClient` instance

**Throws:** None (validation happens on first request)

**Example:**
```php
use NotionWP\API\NotionClient;

$token = get_option('notion_wp_token');
$client = new NotionClient($token);
```

**Security Note:** Never hardcode tokens. Always retrieve from secure storage (WordPress options, environment variables, etc.).

## Public Methods

### `test_connection(): bool`

Tests if the provided token is valid and can authenticate with Notion API.

**Parameters:** None

**Returns:**
- `true` if connection successful
- `false` if connection fails

**Notion API Endpoint:** `GET /v1/users/me`

**Error Handling:**
- Returns `false` on any error (network, authentication, etc.)
- Does not throw exceptions

**Example:**
```php
$client = new NotionClient($token);

if ($client->test_connection()) {
    echo 'Connected successfully!';
} else {
    echo 'Connection failed. Check your token.';
}
```

**Use Cases:**
- Validating token on settings save
- Health check before sync operations
- Troubleshooting connection issues

---

### `get_workspace_info(): array`

Retrieves information about the authenticated integration's workspace and user.

**Parameters:** None

**Returns:** Associative array with workspace information:

```php
[
    'user_id' => 'abc123...',           // Notion user ID
    'bot_id' => 'xyz789...',            // Integration bot ID
    'workspace_name' => 'My Workspace', // Workspace name
    'workspace_icon' => 'emoji',        // Workspace icon (if available)
    'owner_type' => 'workspace',        // Owner type
]
```

**Notion API Endpoint:** `GET /v1/users/me`

**Error Handling:**
- Returns empty array on error
- Check for empty array before using

**Example:**
```php
$client = new NotionClient($token);
$info = $client->get_workspace_info();

if (!empty($info)) {
    echo 'Workspace: ' . esc_html($info['workspace_name']);
    echo 'User ID: ' . esc_html($info['user_id']);
}
```

**Use Cases:**
- Displaying connected workspace in admin UI
- Verifying correct workspace is connected
- Storing workspace metadata

**Response Schema:**
```php
[
    'user_id'        => string,  // Required
    'bot_id'         => string,  // Required
    'workspace_name' => string,  // Required
    'workspace_icon' => ?string, // Optional
    'owner_type'     => string,  // Required
]
```

---

### `list_pages(int $limit = 10): array`

Retrieves a list of pages accessible to the integration.

**Parameters:**
- `$limit` (int, optional): Maximum number of pages to return. Default: `10`, Max: `100`

**Returns:** Array of page objects:

```php
[
    [
        'id' => 'abc123...',
        'title' => 'Page Title',
        'url' => 'https://notion.so/Page-Title-abc123',
        'last_edited_time' => '2025-10-19T12:00:00.000Z',
        'object' => 'page',
    ],
    // ... more pages
]
```

**Notion API Endpoint:** `POST /v1/search`

**Error Handling:**
- Returns empty array on error
- Filters out non-page results (databases, etc.)
- Handles pagination (limited to $limit items)

**Example:**
```php
$client = new NotionClient($token);
$pages = $client->list_pages(20);

foreach ($pages as $page) {
    echo esc_html($page['title']) . '<br>';
    echo 'Last edited: ' . esc_html($page['last_edited_time']) . '<br>';
}
```

**Advanced Example with Error Checking:**
```php
$client = new NotionClient($token);
$pages = $client->list_pages();

if (empty($pages)) {
    echo 'No pages found. Make sure you\'ve shared pages with your integration.';
} else {
    echo 'Found ' . count($pages) . ' pages:';
    foreach ($pages as $page) {
        printf(
            '<li><a href="%s" target="_blank">%s</a></li>',
            esc_url($page['url']),
            esc_html($page['title'])
        );
    }
}
```

**Use Cases:**
- Displaying available pages in admin UI
- Verifying pages are accessible
- Selecting pages to sync

**Response Schema:**
```php
[
    'id'               => string,  // Notion page ID
    'title'            => string,  // Page title (or "Untitled")
    'url'              => string,  // Public Notion URL
    'last_edited_time' => string,  // ISO 8601 timestamp
    'object'           => 'page',  // Always 'page'
]
```

**Notes:**
- Only returns pages explicitly shared with integration
- Does NOT return child pages unless parent is shared
- Title extraction handles empty titles as "Untitled"
- Results are NOT paginated beyond $limit (Phase 0)

---

## Private Methods

### `request(string $method, string $endpoint, array $body = []): array`

Internal method for making HTTP requests to Notion API.

**Parameters:**
- `$method` (string): HTTP method (`GET`, `POST`, `PATCH`, `DELETE`)
- `$endpoint` (string): API endpoint (e.g., `/users/me`, `/search`)
- `$body` (array, optional): Request body (auto-encoded to JSON)

**Returns:** Decoded JSON response as associative array

**Error Handling:**
- Returns empty array on network errors
- Returns empty array on invalid JSON
- Logs errors to WordPress debug log if `WP_DEBUG` is enabled

**Example (internal use only):**
```php
$response = $this->request('POST', '/search', [
    'query' => 'My Page',
    'filter' => ['property' => 'object', 'value' => 'page'],
]);
```

**Headers Sent:**
```php
[
    'Authorization'  => 'Bearer secret_xxx...',
    'Notion-Version' => '2022-06-28',
    'Content-Type'   => 'application/json',
]
```

**This method is not meant for external use.** Use the public methods instead.

---

## Error Handling

The `NotionClient` class uses a graceful error handling approach:

### Error Philosophy

- **No exceptions thrown:** Methods return false/empty arrays on error
- **Silent failures:** Errors logged but not thrown (WordPress convention)
- **Type safety:** Always returns expected types, never `null`

### Common Error Scenarios

#### 1. Invalid Token

**Symptom:** `test_connection()` returns `false`

**Notion Response:**
```json
{
    "object": "error",
    "status": 401,
    "code": "unauthorized",
    "message": "API token is invalid."
}
```

**How to Handle:**
```php
if (!$client->test_connection()) {
    wp_die(
        esc_html__('Invalid Notion token. Please check your token and try again.', 'notion-wp'),
        esc_html__('Connection Error', 'notion-wp')
    );
}
```

#### 2. Network Error

**Symptom:** Methods return empty arrays

**Causes:**
- No internet connection
- Firewall blocking api.notion.com
- Hosting provider blocking outbound requests

**How to Handle:**
```php
$info = $client->get_workspace_info();

if (empty($info)) {
    add_settings_error(
        'notion_wp_settings',
        'network_error',
        __('Could not connect to Notion. Check your internet connection.', 'notion-wp')
    );
}
```

#### 3. Rate Limiting

**Symptom:** Slow responses or empty arrays

**Notion Limit:** ~50 requests per second per integration

**How to Handle:**
```php
// Add caching to reduce API calls
$cached_pages = get_transient('notion_wp_pages');

if (false === $cached_pages) {
    $cached_pages = $client->list_pages();
    set_transient('notion_wp_pages', $cached_pages, HOUR_IN_SECONDS);
}
```

#### 4. Page Not Shared

**Symptom:** `list_pages()` returns empty array despite pages existing

**Cause:** Pages not shared with integration

**How to Handle:**
```php
$pages = $client->list_pages();

if (empty($pages)) {
    echo '<p>';
    echo esc_html__('No pages found. ', 'notion-wp');
    echo '<a href="https://notion.so" target="_blank">';
    echo esc_html__('Share pages with your integration in Notion', 'notion-wp');
    echo '</a>';
    echo '</p>';
}
```

### Debug Logging

Enable debug logging to troubleshoot issues:

```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);

// Errors will be logged to wp-content/debug.log
```

---

## Usage Examples

### Example 1: Complete Connection Flow

```php
use NotionWP\API\NotionClient;

// User submits token via form
$token = sanitize_text_field($_POST['notion_token']);

// Create client
$client = new NotionClient($token);

// Test connection
if (!$client->test_connection()) {
    wp_die('Invalid token');
}

// Get workspace info
$info = $client->get_workspace_info();

if (empty($info)) {
    wp_die('Could not retrieve workspace info');
}

// Save token
update_option('notion_wp_token', $token);

// Display success
printf(
    'Connected to workspace: %s',
    esc_html($info['workspace_name'])
);
```

### Example 2: Display Pages in Admin

```php
use NotionWP\API\NotionClient;

$token = get_option('notion_wp_token');

if (!$token) {
    echo '<p>Please connect to Notion first.</p>';
    return;
}

$client = new NotionClient($token);
$pages = $client->list_pages(50);

if (empty($pages)) {
    echo '<p>No pages available. Share pages with your integration in Notion.</p>';
    return;
}

echo '<ul class="notion-pages-list">';
foreach ($pages as $page) {
    printf(
        '<li><strong>%s</strong> <a href="%s" target="_blank">View in Notion</a></li>',
        esc_html($page['title']),
        esc_url($page['url'])
    );
}
echo '</ul>';
```

### Example 3: Caching for Performance

```php
use NotionWP\API\NotionClient;

function get_cached_notion_pages(int $limit = 10): array {
    $cache_key = 'notion_wp_pages_' . $limit;
    $cached = get_transient($cache_key);

    if (false !== $cached) {
        return $cached;
    }

    $token = get_option('notion_wp_token');
    if (!$token) {
        return [];
    }

    $client = new NotionClient($token);
    $pages = $client->list_pages($limit);

    // Cache for 1 hour
    set_transient($cache_key, $pages, HOUR_IN_SECONDS);

    return $pages;
}
```

### Example 4: Admin Settings Page

```php
use NotionWP\API\NotionClient;

class SettingsPage {
    public function handle_connect(): void {
        // Verify nonce
        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'notion_connect')) {
            wp_die('Security check failed');
        }

        // Verify capability
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }

        // Sanitize token
        $token = sanitize_text_field($_POST['notion_token']);

        if (empty($token)) {
            $this->redirect_with_error('Token is required');
            return;
        }

        // Test connection
        $client = new NotionClient($token);

        if (!$client->test_connection()) {
            $this->redirect_with_error('Invalid token. Please check and try again.');
            return;
        }

        // Get workspace info
        $info = $client->get_workspace_info();

        if (empty($info)) {
            $this->redirect_with_error('Could not retrieve workspace information');
            return;
        }

        // Save token and info
        update_option('notion_wp_token', $token);
        update_option('notion_wp_workspace_name', $info['workspace_name']);
        update_option('notion_wp_user_id', $info['user_id']);

        // Redirect with success
        $this->redirect_with_success(
            sprintf('Connected to %s successfully!', $info['workspace_name'])
        );
    }

    private function redirect_with_error(string $message): void {
        add_settings_error('notion_wp_settings', 'connection_error', $message);
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('admin.php?page=notion-sync'));
        exit;
    }

    private function redirect_with_success(string $message): void {
        add_settings_error('notion_wp_settings', 'connection_success', $message, 'success');
        set_transient('settings_errors', get_settings_errors(), 30);
        wp_redirect(admin_url('admin.php?page=notion-sync'));
        exit;
    }
}
```

---

## Extending the Client

### Adding New Methods (Future Phases)

To add support for additional Notion API endpoints:

```php
// plugin/src/API/NotionClient.php

/**
 * Retrieves a specific page by ID.
 *
 * @param string $page_id Notion page ID.
 * @return array Page data or empty array on error.
 */
public function get_page(string $page_id): array {
    return $this->request('GET', "/pages/{$page_id}");
}

/**
 * Retrieves blocks for a page.
 *
 * @param string $page_id Notion page ID.
 * @return array Array of blocks or empty array on error.
 */
public function get_page_blocks(string $page_id): array {
    $response = $this->request('GET', "/blocks/{$page_id}/children");
    return $response['results'] ?? [];
}
```

### Customizing via Filters

Allow customization through WordPress filters:

```php
// In request() method
$timeout = apply_filters('notion_wp_api_timeout', 30);
$headers = apply_filters('notion_wp_api_headers', $default_headers);

// Usage by plugin developers
add_filter('notion_wp_api_timeout', function($timeout) {
    return 60; // Increase timeout to 60 seconds
});
```

### Creating a Mock Client for Testing

```php
namespace NotionWP\Tests\Mocks;

class MockNotionClient extends \NotionWP\API\NotionClient {
    private array $mock_responses = [];

    public function set_mock_response(string $method, string $endpoint, array $response): void {
        $this->mock_responses[$method][$endpoint] = $response;
    }

    protected function request(string $method, string $endpoint, array $body = []): array {
        return $this->mock_responses[$method][$endpoint] ?? [];
    }
}

// Usage in tests
$client = new MockNotionClient('fake-token');
$client->set_mock_response('GET', '/users/me', [
    'workspace_name' => 'Test Workspace',
]);
```

---

## API Versioning

**Current Notion API Version:** `2022-06-28`

The Notion API version is hardcoded in the client and sent with every request via the `Notion-Version` header.

### Updating API Version

To use a newer Notion API version:

1. Update `$api_version` property in `NotionClient`
2. Review Notion API changelog for breaking changes
3. Update method implementations if needed
4. Test all endpoints thoroughly
5. Update documentation

---

## Rate Limiting

**Notion API Limits:**
- ~50 requests per second per integration
- Burst allowance for short spikes
- 429 status code when exceeded

**Mitigation Strategies:**

1. **Caching:** Use WordPress transients
2. **Batching:** Combine requests where possible
3. **Exponential backoff:** Retry with increasing delays
4. **User feedback:** Show progress for long operations

**Example with retry logic:**
```php
private function request_with_retry(string $method, string $endpoint, array $body = [], int $max_retries = 3): array {
    $attempt = 0;

    while ($attempt < $max_retries) {
        $response = $this->request($method, $endpoint, $body);

        if (!empty($response)) {
            return $response;
        }

        $attempt++;
        sleep(pow(2, $attempt)); // Exponential backoff: 2s, 4s, 8s
    }

    return [];
}
```

---

## Security Considerations

### Token Storage

- **Never** hardcode tokens in code
- **Never** commit tokens to version control
- Store in WordPress options table (encrypted if possible)
- Use environment variables for development

### Token Transmission

- Always use HTTPS (enforced by Notion API)
- Never send token in URL parameters
- Include in `Authorization` header only

### Token Display

- Never display saved tokens in UI
- Use password-type inputs when entering
- Clear from memory after use if possible

### Permission Scope

- Integration can only access shared pages
- No broader workspace access
- Users control sharing explicitly

---

## Related Documentation

- [Phase 0 Plan](/docs/plans/phase-0.md)
- [Development Principles](/docs/development/principles.md)
- [Contributing Guide](/CONTRIBUTING.md)
- [Notion API Reference](https://developers.notion.com/reference)

---

## Changelog

### v0.1-dev (Phase 0)
- Initial implementation
- `test_connection()` method
- `get_workspace_info()` method
- `list_pages()` method
- WordPress HTTP API integration

### Future Versions
- Pagination support for large result sets
- Webhook handling
- Block content retrieval
- Page creation/update methods
- Database query support

---

**Last Updated:** 2025-10-19
**Maintainer:** The VGER Group
**Status:** Active Development (Phase 0)
