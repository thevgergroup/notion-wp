# Skills Quick Reference Matrix

A quick lookup guide for which specialized agent/skill to use for specific tasks in the notion-wp project.

## Task-to-Skill Mapping

| Task                                | Primary Skill               | Secondary Skill             | Skill Name                   |
| ----------------------------------- | --------------------------- | --------------------------- | ---------------------------- |
| **Initial Setup**                   |
| Create plugin folder structure      | WordPress Plugin Engineer   | WordPress Architect         | `wordpress-plugin-dev`       |
| Set up composer.json                | PHP Backend Developer       | WordPress Plugin Engineer   | `php-backend-dev`            |
| Configure autoloading (PSR-4)       | PHP Backend Developer       | -                           | `php-backend-dev`            |
| Set up development environment      | DevOps Engineer             | -                           | `wordpress-devops`           |
| Create Docker/wp-env setup          | DevOps Engineer             | -                           | `wordpress-devops`           |
| **Authentication & Security**       |
| Implement token storage             | Security Specialist         | WordPress Plugin Engineer   | `wordpress-security-expert`  |
| Create settings encryption          | Security Specialist         | -                           | `wordpress-security-expert`  |
| Add capability checks               | Security Specialist         | WordPress Plugin Engineer   | `wordpress-security-expert`  |
| Sanitize user inputs                | Security Specialist         | -                           | `wordpress-security-expert`  |
| Implement nonce verification        | Security Specialist         | WordPress Plugin Engineer   | `wordpress-security-expert`  |
| **Notion API Integration**          |
| Create Notion API client            | Notion API Specialist       | API Integration Engineer    | `notion-api-expert`          |
| Handle pagination                   | Notion API Specialist       | PHP Backend Developer       | `notion-api-expert`          |
| Implement rate limiting             | API Integration Engineer    | Notion API Specialist       | `api-integration-expert`     |
| Set up webhooks                     | API Integration Engineer    | Notion API Specialist       | `api-integration-expert`     |
| Parse Notion block responses        | Notion API Specialist       | Block Converter Specialist  | `notion-api-expert`          |
| Handle API errors/retries           | API Integration Engineer    | PHP Backend Developer       | `api-integration-expert`     |
| **Block Conversion**                |
| Design block converter architecture | Block Converter Specialist  | WordPress Architect         | `block-mapping-expert`       |
| Convert paragraphs/headings         | Block Converter Specialist  | -                           | `block-mapping-expert`       |
| Convert lists (ordered/unordered)   | Block Converter Specialist  | -                           | `block-mapping-expert`       |
| Convert images                      | Block Converter Specialist  | Media Processing Specialist | `block-mapping-expert`       |
| Convert embeds (YouTube, etc.)      | Block Converter Specialist  | -                           | `block-mapping-expert`       |
| Convert callouts                    | Block Converter Specialist  | -                           | `block-mapping-expert`       |
| Convert toggles                     | Block Converter Specialist  | WordPress Plugin Engineer   | `block-mapping-expert`       |
| Convert code blocks                 | Block Converter Specialist  | -                           | `block-mapping-expert`       |
| Convert tables                      | Block Converter Specialist  | -                           | `block-mapping-expert`       |
| Convert to-do checkboxes            | Block Converter Specialist  | -                           | `block-mapping-expert`       |
| Convert columns                     | Block Converter Specialist  | WordPress Plugin Engineer   | `block-mapping-expert`       |
| Handle unsupported blocks           | Block Converter Specialist  | WordPress Plugin Engineer   | `block-mapping-expert`       |
| Create Gutenberg blocks             | WordPress Plugin Engineer   | Block Converter Specialist  | `wordpress-plugin-dev`       |
| **Media Handling**                  |
| Download images from Notion         | Media Processing Specialist | PHP Backend Developer       | `wordpress-media-expert`     |
| Upload to Media Library             | Media Processing Specialist | WordPress Plugin Engineer   | `wordpress-media-expert`     |
| Detect duplicate images             | Media Processing Specialist | Database Designer           | `wordpress-media-expert`     |
| Handle image metadata               | Media Processing Specialist | -                           | `wordpress-media-expert`     |
| Process PDFs/documents              | Media Processing Specialist | -                           | `wordpress-media-expert`     |
| Optimize images                     | Media Processing Specialist | -                           | `wordpress-media-expert`     |
| **Database & Data Structures**      |
| Design meta field schema            | Database Designer           | WordPress Architect         | `wordpress-database-expert`  |
| Create custom post types            | WordPress Plugin Engineer   | Database Designer           | `wordpress-plugin-dev`       |
| Implement field mapping storage     | Database Designer           | -                           | `wordpress-database-expert`  |
| Store Notion page IDs               | Database Designer           | WordPress Plugin Engineer   | `wordpress-database-expert`  |
| Design relationship mappings        | Database Designer           | -                           | `wordpress-database-expert`  |
| Optimize database queries           | Database Designer           | WordPress Architect         | `wordpress-database-expert`  |
| **Sync Engine**                     |
| Design sync architecture            | WordPress Architect         | PHP Backend Developer       | `wordpress-architect`        |
| Implement manual sync               | PHP Backend Developer       | WordPress Plugin Engineer   | `php-backend-dev`            |
| Set up WP-Cron scheduling           | WordPress Plugin Engineer   | PHP Backend Developer       | `wordpress-plugin-dev`       |
| Create background processing        | PHP Backend Developer       | WordPress Architect         | `php-backend-dev`            |
| Build queue system                  | PHP Backend Developer       | -                           | `php-backend-dev`            |
| Implement dry-run mode              | PHP Backend Developer       | WordPress Plugin Engineer   | `php-backend-dev`            |
| Handle conflict resolution          | PHP Backend Developer       | WordPress Architect         | `php-backend-dev`            |
| Track sync status                   | Database Designer           | PHP Backend Developer       | `wordpress-database-expert`  |
| **Navigation & Links**              |
| Convert internal Notion links       | Block Converter Specialist  | Database Designer           | `block-mapping-expert`       |
| Generate WordPress menus            | WordPress Plugin Engineer   | -                           | `wordpress-plugin-dev`       |
| Map parent/child relationships      | Database Designer           | WordPress Plugin Engineer   | `wordpress-database-expert`  |
| Create permalink mappings           | WordPress Plugin Engineer   | Database Designer           | `wordpress-plugin-dev`       |
| **Admin Interface**                 |
| Design settings page                | WordPress Admin UI Designer | UX Researcher               | `wordpress-admin-ui-expert`  |
| Build field mapping UI              | WordPress Admin UI Designer | -                           | `wordpress-admin-ui-expert`  |
| Create sync dashboard               | WordPress Admin UI Designer | -                           | `wordpress-admin-ui-expert`  |
| Implement sync buttons              | WordPress Admin UI Designer | WordPress Plugin Engineer   | `wordpress-admin-ui-expert`  |
| Show sync progress                  | WordPress Admin UI Designer | -                           | `wordpress-admin-ui-expert`  |
| Display error messages              | WordPress Admin UI Designer | UX Researcher               | `wordpress-admin-ui-expert`  |
| Create preview interface            | WordPress Admin UI Designer | -                           | `wordpress-admin-ui-expert`  |
| **WP-CLI Commands**                 |
| Create sync command                 | WordPress CLI Expert        | PHP Backend Developer       | `wp-cli-expert`              |
| Add dry-run flag                    | WordPress CLI Expert        | -                           | `wp-cli-expert`              |
| Implement bulk operations           | WordPress CLI Expert        | PHP Backend Developer       | `wp-cli-expert`              |
| Create diagnostic commands          | WordPress CLI Expert        | -                           | `wp-cli-expert`              |
| **Testing**                         |
| Set up PHPUnit                      | WordPress Testing Engineer  | DevOps Engineer             | `wordpress-testing-expert`   |
| Write unit tests                    | WordPress Testing Engineer  | -                           | `wordpress-testing-expert`   |
| Create integration tests            | WordPress Testing Engineer  | -                           | `wordpress-testing-expert`   |
| Mock WordPress functions            | WordPress Testing Engineer  | -                           | `wordpress-testing-expert`   |
| Mock Notion API responses           | WordPress Testing Engineer  | Notion API Specialist       | `wordpress-testing-expert`   |
| End-to-end testing                  | QA Automation Engineer      | -                           | `wordpress-qa-automation`    |
| Test admin UI                       | QA Automation Engineer      | -                           | `wordpress-qa-automation`    |
| Test sync workflows                 | QA Automation Engineer      | WordPress Testing Engineer  | `wordpress-qa-automation`    |
| Test various block types            | QA Automation Engineer      | Block Converter Specialist  | `wordpress-qa-automation`    |
| **Performance**                     |
| Optimize sync performance           | WordPress Architect         | PHP Backend Developer       | `wordpress-architect`        |
| Implement caching                   | WordPress Architect         | -                           | `wordpress-architect`        |
| Optimize database queries           | Database Designer           | WordPress Architect         | `wordpress-database-expert`  |
| Handle large imports                | PHP Backend Developer       | WordPress Architect         | `php-backend-dev`            |
| Memory optimization                 | PHP Backend Developer       | -                           | `php-backend-dev`            |
| **Documentation**                   |
| Write installation guide            | Technical Writer            | -                           | `wordpress-technical-writer` |
| Document Notion setup               | Technical Writer            | Notion API Specialist       | `wordpress-technical-writer` |
| Create developer docs               | Technical Writer            | -                           | `wordpress-technical-writer` |
| Document hooks/filters              | Technical Writer            | WordPress Plugin Engineer   | `wordpress-technical-writer` |
| Write code examples                 | Technical Writer            | -                           | `wordpress-technical-writer` |
| Create troubleshooting guide        | Technical Writer            | -                           | `wordpress-technical-writer` |
| Write readme.txt                    | Technical Writer            | WordPress Project Manager   | `wordpress-technical-writer` |
| **DevOps & Release**                |
| Set up CI/CD pipeline               | DevOps Engineer             | -                           | `wordpress-devops`           |
| Configure GitHub Actions            | DevOps Engineer             | -                           | `wordpress-devops`           |
| Set up code quality checks          | DevOps Engineer             | -                           | `wordpress-devops`           |
| Automate WordPress.org deploy       | DevOps Engineer             | WordPress Project Manager   | `wordpress-devops`           |
| Create release scripts              | DevOps Engineer             | -                           | `wordpress-devops`           |
| **Project Management**              |
| Plan development phases             | WordPress Project Manager   | -                           | `wordpress-project-manager`  |
| Prioritize features                 | WordPress Project Manager   | UX Researcher               | `wordpress-project-manager`  |
| Manage releases                     | WordPress Project Manager   | DevOps Engineer             | `wordpress-project-manager`  |
| Plan WordPress.org submission       | WordPress Project Manager   | -                           | `wordpress-project-manager`  |

