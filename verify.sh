#!/bin/bash

BASE_URL="http://localhost:8081"
COOKIE_FILE="cookies.txt"
TOKEN_FILE="token.txt"

echo "=== 1. Security Headers ==="
curl -s -I $BASE_URL/ | grep -E "X-Content-Type-Options|X-Frame-Options|X-XSS-Protection"

echo -e "\n=== 2. JSON API ==="
curl -s $BASE_URL/api/status

echo -e "\n=== 3. Custom 404 ==="
curl -s $BASE_URL/missing

echo -e "\n=== 4. Custom 500 ==="
curl -s $BASE_URL/error

echo -e "\n=== 5. CSRF Protection Flow ==="
# Get Form and extract token
curl -s -c $COOKIE_FILE $BASE_URL/form > form.html
TOKEN=$(grep -oP "value=['\"]\K[a-f0-9]{64}" form.html)
echo "Token: $TOKEN"

# POST with valid token
echo "--- Valid Post ---"
curl -s -b $COOKIE_FILE -X POST -d "csrf_token=$TOKEN&message=HelloFromTest" $BASE_URL/form

# POST with invalid token
echo -e "\n--- Invalid Post ---"
curl -s -b $COOKIE_FILE -X POST -d "csrf_token=INVALID&message=Hacker" $BASE_URL/form

echo -e "\n\n=== 6. Public API (Config Exempt) ==="
# POST to /api/public without token (should succeed)
curl -s -X POST -d "param=public_val" $BASE_URL/api/public

echo -e "\n\n=== 7. Private API (Config Bearer Protected) ==="
echo "--- Missing Token (Should be 401) ---"
curl -s -X POST -d "param=private_val" $BASE_URL/api/private

echo -e "\n--- Invalid Token (Should be 401) ---"
curl -s -H "Authorization: Bearer WRONG" -X POST -d "param=private_val" $BASE_URL/api/private

echo -e "\n--- Valid Token (Should be 200) ---"
curl -s -H "Authorization: Bearer secret-token-123" -X POST -d "param=private_val" $BASE_URL/api/private

echo -e "\n\n=== Verification Complete ==="
rm $COOKIE_FILE form.html
