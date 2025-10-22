# Notion Database Structure Research

**Research Date:** 2025-10-20
**Purpose:** Understanding Notion database architecture for WordPress sync implementation

## Executive Summary

**Critical Finding:** Each Notion database entry is a unique page object with its own page ID. A single page CANNOT exist in multiple databases simultaneously. However, Notion provides mechanisms to reference and display database content across multiple locations without duplication.

---

## 1. Database Structure

### Core Concept: Databases as Page Collections

In Notion's architecture:

- **A database is a container** that holds a collection of pages
- **Each database entry IS a page object** with:
    - A unique page ID (32-character alphanumeric identifier)
    - Page properties (structured data conforming to the database schema)
    - Page content (blocks in the page body, like any other Notion page)

From the Notion API documentation:

> "The rows of a data source are individual Pages that live under it and each contain page properties (keys and values that conform to the data source's schema) and content (what you see in the body of the page in the Notion app)."

### Database Entry = Page

Every item in a database—whether displayed as:

- A row in a table view
- A card on a board view
- An event on a calendar view

...is fundamentally a **Notion page** that can be:

- Opened as a full page
- Formatted and styled
- Nested with child content and blocks
- Referenced by its unique page ID

### API Perspective

When working with the Notion API:

```json
// Creating a database entry = Creating a page with database parent
{
	"parent": {
		"type": "database_id",
		"database_id": "abc123..."
	},
	"properties": {
		"Title": { "title": [{ "text": { "content": "My Article" } }] },
		"Status": { "select": { "name": "Published" } }
	}
}
```

When querying a database:

- API returns an array of page objects
- Each page has a unique `page_id`
- Use the `page_id` to retrieve full page content via the `/pages/{page_id}` endpoint

---

## 2. Cross-Database Relations: NO, Pages Cannot Exist in Multiple Databases

### The Definitive Answer

**A single page CANNOT exist in multiple databases simultaneously.** Each page belongs to exactly one parent:

- A database (as a database entry)
- Another page (as a child page)
- The workspace root

### Parent Object Structure

From the Notion API documentation, every page has a `parent` object:

```json
{
	"parent": {
		"type": "database_id",
		"database_id": "abc123..."
	}
}
```

OR

```json
{
	"parent": {
		"type": "page_id",
		"page_id": "xyz789..."
	}
}
```

**A page can only have ONE parent.** This means:

- Row 12 in Database A **cannot** also be Row 3 in Database B
- Each database entry is a distinct page object
- If you need the same content in two databases, you must create two separate pages

### Workarounds for Multi-Database Presence

While a page cannot exist in multiple databases, Notion provides three mechanisms to achieve similar functionality:

#### 1. Relation Properties (Cross-Database Linking)

**What it does:** Links database entries across different databases without duplicating pages.

**Example Use Case:**

- Authors Database (Database A)
- Articles Database (Database B)
- Articles have a "Relation" property linking to Authors

**API Implementation:**

```json
// In Articles Database
{
	"properties": {
		"Author": {
			"relation": [{ "id": "page_id_from_authors_database" }]
		}
	}
}
```

**Requirements:**

- Both databases must be shared with your integration
- Relations reference pages by their unique page IDs
- Avoid relations more than 1 level deep (nested relations may not work reliably)

**API Constraints:**

- Related database must be shared with integration
- If related database is not shared, relation properties will not appear in API responses

#### 2. Linked Databases (Synced Views)

**What it does:** Creates multiple views of the same underlying database in different locations.

**Key Characteristics:**

- A "linked database" is a mirror/reference to the original database
- Same data source, different visual locations
- Changes to content sync across all linked instances
- Views, filters, and sorts are independent per linked instance

**Important Distinction:**

- This is NOT duplication of pages
- This is NOT multiple databases containing the same page
- This is one database displayed in multiple places

**Use Case:**

- Original database on a "Master Database" page
- Linked view filtered for "Published" articles on a "Blog" page
- Linked view filtered for "Draft" articles on a "Drafts" page
- All three views reference the same underlying pages

#### 3. Multiple Data Sources in One Database (New Feature)