## When to Use Multiple Skills Together

### Complex Tasks Requiring 3+ Skills

#### Implementing Complete Sync Flow

1. **WordPress Architect** - Overall sync architecture
2. **Notion API Specialist** - Fetch data from Notion
3. **Block Converter Specialist** - Convert blocks
4. **Media Processing Specialist** - Handle images
5. **Database Designer** - Store sync mappings
6. **PHP Backend Developer** - Background processing

#### Creating Admin Settings Page

1. **WordPress Admin UI Designer** - UI design
2. **UX Researcher** - User flow analysis
3. **WordPress Plugin Engineer** - Settings API implementation
4. **Security Specialist** - Sanitization and validation

#### Setting Up Complete Testing

1. **WordPress Testing Engineer** - Unit/integration tests
2. **QA Automation Engineer** - End-to-end tests
3. **DevOps Engineer** - CI/CD pipeline
4. **WordPress Project Manager** - Test planning

## Current Available Skills (Already Installed)

| Existing Skill     | How to Use It for This Project                      |
| ------------------ | --------------------------------------------------- |
| **webapp-testing** | Test WordPress admin UI using Playwright            |
| **mcp-builder**    | Create custom WordPress or Notion MCP server        |
| **skill-creator**  | Build the custom skills outlined in this guide      |
| **docx**           | Create user documentation in Word format            |
| **pdf**            | Generate PDF user guides                            |
| **context7**       | Fetch latest WordPress and Notion API documentation |

