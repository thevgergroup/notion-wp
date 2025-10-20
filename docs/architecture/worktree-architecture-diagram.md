# Git Worktree Architecture Diagram

## Visual Overview

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         MAIN REPOSITORY (notion-wp/)                        │
│                                                                             │
│  ┌─────────────┐  ┌──────────────┐  ┌─────────────┐  ┌──────────────────┐ │
│  │   docker/   │  │   plugin/    │  │   scripts/  │  │      docs/       │ │
│  │             │  │              │  │             │  │                  │ │
│  │ compose.yml │  │    src/      │  │ setup-wt.sh │  │  architecture/   │ │
│  │ traefik/    │  │   assets/    │  │ teardown.sh │  │  product/        │ │
│  │ config/     │  │  templates/  │  │             │  │  requirements/   │ │
│  │             │  │   tests/     │  │             │  │                  │ │
│  └─────────────┘  └──────────────┘  └─────────────┘  └──────────────────┘ │
│                                                                             │
│  ┌───────────────┐                                                          │
│  │ .env.template │  (Shared configs, committed to Git)                     │
│  │  Makefile     │                                                          │
│  │ .gitignore    │                                                          │
│  └───────────────┘                                                          │
└─────────────────────────────────────────────────────────────────────────────┘
                                      │
                                      │ git worktree add
                                      │
            ┌─────────────────────────┼─────────────────────────┐
            │                         │                         │
            ▼                         ▼                         ▼
┌───────────────────────┐ ┌───────────────────────┐ ┌───────────────────────┐
│  WORKTREE: main       │ │ WORKTREE: feature-sync │ │ WORKTREE: feature-nav │
│  (branch: main)       │ │ (branch: feature-sync) │ │ (branch: feature-nav) │
│                       │ │                        │ │                       │
│ ┌───────────────────┐ │ │ ┌───────────────────┐  │ │ ┌───────────────────┐ │
│ │ .env (GITIGNORED) │ │ │ │ .env (GITIGNORED) │  │ │ │ .env (GITIGNORED) │ │
│ │─────────────────  │ │ │ │─────────────────  │  │ │ │─────────────────  │ │
│ │ PROJECT=notionwp_ │ │ │ │ PROJECT=notionwp_ │  │ │ │ PROJECT=notionwp_ │ │
│ │        main       │ │ │ │     feature_sync  │  │ │ │     feature_nav   │ │
│ │ HOST=main.local   │ │ │ │ HOST=sync.local   │  │ │ │ HOST=nav.local    │ │
│ │      test.me      │ │ │ │      test.me      │  │ │ │      test.me      │ │
│ │ HTTP_PORT=8080    │ │ │ │ HTTP_PORT=8081    │  │ │ │ HTTP_PORT=8082    │ │
│ │ DB_PORT=3306      │ │ │ │ DB_PORT=3307      │  │ │ │ DB_PORT=3308      │ │
│ │ DB_NAME=wp_main   │ │ │ │ DB_NAME=wp_sync   │  │ │ │ DB_NAME=wp_nav    │ │
│ └───────────────────┘ │ │ └───────────────────┘  │ │ └───────────────────┘ │
│                       │ │                        │ │                       │
│ plugin/ (shared src)  │ │ plugin/ (shared src)   │ │ plugin/ (shared src)  │
│ ├── config/           │ │ ├── config/            │ │ ├── config/           │
│ │   ├── block-maps.  │ │ │   ├── block-maps.    │ │ │   ├── block-maps.   │
│ │   │      json (WT) │ │ │   │      json (WT)   │ │ │   │      json (WT)  │
│ │   └── field-maps.  │ │ │   └── field-maps.    │ │ │   └── field-maps.   │
│ │          json (WT) │ │ │          json (WT)   │ │ │          json (WT)  │
│ ├── assets/dist/     │ │ ├── assets/dist/       │ │ ├── assets/dist/      │
│ │   (GITIGNORED)     │ │ │   (GITIGNORED)       │ │ │   (GITIGNORED)      │
│                       │ │                        │ │                       │
│ logs/ (GITIGNORED)    │ │ logs/ (GITIGNORED)     │ │ logs/ (GITIGNORED)    │
└───────────┬───────────┘ └───────────┬────────────┘ └───────────┬───────────┘
            │                         │                           │
            │ docker compose          │ docker compose            │ docker compose
            │ -f ../docker/compose.yml│ -f ../docker/compose.yml  │ -f ../docker/compose.yml
            │                         │                           │
            ▼                         ▼                           ▼