**What it does:** A single database can now contain multiple data sources (as of Notion's 2024-2025 updates).

**Notion API Version:** `2025-09-03` supports this new structure

**Important Note:**

- This allows combining data from different sources within one database container
- Each data source still contains distinct page objects
- A page still belongs to only one data source

---

## 3. Database vs Page: Key Differences

| Aspect            | Database                                               | Page (Database Entry)                  | Regular Page              |
| ----------------- | ------------------------------------------------------ | -------------------------------------- | ------------------------- |
| **Definition**    | Collection container                                   | Page with structured properties        | Standalone page           |
| **Parent**        | Page or workspace                                      | Database                               | Page or workspace         |
| **Properties**    | Schema definition                                      | Property values (conforming to schema) | No structured properties  |
| **ID Type**       | `database_id`                                          | `page_id`                              | `page_id`                 |
| **API Endpoints** | `/databases/{id}`                                      | `/pages/{id}`                          | `/pages/{id}`             |
| **Content**       | No content (only schema)                               | Has page content (blocks)              | Has page content (blocks) |
| **Views**         | Supports multiple views (table, board, calendar, etc.) | N/A                                    | N/A                       |

### Important API Distinction

When creating a page via the API:

**Database Entry:**

```json
{
	"parent": { "type": "database_id", "database_id": "abc123" },
	"properties": {
		/* Required: must match database schema */
	}
}
```

**Regular Page:**

```json
{
	"parent": { "type": "page_id", "page_id": "xyz789" },
	"properties": {
		"title": {
			/* Only title property */
		}
	}
}
```

---

## 4. Relation Properties: How They Work

### Relation Types

Notion's "Relation" property type can link:

✅ **Between entries in the same database**
Example: Tasks database with "Blocked By" relation to other tasks

✅ **Between entries in different databases**
Example: Articles → Authors, Projects → Team Members

❌ **To pages outside databases (NOT directly supported)**
Workaround: Create a "Pages" database containing those pages, then relate to it

### Relation Property Schema

In the database schema:

```json
{
	"Relation Name": {
		"type": "relation",
		"relation": {
			"database_id": "target_database_id",
			"type": "single_property" // or "dual_property" for bidirectional
		}
	}
}
```

When setting relation values on a page:

```json
{
	"Relation Name": {
		"relation": [{ "id": "page_id_1" }, { "id": "page_id_2" }]
	}
}
```

### Bidirectional Relations

Relations can be:

- **Single property:** One-way link (appears only in source database)
- **Dual property:** Two-way link (appears in both databases as reciprocal properties)

### Rollup Properties

After creating a relation, you can create **Rollup** properties that:

- Aggregate data from related pages
- Calculate sums, averages, counts, etc.
- Display related property values

Example: Articles database can have a rollup showing "Total Articles by This Author" based on the Author relation.

---

## 5. Synced Databases / Linked Databases

### What Are Linked Databases?

**Linked databases** (also called "synced databases" or "database views") allow you to display the same database in multiple locations with different filters/sorts.

### Key Points

1. **Not Separate Databases:** A linked database is a reference to the original database, not a copy
2. **Same Underlying Data:** All linked instances share the same page objects
3. **Independent Views:** Each linked instance can have unique:
    - Filters
    - Sorts
    - View types (table vs. board vs. calendar)
    - Hidden/shown properties
4. **Synchronized Content:** Changes to page content or properties sync across all instances immediately

### Creating Linked Databases

In the Notion UI:

- Type `/linked` on any page
- Select the database to link
- Choose to copy an existing view or create a new one

### Use Cases

- **Dashboard Pages:** Display filtered subsets of a master task database
- **Departmental Views:** Same projects database, different filters per team
- **Status Boards:** Separate linked views for "In Progress," "Review," "Completed"

### API Considerations

From an API perspective:

- Querying a linked database queries the original database
- There's no separate database ID for linked views
- Filters/sorts must be specified in API query parameters

---

## 6. Unique Identifiers

### Page IDs (System-Generated)

Every page (including database entries) has a **page ID**:

- 32-character alphanumeric string
- Format: `abc123def456...` (no hyphens in API)
- URL format: `https://notion.so/Page-Name-abc123def456...`
- Globally unique across all of Notion
- Immutable (never changes)

**Finding Page IDs:**

- From URL: The 32 characters after the last dash before the query string
- From API: The `id` field in page objects

### Database IDs

Every database has a **database ID**:

- Same 32-character format as page IDs
- Found in the database URL
- Used in API calls to query or create database entries

### Unique ID Property (User-Managed)

Notion offers a **Unique ID** property type you can add to databases:

- Generates sequential IDs (e.g., `TASK-001`, `ARTICLE-042`)
- User-defined prefix
- Creates special URLs: `notion.so/TASK-001` → redirects to the page
- Useful for human-readable references

**Important Distinction:**

- **Page ID** = System identifier (immutable, always present)
- **Unique ID property** = Optional user-facing identifier (customizable)

---

## 7. Practical Use Cases & Real-World Patterns

### Common Database Architectures

#### Content Management System

```
Articles Database (main content)
  └─ Relation to: Authors Database
  └─ Relation to: Categories Database
  └─ Relation to: Tags Database

Authors Database
  └─ Rollup: Count of Articles

Categories Database
  └─ Rollup: List of Articles
```

**For WordPress Sync:**

- Each article is a unique page in Articles Database
- Article's author relation → map to WordPress author user
- Categories/tags → map to WordPress taxonomy terms

#### Project Management System

```
Projects Database
  └─ Relation to: Tasks Database
  └─ Relation to: Team Members Database

Tasks Database
  └─ Relation to: Projects Database (bidirectional)
  └─ Relation to: Tasks Database (self-relation for dependencies)

Team Members Database
  └─ Rollup: Count of Assigned Tasks
```

#### Knowledge Base / Wiki

```
Articles Database (main content)
  └─ Self-relation: Related Articles
  └─ Relation to: Topics Database

Topics Database (taxonomy)
  └─ Rollup: Articles in This Topic

Glossary Database
  └─ Linked in article content via mentions
```

### Anti-Patterns to Avoid

❌ **Attempting to duplicate pages across databases**

- Instead: Use relations to link databases

❌ **Creating multiple databases for slight variations**

- Instead: Use linked databases with different filters

❌ **Treating relation properties as "page membership"**

- Instead: Understand relations as references, not containment

---

## 8. Implications for WordPress Sync Plugin

### Critical Design Decisions

#### 1. One-to-One Page Mapping

**Decision:** Each Notion page maps to exactly one WordPress post.

**Implementation:**

- Store Notion `page_id` in WordPress post meta: `notion_page_id`
- Store WordPress `post_id` in Notion page properties: `wp_post_id` (custom property)
- Use these IDs for bidirectional sync and conflict resolution

#### 2. Relation Property Handling

**Challenge:** How to sync Notion relations to WordPress?

**Options:**

**Option A: Convert Relations to Taxonomy Terms**

- Notion: Articles → Categories (relation)
- WordPress: Post → Categories (taxonomy)
- Limitation: WordPress categories are hierarchical, not relational

**Option B: Convert Relations to Post Meta (Recommended)**

- Store related page IDs as serialized arrays in custom fields
- Useful for ACF relationship fields
- Requires resolving Notion page IDs to WordPress post IDs

**Option C: Use WordPress Post-to-Post Connections**

- Requires plugin like ACF or Posts 2 Posts
- Best for complex relational data
- Example: "Related Articles" feature

**Recommendation:** Support multiple strategies via plugin settings:

1. Ignore relations (default for MVP)
2. Map to custom fields (for ACF users)
3. Map to taxonomy terms (for simple categorization)
4. Map to post relationships (for advanced users)

#### 3. Database-to-Post-Type Mapping

**Mapping Strategy:**

| Notion Side                        | WordPress Side                               |
| ---------------------------------- | -------------------------------------------- |
| Database                           | Custom Post Type                             |
| Database Entry (Page)              | Post                                         |
| Database Properties                | Post Meta / Custom Fields                    |
| Property Type: Title               | Post Title                                   |
| Property Type: Rich Text           | Post Content or Custom Field                 |
| Property Type: Date                | Post Date or Custom Field                    |
| Property Type: Select/Multi-Select | Taxonomy Terms                               |
| Property Type: Relation            | Custom Field (page IDs) or Post Relationship |
| Property Type: Files               | Media Library Attachments                    |

#### 4. Linked Database Handling

**Challenge:** User has 3 linked views of the same Notion database. Should we create 3 WordPress post types or 1?

**Answer:** One WordPress post type, because all linked views reference the same underlying pages.

**Implementation:**

- Detect if multiple databases are actually linked views of the same source
- API approach: Query database entries and check for duplicate page IDs
- Warn user if they try to sync the same database twice

#### 5. Self-Relations and Circular References

**Challenge:** Notion allows self-relations (e.g., "Blocked By" task → task).

**WordPress Considerations:**

- WordPress doesn't natively support post-to-post relationships
- Circular references can cause infinite loops during sync

**Mitigation:**

- Detect self-relations during database schema inspection
- Store as serialized IDs in custom fields
- Optionally integrate with Posts 2 Posts or ACF Relationship fields
- Implement cycle detection in sync logic

#### 6. Rollup Properties

**Challenge:** Rollup properties aggregate data from relations (counts, sums, etc.).

**Sync Strategy:**

- Rollups are computed values, not source data
- **Recommendation:** Skip rollups during sync (read-only in Notion)
- Alternative: Recalculate rollup values on WordPress side using custom queries

#### 7. Multi-Database Syncs

**User Scenario:** User wants to sync:

- Articles Database → Blog Posts (post type)
- Authors Database → Author Pages (custom post type)
- Categories Database → Categories (taxonomy)

**Architecture:**

- Support syncing multiple databases in one plugin instance
- Each database maps to a separate WordPress post type or taxonomy
- Relation properties create cross-references between synced content
- Sync order matters: Sync "Authors" before "Articles" so relations resolve correctly

#### 8. Conflict Resolution with Relations

**Scenario:** Article A in Notion relates to Author B. Both are edited simultaneously.

**Conflict Cases:**

1. **Article updated in Notion, Author updated in WordPress**
    - No conflict: Independent objects
2. **Relation changed in Notion, related page updated in WordPress**
    - Sync relation update to WordPress custom field
    - Update related post's backlink (if bidirectional)
3. **Relation changed in both Notion and WordPress**
    - Use last-edited timestamp to determine winner
    - Option: Merge relations (union of both sets)

### API Implementation Notes

#### Fetching Related Pages

When syncing a database entry with relations:

1. **Query the database** to get pages with properties
2. **Extract relation property values** (array of page IDs)
3. **For each related page ID:**
    - Check if page is in another synced database (map to WordPress post ID)
    - If not synced, optionally fetch page title/properties for metadata
    - Store mapping: `notion_page_id` → `wordpress_post_id`

Example API Flow:

```javascript
// 1. Query Articles Database
const articles = await notion.databases.query({
	database_id: articlesDbId,
});

for (const article of articles.results) {
	// 2. Extract author relation
	const authorRelation = article.properties.Author.relation;
	const authorPageIds = authorRelation.map((r) => r.id);

	// 3. Map to WordPress
	const wpAuthorIds = [];
	for (const authorPageId of authorPageIds) {
		const wpAuthorId = await getWordPressIdFromNotionPageId(authorPageId);
		if (wpAuthorId) {
			wpAuthorIds.push(wpAuthorId);
		}
	}

	// 4. Store in WordPress post meta
	update_post_meta(wpPostId, 'notion_authors', wpAuthorIds);
}
```

#### Handling Missing Relations

**Edge Case:** Notion page relates to a page that's not shared with the integration.

**API Behavior:**

- Relation property may be empty in API response
- No error thrown, just missing data

**Plugin Handling:**

- Log warning about missing related pages
- Prompt user to share related databases with integration
- Store relation as unresolved until related page becomes accessible

---

## 9. Key Takeaways for Plugin Development

### Database Entry = Page Object

✅ **Always treat database entries as pages**

- Use `/pages/{page_id}` endpoint to get full content
- Store `page_id` as the primary identifier in WordPress
- Remember: Each entry has page properties + page content (blocks)

### Pages Cannot Be in Multiple Databases

✅ **Never assume a page can exist in two databases**

- If user tries to sync overlapping databases, detect and warn
- Use relations instead of duplication for cross-database references

### Linked Databases Are Views, Not Copies

✅ **Detect linked databases to avoid duplicate syncs**

- Same `database_id` should map to one WordPress post type
- User may have multiple "sync configurations" for different filtered views

### Relations Are Cross-References, Not Membership

✅ **Map relations to WordPress appropriately**

- Don't confuse relations with categories/tags (those are properties)
- Relations are like "Related Posts" or ACF relationship fields
- Store as post meta with mappings: `[notion_page_id] => [wp_post_id]`

### Rollups Are Computed, Not Stored

✅ **Skip rollup properties during sync**

- Rollups recalculate automatically in Notion
- Don't try to sync computed values to WordPress
- If needed, recalculate on WordPress side

### Sync Order Matters with Relations

✅ **Sync databases in dependency order**

- Sync "Authors" before "Articles"
- Sync "Categories" before "Products"
- Build a dependency graph from relation properties

### Permission Requirements

✅ **Ensure all related databases are shared with integration**

- Test for missing relation properties in API responses
- Provide clear error messages about sharing requirements
- Document setup steps: "Share all related databases with integration"

---

## 10. Open Questions for Implementation

### Question 1: Handling Notion "Database" Property Type

**What is it:** Notion has a "Database" property type that embeds an inline database within a page.

**Question:** Do we sync inline databases? How do we distinguish them from top-level databases?

**Research Needed:** Test Notion API behavior with inline databases.

### Question 2: Notion's New "Data Source" Model

**What changed:** As of 2024-2025, Notion databases can contain multiple data sources.

**Question:** Does this affect API queries? Do we need to handle multiple data sources differently?

**Research Needed:** Test with Notion API version `2025-09-03`.

### Question 3: Relation Depth Limits

**Known limitation:** Relations more than 1 level deep may not work reliably.

**Question:** What exactly fails? Can we query related pages' relations?

**Research Needed:** Test nested relation retrieval via API.

### Question 4: Circular Relations

**Scenario:** Database A relates to Database B, Database B relates back to Database A.

**Question:** Does Notion API handle this gracefully? Do we need cycle detection?

**Research Needed:** Create test case with circular relations and sync.

---

## References

### Notion API Documentation

- Working with Databases: https://developers.notion.com/docs/working-with-databases
- Database Object: https://developers.notion.com/reference/database
- Page Object: https://developers.notion.com/reference/page
- Property Object: https://developers.notion.com/reference/property-object
- Parent Object: https://developers.notion.com/reference/parent-object

### Notion Help Center

- Intro to Databases: https://www.notion.com/help/intro-to-databases
- Relations & Rollups: https://www.notion.com/help/relations-and-rollups
- Synced Databases: https://www.notion.com/help/synced-databases
- Linked Databases: https://www.notion.com/help/data-sources-and-linked-databases
- Unique ID Property: https://www.notion.com/help/unique-id

### Community Resources

- Notion Databases Explained (New Data Model): https://www.simonesmerilli.com/life/notion-database-data-source
- Multiple Database Relations Tutorial: https://www.landmarklabs.co/notion-tutorials/notion-relation-to-multiple-databases
- Linked Databases Tutorial: https://www.templates4notion.com/blog/notion-linked-databases

### API Version Changes

- Current API Version: `2025-09-03` (as of late 2024/early 2025)
- Significant changes to database structure and multiple data sources support
- Relation and rollup properties can now be created via API

---

## Revision History

- **2025-10-20:** Initial research document created
- Research conducted via web search of Notion API documentation and community resources
- Findings compiled for WordPress sync plugin development

---

## Next Steps

1. **Test API Behavior:** Create test Notion workspace with:
    - Multiple databases with relations
    - Linked database views
    - Self-relations
    - Circular relations

2. **Document API Responses:** Capture actual API response structures for:
    - Database queries with relations
    - Page objects with relation properties
    - Missing/unshared related pages

3. **Define Sync Strategy:** Based on this research, create formal specification for:
    - Database-to-post-type mapping
    - Relation-to-custom-field mapping
    - Conflict resolution with relations
    - Sync order determination

4. **Update PRD:** Incorporate these findings into the product requirements document