## MCP Servers Available

| MCP Server         | Use Case                                                          |
| ------------------ | ----------------------------------------------------------------- |
| **context7**       | Get up-to-date docs for WordPress APIs, Notion API, PHP libraries |
| **playwright-mcp** | Automate testing of WordPress admin interface                     |
| **tmux**           | Manage multiple terminal sessions for development                 |
| **shadcn-ui**      | (Less relevant - more for React apps than WordPress)              |

## Priority Skills to Create First

Based on project phase and dependencies:

### Phase 1: Foundation (Create These First)

1. **wordpress-plugin-dev** - Essential for all WordPress work
2. **wordpress-architect** - Define architecture before coding
3. **notion-api-expert** - Core to entire plugin functionality
4. **wordpress-security-expert** - Security from day one

### Phase 2: Core Implementation

5. **block-mapping-expert** - Central feature of the plugin
6. **php-backend-dev** - Sync engine implementation
7. **wordpress-database-expert** - Data structure design
8. **wordpress-media-expert** - Critical for image handling

### Phase 3: Interface & Quality

9. **wordpress-admin-ui-expert** - User-facing configuration
10. **wordpress-testing-expert** - Quality assurance
11. **wp-cli-expert** - Developer experience

### Phase 4: Launch Preparation

12. **wordpress-technical-writer** - Documentation
13. **wordpress-devops** - Deployment automation
14. **wordpress-project-manager** - Release coordination

