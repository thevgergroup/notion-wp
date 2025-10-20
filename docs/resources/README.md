# Notion-WP Development Resources

This directory contains comprehensive guides for specialized agents, skills, and development workflows needed to build the Notion-WordPress sync plugin.

## Documents Overview

### 1. [Specialized Agents and Skills](./specialized-agents-and-skills.md)

**Purpose:** Comprehensive catalog of all specialized expertise areas needed for the project.

**Contains:**

- 17 specialized agent/skill definitions
- Detailed expertise areas for each
- Use cases and applications
- Priority recommendations by development phase
- Implementation options (custom skills, MCP servers, slash commands)

**When to Use:**

- Planning which skills to create
- Understanding what expertise is needed for specific tasks
- Determining team composition or skill gaps
- Reference for skill capabilities

**Key Sections:**

- Technical Development Agents (5 skills)
- Architecture & Design Agents (3 skills)
- Testing & Quality Agents (2 skills)
- UI/UX & Documentation Agents (3 skills)
- Project Management Agents (2 skills)
- Additional Specialized Skills (2 skills)

---

### 2. [Skills Quick Reference Matrix](./skills-quick-reference.md)

**Purpose:** Fast lookup table for which skill to use for any specific task.

**Contains:**

- Task-to-skill mapping table (100+ tasks)
- Multi-skill collaboration guidance
- Current available skills
- Priority skill creation order
- Quick reference workflow examples

**When to Use:**

- Need to find which skill handles a specific task
- Want to know which skills to invoke together
- Quick lookup during active development
- Planning skill creation sequence

**Key Features:**

- Sortable by task type
- Shows primary and secondary skills
- Includes skill names for invocation
- Examples of complex multi-skill tasks

---

### 3. [Skill Implementation Guide](./skill-implementation-guide.md)

**Purpose:** Practical step-by-step guide for creating and using skills.

**Contains:**

- How to create skills with `skill-creator`
- Complete skill templates (copy-paste ready)
- Slash command creation guide
- MCP server usage and creation
- Real-world skill usage examples
- Multi-skill workflow patterns

**When to Use:**

- Actually creating a new skill
- Need template to follow
- Learning how to invoke skills effectively
- Setting up slash commands
- Creating custom MCP servers

**Key Features:**

- 3 complete skill templates (WordPress Plugin Engineer, Notion API Specialist, Block Converter Specialist)
- Step-by-step skill creation process
- 5 detailed usage examples
- Slash command examples
- Best practices and troubleshooting

---

## Quick Start Guide

### For Planning Development

1. **Read:** [Specialized Agents and Skills](./specialized-agents-and-skills.md)
2. **Focus on:** Priority Recommendations section
3. **Identify:** Which skills you need first

### For Active Development

1. **Use:** [Skills Quick Reference](./skills-quick-reference.md)
2. **Look up:** Your current task in the mapping table
3. **Invoke:** The recommended skill(s)

### For Creating Skills

1. **Open:** [Skill Implementation Guide](./skill-implementation-guide.md)
2. **Copy:** Relevant skill template
3. **Follow:** Creation steps
4. **Test:** With example prompts

---

## Recommended Workflow

### Phase 1: Foundation Setup

**Goal:** Create core skills and project structure

1. **Create these skills first:**
    - `wordpress-plugin-dev` - WordPress Plugin Engineer
    - `wordpress-architect` - WordPress Architect
    - `notion-api-expert` - Notion API Specialist
    - `wordpress-security-expert` - Security Specialist

2. **Use skills to:**
    - Set up plugin boilerplate structure
    - Design overall architecture
    - Implement Notion API client
    - Secure token storage

**Estimated Time:** 1-2 days

---

### Phase 2: Core Implementation

**Goal:** Implement main sync functionality

3. **Create these skills:**
    - `block-mapping-expert` - Block Converter Specialist
    - `php-backend-dev` - PHP Backend Developer
    - `wordpress-database-expert` - Database Designer
    - `wordpress-media-expert` - Media Processing Specialist

4. **Use skills to:**
    - Implement block converters
    - Build sync engine
    - Design database schema
    - Handle media imports

**Estimated Time:** 2-3 weeks

---

### Phase 3: Interface & Testing

**Goal:** Build admin UI and comprehensive tests

5. **Create these skills:**
    - `wordpress-admin-ui-expert` - Admin UI Designer
    - `wordpress-testing-expert` - Testing Engineer
    - `wordpress-qa-automation` - QA Automation Engineer
    - `wp-cli-expert` - CLI Expert

6. **Use skills to:**
    - Create admin settings pages
    - Build sync dashboard
    - Write unit and integration tests
    - Create WP-CLI commands

**Estimated Time:** 1-2 weeks

---

### Phase 4: Documentation & Launch

**Goal:** Complete documentation and prepare for release

7. **Create these skills:**
    - `wordpress-technical-writer` - Technical Writer
    - `wordpress-devops` - DevOps Engineer
    - `wordpress-project-manager` - Project Manager

8. **Use skills to:**
    - Write user documentation
    - Create developer guides
    - Set up CI/CD
    - Prepare WordPress.org submission

**Estimated Time:** 1 week

---

## Available Tools & Resources

### Already Installed

#### Skills

- `skill-creator` - Create new custom skills
- `webapp-testing` - Test WordPress admin UI with Playwright
- `mcp-builder` - Build custom MCP servers
- `docx`, `pdf` - Create documentation

