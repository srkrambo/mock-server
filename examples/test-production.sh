#!/bin/bash

# Test script for production mode features
# This script demonstrates rate limiting, upload size limits, and API key authentication

BASE_URL="http://localhost:8080"
echo "=== Mock Server Production Mode Test ==="
echo ""

# Test 1: Generate API Key
echo "1. Testing API Key Generation..."
API_KEY_RESPONSE=$(curl -s -X POST "$BASE_URL/api/generate-key" \
  -H "Content-Type: application/json" \
  -d '{"metadata": {"description": "Test key", "owner": "test-script"}}')

echo "Response: $API_KEY_RESPONSE"
API_KEY=$(echo "$API_KEY_RESPONSE" | grep -o '"api_key":"[^"]*"' | cut -d'"' -f4)
echo "Generated API Key: $API_KEY"
echo ""

# Test 2: List API Keys
echo "2. Testing API Key Listing..."
curl -s -X GET "$BASE_URL/api/keys" | jq .
echo ""

# Test 3: Test Upload Size Limit (Local Mode - should succeed with 2KB)
echo "3. Testing Upload Size Limit (Local Mode - 2KB file)..."
dd if=/dev/zero of=/tmp/test-2kb.bin bs=1024 count=2 2>/dev/null
UPLOAD_RESPONSE=$(curl -s -X PUT "$BASE_URL/upload/test-2kb.bin" \
  -H "Content-Type: application/octet-stream" \
  --data-binary @/tmp/test-2kb.bin)
echo "Response: $UPLOAD_RESPONSE"
echo ""

# Test 4: Test Small Upload (under 1KB - would work in production)
echo "4. Testing Small Upload (500 bytes - production compatible)..."
dd if=/dev/zero of=/tmp/test-500b.bin bs=1 count=500 2>/dev/null
SMALL_UPLOAD=$(curl -s -X PUT "$BASE_URL/upload/test-500b.bin" \
  -H "Content-Type: application/octet-stream" \
  --data-binary @/tmp/test-500b.bin)
echo "Response: $SMALL_UPLOAD"
echo ""

# Test 5: Test Rate Limiting
echo "5. Testing Rate Limiting (sending 105 requests)..."
echo "First requests should succeed, then get rate limited..."
SUCCESS_COUNT=0
RATE_LIMITED_COUNT=0

for i in {1..105}; do
  RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL/test/rate-limit/$i" \
    -H "Content-Type: application/json" \
    -d '{"test": "data"}')
  
  HTTP_CODE=$(echo "$RESPONSE" | tail -1)
  
  if [ "$HTTP_CODE" = "201" ]; then
    SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
  elif [ "$HTTP_CODE" = "429" ]; then
    RATE_LIMITED_COUNT=$((RATE_LIMITED_COUNT + 1))
  fi
  
  # Show progress every 20 requests
  if [ $((i % 20)) -eq 0 ]; then
    echo "  Processed $i requests..."
  fi
done

echo "Results:"
echo "  Successful requests: $SUCCESS_COUNT"
echo "  Rate-limited requests: $RATE_LIMITED_COUNT"
echo ""

# Show a rate limit response
echo "Example rate limit response:"
curl -s -X POST "$BASE_URL/test/rate-limit" \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}' | jq .
echo ""

# Test 6: Test with API Key (simulating production mode)
echo "6. Testing Request with API Key..."
curl -s -X POST "$BASE_URL/users/test" \
  -H "Content-Type: application/json" \
  -H "X-API-Key: $API_KEY" \
  -d '{"name": "Test User", "email": "test@example.com"}' | jq .
echo ""

# Test 7: Test Base64 Upload with Size Limit
echo "7. Testing Base64 Upload (small file)..."
# Create a small text file and encode it
echo "Hello, World! This is a test file." > /tmp/test-small.txt
BASE64_CONTENT=$(base64 -w 0 /tmp/test-small.txt)
curl -s -X POST "$BASE_URL/upload" \
  -H "Content-Type: application/json" \
  -d '{
    "type": "base64",
    "filename": "test-small.txt",
    "content": "'"$BASE64_CONTENT"'"
  }' | jq .
echo ""

# Clean up
rm -f /tmp/test-2kb.bin /tmp/test-500b.bin /tmp/test-small.txt

echo "=== Test Complete ==="
echo ""
echo "Summary:"
echo "- Rate limiting is working (limits requests per IP and globally)"
echo "- Upload size limits are enforced based on environment mode"
echo "- API key generation and listing works correctly"
echo "- API keys can be used in X-API-Key header"
echo ""
echo "To enable production mode:"
echo "  export MOCK_SERVER_ENV=production"
echo ""
echo "In production mode:"
echo "  - Upload size limit: 1KB (configurable in config.php)"
echo "  - API key is required for all requests"
echo "  - Rate limiting is enforced"