## How to Use This Reference

1. **Find your task** in the left column
2. **Check Primary Skill** for the main expertise needed
3. **Check Secondary Skill** for supporting expertise
4. **Use the Skill Name** to invoke or create the skill
5. **Review "When to Use Multiple Skills"** for complex tasks

## Creating a New Skill

To create any of these skills using the existing `skill-creator`:

```bash
# Use the Skill tool
Skill: "skill-creator"

# Then provide details:
# - Skill name (e.g., "wordpress-plugin-dev")
# - Description from the specialized-agents-and-skills.md document
# - Specific tools and knowledge areas
# - Example prompts and use cases
```

## Example Workflow

**Task:** Implement image sync from Notion to WordPress

**Skills Needed:**

1. `notion-api-expert` - Fetch image URLs from Notion blocks
2. `wordpress-media-expert` - Download and import to Media Library
3. `wordpress-database-expert` - Store mappings to prevent duplicates
4. `php-backend-dev` - Implement queue system for bulk downloads
5. `wordpress-testing-expert` - Test image import functionality

**Order of Execution:**

1. Use `notion-api-expert` to understand image block structure
2. Use `wordpress-media-expert` to design import workflow
3. Use `wordpress-database-expert` to design duplicate detection
4. Use `php-backend-dev` to implement background processing
5. Use `wordpress-testing-expert` to create test cases

---

## Notes

- Skills can be invoked multiple times during a task
- Some tasks may only need one skill
- Complex features require coordinating multiple skills
- Always start with architecture/design skills before implementation skills
