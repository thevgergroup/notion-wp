#!/bin/bash
# Install PCOV for code coverage

set -e

echo "Installing PCOV for code coverage..."

# Check if PCOV is already installed
if php -m | grep -q pcov; then
    echo "✅ PCOV is already installed"
    php -v | head -1
    php -m | grep pcov
    exit 0
fi

# Try to install via PECL
if command -v pecl &> /dev/null; then
    echo "Installing PCOV via PECL..."
    pecl install pcov

    # Find php.ini location
    PHP_INI=$(php --ini | grep "Loaded Configuration File" | awk '{print $4}')
    PHP_CONF_DIR=$(dirname "$PHP_INI")/conf.d

    # Create pcov.ini
    echo "Creating PCOV configuration..."
    cat > "$PHP_CONF_DIR/pcov.ini" <<EOF
extension=pcov.so
pcov.enabled=1
pcov.directory=.
EOF

    echo "✅ PCOV installed successfully"
    php -m | grep pcov
else
    echo "❌ PECL not found. Please install PCOV manually:"
    echo ""
    echo "For macOS (Homebrew):"
    echo "  pecl install pcov"
    echo ""
    echo "For Ubuntu/Debian:"
    echo "  sudo apt-get install php-pcov"
    echo ""
    echo "For Docker:"
    echo "  docker-php-ext-install pcov"
    echo ""
    exit 1
fi
