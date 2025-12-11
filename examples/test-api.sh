#!/bin/bash
# Test script for PHP Mock Server

BASE_URL="http://localhost:8080"

echo "=== PHP Mock Server Test Suite ==="
echo ""

# Test 1: Login
echo "1. Testing Login..."
LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/login" \
  -H "Content-Type: application/json" \
  -d '{"username": "admin", "password": "admin123"}')
echo "$LOGIN_RESPONSE" | jq '.'
TOKEN=$(echo "$LOGIN_RESPONSE" | jq -r '.token')
echo ""

# Test 2: Create Resource (POST)
echo "2. Testing POST (Create Resource)..."
curl -s -X POST "$BASE_URL/users/1" \
  -H "Content-Type: application/json" \
  -d '{"name": "John Doe", "email": "john@example.com", "age": 30}' | jq '.'
echo ""

# Test 3: Get Resource (GET)
echo "3. Testing GET (Retrieve Resource)..."
curl -s -X GET "$BASE_URL/users/1" | jq '.'
echo ""

# Test 4: Update Resource (PUT)
echo "4. Testing PUT (Update Resource)..."
curl -s -X PUT "$BASE_URL/users/1" \
  -H "Content-Type: application/json" \
  -d '{"name": "John Smith", "email": "john.smith@example.com", "age": 31}' | jq '.'
echo ""

# Test 5: Partial Update (PATCH)
echo "5. Testing PATCH (Partial Update)..."
curl -s -X PATCH "$BASE_URL/users/1" \
  -H "Content-Type: application/json" \
  -d '{"age": 32}' | jq '.'
echo ""

# Test 6: List Resources
echo "6. Testing List Resources..."
curl -s -X GET "$BASE_URL/resources" | jq '.'
echo ""

# Test 7: Delete Resource
echo "7. Testing DELETE..."
curl -s -X DELETE "$BASE_URL/users/1" | jq '.'
echo ""

# Test 8: OAuth Token
echo "8. Testing OAuth 2.0 Token..."
curl -s -X POST "$BASE_URL/oauth/token" \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "client_credentials",
    "client_id": "mock-client-id",
    "client_secret": "mock-client-secret"
  }' | jq '.'
echo ""

echo "=== Tests Complete ==="
