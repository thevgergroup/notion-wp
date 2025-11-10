#!/bin/bash

# Script to bump version across all files
# Usage: ./scripts/bump-version.sh <new-version>
# Example: ./scripts/bump-version.sh 1.0.1

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if version argument is provided
if [ -z "$1" ]; then
    echo -e "${RED}Error: Version number required${NC}"
    echo "Usage: ./scripts/bump-version.sh <version>"
    echo "Example: ./scripts/bump-version.sh 1.0.1"
    exit 1
fi

NEW_VERSION=$1

# Validate semantic versioning format
if ! [[ $NEW_VERSION =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    echo -e "${RED}Error: Invalid version format${NC}"
    echo "Version must follow semantic versioning: MAJOR.MINOR.PATCH"
    echo "Example: 1.0.1"
    exit 1
fi

echo -e "${YELLOW}Bumping version to ${NEW_VERSION}...${NC}\n"

# Get current version from plugin file
CURRENT_VERSION=$(grep "Version:" plugin/vger-sync-for-notion.php | awk '{print $3}')
echo -e "Current version: ${CURRENT_VERSION}"
echo -e "New version: ${NEW_VERSION}\n"

# Confirm with user
read -p "Continue? (y/n) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Cancelled"
    exit 1
fi

# Update plugin/vger-sync-for-notion.php (header)
echo -e "${YELLOW}Updating plugin/vger-sync-for-notion.php...${NC}"
sed -i.bak "s/Version: ${CURRENT_VERSION}/Version: ${NEW_VERSION}/" plugin/vger-sync-for-notion.php
rm plugin/vger-sync-for-notion.php.bak

# Update plugin/vger-sync-for-notion.php (constant)
sed -i.bak "s/VGER_SYNC_VERSION', '${CURRENT_VERSION}/VGER_SYNC_VERSION', '${NEW_VERSION}/" plugin/vger-sync-for-notion.php
rm plugin/vger-sync-for-notion.php.bak

# Update package.json
echo -e "${YELLOW}Updating package.json...${NC}"
sed -i.bak "s/\"version\": \"${CURRENT_VERSION}\"/\"version\": \"${NEW_VERSION}\"/" package.json
rm package.json.bak

# Update plugin/readme.txt
echo -e "${YELLOW}Updating plugin/readme.txt...${NC}"
sed -i.bak "s/Stable tag: ${CURRENT_VERSION}/Stable tag: ${NEW_VERSION}/" plugin/readme.txt
rm plugin/readme.txt.bak

echo -e "\n${GREEN}✓ Version bumped successfully!${NC}\n"

echo "Next steps:"
echo "1. Review changes: git diff"
echo "2. Commit changes: git add -A && git commit -m \"chore: bump version to ${NEW_VERSION}\""
echo "3. Create tag: git tag -a v${NEW_VERSION} -m \"Release v${NEW_VERSION}\""
echo "4. Push changes: git push && git push --tags"
echo ""
echo "GitHub Actions will automatically create a release when the tag is pushed."
