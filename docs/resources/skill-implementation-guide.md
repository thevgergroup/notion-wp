# Skill Implementation Guide for Notion-WordPress Plugin

This guide provides practical instructions for creating and using the specialized skills needed for the notion-wp project.

## Table of Contents

1. [Overview](#overview)
2. [Creating Skills with skill-creator](#creating-skills-with-skill-creator)
3. [Skill Templates](#skill-templates)
4. [Using Slash Commands](#using-slash-commands)
5. [Leveraging MCP Servers](#leveraging-mcp-servers)
6. [Skill Usage Examples](#skill-usage-examples)

---

## Overview

There are three main approaches to implementing specialized expertise for this project:

### 1. Custom Skills (Recommended)

Create reusable skills using the `skill-creator` skill that can be invoked with the `Skill` tool.

**Pros:**

- Reusable across sessions
- Encapsulated expertise
- Easy to share with team

**Cons:**

- Initial setup time
- Requires planning skill structure

### 2. Slash Commands

Create `.claude/commands/*.md` files for project-specific workflows.

**Pros:**

- Quick to create
- Project-specific
- Version controlled

**Cons:**

- Less portable
- Simpler than full skills

### 3. MCP Servers

Build custom MCP servers for WordPress or Notion-specific tools.

**Pros:**

- Provides actual tools, not just prompts
- Can integrate with external services
- Very powerful

**Cons:**

- Requires Node.js/Python development
- More complex setup

---

## Creating Skills with skill-creator

### Step 1: Invoke skill-creator

```bash
# Use the Skill tool
Skill: "skill-creator"
```

### Step 2: Provide Skill Details

When prompted, provide:

1. **Skill Name**: Short, descriptive name (e.g., `wordpress-plugin-dev`)
2. **Description**: What the skill does and when to use it
3. **Expertise Areas**: List specific knowledge domains
4. **Tools Available**: What tools the skill can use
5. **Example Prompts**: Sample invocations

### Step 3: Test the Skill

```bash
# Invoke your new skill
Skill: "wordpress-plugin-dev"

# Then provide a task
"Set up the initial plugin structure for notion-wp"
```

---

## Skill Templates

### Template 1: WordPress Plugin Engineer Skill

````markdown
# WordPress Plugin Engineer Skill

## Description

Expert WordPress plugin developer specializing in WordPress plugin architecture, hooks system, Gutenberg blocks, and WordPress coding standards.

## When to Use

- Setting up plugin structure
- Implementing WordPress hooks
- Creating admin pages
- Developing Gutenberg blocks
- Ensuring WordPress coding standards

## Expertise Areas

- WordPress plugin architecture
- Hooks and filters system
- WordPress coding standards (WPCS)
- Settings API
- Gutenberg block development
- WordPress REST API
- Plugin security (nonces, capabilities)

## Available Tools

- Read, Write, Edit for file operations
- Bash for running wp scaffold and composer commands
- Grep/Glob for searching WordPress codebases
- context7 for fetching WordPress documentation

## Example Prompts

### Plugin Setup

"Create the initial WordPress plugin structure for notion-wp with:

- Main plugin file with proper headers
- Directory structure (includes, admin, public)
- Composer.json with PSR-4 autoloading
- Basic activation/deactivation hooks"

### Settings Page

"Create a WordPress admin settings page for storing the Notion API token with:

- Settings API implementation
- Proper sanitization
- Nonce verification
- Capability checks"

### Custom Gutenberg Block

"Create a custom Gutenberg block for displaying Notion sync status with:

- Block registration
- Block editor script
- Block save function
- Server-side rendering"

## Best Practices

1. Follow WordPress Coding Standards (WPCS)
2. Always sanitize inputs and escape outputs
3. Use nonces for form submissions
4. Check user capabilities
5. Use WordPress APIs (don't reinvent the wheel)
6. Prefix all functions and classes
7. Use proper text domain for translations
8. Document code with phpDoc comments

## Common Patterns

### Plugin Main File Header

```php
<?php
/**
 * Plugin Name: Notion-WordPress Sync
 * Plugin URI: https://example.com/notion-wp
 * Description: Bi-directional sync between Notion and WordPress
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: notion-wp
 * Domain Path: /languages
 */
```
````

### Activation Hook

```php
register_activation_hook( __FILE__, 'notion_wp_activate' );
function notion_wp_activate() {
    // Set default options
    add_option( 'notion_wp_version', '1.0.0' );

    // Create custom database tables if needed
    // flush_rewrite_rules() if needed
}
```

### Settings API

```php
add_action( 'admin_init', 'notion_wp_register_settings' );
function notion_wp_register_settings() {
    register_setting(
        'notion_wp_options',
        'notion_wp_api_token',
        [
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ]
    );
}
```

## Resources to Use

- context7 MCP: Fetch WordPress Codex and Developer Handbook
- WordPress.org Developer Resources
- WordPress Coding Standards (WPCS)
- WordPress Plugin Handbook

````

### Template 2: Notion API Specialist Skill

```markdown
# Notion API Specialist Skill

## Description
Expert in Notion API integration, including all endpoints, block types, database queries, pagination, rate limiting, and webhook configuration.

## When to Use
- Implementing Notion API client
- Parsing Notion responses
- Handling pagination and rate limits
- Setting up webhooks
- Understanding Notion block structure

## Expertise Areas
- All Notion API endpoints
- Notion block types (70+ types)
- Database queries and filtering
- API authentication and permissions
- Rate limiting (~50 req/sec)
- Pagination (100 items per request)
- Webhook events and verification

## Available Tools
- Read, Write, Edit for code implementation
- WebFetch for accessing Notion API docs
- context7 for latest Notion API documentation
- Bash for testing API calls with curl

## Example Prompts

### API Client
"Create a Notion API client class with:
- Authentication token handling
- Pagination support
- Rate limiting
- Error handling
- Methods for retrieve_block_children, query_database, retrieve_page"

### Block Parsing
"Parse this Notion API block response and extract:
- Block type
- Text content and annotations
- Child blocks
- Media URLs
- Metadata"

### Database Query
"Create a function to query a Notion database with:
- Filtering by properties
- Sorting
- Pagination handling
- Return all results (handling 100 item limit)"

## Common Block Types

### Paragraph Block
```json
{
  "type": "paragraph",
  "paragraph": {
    "rich_text": [
      {
        "type": "text",
        "text": { "content": "Hello world" },
        "annotations": {
          "bold": false,
          "italic": false,
          "strikethrough": false,
          "underline": false,
          "code": false,
          "color": "default"
        }
      }
    ]
  }
}
````

### Image Block

```json
{
  "type": "image",
  "image": {
    "type": "file",
    "file": {
      "url": "https://s3.amazonaws.com/...",
      "expiry_time": "2024-01-01T00:00:00.000Z"
    },
    "caption": [...]
  }
}
```

## API Rate Limiting

- Notion API: ~50 requests per second
- Implement exponential backoff
- Use retry-after header
- Queue requests if needed

## Best Practices

1. Cache API responses when possible
2. Handle pagination properly (100 items max)
3. Respect rate limits
4. Implement retry logic with exponential backoff
5. Store block IDs for mapping
6. Handle expiring S3 URLs for images
7. Use database queries efficiently
8. Verify webhook signatures

## Resources

- context7: Fetch latest Notion API docs
- Notion Developer Portal: https://developers.notion.com
- Notion API reference: https://developers.notion.com/reference

````

### Template 3: Block Converter Specialist Skill

```markdown
# Block Converter Specialist Skill

## Description
Expert in converting between Notion block format and WordPress Gutenberg blocks or HTML, handling 70+ Notion block types with proper structure and formatting preservation.

## When to Use
- Designing block conversion architecture
- Implementing converters for specific block types
- Handling unsupported blocks
- Creating reverse converters (WP → Notion)
- Testing conversion accuracy

## Expertise Areas
- All Notion block types and structure
- WordPress Gutenberg block format
- HTML semantic structure
- Rich text annotations
- Nested block hierarchies
- Special blocks (callouts, toggles, databases)

## Available Tools
- Read, Write, Edit for converter implementation
- context7 for Gutenberg block documentation
- Bash for testing conversions

## Example Prompts

### Converter Architecture
"Design a block converter architecture with:
- Base converter interface
- Individual converters for each block type
- Converter registry
- Extensibility hooks for custom converters
- Fallback handler for unsupported blocks"

### Specific Block Conversion
"Implement a converter for Notion callout blocks to WordPress with:
- Preserve icon and color
- Convert to styled div or custom Gutenberg block
- Handle nested content
- Map Notion colors to WordPress/CSS equivalents"

### Rich Text Conversion
"Convert Notion rich text with annotations to:
- HTML with proper tags (strong, em, code, etc.)
- WordPress paragraph block with formatting
- Preserve links and colors"

## Block Type Mappings

| Notion Block | WordPress Equivalent |
|--------------|---------------------|
| paragraph | core/paragraph |
| heading_1 | core/heading (level 1) |
| heading_2 | core/heading (level 2) |
| heading_3 | core/heading (level 3) |
| bulleted_list_item | core/list (unordered) |
| numbered_list_item | core/list (ordered) |
| quote | core/quote |
| code | core/code |
| image | core/image |
| video | core/video |
| file | core/file |
| embed | core/embed |
| callout | custom block or styled div |
| toggle | core/details or custom block |
| to_do | custom block or checkbox list |
| table | core/table |
| column_list | core/columns |
| divider | core/separator |

## Conversion Challenges

### Callouts
Notion has callouts with icons and colors. WordPress doesn't have a core equivalent.

**Options:**
1. Create custom Gutenberg block
2. Use styled div with classes
3. Use core/quote with custom styling

### Toggles
Notion toggles can contain any content. WordPress has limited support.

**Options:**
1. Use HTML details/summary elements
2. Create custom Gutenberg block
3. Convert to heading with nested content

### To-Do Checkboxes
Notion to-dos are interactive. WordPress doesn't have core support.

**Options:**
1. Create custom Gutenberg block
2. Use list with checkbox HTML
3. Use paragraph with checkbox character

## Best Practices
1. Preserve semantic meaning when possible
2. Gracefully degrade unsupported features
3. Provide hooks for custom converters
4. Log conversion warnings/errors
5. Test with complex nested structures
6. Handle malformed/invalid blocks
7. Preserve as much styling as possible
8. Consider round-trip conversion (WP → Notion → WP)

## Resources
- Gutenberg Block API: https://developer.wordpress.org/block-editor/
- Notion block types: https://developers.notion.com/reference/block
- HTML semantic elements: https://developer.mozilla.org/en-US/docs/Web/HTML/Element
````

---

## Using Slash Commands

Slash commands are simpler than full skills and great for project-specific workflows.

### Creating a Slash Command

1. **Create the directory:**

```bash
mkdir -p .claude/commands
```

2. **Create a command file:**

```bash
# .claude/commands/setup-plugin.md
You are a WordPress plugin development expert. When invoked, help set up the WordPress plugin structure for notion-wp.

## Steps:
1. Create main plugin file with proper headers
2. Set up directory structure (includes/, admin/, public/)
3. Create composer.json with PSR-4 autoloading
4. Add basic activation/deactivation hooks
5. Create README.md with setup instructions

Follow WordPress Coding Standards (WPCS) and use proper sanitization and escaping.
```

3. **Use the command:**

```bash
/setup-plugin
```

### Example Slash Commands for This Project

#### .claude/commands/notion-client.md

```markdown
You are a Notion API specialist. Help implement or improve the Notion API client for the notion-wp plugin.

## Tasks:

- Implement Notion API endpoints (retrieve_block_children, query_database, etc.)
- Handle pagination (100 items per request)
- Implement rate limiting (~50 requests/second)
- Add retry logic with exponential backoff
- Create response caching
- Handle authentication with Notion token

Use context7 to fetch latest Notion API documentation if needed.
```

#### .claude/commands/test-converter.md

```markdown
You are a block conversion testing expert. Help test block conversions between Notion and WordPress.

## Tasks:

1. Review the block converter code
2. Identify edge cases and potential issues
3. Create test fixtures with various Notion block types
4. Write PHPUnit tests for each converter
5. Test nested structures and complex scenarios
6. Verify round-trip conversion accuracy

Focus on ensuring no data loss during conversion.
```

---

## Leveraging MCP Servers

### Using Existing MCP Servers

#### context7 for Documentation

```bash
# Get WordPress documentation
mcp__context7__resolve-library-id "WordPress"
mcp__context7__get-library-docs "/wordpress/docs" "Gutenberg blocks"

# Get Notion API documentation
mcp__context7__resolve-library-id "Notion API"
mcp__context7__get-library-docs "/notion/api" "block types"
```

#### Playwright for Testing

```bash
# Test WordPress admin UI
mcp__playwright-mcp__browser_navigate "http://localhost:8080/wp-admin"
mcp__playwright-mcp__browser_snapshot
mcp__playwright-mcp__browser_click "Settings menu"
```

### Creating Custom MCP Server

Use the `mcp-builder` skill to create a WordPress-specific MCP server:

```bash
# Invoke mcp-builder skill
Skill: "mcp-builder"

# Then describe what you want:
"Create an MCP server with tools for:
- Scaffolding WordPress plugin files
- Running WP-CLI commands
- Validating WordPress plugin headers
- Checking coding standards with phpcs
- Running PHPUnit tests"
```

---

## Skill Usage Examples

### Example 1: Setting Up Plugin Structure

```bash
# Invoke the WordPress Plugin Engineer skill
Skill: "wordpress-plugin-dev"

# Provide the task
"Set up the initial WordPress plugin structure for notion-wp with:
- Main plugin file (notion-wp.php) with proper headers
- Directory structure:
  - includes/ (core classes)
  - admin/ (admin interface)
  - public/ (public-facing functionality)
  - languages/ (translations)
- composer.json with PSR-4 autoloading for NotionWP namespace
- Basic activation hook to set plugin version
- Basic deactivation hook for cleanup"
```

### Example 2: Implementing Notion API Client

```bash
# Invoke the Notion API Specialist skill
Skill: "notion-api-expert"

# Provide the task
"Create a NotionWP\API\Client class with:
- Constructor accepting API token
- Method: get_page_blocks($page_id) - retrieve all blocks with pagination
- Method: get_database_entries($database_id, $filters = [])
- Method: create_page($parent_id, $properties, $blocks)
- Built-in rate limiting (50 req/sec)
- Retry logic with exponential backoff
- Response caching using WordPress transients
- Comprehensive error handling"
```

### Example 3: Converting Blocks

```bash
# Invoke the Block Converter Specialist skill
Skill: "block-mapping-expert"

# Provide the task
"Design and implement the block converter architecture:

1. Create NotionWP\Converters\BaseConverter interface with:
   - convert(array $notion_block): array (WordPress block)
   - supports(string $block_type): bool

2. Create converters for these Notion block types:
   - Paragraph
   - Headings (1, 2, 3)
   - Lists (bulleted, numbered)
   - Image (with caption and alt text)
   - Quote
   - Code (with language support)

3. Create NotionWP\Converters\Registry to:
   - Register converters
   - Route blocks to appropriate converter
   - Provide fallback for unsupported blocks
   - Allow developers to register custom converters via filter

4. Handle nested blocks recursively

5. Add detailed logging for conversion process"
```

### Example 4: Building Admin Interface

```bash
# Invoke the WordPress Admin UI Designer skill
Skill: "wordpress-admin-ui-expert"

# Provide the task
"Create the Notion-WP admin settings page:

1. Main settings page under Settings menu with:
   - Notion API token field (password input)
   - Connection test button
   - Sync strategy dropdown (Add Only, Add & Update, Full Mirror)
   - Sync interval for WP-Cron
   - Enable/disable webhook option

2. Sync dashboard page with:
   - List of synced Notion pages/databases
   - Last sync timestamp for each
   - Sync status (success, failed, in progress)
   - Manual sync trigger button per item
   - Bulk sync all button
   - View sync log button

3. Field mapping page with:
   - Select Notion database dropdown
   - Map Notion properties to WordPress fields
   - Custom post type selection
   - Category/tag mapping
   - Save mapping button

Follow WordPress admin UI guidelines and use proper admin notices for feedback."
```

### Example 5: Writing Tests

```bash
# Invoke the WordPress Testing Engineer skill
Skill: "wordpress-testing-expert"

# Provide the task
"Set up comprehensive testing for notion-wp:

1. PHPUnit configuration:
   - Install WordPress test library
   - Create phpunit.xml
   - Set up test database

2. Unit tests for:
   - Each block converter
   - Notion API client methods
   - Field mapping logic
   - Image download and import

3. Integration tests for:
   - Complete sync flow
   - Database query handling
   - Media library import
   - Menu generation

4. Test fixtures:
   - Sample Notion API responses for various block types
   - WordPress test posts
   - Mock Notion databases

5. Achieve 80%+ code coverage

6. Set up test running in CI/CD (GitHub Actions)"
```

---

## Combining Multiple Skills

For complex tasks, use multiple skills in sequence:

```bash
# Step 1: Architecture
Skill: "wordpress-architect"
"Design the overall sync engine architecture with extensibility points"

# Step 2: Notion Integration
Skill: "notion-api-expert"
"Implement the Notion data fetching based on the architecture"

# Step 3: Block Conversion
Skill: "block-mapping-expert"
"Implement block converters following the architecture"

# Step 4: Security Review
Skill: "wordpress-security-expert"
"Review all code for security issues and add necessary sanitization"

# Step 5: Testing
Skill: "wordpress-testing-expert"
"Create comprehensive tests for the sync engine"
```

---

## Best Practices

### 1. Start with Architecture Skills

Before implementing, use architect skills to plan structure.

### 2. Use Specific, Detailed Prompts

Instead of "set up the plugin", provide detailed requirements.

### 3. Leverage context7 for Current Docs

Always fetch latest WordPress and Notion API documentation.

### 4. Combine Skills for Complex Tasks

Use architect → specialist → testing workflow.

### 5. Create Project-Specific Slash Commands

For repeated workflows, create slash commands.

### 6. Build MCP Servers for Tools

If you need specific tools (e.g., WP-CLI wrapper), create MCP server.

---

## Next Steps

1. **Create Priority Skills First:**
    - WordPress Plugin Engineer (`wordpress-plugin-dev`)
    - Notion API Specialist (`notion-api-expert`)
    - WordPress Architect (`wordpress-architect`)

2. **Set Up Project Structure:**
    - Use `wordpress-plugin-dev` skill to scaffold plugin
    - Set up development environment (Docker/wp-env)

3. **Implement Core Features:**
    - Use `notion-api-expert` for API client
    - Use `block-mapping-expert` for converters
    - Use `wordpress-security-expert` for security review

4. **Create Slash Commands for Common Workflows:**
    - Plugin setup
    - Running tests
    - Code standard checks
    - Deployment

5. **Consider Building MCP Server:**
    - WordPress-specific tools
    - Notion API testing tools
    - Automated testing helpers

---

## Troubleshooting

### Skill Not Found

- Verify skill was created successfully
- Check skill name spelling
- Try recreating the skill

### Skill Doesn't Have Expected Knowledge

- Provide more detailed description when creating skill
- Include specific resources and documentation
- Add more example prompts

### Need More Specialized Skills

- Break down into more granular skills
- Create sub-skills for specific frameworks or APIs
- Use skill-creator to generate variations

---

## Conclusion

By creating these specialized skills and using them strategically, you can significantly accelerate development of the notion-wp plugin while maintaining high code quality and WordPress best practices.

Start with the foundational skills (WordPress Plugin Engineer, Notion API Specialist, WordPress Architect) and build from there as the project evolves.
