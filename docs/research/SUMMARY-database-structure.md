# Notion Database Structure: Quick Reference

## The Critical Answer

**Can a single Notion page/entry exist in multiple databases?**

**NO.** Each Notion database entry is a unique page object with its own page ID. A page belongs to exactly ONE parent:

- A database (as a database entry), OR
- Another page (as a child page), OR
- The workspace root

**However**, you can achieve similar functionality through:

1. **Relation Properties** - Link pages across different databases (like foreign keys)
2. **Linked Databases** - Display the same database in multiple locations with different views/filters
3. **Multiple Data Sources** - Combine different data sources within one database container (new feature)

---

## Key Facts for Plugin Development

### Database = Collection of Pages

```
Database "Articles"
├─ Page: "Getting Started with Notion" (page_id: abc123)
├─ Page: "Advanced Database Tips" (page_id: def456)
└─ Page: "Notion API Guide" (page_id: ghi789)
```

Each database entry IS a page that has:

- Unique `page_id` (32-character identifier)
- Properties (structured data from database schema)
- Content (blocks in page body)

### One Page = One Parent

```json
{
	"id": "abc123",
	"parent": {
		"type": "database_id",
		"database_id": "articles_db_123"
	}
}
```

The `parent` field determines where the page lives. It cannot have multiple parents.

### Relations = Cross-Database Links

```
Authors Database              Articles Database
├─ "Jane Doe" (page_id: xyz1) ─┐
└─ "John Smith" (page_id: xyz2) │
                                ▼
                    ┌───────────┴────────────┐
                    │ Author: [xyz1, xyz2]   │ ◄─ Relation property
                    │ (not duplication!)     │
                    └────────────────────────┘
```

Relations store page IDs as references, not copies. The author pages remain in the Authors Database.

### Linked Databases = Same Data, Different Views

```
Master Database (actual data)
├─ Article 1
├─ Article 2
└─ Article 3

Linked View on Page A (filtered: Status = Published)
├─ Article 1 ──────┐
└─ Article 3       │  Same page objects,
                   │  just displayed differently
Linked View on Page B (filtered: Status = Draft)
└─ Article 2 ──────┘
```

Linked databases are NOT separate databases. They're views/references to the original.

---

## WordPress Sync Implications

### 1. One-to-One Mapping

- Notion Page (page_id: abc123) → WordPress Post (post_id: 456)
- Store mapping in post meta: `notion_page_id` = "abc123"

### 2. Database = Post Type

- Notion "Articles" Database → WordPress "Post" (or custom post type)
- Each page in database → Individual post

### 3. Relations = Custom Fields or Post Relationships

- Notion: Article relates to Author (stores author page_id)
- WordPress: Store in post meta or use ACF/Posts2Posts plugin
- Must resolve: `notion_author_page_id` → `wp_author_post_id`

### 4. Linked Databases = Same Sync

- Multiple linked views of same database = One sync operation
- Don't create duplicate WordPress posts for linked views
- User may want different filters, but underlying data is the same

### 5. Sync Order Matters

If syncing multiple databases with relations:

1. Sync "Authors" database first
2. Then sync "Articles" database
3. Resolve relations using already-synced author page IDs

---

## Common Misconceptions

❌ **WRONG:** "Row 12 in Database A can also be Row 3 in Database B"
✅ **CORRECT:** Each database entry is a unique page. Use relations to link between databases.

❌ **WRONG:** "Linked databases create copies of pages"
✅ **CORRECT:** Linked databases are views of the same pages, not copies.

❌ **WRONG:** "Relations mean a page exists in two databases"
✅ **CORRECT:** Relations are references (like foreign keys in SQL), not membership.

---

## API Code Examples

### Creating a Database Entry (Page)

```javascript
await notion.pages.create({
	parent: {
		database_id: 'abc123', // Page belongs to this database
	},
	properties: {
		Title: {
			title: [{ text: { content: 'My Article' } }],
		},
	},
});
```

### Setting a Relation Property

```javascript
await notion.pages.update({
	page_id: 'article_page_id',
	properties: {
		Author: {
			relation: [
				{ id: 'author_page_id_1' }, // References another page
				{ id: 'author_page_id_2' },
			],
		},
	},
});
```

### Querying a Database

```javascript
const response = await notion.databases.query({
	database_id: 'abc123',
});

// response.results = array of page objects
for (const page of response.results) {
	console.log(page.id); // Each has unique page_id
	console.log(page.properties.Author.relation); // Array of {id: "..."}
}
```

---

## Quick Decision Matrix

| Scenario                                    | Solution                                                |
| ------------------------------------------- | ------------------------------------------------------- |
| Want same data in multiple locations        | Use **Linked Databases**                                |
| Want to reference pages across databases    | Use **Relation Properties**                             |
| Want to combine data from different sources | Use **Multiple Data Sources** (new feature)             |
| Want to duplicate content                   | Create separate pages (not recommended)                 |
| Want hierarchical categories                | Use **Parent Page** relationships or taxonomy databases |

---

## Must-Read Sections in Full Document

For implementation details, see full document `/docs/research/notion-database-structure.md`:

- **Section 7:** Practical Use Cases & Real-World Patterns
- **Section 8:** Implications for WordPress Sync Plugin
- **Section 4:** Relation Properties: How They Work

---

## Further Reading

- Notion API: https://developers.notion.com/reference/database
- Working with Databases: https://developers.notion.com/docs/working-with-databases
- Relations & Rollups: https://www.notion.com/help/relations-and-rollups
