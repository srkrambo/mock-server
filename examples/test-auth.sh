#!/bin/bash
# Test script for authentication methods

BASE_URL="http://localhost:8080"

echo "=== Authentication Test Suite ==="
echo ""
echo "Note: Edit config.php to enable different auth methods"
echo ""

# Test 1: No Auth (default)
echo "1. Testing No Authentication..."
curl -s -X POST "$BASE_URL/users/test" \
  -H "Content-Type: application/json" \
  -d '{"name": "Test User"}' | jq '.'
echo ""

# Test 2: Basic Auth
echo "2. Testing Basic Authentication..."
echo "   (Set 'default_method' => 'basic' in config.php)"
curl -s -X GET "$BASE_URL/users/test" \
  -u admin:admin123 | jq '.'
echo ""

# Test 3: API Key Auth
echo "3. Testing API Key Authentication..."
echo "   (Set 'default_method' => 'api_key' in config.php)"
curl -s -X GET "$BASE_URL/users/test" \
  -H "X-API-Key: test-api-key-123" | jq '.'
echo ""

# Test 4: JWT Auth with Login
echo "4. Testing JWT Authentication..."
echo "   Step 1: Login to get token"
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}')
echo "$LOGIN_RESPONSE" | jq '.'

TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token')
echo ""
echo "   Step 2: Use JWT token (Set 'default_method' => 'jwt' in config.php)"
curl -s -X GET "$BASE_URL/users/test" \
  -H "Authorization: Bearer $TOKEN" | jq '.'
echo ""

# Test 5: OAuth 2.0
echo "5. Testing OAuth 2.0 Authentication..."
echo "   Step 1: Get access token"
OAUTH_RESPONSE=$(curl -s -X POST "$BASE_URL/oauth/token" \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "client_credentials",
    "client_id": "mock-client-id",
    "client_secret": "mock-client-secret"
  }')
echo "$OAUTH_RESPONSE" | jq '.'

ACCESS_TOKEN=$(echo "$OAUTH_RESPONSE" | jq -r '.access_token')
echo ""
echo "   Step 2: Use access token (Set 'default_method' => 'oauth2' in config.php)"
curl -s -X GET "$BASE_URL/users/test" \
  -H "Authorization: Bearer $ACCESS_TOKEN" | jq '.'
echo ""

# Test 6: Invalid credentials
echo "6. Testing Invalid Credentials..."
curl -s -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "wrong", "password": "wrong"}' | jq '.'
echo ""

echo "=== Authentication Tests Complete ==="
