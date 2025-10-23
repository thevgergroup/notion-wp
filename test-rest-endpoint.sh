#!/bin/bash
# Test REST API endpoint with proper WordPress authentication

echo "Testing Notion Sync REST API endpoint..."
echo ""

# Get WordPress login cookie
echo "1. Getting authentication cookie..."
COOKIE=$(curl -s -c - -X POST \
  "http://phase3.localtest.me/wp-login.php" \
  -d "log=admin&pwd=admin123&wp-submit=Log+In" \
  | grep wordpress_logged_in | awk '{print $7}')

if [ -z "$COOKIE" ]; then
  echo "❌ Failed to get authentication cookie"
  echo "   Make sure WordPress admin credentials are: admin/admin123"
  exit 1
fi

echo "✅ Got authentication cookie"
echo ""

# Get WordPress nonce
echo "2. Getting REST API nonce..."
NONCE=$(curl -s -b "wordpress_logged_in_${COOKIE}=${COOKIE}" \
  "http://phase3.localtest.me/wp-admin/admin.php?page=notion-sync" \
  | grep -o "restNonce\":\"[^\"]*" | cut -d'"' -f3)

if [ -z "$NONCE" ]; then
  echo "⚠️  Could not extract nonce from page, using cookie auth only"
else
  echo "✅ Got REST nonce: ${NONCE:0:20}..."
fi
echo ""

# Test endpoint without parameters
echo "3. Testing endpoint: /?rest_route=/notion-sync/v1/sync-status"
RESPONSE=$(curl -s -b "wordpress_logged_in_${COOKIE}=${COOKIE}" \
  -H "X-WP-Nonce: ${NONCE}" \
  "http://phase3.localtest.me/?rest_route=/notion-sync/v1/sync-status")

echo "Response:"
echo "$RESPONSE" | jq '.' 2>/dev/null || echo "$RESPONSE"
echo ""

# Check if successful
if echo "$RESPONSE" | grep -q '"pages"'; then
  echo "✅ Endpoint responding correctly!"
else
  echo "❌ Endpoint not returning expected format"
fi
echo ""

# Test with batch_id parameter
echo "4. Testing with batch_id parameter..."
RESPONSE2=$(curl -s -b "wordpress_logged_in_${COOKIE}=${COOKIE}" \
  -H "X-WP-Nonce: ${NONCE}" \
  "http://phase3.localtest.me/?rest_route=/notion-sync/v1/sync-status&batch_id=test_batch_123")

echo "Response:"
echo "$RESPONSE2" | jq '.' 2>/dev/null || echo "$RESPONSE2"
echo ""

if echo "$RESPONSE2" | grep -q '"batch"'; then
  echo "✅ Batch parameter working!"
else
  echo "⚠️  Batch parameter response differs (may be expected if batch doesn't exist)"
fi
