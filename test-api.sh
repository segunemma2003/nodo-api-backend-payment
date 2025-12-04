#!/bin/bash

# Nodopay API Testing Script
# This script tests all API endpoints locally

BASE_URL="http://localhost:8000/api"
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== Nodopay API Testing Script ===${NC}\n"

# Test 1: Customer Login
echo -e "${YELLOW}Test 1: Customer Login${NC}"
CUSTOMER_LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/customer/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "customer@example.com",
    "password": "password123"
  }')
echo "$CUSTOMER_LOGIN_RESPONSE" | jq .
CUSTOMER_TOKEN=$(echo "$CUSTOMER_LOGIN_RESPONSE" | jq -r '.token // empty')
echo ""

# Test 2: Business Login
echo -e "${YELLOW}Test 2: Business Login${NC}"
BUSINESS_LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/business/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "business@example.com",
    "password": "password123"
  }')
echo "$BUSINESS_LOGIN_RESPONSE" | jq .
BUSINESS_TOKEN=$(echo "$BUSINESS_LOGIN_RESPONSE" | jq -r '.token // empty')
BUSINESS_API_TOKEN=$(echo "$BUSINESS_LOGIN_RESPONSE" | jq -r '.business.api_token // empty')
echo ""

# Test 3: Admin Login
echo -e "${YELLOW}Test 3: Admin Login${NC}"
ADMIN_LOGIN_RESPONSE=$(curl -s -X POST "$BASE_URL/auth/admin/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@nodopay.com",
    "password": "admin_password"
  }')
echo "$ADMIN_LOGIN_RESPONSE" | jq .
ADMIN_TOKEN=$(echo "$ADMIN_LOGIN_RESPONSE" | jq -r '.token // empty')
echo ""

# Test 4: Get Customer Credit Overview
echo -e "${YELLOW}Test 4: Get Customer Credit Overview${NC}"
curl -s -X GET "$BASE_URL/customer/credit-overview?customer_id=1" \
  -H "Content-Type: application/json" | jq .
echo ""

# Test 5: Get Customer Invoices
echo -e "${YELLOW}Test 5: Get Customer Invoices${NC}"
curl -s -X GET "$BASE_URL/customer/invoices?customer_id=1" \
  -H "Content-Type: application/json" | jq .
echo ""

# Test 6: Get Customer Repayment Account
echo -e "${YELLOW}Test 6: Get Customer Repayment Account${NC}"
curl -s -X GET "$BASE_URL/customer/repayment-account?customer_id=1" \
  -H "Content-Type: application/json" | jq .
echo ""

# Test 7: Get Business Dashboard
echo -e "${YELLOW}Test 7: Get Business Dashboard${NC}"
curl -s -X GET "$BASE_URL/business/dashboard?business_id=1" \
  -H "Content-Type: application/json" | jq .
echo ""

# Test 8: Check Customer Credit (Pay with Nodopay)
echo -e "${YELLOW}Test 8: Check Customer Credit${NC}"
curl -s -X POST "$BASE_URL/pay-with-nodopay/check-credit" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: $BUSINESS_API_TOKEN" \
  -d '{
    "customer_id": 1,
    "amount": 50000.00
  }' | jq .
echo ""

# Test 9: Purchase Request (Pay with Nodopay)
echo -e "${YELLOW}Test 9: Purchase Request${NC}"
curl -s -X POST "$BASE_URL/pay-with-nodopay/purchase" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: $BUSINESS_API_TOKEN" \
  -d '{
    "customer_id": 1,
    "customer_email": "customer@example.com",
    "amount": 50000.00,
    "purchase_date": "2024-01-15",
    "order_reference": "ORD-TEST-001",
    "items": [
      {
        "name": "Product A",
        "quantity": 2,
        "price": 15000.00,
        "description": "Test product A"
      },
      {
        "name": "Product B",
        "quantity": 1,
        "price": 20000.00,
        "description": "Test product B"
      }
    ]
  }' | jq .
echo ""

# Test 10: Get Customer Details (Pay with Nodopay)
echo -e "${YELLOW}Test 10: Get Customer Details${NC}"
curl -s -X GET "$BASE_URL/pay-with-nodopay/customer?customer_id=1&customer_email=customer@example.com" \
  -H "Content-Type: application/json" \
  -H "X-API-Token: $BUSINESS_API_TOKEN" | jq .
echo ""

# Test 11: Payment Webhook
echo -e "${YELLOW}Test 11: Payment Webhook${NC}"
curl -s -X POST "$BASE_URL/payments/webhook" \
  -H "Content-Type: application/json" \
  -d '{
    "account_number": "1234567890123456",
    "amount": 25000.00,
    "transaction_reference": "TXN-TEST-001",
    "payment_date": "2024-01-20"
  }' | jq .
echo ""

# Test 12: Admin - Get Dashboard Stats
echo -e "${YELLOW}Test 12: Admin Dashboard Stats${NC}"
curl -s -X GET "$BASE_URL/admin/dashboard/stats" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .
echo ""

# Test 13: Admin - Get All Customers
echo -e "${YELLOW}Test 13: Admin - Get All Customers${NC}"
curl -s -X GET "$BASE_URL/admin/customers" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .
echo ""

# Test 14: Admin - Get All Businesses
echo -e "${YELLOW}Test 14: Admin - Get All Businesses${NC}"
curl -s -X GET "$BASE_URL/admin/businesses" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .
echo ""

# Test 15: Admin - Get All Invoices
echo -e "${YELLOW}Test 15: Admin - Get All Invoices${NC}"
curl -s -X GET "$BASE_URL/admin/invoices" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $ADMIN_TOKEN" | jq .
echo ""

echo -e "${GREEN}=== Testing Complete ===${NC}"
echo -e "${YELLOW}Note: Some tests may fail if test data doesn't exist in database${NC}"
echo -e "${YELLOW}Make sure to run migrations and seed test data first${NC}"