┌───────────────────────┐ ┌───────────────────────┐ ┌───────────────────────┐
│   DOCKER STACK #1     │ │   DOCKER STACK #2     │ │   DOCKER STACK #3     │
│                       │ │                        │ │                       │
│ ┌─────────────────┐   │ │ ┌─────────────────┐    │ │ ┌─────────────────┐   │
│ │ notionwp_main_wp│   │ │ │notionwp_sync_wp │    │ │ │ notionwp_nav_wp │   │
│ │ (WP Container)  │   │ │ │ (WP Container)  │    │ │ │ (WP Container)  │   │
│ │                 │   │ │ │                 │    │ │ │                 │   │
│ │ /wp-content/    │   │ │ │ /wp-content/    │    │ │ │ /wp-content/    │   │
│ │  plugins/       │◄──┼─┼─┤  plugins/       │◄───┼─┼─┤  plugins/       │   │
│ │   notion-sync/  │   │ │ │   notion-sync/  │    │ │ │   notion-sync/  │   │
│ │   (mounted from │   │ │ │   (mounted from │    │ │ │   (mounted from │   │
│ │    worktree)    │   │ │ │    worktree)    │    │ │ │    worktree)    │   │
│ └─────────────────┘   │ │ └─────────────────┘    │ │ └─────────────────┘   │
│                       │ │                        │ │                       │
│ ┌─────────────────┐   │ │ ┌─────────────────┐    │ │ ┌─────────────────┐   │
│ │ notionwp_main_db│   │ │ │notionwp_sync_db │    │ │ │ notionwp_nav_db │   │
│ │ (MariaDB)       │   │ │ │ (MariaDB)       │    │ │ │ (MariaDB)       │   │
│ │                 │   │ │ │                 │    │ │ │                 │   │
│ │ Port: 3306      │   │ │ │ Port: 3307      │    │ │ │ Port: 3308      │   │
│ │ DB: wp_main     │   │ │ │ DB: wp_sync     │    │ │ │ DB: wp_nav      │   │
│ └─────────────────┘   │ │ └─────────────────┘    │ │ └─────────────────┘   │
│                       │ │                        │ │                       │
│ ┌─────────────────┐   │ │ ┌─────────────────┐    │ │ ┌─────────────────┐   │
│ │   VOLUMES:      │   │ │ │   VOLUMES:      │    │ │ │   VOLUMES:      │   │
│ │ notionwp_main_  │   │ │ │ notionwp_sync_  │    │ │ │ notionwp_nav_   │   │
│ │   db_data       │   │ │ │   db_data       │    │ │ │   db_data       │   │
│ │ notionwp_main_  │   │ │ │ notionwp_sync_  │    │ │ │ notionwp_nav_   │   │
│ │   wp_data       │   │ │ │   wp_data       │    │ │ │   wp_data       │   │
│ └─────────────────┘   │ │ └─────────────────┘    │ │ └─────────────────┘   │
└───────────┬───────────┘ └───────────┬────────────┘ └───────────┬───────────┘
            │                         │                           │
            │                         │                           │
            └─────────────────────────┼───────────────────────────┘
                                      │
                                      ▼
                        ┌─────────────────────────┐
                        │   TRAEFIK PROXY         │
                        │   (Shared Instance)     │
                        │                         │
                        │   Port: 80              │
                        │   Dashboard: 8080       │
                        │                         │
                        │   Routes:               │
                        │   main.localtest.me     │◄─── Browser
                        │     → notionwp_main_wp  │
                        │   sync.localtest.me     │◄─── Browser
                        │     → notionwp_sync_wp  │
                        │   nav.localtest.me      │◄─── Browser
                        │     → notionwp_nav_wp   │
                        └─────────────────────────┘
```

## Data Flow

### 1. Code Changes (Git Flow)

```
Developer
   │
   ├─► Edit file in worktree: feature-sync/plugin/src/Sync/SyncOrchestrator.php
   │
   ├─► Git tracks change in feature-sync branch
   │
   ├─► File immediately available in Docker container (volume mount)
   │
   └─► Test in isolated environment: http://sync.localtest.me
