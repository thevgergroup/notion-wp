# Phase 5.8 DevOps Cleanup - Audit Findings

Generated: 2025-11-02

## 1. Composer.json Analysis

### Current State

We have **TWO** composer.json files:

#### Root: `/composer.json`
- **Purpose**: Development environment, testing, and code quality tools
- **License**: GPL-2.0-or-later
- **Vendor Dir**: `plugin/vendor` (installs into plugin directory)
- **Key Features**:
  - Dev dependencies: PHPUnit, PHPStan, PHPCS, PHP-CS-Fixer
  - Autoloading: `NotionWP\` and `NotionSync\` → `plugin/src/`
  - Comprehensive lint/test scripts
  - Post-install/update hooks with friendly messages

**Dependencies:**
- `woocommerce/action-scheduler: ^3.7`

**Dev Dependencies:**
- brain/monkey, mockery (testing)
- phpunit/phpunit
- phpstan + extensions (static analysis)
- squizlabs/php_codesniffer + wpcs (code style)
- friendsofphp/php-cs-fixer (alternative formatter)

#### Plugin: `/plugin/composer.json`
- **Purpose**: Plugin standalone deployment
- **License**: GPL-3.0-or-later (DIFFERENT!)
- **Vendor Dir**: Default (plugin/vendor)
- **Key Features**:
  - Minimal dependencies (production only)
  - Autoloading: `NotionSync\` and `NotionWP\` → `src/`
  - Limited scripts (points to parent directory for PHPUnit)

**Dependencies:**
- `woocommerce/action-scheduler: ^3.9` (DIFFERENT VERSION!)

### Issues Identified

1. **License Inconsistency**: Root uses GPL-2.0, plugin uses GPL-3.0
2. **Version Mismatch**: Action Scheduler ^3.7 vs ^3.9
3. **Duplicate Autoload Configuration**: Both define same namespaces
4. **Script Confusion**: Plugin scripts reference `../phpunit.xml` (parent directory)
5. **No Clear Documentation**: When to use which composer.json

### Recommendations

**KEEP BOTH FILES** but clarify their roles:

#### Root composer.json
- **For**: Local development, CI/CD, testing
- **Contains**: All dev tools, testing frameworks, linters
- **Vendor Dir**: `plugin/vendor` (so tools can analyze plugin code)
- **Used By**: Developers, GitHub Actions, local testing

#### Plugin composer.json
- **For**: WordPress.org deployment, standalone distribution
- **Contains**: Only production dependencies
- **Vendor Dir**: Default `vendor/` (standard WordPress plugin structure)
- **Used By**: WordPress.org SVN, plugin zip distribution

#### Actions Needed
1. Standardize license to GPL-3.0-or-later (WordPress.org requirement)
2. Sync Action Scheduler version to ^3.9
3. Add clear comments explaining each file's purpose
4. Document in DEVELOPMENT.md when to use `composer install` vs `cd plugin && composer install`

---

## 2. Documentation Files Audit

### Files in Project Root (15 files)

#### Keep in Root - User-Facing Documentation
1. ✅ **README.md** - Primary project documentation
2. ✅ **CHANGELOG.md** - Version history
3. ✅ **CONTRIBUTING.md** - Contribution guidelines
4. ✅ **DEVELOPMENT.md** - Developer setup guide
5. ✅ **CLAUDE.md** - AI assistant instructions

#### Move to docs/archive/ - Implementation History
6. 📦 **CACHING_IMPLEMENTATION.md** - Phase 4 implementation notes
7. 📦 **CHILD-DATABASE-CONVERTER-CHANGES.md** - Phase 5.2 changes
8. 📦 **DYNAMIC_BLOCK_FIX_SUMMARY.md** - Phase 5.3 bug fix
9. 📦 **FILES-CREATED.md** - Old file tracking (superseded by git)
10. 📦 **IMPLEMENTATION-SUMMARY.md** - Generic implementation notes
11. 📦 **PHASE-5.3-BLOCK-IMPLEMENTATION.md** - Phase 5.3 work log
12. 📦 **PHASE-5.3-CHECKLIST.md** - Phase 5.3 checklist
13. 📦 **PHASE-5.3-IMPLEMENTATION-SUMMARY.md** - Phase 5.3 summary
14. 📦 **PHASE-5.3.4-SUMMARY.md** - Phase 5.3.4 summary
15. 📦 **TESTING-CHECKLIST.md** - Old testing checklist

### Proposed Structure

```
/
├── README.md                    # Keep - main documentation
├── CHANGELOG.md                 # Keep - version history
├── CONTRIBUTING.md              # Keep - how to contribute
├── DEVELOPMENT.md               # Keep - setup guide
├── CLAUDE.md                    # Keep - AI instructions
└── docs/
    ├── archive/
    │   ├── phase-4/
    │   │   └── CACHING_IMPLEMENTATION.md
    │   ├── phase-5.2/
    │   │   └── CHILD-DATABASE-CONVERTER-CHANGES.md
    │   ├── phase-5.3/
    │   │   ├── BLOCK-IMPLEMENTATION.md
    │   │   ├── CHECKLIST.md
    │   │   ├── IMPLEMENTATION-SUMMARY.md
    │   │   ├── DYNAMIC_BLOCK_FIX_SUMMARY.md
    │   │   └── 5.3.4-SUMMARY.md
    │   └── legacy/
    │       ├── FILES-CREATED.md
    │       ├── IMPLEMENTATION-SUMMARY.md
    │       └── TESTING-CHECKLIST.md
    ├── plans/          # Already exists with main-plan.md
    ├── product/        # Already exists with prd.md
    └── requirements/   # Already exists with requirements.md
