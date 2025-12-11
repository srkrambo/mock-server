#!/bin/bash

# Test script for Google OAuth authentication in API key generation
# This script demonstrates the Google authentication flow

BASE_URL="http://localhost:8080"
echo "=== Mock Server Google OAuth Authentication Test ==="
echo ""

# Test 1: Try to generate API key without authentication (should fail)
echo "1. Testing API key generation WITHOUT authentication (should fail)..."
RESPONSE=$(curl -s -X POST "$BASE_URL/api/generate-key" \
  -H "Content-Type: application/json" \
  -d '{"metadata": {"description": "Test without auth"}}')
echo "Response: $RESPONSE"
echo ""

# Test 2: Check Google auth endpoint (will show config error if not configured)
echo "2. Testing Google auth endpoint status..."
RESPONSE=$(curl -s -X GET "$BASE_URL/auth/google")
echo "Response: $RESPONSE"
echo ""

# Test 3: Generate a mock JWT token (simulating Google authentication)
echo "3. Generating mock JWT token (simulating Google authentication)..."
echo "   Note: In production, this token would come from actual Google OAuth flow"
JWT=$(php -r '
$header = ["typ" => "JWT", "alg" => "HS256"];
$payload = [
    "sub" => "user@example.com",
    "email" => "user@example.com",
    "name" => "Example User",
    "iss" => "accounts.google.com",
    "iat" => time(),
    "exp" => time() + 3600,
];
function base64UrlEncode($data) {
    return rtrim(strtr(base64_encode($data), "+/", "-_"), "=");
}
$headerEncoded = base64UrlEncode(json_encode($header));
$payloadEncoded = base64UrlEncode(json_encode($payload));
$secret = "your-secret-key-change-in-production";
$signature = base64UrlEncode(hash_hmac("sha256", "$headerEncoded.$payloadEncoded", $secret, true));
echo "$headerEncoded.$payloadEncoded.$signature";
')

echo "   Generated JWT token (first 50 chars): ${JWT:0:50}..."
echo ""

# Test 4: Use JWT token to generate API key (should succeed)
echo "4. Testing API key generation WITH Google authentication (should succeed)..."
RESPONSE=$(curl -s -X POST "$BASE_URL/api/generate-key" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $JWT" \
  -d '{"metadata": {"description": "Test with Google auth", "app": "test-suite"}}')
echo "Response: $RESPONSE"
echo ""

# Extract API key from response
API_KEY=$(echo "$RESPONSE" | grep -o '"api_key":"[^"]*"' | cut -d'"' -f4)

# Test 5: Use the generated API key to make a request
if [ ! -z "$API_KEY" ]; then
    echo "5. Testing request with generated API key..."
    echo "   API Key: $API_KEY"
    RESPONSE=$(curl -s -X POST "$BASE_URL/test/data" \
      -H "Content-Type: application/json" \
      -H "X-API-Key: $API_KEY" \
      -d '{"message": "Hello from authenticated request"}')
    echo "   Response: $RESPONSE"
    echo ""
fi

# Test 6: List API keys to verify metadata
echo "6. Listing all API keys (should show generated_by and auth_method)..."
curl -s -X GET "$BASE_URL/api/keys" | jq '.keys[] | {created_at, metadata, key_masked}'
echo ""

# Test 7: Test logout endpoint
echo "7. Testing logout endpoint..."
RESPONSE=$(curl -s -X POST "$BASE_URL/auth/google/logout")
echo "Response: $RESPONSE"
echo ""

echo "=== Test Complete ==="
echo ""
echo "How to use Google OAuth in production:"
echo ""
echo "1. Set up Google Cloud Console:"
echo "   - Go to https://console.cloud.google.com/"
echo "   - Create a new project"
echo "   - Enable Google+ API"
echo "   - Create OAuth 2.0 credentials"
echo "   - Add authorized redirect URI: http://localhost:8080/auth/google/callback"
echo ""
echo "2. Configure environment variables:"
echo "   export GOOGLE_CLIENT_ID='your-client-id.apps.googleusercontent.com'"
echo "   export GOOGLE_CLIENT_SECRET='your-client-secret'"
echo "   export GOOGLE_REDIRECT_URI='http://localhost:8080/auth/google/callback'"
echo ""
echo "3. Start the server:"
echo "   php -S localhost:8080 router.php"
echo ""
echo "4. Authenticate with Google:"
echo "   Visit: http://localhost:8080/auth/google"
echo "   (This will redirect to Google for authentication)"
echo ""
echo "5. After authentication, use the returned JWT token to generate API keys:"
echo "   curl -X POST http://localhost:8080/api/generate-key \\"
echo "     -H 'Content-Type: application/json' \\"
echo "     -H 'Authorization: Bearer <jwt-token>' \\"
echo "     -d '{\"metadata\": {\"description\": \"My API key\"}}'"