```

### 2. Docker Isolation Mechanism

```
┌──────────────────────────────────────────────────────────────┐
│                    Docker Compose                            │
│                                                              │
│  Read .env file from worktree                                │
│  ├─ COMPOSE_PROJECT_NAME=notionwp_feature_sync              │
│  ├─ WP_SITE_HOST=feature-sync.localtest.me                  │
│  └─ DB_NAME=wp_feature_sync                                 │
│                                                              │
│  Generate unique resource names:                             │
│  ├─ Containers:  notionwp_feature_sync_wp                   │
│  │               notionwp_feature_sync_db                   │
│  ├─ Networks:    notionwp_feature_sync_internal             │
│  ├─ Volumes:     notionwp_feature_sync_db_data              │
│  │               notionwp_feature_sync_wp_data              │
│  └─ Labels:      traefik.http.routers.notionwp_feature_sync │
│                                                              │
│  ✓ No conflicts with other worktrees                        │
│  ✓ Complete isolation                                        │
└──────────────────────────────────────────────────────────────┘
```

### 3. Shared vs. Isolated Resources

```
┌─────────────────────────────────────────────────────────────────┐
│                     SHARED (Across Worktrees)                   │
├─────────────────────────────────────────────────────────────────┤
│  • docker/compose.yml                                           │
│  • docker/config/ (PHP settings, etc.)                          │
│  • Traefik container (single instance)                          │
│  • Traefik network                                              │
│  • plugin/src/ source code (via Git)                            │
│  • plugin/assets/src/ (source files)                            │
│  • plugin/tests/                                                │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                    ISOLATED (Per Worktree)                      │
├─────────────────────────────────────────────────────────────────┤
│  • .env file                                                    │
│  • Docker containers (wp, db, wpcli)                            │
│  • Docker volumes (database data, WordPress files)              │
│  • Docker internal network                                      │
│  • plugin/config/*.json (runtime configs)                       │
│  • plugin/assets/dist/ (compiled assets)                        │
│  • plugin/vendor/ (Composer deps)                               │
│  • plugin/node_modules/ (NPM deps)                              │
│  • logs/                                                        │
│  • WordPress installation                                       │
│  • Database content                                             │
└─────────────────────────────────────────────────────────────────┘
```

## Network Architecture

```
                        Internet / Local Network
                                  │
                                  │
                                  ▼
                        ┌──────────────────┐
                        │   Port 80        │
                        │   Traefik Proxy  │
                        └────────┬─────────┘
                                 │
                 ┌───────────────┼───────────────┐
                 │               │               │
                 ▼               ▼               ▼
          Host header:    Host header:    Host header:
       main.localtest.me sync.localtest.me nav.localtest.me
                 │               │               │
                 ▼               ▼               ▼
        ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
        │ WP Container │ │ WP Container │ │ WP Container │
        │ notionwp_    │ │ notionwp_    │ │ notionwp_    │
        │   main_wp    │ │   sync_wp    │ │   nav_wp     │
        └──────┬───────┘ └──────┬───────┘ └──────┬───────┘
               │                │                │
               │ Internal       │ Internal       │ Internal
               │ Network        │ Network        │ Network
               │                │                │
               ▼                ▼                ▼
        ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
        │ DB Container │ │ DB Container │ │ DB Container │
        │ notionwp_    │ │ notionwp_    │ │ notionwp_    │
        │   main_db    │ │   sync_db    │ │   nav_db     │
        └──────────────┘ └──────────────┘ └──────────────┘
             │ :3306          │ :3307          │ :3308
             │                │                │
             └────────────────┴────────────────┘
                              │
                              ▼
                        Host Machine
                   (External DB Access)
```

## File System Layout

```
~/Projects/thevgergroup/
└── notion-wp/                      # Main repo (could be bare)
    ├── .git/
    ├── docker/                     # Shared Docker infrastructure
    ├── plugin/                     # Plugin source (shared)
    ├── scripts/
    ├── docs/
    ├── .env.template
    ├── Makefile
    └── .gitignore

~/Projects/thevgergroup/
├── main/                           # Worktree #1
│   ├── .env                        # → notionwp_main, port 8080, db: wp_main
│   ├── plugin/                     # → same files as notion-wp/plugin/
│   ├── logs/
│   └── .git → ../notion-wp/.git/worktrees/main/
│
├── feature-sync/                   # Worktree #2
│   ├── .env                        # → notionwp_sync, port 8081, db: wp_sync
│   ├── plugin/                     # → same files, different branch checkout
│   ├── logs/
│   └── .git → ../notion-wp/.git/worktrees/feature-sync/
│
└── feature-nav/                    # Worktree #3
    ├── .env                        # → notionwp_nav, port 8082, db: wp_nav
    ├── plugin/                     # → same files, different branch checkout
    ├── logs/
    └── .git → ../notion-wp/.git/worktrees/feature-nav/
```

## Plugin Mount Strategy

```
┌────────────────────────────────────────────────────────────┐
│  WordPress Container (notionwp_main_wp)                    │
│                                                            │
│  /var/www/html/                                            │
│  ├── wp-admin/                                             │
│  ├── wp-includes/                                          │
│  ├── wp-content/                                           │
│  │   ├── themes/                                           │
│  │   ├── uploads/                                          │
│  │   └── plugins/                                          │
│  │       └── notion-sync/  ◄─────────────────────┐        │
│  │           (MOUNTED FROM HOST)                  │        │
│  └── ...                                          │        │
└───────────────────────────────────────────────────┼────────┘
                                                    │
                                                    │ Bind Mount
                                                    │ (live sync)
                                                    │
┌───────────────────────────────────────────────────┼────────┐
│  Host Machine (worktree)                          │        │
│                                                   │        │
│  ~/Projects/notion-wp/main/                       │        │
│  └── plugin/  ────────────────────────────────────┘        │
│      ├── notion-sync.php                                   │
│      ├── src/                                              │
│      │   ├── Admin/                                        │
│      │   ├── Sync/                                         │
│      │   └── ...                                           │
│      ├── assets/                                           │
│      ├── templates/                                        │
│      └── ...                                               │
│                                                            │
│  Changes to files here are IMMEDIATELY visible             │
│  in the container (no rebuild required)                    │
└────────────────────────────────────────────────────────────┘
```

## Workflow Example: Feature Development

```
┌─────────────────────────────────────────────────────────────┐
│  Step 1: Create Worktree                                    │
└─────────────────────────────────────────────────────────────┘
$ ./scripts/setup-worktree.sh feature-media 8083 3309
  ├─► Creates git worktree: feature-media branch
  ├─► Generates .env with unique values
  ├─► Spins up Docker stack (WP + DB)
  ├─► Installs WordPress
  ├─► Installs dependencies
  └─► Builds assets

┌─────────────────────────────────────────────────────────────┐
│  Step 2: Develop Feature                                    │
└─────────────────────────────────────────────────────────────┘
$ cd ../feature-media
$ vim plugin/src/Media/MediaImporter.php
  ├─► Edit code
  ├─► File auto-synced to container
  └─► Test at http://feature-media.localtest.me

┌─────────────────────────────────────────────────────────────┐
│  Step 3: Concurrent Development (Different Terminal)        │
└─────────────────────────────────────────────────────────────┘
$ cd ../feature-sync
$ vim plugin/src/Sync/SyncOrchestrator.php
  ├─► Edit different feature
  ├─► Completely isolated environment
  └─► Test at http://feature-sync.localtest.me

┌─────────────────────────────────────────────────────────────┐
│  Step 4: Commit & Merge                                     │
└─────────────────────────────────────────────────────────────┘
$ cd ../feature-media
$ git add plugin/src/Media/MediaImporter.php
$ git commit -m "Add media import with deduplication"
$ git push origin feature-media
  └─► Create PR or merge to main

┌─────────────────────────────────────────────────────────────┐
│  Step 5: Cleanup                                            │
└─────────────────────────────────────────────────────────────┘
$ ./scripts/teardown-worktree.sh feature-media --delete-branch
  ├─► Stops Docker containers
  ├─► Removes volumes (database + WordPress files)
  ├─► Deletes worktree
  └─► Optionally deletes branch
```

## Benefits of This Architecture

1. **Complete Isolation**: Each worktree has its own WordPress + database
2. **No Port Conflicts**: Traefik handles hostname-based routing
3. **Parallel Development**: Work on multiple features simultaneously
4. **Fast Testing**: No need to switch branches or reset databases
5. **Clean Separation**: Shared code via Git, isolated config via .env
6. **Easy Cleanup**: Single command to teardown entire environment
7. **Scalable**: Add unlimited worktrees without infrastructure changes

## Resource Naming Convention

```
Pattern: ${COMPOSE_PROJECT_NAME}_<resource>

Example for worktree "feature-sync":
  COMPOSE_PROJECT_NAME=notionwp_feature_sync

  Containers:
    ├─ notionwp_feature_sync_wp
    ├─ notionwp_feature_sync_db
    └─ notionwp_feature_sync_wpcli

  Volumes:
    ├─ notionwp_feature_sync_db_data
    └─ notionwp_feature_sync_wp_data

  Network:
    └─ notionwp_feature_sync_internal

  Traefik Router:
    └─ notionwp_feature_sync
```

This naming ensures zero conflicts between worktrees running concurrently.
