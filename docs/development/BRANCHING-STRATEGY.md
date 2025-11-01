# Branching Strategy

## Overview

This project uses a **simplified branch-based workflow** for feature development and epic/phase work. We **do not use Git worktrees** to avoid complexity and confusion.

## Branch Structure

### Main Branch
- `main` - Production-ready code
- Always deployable
- Protected branch requiring PR reviews
- All changes merged via Pull Requests

### Feature Branches
For individual features or bug fixes:

```
feature/<short-description>
fix/<bug-description>
chore/<task-description>
```

**Examples:**
- `feature/image-converter`
- `fix/email-validation`
- `chore/update-dependencies`

### Epic/Phase Branches
For larger bodies of work spanning multiple features:

```
epic/<epic-name>
phase-<number>-<name>
```

**Examples:**
- `phase-5-hierarchy-navigation`
- `epic/database-views`

## Workflow

### Starting New Work

1. **Ensure you're on main with latest changes:**
   ```bash
   git checkout main
   git pull origin main
   ```

2. **Create your branch:**
   ```bash
   # For a feature
   git checkout -b feature/image-optimization

   # For a phase
   git checkout -b phase-6-webhooks
   ```

3. **Make your changes and commit regularly:**
   ```bash
   git add .
   git commit -m "feat: add image optimization"
   ```

4. **Push to remote:**
   ```bash
   git push -u origin feature/image-optimization
   ```

### Creating a Pull Request

1. **Push your latest changes:**
   ```bash
   git push origin feature/image-optimization
   ```

2. **Create PR via GitHub CLI or web:**
   ```bash
   gh pr create --title "Add image optimization" --body "Description..."
   ```

3. **Address review feedback:**
   ```bash
   # Make changes
   git add .
   git commit -m "fix: address review feedback"
   git push origin feature/image-optimization
   ```

4. **Merge when approved:**
   - Use "Squash and merge" for feature branches
   - Use "Create a merge commit" for epic/phase branches
   - Delete branch after merging

### Working with Docker

**Single Docker environment on main branch:**

```bash
# Start Docker environment
make up

# Stop Docker environment
make down

# Clean everything (containers + volumes)
make clean
```

**Switching branches:**

```bash
# Stop current environment
make down

# Switch branches
git checkout feature/new-feature

# Start environment (rebuilds if needed)
make up
```

## Best Practices

### Branch Naming
- ✅ Use kebab-case: `feature/block-converter`
- ✅ Be descriptive: `fix/email-validation-error`
- ❌ Avoid: `feature/update`, `my-branch`, `test`

### Commits
- Follow [Conventional Commits](https://www.conventionalcommits.org/)
- Types: `feat`, `fix`, `docs`, `chore`, `test`, `refactor`
- Write clear commit messages
- Commit frequently with logical units of work

### Pull Requests
- Keep PRs focused and reasonably sized
- Write clear descriptions with context
- Include test coverage
- Update documentation as needed
- Link related issues

### Keeping Branches Updated

**Rebase workflow (recommended for feature branches):**
```bash
git checkout main
git pull origin main
git checkout feature/my-feature
git rebase main
git push --force-with-lease origin feature/my-feature
```

**Merge workflow (for long-running epic branches):**
```bash
git checkout epic/database-views
git merge main
git push origin epic/database-views
```

## Branch Lifecycle

### Feature Branch Lifecycle
1. Create from `main`
2. Develop and commit
3. Push to remote
4. Create Pull Request
5. Review and iterate
6. Merge to `main` (squash)
7. Delete branch

**Typical lifespan:** 1-5 days

### Epic/Phase Branch Lifecycle
1. Create from `main`
2. Develop multiple features
3. Merge `main` into epic regularly (weekly)
4. Push to remote frequently
5. Create Pull Request when complete
6. Review and iterate
7. Merge to `main` (merge commit)
8. Delete branch

**Typical lifespan:** 1-4 weeks

## Commands Reference

### Quick Start
```bash
# Start work on new feature
git checkout main && git pull && git checkout -b feature/my-feature

# Commit and push
git add . && git commit -m "feat: description" && git push -u origin feature/my-feature

# Create PR
gh pr create

# After PR merged, cleanup
git checkout main && git pull && git branch -d feature/my-feature
```

### Check Status
```bash
# See current branch
git branch

# See all branches
git branch -a

# See branch status
git status
```

### Cleanup
```bash
# Delete merged local branch
git branch -d feature/my-feature

# Delete remote branch (after PR merged)
git push origin --delete feature/my-feature

# Prune deleted remote branches
git fetch --prune
```

## Common Scenarios

### "I need to switch to work on something else"

```bash
# Commit current work
git add .
git commit -m "wip: work in progress"

# Switch to main and create new branch
git checkout main
git pull origin main
git checkout -b feature/urgent-fix

# When ready to return
git checkout feature/my-feature
```

### "Main has been updated and I need the changes"

```bash
# Save your work
git add .
git commit -m "wip: save point"

# Get latest from main
git checkout main
git pull origin main

# Rebase your branch
git checkout feature/my-feature
git rebase main

# If conflicts, resolve them and continue
git add .
git rebase --continue
```

### "I want to test someone else's branch"

```bash
# Fetch all branches
git fetch origin

# Checkout their branch
git checkout -b feature/their-feature origin/feature/their-feature

# Start Docker environment
make up

# When done
git checkout main
git branch -d feature/their-feature
```

## Migration from Worktrees

If you have existing worktrees, clean them up:

```bash
# List existing worktrees
git worktree list

# Remove each worktree
git worktree remove /path/to/worktree

# Delete associated branches if merged
git branch -d branch-name
```

## FAQ

**Q: Why not use worktrees?**
A: Worktrees add complexity with multiple Docker environments, filesystem confusion, and cognitive overhead. A simple branch workflow is faster and less error-prone.

**Q: How do I work on multiple features at once?**
A: Commit your current work (even as WIP) and switch branches. Git makes switching fast and safe.

**Q: What about Docker containers when switching branches?**
A: Stop Docker (`make down`), switch branches, and restart (`make up`). This ensures database schema and code are in sync.

**Q: Can I still use worktrees if I want?**
A: While possible, it's not recommended. The project is optimized for single-branch workflow.

## Related Documentation

- [Docker Setup](../development/phases/DOCKER-QUICKSTART.md)
- [Contributing Guidelines](../../CONTRIBUTING.md)
- [Commit Message Format](https://www.conventionalcommits.org/)
