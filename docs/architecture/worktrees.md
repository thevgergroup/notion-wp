# Worktrees Directory

This directory contains git worktrees for working on multiple branches simultaneously.

## Creating a New Worktree

To create a new worktree for a feature branch:

```bash
# From the main repository directory
git worktree add worktrees/feature-name -b feature-name

# Or to check out an existing branch
git worktree add worktrees/phase-2 phase-2
```

## Listing Worktrees

```bash
git worktree list
```

## Removing a Worktree

```bash
# Remove the worktree
git worktree remove worktrees/feature-name

# Delete the branch (if merged)
git branch -d feature-name
```

## Benefits of This Structure

- All worktrees are organized under `notion-wp/worktrees/`
- Easy to find and manage multiple branches
- Clean parent directory structure
- Worktrees are automatically ignored by git

## Current Worktrees

Check `git worktree list` for the current active worktrees.
