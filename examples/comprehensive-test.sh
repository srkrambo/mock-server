#!/bin/bash
# Comprehensive test of all PHP Mock Server features

BASE_URL="http://localhost:8080"
BOLD='\033[1m'
GREEN='\033[0;32m'
RED='\033[0;31m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BOLD}========================================${NC}"
echo -e "${BOLD}  PHP Mock Server - Comprehensive Test${NC}"
echo -e "${BOLD}========================================${NC}"
echo ""

# Counter for tests
TOTAL=0
PASSED=0
FAILED=0

test_endpoint() {
    TOTAL=$((TOTAL + 1))
    local name="$1"
    local response="$2"
    local expect_error="${3:-false}"
    
    echo -e "${BLUE}Test $TOTAL:${NC} $name"
    
    if echo "$response" | jq . > /dev/null 2>&1; then
        echo "$response" | jq '.'
        
        local has_success=$(echo "$response" | jq 'has("success") and .success == true')
        local has_message=$(echo "$response" | grep -q '"message"' && echo "true" || echo "false")
        local has_data=$(echo "$response" | jq 'has("data")')
        local has_error=$(echo "$response" | jq 'has("error")')
        local has_files=$(echo "$response" | jq 'has("files")')
        local has_resources=$(echo "$response" | jq 'has("resources")')
        
        if [ "$expect_error" = "true" ]; then
            # For tests expecting errors, check if error is present
            if [ "$has_error" = "true" ]; then
                echo -e "${GREEN}✓ PASSED (Expected error)${NC}"
                PASSED=$((PASSED + 1))
            else
                echo -e "${RED}✗ FAILED (Expected error but got success)${NC}"
                FAILED=$((FAILED + 1))
            fi
        else
            # For normal tests, check for success indicators
            if [ "$has_success" = "true" ] || [ "$has_message" = "true" ] || [ "$has_data" = "true" ] || [ "$has_files" = "true" ] || [ "$has_resources" = "true" ]; then
                echo -e "${GREEN}✓ PASSED${NC}"
                PASSED=$((PASSED + 1))
            else
                echo -e "${RED}✗ FAILED${NC}"
                FAILED=$((FAILED + 1))
            fi
        fi
    else
        echo "$response"
        echo -e "${RED}✗ FAILED (Invalid JSON)${NC}"
        FAILED=$((FAILED + 1))
    fi
    echo ""
}

# === Authentication Tests ===
echo -e "${BOLD}=== 1. Authentication Tests ===${NC}"
echo ""

# 1.1 Login
RESPONSE=$(curl -s -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}')
test_endpoint "Login with valid credentials" "$RESPONSE"
TOKEN=$(echo "$RESPONSE" | jq -r '.token')

# 1.2 OAuth Token
RESPONSE=$(curl -s -X POST "$BASE_URL/oauth/token" \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "client_credentials",
    "client_id": "mock-client-id",
    "client_secret": "mock-client-secret"
  }')
test_endpoint "OAuth 2.0 token generation" "$RESPONSE"

# === CRUD Operations ===
echo -e "${BOLD}=== 2. CRUD Operations ===${NC}"
echo ""

# 2.1 Create (POST)
RESPONSE=$(curl -s -X POST "$BASE_URL/api/users/100" \
  -H "Content-Type: application/json" \
  -d '{"name": "John Doe", "email": "john@example.com", "role": "admin"}')
test_endpoint "Create user resource (POST)" "$RESPONSE"

# 2.2 Read (GET)
RESPONSE=$(curl -s -X GET "$BASE_URL/api/users/100")
test_endpoint "Read user resource (GET)" "$RESPONSE"

# 2.3 Update (PUT)
RESPONSE=$(curl -s -X PUT "$BASE_URL/api/users/100" \
  -H "Content-Type: application/json" \
  -d '{"name": "John Smith", "email": "john.smith@example.com", "role": "superadmin"}')
test_endpoint "Update user resource (PUT)" "$RESPONSE"

# 2.4 Partial Update (PATCH)
RESPONSE=$(curl -s -X PATCH "$BASE_URL/api/users/100" \
  -H "Content-Type: application/json" \
  -d '{"role": "user"}')
test_endpoint "Partial update (PATCH)" "$RESPONSE"

# 2.5 Delete (DELETE)
RESPONSE=$(curl -s -X DELETE "$BASE_URL/api/users/100")
test_endpoint "Delete resource (DELETE)" "$RESPONSE"

# === File Upload Tests ===
echo -e "${BOLD}=== 3. File Upload Tests ===${NC}"
echo ""

# Create test files
echo "This is a test file for multipart upload" > /tmp/test-multipart.txt
echo "Binary data for raw upload" > /tmp/test-raw.bin
echo "Data for TUS upload" > /tmp/test-tus.dat

