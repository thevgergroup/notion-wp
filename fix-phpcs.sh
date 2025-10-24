#!/bin/bash
# Fix PHPCS errors in Media classes

# Auto-fix what can be auto-fixed
echo "Running phpcbf on all Media files..."
./vendor/bin/phpcbf plugin/src/Media/ --standard=WordPress 2>&1

# Run final check
echo -e "\n\nFinal PHPCS check (excluding filename warnings)..."
./vendor/bin/phpcs plugin/src/Media/ --standard=WordPress 2>&1 | grep -v "Filenames should be" | grep -v "Class file names"
