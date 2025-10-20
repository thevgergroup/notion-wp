# Development Principles

## Core Principles

### 1. KISS (Keep It Simple, Stupid)
- Favor simple, readable solutions over clever abstractions
- One file, one responsibility
- Avoid premature optimization
- Question every dependency before adding it

### 2. Small Incremental Changes
- **No big bang releases** - each phase must produce working, visible results
- **Full-stack increments** - Don't build entire backend then defer frontend
- Every pull request should be deployable
- Aim for daily/weekly progress that can be demoed

### 3. Proof Points & Gatekeeping
- **Phase 0 is mandatory** - Prove the basics work before proceeding
- Each phase has clear acceptance criteria that must pass before moving forward
- **Show, don't tell** - Working UI trumps backend plumbing
- Progressive disclosure: simple flows first, edge cases later

### 4. Code Quality Standards

#### File Size Limits
- **Maximum 500 lines per file** (including comments)
- If you hit the limit, refactor into smaller, focused files
- Exception: Configuration files only

#### Linting Requirements
All code must pass linting before commit:

**PHP:**
- WordPress Coding Standards (WPCS)
- PHP_CodeSniffer (phpcs)
- PHPStan level 5 minimum
- PHP-CS-Fixer for auto-formatting

**JavaScript:**
- ESLint with WordPress preset
- Prettier for formatting
- No console.log in production code

**CSS:**
- Stylelint with WordPress config
- No !important except documented exceptions

**HTML:**
- Valid HTML5
- Accessibility checks (WCAG 2.1 AA minimum)

#### Pre-commit Hooks
- All linting must pass before commit
- Auto-fix what can be auto-fixed
- Block commit on failures

## Phase 0: Proof of Concept (MANDATORY)

Before any real development, prove the fundamentals:

### Must Demonstrate:
1. **Authentication Flow**
   - User can enter Notion API token
   - Token validation works
   - Clear error messages for invalid tokens

2. **Connection Verification**
   - Show user's connected Notion workspaces
   - Display available pages/databases
   - Prove API communication works

3. **Disconnect/Reset**
   - User can disconnect and try again
   - Clean state management
   - No orphaned data

4. **Basic Admin UI**
   - Simple, clean settings page
   - Progressive disclosure of complexity
   - Works on mobile

### Gatekeeping Criteria:
- [ ] Non-technical user can authenticate successfully
- [ ] Error messages are helpful, not cryptic
- [ ] UI is intuitive without documentation
- [ ] No console errors or PHP warnings
- [ ] All linting passes

**DO NOT PROCEED** to Phase 1 until Phase 0 criteria are met.

## Development Workflow

### Branch Strategy
```
main/
├── phase-0/foundation      # Worktree 1: Auth & setup
├── phase-0/admin-ui        # Worktree 2: Settings page
└── phase-0/linting-setup   # Worktree 3: Quality tools
```

### Daily Standup Questions
1. What did I ship yesterday? (must be visible)
2. What will I ship today? (must be demoable)
3. What's blocking me?

### Definition of Done
- [ ] Code written and linted
- [ ] Tests pass (when testing is set up)
- [ ] UI is functional (for user-facing features)
- [ ] No new console errors or warnings
- [ ] Documented (inline comments + README updates)
- [ ] Reviewed (self-review minimum)
- [ ] Merged and deployed to development environment

## Anti-Patterns to Avoid

### ❌ Don't Do This:
- Building entire data layer before any UI
- "I'll add tests later"
- "It works on my machine" (use Docker setup)
- Skipping linting because "it's just a quick fix"
- Large PRs (>500 lines changed)
- Magic numbers without constants/config

### ✅ Do This Instead:
- Build one complete feature (UI + backend) at a time
- Write tests as you go (TDD when possible)
- Use shared Docker environment
- Fix linting immediately
- Small, focused PRs
- Named constants and configuration files

## Success Metrics

### Phase-Level Metrics:
- Time to complete phase (target: 1-2 weeks max per phase)
- Number of blockers encountered
- User feedback on usability

### Code Quality Metrics:
- Linting pass rate: 100% required
- Average file size: <300 lines (500 is max)
- Test coverage: TBD when testing setup complete
- Zero console errors/warnings

## Review Checklist

Before marking any phase complete:
- [ ] All acceptance criteria met
- [ ] Linting passes
- [ ] No console errors
- [ ] Can demo to non-technical user
- [ ] Documentation updated
- [ ] No files >500 lines
- [ ] Full-stack feature complete (not just backend)

## References

- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Notion API Documentation](https://developers.notion.com/)