# 3.1 Multipart Upload
RESPONSE=$(curl -s -X POST "$BASE_URL/upload" \
  -F "file=@/tmp/test-multipart.txt")
test_endpoint "Multipart form-data upload" "$RESPONSE"

# 3.2 Raw Binary Upload
RESPONSE=$(curl -s -X PUT "$BASE_URL/upload/raw-test.bin" \
  -H "Content-Type: application/octet-stream" \
  --data-binary @/tmp/test-raw.bin)
test_endpoint "Raw binary upload (PUT)" "$RESPONSE"

# 3.3 Base64 Upload
BASE64_CONTENT=$(echo "Hello from Base64!" | base64)
RESPONSE=$(curl -s -X POST "$BASE_URL/upload" \
  -H "Content-Type: application/json" \
  -d "{\"type\": \"base64\", \"filename\": \"base64-test.txt\", \"content\": \"$BASE64_CONTENT\"}")
test_endpoint "Base64 encoded upload" "$RESPONSE"

# 3.4 TUS Resumable Upload
FILE_SIZE=$(stat -f%z /tmp/test-tus.dat 2>/dev/null || stat -c%s /tmp/test-tus.dat)
RESPONSE=$(curl -s -X POST "$BASE_URL/upload" \
  -H "Tus-Resumable: 1.0.0" \
  -H "Upload-Length: $FILE_SIZE")
test_endpoint "TUS upload creation" "$RESPONSE"

UPLOAD_ID=$(echo "$RESPONSE" | jq -r '.upload_id')
if [ "$UPLOAD_ID" != "null" ] && [ -n "$UPLOAD_ID" ]; then
    RESPONSE=$(curl -s -X PATCH "$BASE_URL/upload/$UPLOAD_ID" \
      -H "Tus-Resumable: 1.0.0" \
      -H "Upload-Offset: 0" \
      -H "Content-Type: application/offset+octet-stream" \
      --data-binary @/tmp/test-tus.dat)
    test_endpoint "TUS upload chunk (PATCH)" "$RESPONSE"
fi

# === Header Validation Tests ===
echo -e "${BOLD}=== 4. Header Validation Tests ===${NC}"
echo ""

# 4.1 Missing Content-Type
RESPONSE=$(curl -s -X POST "$BASE_URL/test/header" \
  -H "Content-Type:" \
  --data-raw '{"test": "data"}')
test_endpoint "POST without Content-Type (should fail)" "$RESPONSE" "true"

# 4.2 Wrong Content-Type on PATCH
RESPONSE=$(curl -s -X PATCH "$BASE_URL/api/users/1" \
  -H "Content-Type: text/plain" \
  -d '{"name": "test"}')
test_endpoint "PATCH with wrong Content-Type (should fail)" "$RESPONSE" "true"

# 4.3 Missing TUS headers
RESPONSE=$(curl -s -X POST "$BASE_URL/upload" \
  -H "Tus-Resumable: 1.0.0")
test_endpoint "TUS without Upload-Length (should fail)" "$RESPONSE" "true"

# === List Operations ===
echo -e "${BOLD}=== 5. List Operations ===${NC}"
echo ""

# 5.1 List Files
RESPONSE=$(curl -s -X GET "$BASE_URL/files")
test_endpoint "List uploaded files" "$RESPONSE"

# 5.2 List Resources
RESPONSE=$(curl -s -X GET "$BASE_URL/resources")
test_endpoint "List stored resources" "$RESPONSE"

# === Complex Scenarios ===
echo -e "${BOLD}=== 6. Complex Scenarios ===${NC}"
echo ""

# 6.1 Create nested resource
RESPONSE=$(curl -s -X POST "$BASE_URL/api/v1/projects/1/tasks/1" \
  -H "Content-Type: application/json" \
  -d '{"title": "Task 1", "status": "pending", "priority": "high"}')
test_endpoint "Create nested resource" "$RESPONSE"

# 6.2 JSON array
RESPONSE=$(curl -s -X POST "$BASE_URL/api/users/batch" \
  -H "Content-Type: application/json" \
  -d '[{"name": "User1"}, {"name": "User2"}, {"name": "User3"}]')
test_endpoint "POST JSON array" "$RESPONSE"

# Cleanup
rm -f /tmp/test-multipart.txt /tmp/test-raw.bin /tmp/test-tus.dat

# === Summary ===
echo -e "${BOLD}========================================${NC}"
echo -e "${BOLD}           Test Summary${NC}"
echo -e "${BOLD}========================================${NC}"
echo -e "Total Tests:  ${BOLD}$TOTAL${NC}"
echo -e "Passed:       ${GREEN}$PASSED${NC}"
echo -e "Failed:       ${RED}$FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}${BOLD}✓ All tests passed!${NC}"
    exit 0
else
    echo -e "${RED}${BOLD}✗ Some tests failed${NC}"
    exit 1
fi
