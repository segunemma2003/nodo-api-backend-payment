<?php

/**
 * FSCredit API Testing Script
 * Run: php test-api.php
 */

$baseUrl = 'http://localhost:8000/api';

echo "=== FSCredit API Testing Script ===\n\n";

// Helper function to make API requests
function makeRequest($method, $url, $data = null, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    $allHeaders = ['Content-Type: application/json'];
    foreach ($headers as $key => $value) {
        $allHeaders[] = "$key: $value";
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    
    if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'body' => json_decode($response, true)
    ];
}

// Test 1: Customer Login
echo "Test 1: Customer Login\n";
$response = makeRequest('POST', "$baseUrl/auth/customer/login", [
    'email' => 'customer@example.com',
    'password' => 'password123'
]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
$customerToken = $response['body']['token'] ?? null;
echo "\n";

// Test 2: Business Login
echo "Test 2: Business Login\n";
$response = makeRequest('POST', "$baseUrl/auth/business/login", [
    'email' => 'business@example.com',
    'password' => 'password123'
]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
$businessApiToken = $response['body']['business']['api_token'] ?? null;
echo "\n";

// Test 3: Admin Login
echo "Test 3: Admin Login\n";
$response = makeRequest('POST', "$baseUrl/auth/admin/login", [
    'email' => 'admin@fscredit.com',
    'password' => 'admin_password'
]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
$adminToken = $response['body']['token'] ?? null;
echo "\n";

// Test 4: Get Customer Credit Overview
echo "Test 4: Get Customer Credit Overview\n";
$response = makeRequest('GET', "$baseUrl/customer/credit-overview?customer_id=1");
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 5: Get Customer Invoices
echo "Test 5: Get Customer Invoices\n";
$response = makeRequest('GET', "$baseUrl/customer/invoices?customer_id=1");
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 6: Get Customer Repayment Account
echo "Test 6: Get Customer Repayment Account\n";
$response = makeRequest('GET', "$baseUrl/customer/repayment-account?customer_id=1");
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 7: Get Business Dashboard
echo "Test 7: Get Business Dashboard\n";
$response = makeRequest('GET', "$baseUrl/business/dashboard?business_id=1");
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 8: Check Customer Credit (Pay with FSCredit)
echo "Test 8: Check Customer Credit\n";
$response = makeRequest('POST', "$baseUrl/pay-with-fscredit/check-credit", [
    'customer_id' => 1,
    'amount' => 50000.00
], ['X-API-Token' => $businessApiToken]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 9: Purchase Request (Pay with FSCredit)
echo "Test 9: Purchase Request\n";
$response = makeRequest('POST', "$baseUrl/pay-with-fscredit/purchase", [
    'customer_id' => 1,
    'customer_email' => 'customer@example.com',
    'amount' => 50000.00,
    'purchase_date' => '2024-01-15',
    'order_reference' => 'ORD-TEST-001',
    'items' => [
        [
            'name' => 'Product A',
            'quantity' => 2,
            'price' => 15000.00,
            'description' => 'Test product A'
        ],
        [
            'name' => 'Product B',
            'quantity' => 1,
            'price' => 20000.00,
            'description' => 'Test product B'
        ]
    ]
], ['X-API-Token' => $businessApiToken]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 10: Get Customer Details (Pay with FSCredit)
echo "Test 10: Get Customer Details\n";
$response = makeRequest('GET', "$baseUrl/pay-with-fscredit/customer?customer_id=1&customer_email=customer@example.com", null, ['X-API-Token' => $businessApiToken]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 11: Payment Webhook (using account_number)
echo "Test 11: Payment Webhook\n";
$response = makeRequest('POST', "$baseUrl/payments/webhook", [
    'account_number' => '1234567890123456',
    'amount' => 25000.00,
    'transaction_reference' => 'TXN-TEST-001',
    'payment_date' => '2024-01-20'
]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 12: Admin - Get Dashboard Stats
echo "Test 12: Admin Dashboard Stats\n";
$response = makeRequest('GET', "$baseUrl/admin/dashboard/stats", null, ['Authorization' => "Bearer $adminToken"]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 13: Admin - Get All Customers
echo "Test 13: Admin - Get All Customers\n";
$response = makeRequest('GET', "$baseUrl/admin/customers", null, ['Authorization' => "Bearer $adminToken"]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 14: Admin - Get All Businesses
echo "Test 14: Admin - Get All Businesses\n";
$response = makeRequest('GET', "$baseUrl/admin/businesses", null, ['Authorization' => "Bearer $adminToken"]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

// Test 15: Admin - Get All Invoices
echo "Test 15: Admin - Get All Invoices\n";
$response = makeRequest('GET', "$baseUrl/admin/invoices", null, ['Authorization' => "Bearer $adminToken"]);
echo "Status: {$response['code']}\n";
print_r($response['body']);
echo "\n";

echo "=== Testing Complete ===\n";
echo "Note: Some tests may fail if test data doesn't exist in database\n";
echo "Make sure to run migrations and seed test data first\n";