#### MCP Servers

- `context7` - Fetch WordPress and Notion API docs
- `playwright-mcp` - Browser automation for testing
- `tmux` - Terminal session management
- `shadcn-ui-server` - UI components (less relevant for WP)

### To Be Created

#### Priority Skills (Create First)

1. `wordpress-plugin-dev`
2. `wordpress-architect`
3. `notion-api-expert`
4. `wordpress-security-expert`

#### Secondary Skills (Create as Needed)

5. `block-mapping-expert`
6. `php-backend-dev`
7. `wordpress-database-expert`
8. `wordpress-media-expert`

#### Optional Skills (Nice to Have)

9. `wordpress-admin-ui-expert`
10. `wordpress-testing-expert`
11. `wp-cli-expert`
12. `wordpress-technical-writer`

---

## Common Task Examples

### "I need to set up the plugin structure"

1. **Open:** [Skills Quick Reference](./skills-quick-reference.md)
2. **Find:** "Create plugin folder structure" → `wordpress-plugin-dev`
3. **Invoke:** `Skill: "wordpress-plugin-dev"`
4. **Prompt:** "Set up initial plugin structure for notion-wp"

### "I need to implement Notion API pagination"

1. **Open:** [Skills Quick Reference](./skills-quick-reference.md)
2. **Find:** "Handle pagination" → `notion-api-expert`
3. **Invoke:** `Skill: "notion-api-expert"`
4. **Prompt:** "Implement pagination for Notion database queries"

### "I need to convert Notion callout blocks"

1. **Open:** [Skills Quick Reference](./skills-quick-reference.md)
2. **Find:** "Convert callouts" → `block-mapping-expert`
3. **Invoke:** `Skill: "block-mapping-expert"`
4. **Prompt:** "Convert Notion callout blocks to WordPress"

### "I need to build the admin settings page"

1. **Open:** [Skills Quick Reference](./skills-quick-reference.md)
2. **Find:** "Design settings page" → `wordpress-admin-ui-expert` + `ux-researcher`
3. **Invoke:** `Skill: "wordpress-admin-ui-expert"`
4. **Prompt:** "Create admin settings page for Notion token and sync options"

---

## Skill Creation Checklist

When creating a new skill, ensure it includes:

- [ ] Clear description of expertise
- [ ] When to use this skill
- [ ] List of specific knowledge areas
- [ ] Available tools it can use
- [ ] 3-5 example prompts
- [ ] Best practices
- [ ] Common patterns or code templates
- [ ] Resources to leverage (MCP servers, docs)
- [ ] Related skills for complex tasks

---

## Best Practices

### 1. Always Start with Architecture

Before coding, use `wordpress-architect` to plan the structure.

### 2. Use Specific, Detailed Prompts

Instead of "implement sync", provide detailed requirements with bullet points.

### 3. Leverage context7 for Current Docs

Always fetch latest WordPress and Notion documentation:

```bash
mcp__context7__resolve-library-id "WordPress"
mcp__context7__get-library-docs "/wordpress/docs" "hooks and filters"
```

### 4. Combine Skills for Complex Tasks

Use architect → specialist → tester workflow for features.

### 5. Security First

Always involve `wordpress-security-expert` for any user input or data storage.

### 6. Test Everything

Use `wordpress-testing-expert` to create tests as you build features.

### 7. Document as You Go

Use `wordpress-technical-writer` to document hooks and APIs immediately.

---

## Integration with Project Documentation

These resources complement the existing project docs:

- **[CLAUDE.md](../../CLAUDE.md)** - Overall project guidance
- **[docs/product/prd.md](../product/prd.md)** - Product requirements
- **[docs/requirements/requirements.md](../requirements/requirements.md)** - Technical requirements

**This resources directory** provides the "how" while the other docs provide the "what".

---

## Next Steps

1. **Review all three documents** to understand the full skill ecosystem
2. **Create foundation skills** using the templates in the implementation guide
3. **Start using the quick reference** as your go-to lookup during development
4. **Iterate and expand** skills as you discover new needs

---

## Questions or Issues?

### Skill doesn't have the right expertise?

- Edit the skill definition to add more knowledge areas
- Provide more detailed prompts with context
- Consider creating a more specialized sub-skill

### Task requires multiple skills?

- Check the "When to Use Multiple Skills Together" section in the quick reference
- Invoke skills sequentially for dependent tasks
- Use them in parallel for independent tasks

### Need a skill that's not documented?

- Use `skill-creator` to generate a new skill
- Follow the template pattern from existing skills
- Add it to the quick reference for future use

---

## Contributing

As you create skills and workflows:

1. **Document new skills** in specialized-agents-and-skills.md
2. **Add task mappings** to skills-quick-reference.md
3. **Share usage examples** in skill-implementation-guide.md
4. **Keep templates updated** with lessons learned

This ensures the knowledge base grows with the project.

---

## Summary

This resources directory provides everything needed to implement a skill-based development workflow for the notion-wp plugin:

- **What skills exist** → [Specialized Agents and Skills](./specialized-agents-and-skills.md)
- **Which skill for what task** → [Skills Quick Reference](./skills-quick-reference.md)
- **How to create and use skills** → [Skill Implementation Guide](./skill-implementation-guide.md)

Use these resources throughout development to maintain consistency, quality, and efficiency.