```

---

## 3. Config Files Audit

### Config Files in Root (13 files)

#### Essential - Keep and Verify Working
1. ✅ **.editorconfig** - Editor formatting rules
2. ✅ **.env** - Docker environment (gitignored, user-created)
3. ✅ **.env.example** - Docker environment template
4. ✅ **.env.template** - Docker environment template (duplicate?)
5. ✅ **.php-cs-fixer.php** - PHP-CS-Fixer rules

#### Node/NPM Related - Verify Usage
6. ❓ **.eslintrc.json** - ESLint configuration (is JS being linted?)
7. ❓ **.npmrc** - NPM configuration (npm install working?)
8. ❓ **.prettierignore** - Prettier ignore patterns
9. ❓ **.prettierrc** - Prettier formatting rules
10. ❓ **.stylelintrc.json** - Stylelint configuration (CSS linting?)

#### MCP/AI Related
11. ✅ **.mcp.json** - MCP server configuration (Claude Code)
12. ❓ **.serenaignore** - Serena ignore patterns (is Serena used?)

#### Build Artifacts - Remove from Git
13. 🗑️ **.phpunit.result.cache** - PHPUnit cache (should be gitignored)

### Verification Tests Needed

```bash
# Test Node linting configs
npm run lint           # Does this work?
npm run format         # Does this work?

# Test PHP configs
composer lint:phpcs    # Uses .phpcs.xml.dist
composer lint:php-cs-fixer  # Uses .php-cs-fixer.php

# Check if Serena is configured
docker compose ps | grep serena

# Verify .env templates
diff .env.example .env.template  # Are these duplicates?
```

### Actions Needed

1. **Test all linting commands** to verify configs are working
2. **Check package.json scripts** for ESLint, Prettier, Stylelint usage
3. **Remove .phpunit.result.cache** and add to .gitignore
4. **Consolidate .env.example and .env.template** if duplicates
5. **Document each config file's purpose** in DEVELOPMENT.md
6. **Remove or document .serenaignore** if Serena isn't being used

---

## 4. Make vs Composer Commands

### Current Command Overlap

| Task | Make Command | Composer Command | Location |
|------|-------------|------------------|----------|
| Run linters | - | `composer lint` | Root |
| Run PHPCS | - | `composer lint:phpcs` | Root |
| Run PHPStan | - | `composer lint:phpstan` | Root |
| Auto-fix code | - | `composer lint:fix` | Root |
| Run tests | - | `composer test` | Root/Plugin |
| Start Docker | `make up` | - | Root |
| Install WordPress | `make install` | - | Root |
| WP-CLI commands | `make wp ARGS="..."` | - | Root |
| View logs | `make logs` | - | Root |

### Recommendation: Clear Separation

#### Use Make For:
- **Docker operations**: `up`, `down`, `restart`, `clean`
- **WordPress management**: `install`, `wp`, `activate`, `deactivate`
- **Environment operations**: `shell`, `logs`, `status`
- **Database operations**: `db-export`, `db-import`, `reset-wp`
- **WP-Cron operations**: `cron`, `cron-status`, `cron-loop`

#### Use Composer For:
- **PHP code quality**: `lint`, `lint:phpcs`, `lint:phpstan`
- **PHP auto-fixing**: `lint:fix`, `lint:phpcbf`
- **PHP testing**: `test`, `test:unit`
- **Dependency management**: `install`, `update`, `require`

#### Use NPM For:
- **Asset building**: `npm run build`, `npm run watch`
- **JS/CSS linting**: `npm run lint` (if configured)
- **JS/CSS formatting**: `npm run format` (if configured)

---

## 5. Summary of Actions

### Immediate Tasks
1. ✅ Standardize Action Scheduler version to ^3.9
2. ✅ Standardize license to GPL-3.0-or-later
3. ✅ Add explanatory comments to both composer.json files
4. ✅ Move 9 implementation docs to docs/archive/
5. ✅ Test all linting config files
6. ✅ Remove .phpunit.result.cache and add to .gitignore
7. ✅ Consolidate .env.example and .env.template if duplicates
8. ✅ Document command usage in DEVELOPMENT.md

### Documentation Updates Needed
- DEVELOPMENT.md: Add "Command Reference" section
- composer.json: Add header comments explaining purpose
- .gitignore: Add .phpunit.result.cache

### Testing Required
- Verify all composer scripts work
- Verify all make commands work
- Test npm scripts if they exist
- Verify all linting configs are functional
