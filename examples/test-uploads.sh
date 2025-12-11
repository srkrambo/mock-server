#!/bin/bash
# Test script for file uploads

BASE_URL="http://localhost:8080"

echo "=== File Upload Test Suite ==="
echo ""

# Create test files
echo "Creating test files..."
echo "This is a test file" > /tmp/test.txt
echo "Binary content" > /tmp/test.bin
echo ""

# Test 1: Multipart Upload
echo "1. Testing Multipart Form-Data Upload..."
curl -s -X POST "$BASE_URL/upload" \
  -F "file=@/tmp/test.txt" | jq '.'
echo ""

# Test 2: Raw Binary Upload
echo "2. Testing Raw Binary Upload..."
curl -s -X PUT "$BASE_URL/upload/test-raw.bin" \
  -H "Content-Type: application/octet-stream" \
  --data-binary @/tmp/test.bin | jq '.'
echo ""

# Test 3: Base64 Upload
echo "3. Testing Base64 Encoded Upload..."
BASE64_CONTENT=$(echo "Hello World from Base64" | base64)
curl -s -X POST "$BASE_URL/upload" \
  -H "Content-Type: application/json" \
  -d "{
    \"type\": \"base64\",
    \"filename\": \"test-base64.txt\",
    \"content\": \"$BASE64_CONTENT\"
  }" | jq '.'
echo ""

# Test 4: TUS Resumable Upload - Create
echo "4. Testing TUS Upload - Create..."
TUS_RESPONSE=$(curl -s -X POST "$BASE_URL/upload" \
  -H "Tus-Resumable: 1.0.0" \
  -H "Upload-Length: 1000")
echo "$TUS_RESPONSE" | jq '.'
UPLOAD_ID=$(echo "$TUS_RESPONSE" | jq -r '.upload_id')
echo ""

# Test 5: TUS Upload - Patch (Upload chunk)
if [ "$UPLOAD_ID" != "null" ]; then
    echo "5. Testing TUS Upload - PATCH (Upload Chunk)..."
    curl -s -X PATCH "$BASE_URL/upload/$UPLOAD_ID" \
      -H "Tus-Resumable: 1.0.0" \
      -H "Upload-Offset: 0" \
      -H "Content-Type: application/offset+octet-stream" \
      --data-binary @/tmp/test.bin | jq '.'
    echo ""
    
    # Test 6: TUS Upload - HEAD (Check status)
    echo "6. Testing TUS Upload - HEAD (Check Status)..."
    curl -s -I "$BASE_URL/upload/$UPLOAD_ID" \
      -H "Tus-Resumable: 1.0.0"
    echo ""
fi

# Test 7: List Files
echo "7. Testing List Files..."
curl -s -X GET "$BASE_URL/files" | jq '.'
echo ""

echo "=== Upload Tests Complete ==="

# Cleanup
rm -f /tmp/test.txt /tmp/test.bin
