# Nodopay Payment Gateway Integration Guide

## Table of Contents
1. [Overview](#overview)
2. [Getting Started](#getting-started)
3. [Authentication](#authentication)
4. [Integration Steps](#integration-steps)
5. [API Endpoints](#api-endpoints)
6. [Integration Examples](#integration-examples)
7. [Webhooks](#webhooks)
8. [Error Handling](#error-handling)
9. [Testing](#testing)
10. [Best Practices](#best-practices)

---

## Overview

Nodopay Payment Gateway allows businesses to offer invoice financing to their customers. When customers choose "Pay with Nodopay", the system:

- Validates customer credit availability
- Creates invoice automatically
- Pays your business immediately
- Handles all repayment processing
- Manages interest calculations

**Key Benefits:**
- Instant payment to your business
- No payment delays
- Automatic invoice management
- Customer credit validation
- Real-time webhook notifications

---

## Getting Started

### Step 1: Register Your Business

Contact Nodopay admin to register your business. You'll need to provide:
- Business name and contact information
- KYC documents
- Webhook URL (optional, for receiving notifications)

### Step 2: Get Your API Token

Once your business is approved:
1. Login to your business dashboard
2. Navigate to profile settings
3. Your API token will be displayed
4. Or call `POST /api/business/generate-api-token` to generate a new one

**API Token Format**: `nodo_biz_xxxxxxxxxxxxx`

### Step 3: Configure Your Webhook URL

Set your webhook URL in your business profile to receive real-time notifications:
- Payment confirmations
- Invoice status updates
- Error notifications

---

## Authentication

All API requests require your API token in the header:

```
X-API-Token: nodo_biz_your_api_token_here
```

Or as a query parameter:
```
?api_token=nodo_biz_your_api_token_here
```

**Important**: Keep your API token secure. Never expose it in client-side code.

---

## Integration Steps

### Step 1: Check Customer Credit (Before Checkout)

Before allowing customer to proceed with "Pay with Nodopay", check if they have sufficient credit.

**Important**: Customers will enter their **16-digit account number** at checkout, not their customer ID. The account number is a unique identifier assigned to each customer when their account is created.

**Endpoint**: `POST /api/pay-with-nodopay/check-credit`

**Request**:
```json
{
  "account_number": "1234567890123456",
  "amount": 50000.00
}
```

**Note**: `account_number` must be exactly 16 digits and must exist in the system.

**Response**:
```json
{
  "success": true,
  "has_credit": true,
  "available_credit": 75000.00,
  "current_balance": 25000.00,
  "credit_limit": 100000.00
}
```

**Integration Example**:
```javascript
// Check credit before showing "Pay with Nodopay" option
async function checkNodopayCredit(accountNumber, amount) {
  const response = await fetch('https://api.nodopay.com/api/pay-with-nodopay/check-credit', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-API-Token': 'nodo_biz_your_api_token_here'
    },
    body: JSON.stringify({
      account_number: accountNumber,
      amount: amount
    })
  });
  
  const data = await response.json();
  return data.has_credit; // Show "Pay with Nodopay" if true
}
```

---

### Step 2: Process Purchase with Nodopay

When customer clicks "Pay with Nodopay", call the purchase endpoint.

**Endpoint**: `POST /api/pay-with-nodopay/purchase`

**Request**:
```json
{
  "account_number": "1234567890123456",
  "customer_email": "customer@example.com",
  "amount": 50000.00,
  "purchase_date": "2024-01-15",
  "order_reference": "ORD-12345",
  "items": [
    {
      "name": "Product A",
      "quantity": 2,
      "price": 15000.00,
      "description": "High quality product"
    },
    {
      "name": "Product B",
      "quantity": 1,
      "price": 20000.00,
      "description": "Premium product"
    }
  ]
}
```

**Required Fields**:
- `account_number` (string, 16 digits): The customer's unique 16-digit account number (what they enter at checkout)
- `customer_email` (string): Must match the email on the customer's account
- `amount` (decimal): Total purchase amount
- `items` (array): List of purchased items (required)

**Note**: The `account_number` is what customers will type at checkout, not the internal customer ID.

**Items Array (Required):**
- `items`: Array of purchased items
  - `name` (required): Item name
  - `quantity` (required): Quantity purchased (minimum: 1)
  - `price` (required): Unit price (minimum: 0.01)
  - `description` (optional): Item description

**Response (Success - 201)**:
```json
{
  "success": true,
  "message": "Purchase financed successfully",
  "invoice": {
    "invoice_id": "NODO-ABC123",
    "amount": 50000.00,
    "due_date": "2024-04-15",
    "status": "pending"
  },
  "customer": {
    "available_balance": 50000.00,
    "current_balance": 50000.00
  }
}
```

**Response (Insufficient Credit - 400)**:
```json
{
  "success": false,
  "message": "Insufficient credit available",
  "available_credit": 25000.00
}
```

**Integration Example**:
```javascript
// Process purchase with Nodopay
async function processNodopayPayment(orderData) {
  try {
    const response = await fetch('https://api.nodopay.com/api/pay-with-nodopay/purchase', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Token': 'nodo_biz_your_api_token_here'
      },
      body: JSON.stringify({
        account_number: orderData.accountNumber,
        customer_email: orderData.customerEmail,
        amount: orderData.totalAmount,
        purchase_date: new Date().toISOString().split('T')[0],
        order_reference: orderData.orderId,
        items: orderData.items
      })
    });
    
    const data = await response.json();
    
    if (data.success) {
      // Payment successful - update your order status
      updateOrderStatus(orderData.orderId, 'paid');
      showSuccessMessage('Payment processed successfully!');
      return data.invoice;
    } else {
      // Payment failed - show error
      showErrorMessage(data.message);
      return null;
    }
  } catch (error) {
    console.error('Payment error:', error);
    showErrorMessage('Payment processing failed. Please try again.');
    return null;
  }
}
```

---

### Step 3: Get Customer Details (Optional)

You can retrieve customer credit information to display to customers.

**Endpoint**: `GET /api/pay-with-nodopay/customer?account_number=1234567890123456&customer_email=customer@example.com`

**Query Parameters**:
- `account_number` (required): 16-digit customer account number
- `customer_email` (optional): Customer email for verification
- `customer_phone` (optional): Customer phone for verification

**Response**:
```json
{
  "success": true,
  "customer": {
    "id": 123,
    "account_number": "1234567890123456",
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "credit_limit": 100000.00,
    "available_balance": 75000.00,
    "current_balance": 25000.00,
    "status": "active"
  }
}
```

---

## Complete Integration Flow

### Frontend Integration

```javascript
// 1. On checkout page, check if customer has Nodopay account
async function showNodopayOption(accountNumber, orderTotal) {
  const hasCredit = await checkNodopayCredit(accountNumber, orderTotal);
  
  if (hasCredit) {
    // Show "Pay with Nodopay" button
    document.getElementById('nodopay-button').style.display = 'block';
  }
}

// 2. When customer clicks "Pay with Nodopay"
document.getElementById('nodopay-button').addEventListener('click', async () => {
  const orderData = {
    accountNumber: getAccountNumber(),
    customerEmail: getCustomerEmail(),
    totalAmount: getOrderTotal(),
    orderId: getOrderId(),
    items: getOrderItems()
  };
  
  // Show loading state
  showLoading();
  
  // Process payment
  const invoice = await processNodopayPayment(orderData);
  
  if (invoice) {
    // Payment successful
    hideLoading();
    redirectToSuccessPage(invoice.invoice_id);
  } else {
    // Payment failed
    hideLoading();
    showErrorModal();
  }
});
```

### Backend Integration (PHP Example)

```php
<?php

class NodopayIntegration {
    private $apiToken = 'nodo_biz_your_api_token_here';
    private $baseUrl = 'https://api.nodopay.com/api';
    
    public function checkCredit($accountNumber, $amount) {
        $response = $this->makeRequest('POST', '/pay-with-nodopay/check-credit', [
            'account_number' => $accountNumber,
            'amount' => $amount
        ]);
        
        return $response['has_credit'] ?? false;
    }
    
    public function processPurchase($accountNumber, $customerEmail, $amount, $orderReference, $items = []) {
        $response = $this->makeRequest('POST', '/pay-with-nodopay/purchase', [
            'account_number' => $accountNumber,
            'customer_email' => $customerEmail,
            'amount' => $amount,
            'purchase_date' => date('Y-m-d'),
            'order_reference' => $orderReference,
            'items' => $items
        ]);
        
        return $response;
    }
    
    private function makeRequest($method, $endpoint, $data = []) {
        $url = $this->baseUrl . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Token: ' . $this->apiToken
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}

// Usage
$nodopay = new NodopayIntegration();

// Check credit before checkout
if ($nodopay->checkCredit($accountNumber, $orderTotal)) {
    // Show "Pay with Nodopay" option
}

// Process payment
$result = $nodopay->processPurchase(
    $accountNumber,
    $customerEmail,
    $orderTotal,
    $orderId,
    $items
);

if ($result['success']) {
    // Update order status to paid
    // Redirect to success page
}
```

---

## Webhooks

### Setting Up Webhooks

Configure your webhook URL in your business profile. Nodopay will send HTTP POST requests to this URL for:

- Invoice creation
- Payment confirmations
- Status updates
- Errors

### Webhook Events

#### 1. Invoice Created
**Event**: `invoice.created`

**Payload**:
```json
{
  "event": "invoice.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "invoice_id": "NODO-ABC123",
    "account_number": "1234567890123456",
    "customer_id": 123,
    "amount": 50000.00,
    "status": "pending",
    "due_date": "2024-04-15"
  }
}
```

**When**: Sent immediately after invoice is created and payment is processed.

---

#### 2. Payment Received
**Event**: `payment.received`

**Payload**:
```json
{
  "event": "payment.received",
  "timestamp": "2024-01-20T10:30:00Z",
  "data": {
    "payment_reference": "PAY-XYZ789",
    "invoice_id": "NODO-ABC123",
    "amount": 25000.00,
    "account_number": "1234567890123456",
    "customer_id": 123,
    "status": "completed"
  }
}
```

**When**: Sent when customer makes a repayment (if invoice is linked to your business).

---

#### 3. Status Updated
**Event**: "status.updated"

**Payload**:
```json
{
  "event": "status.updated",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "invoice_id": "NODO-ABC123",
    "old_status": "pending",
    "new_status": "in_grace",
    "account_number": "1234567890123456",
    "customer_id": 123
  }
}
```

---

#### 4. Error Occurred
**Event**: `error.occurred`

**Payload**:
```json
{
  "event": "error.occurred",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "error": "Purchase request failed",
    "message": "Insufficient credit available",
    "account_number": "1234567890123456",
    "amount": 50000.00
  }
}
```

---

### Webhook Security

**Important**: Always validate webhook requests:

1. Verify the request comes from Nodopay IP addresses
2. Check webhook signature (if implemented)
3. Validate event structure
4. Handle duplicate events (idempotency)

**Example Webhook Handler**:
```php
<?php

// Webhook endpoint on your server
function handleNodopayWebhook() {
    $payload = json_decode(file_get_contents('php://input'), true);
    
    // Validate event structure
    if (!isset($payload['event']) || !isset($payload['data'])) {
        http_response_code(400);
        return;
    }
    
    $event = $payload['event'];
    $data = $payload['data'];
    
    switch ($event) {
        case 'invoice.created':
            // Update your order status
            updateOrderStatus($data['order_reference'], 'paid');
            // Send confirmation email to customer
            sendOrderConfirmation($data['account_number']);
            break;
            
        case 'payment.received':
            // Log payment for your records
            logPayment($data['payment_reference'], $data['amount']);
            break;
            
        case 'error.occurred':
            // Handle error - notify customer, log issue
            handlePaymentError($data);
            break;
    }
    
    // Always return 200 to acknowledge receipt
    http_response_code(200);
    echo json_encode(['status' => 'received']);
}
```

---

## Error Handling

### Common Error Responses

#### Insufficient Credit
```json
{
  "success": false,
  "message": "Insufficient credit available",
  "available_credit": 25000.00
}
```

**Action**: Inform customer they don't have enough credit. Show available credit amount.

---

#### Customer Not Found
```json
{
  "success": false,
  "message": "Customer account is not active"
}
```

**Action**: Customer needs to activate their Nodopay account or contact support.

---

#### Invalid API Token
```json
{
  "success": false,
  "message": "Invalid or inactive API token"
}
```

**Action**: Check your API token. Generate a new one if needed.

---

#### Business Not Approved
```json
{
  "success": false,
  "message": "Business is not approved or inactive"
}
```

**Action**: Contact Nodopay admin to get your business approved.

---

## Testing

### Test Mode

Use test customer IDs and amounts to test integration:

**Test Customer**:
- Account Number: Use a test customer's 16-digit account number created by admin
- Email: Must match customer record
- Amount: Use small amounts for testing

**Test Flow**:
1. Create test customer in Nodopay admin panel
2. Use test customer's account_number in your integration
3. Test credit check endpoint
4. Test purchase endpoint with small amounts
5. Verify webhook notifications

### Testing Checklist

- [ ] API token authentication works
- [ ] Credit check returns correct values
- [ ] Purchase request processes successfully
- [ ] Error handling works for insufficient credit
- [ ] Webhooks are received correctly
- [ ] Payment confirmations are handled
- [ ] Order status updates correctly

---

## Best Practices

### 1. Always Check Credit First

Before showing "Pay with Nodopay" option, check credit availability:

```javascript
// Good practice
const hasCredit = await checkNodopayCredit(accountNumber, orderTotal);
if (hasCredit) {
  showNodopayOption();
}
```

### 2. Handle Errors Gracefully

Always handle API errors and show user-friendly messages:

```javascript
try {
  const result = await processNodopayPayment(orderData);
  if (!result.success) {
    showErrorMessage(result.message);
  }
} catch (error) {
  showErrorMessage('Payment processing failed. Please try again.');
  logError(error);
}
```

### 3. Store Invoice ID

Store the returned invoice ID for reference:

```javascript
const invoice = await processNodopayPayment(orderData);
if (invoice) {
  // Store invoice_id with your order
  saveOrderInvoice(orderId, invoice.invoice_id);
}
```

### 4. Implement Webhook Retry Logic

Webhooks may fail due to network issues. Implement retry logic:

```php
function handleWebhook($payload) {
    try {
        processWebhook($payload);
    } catch (Exception $e) {
        // Queue for retry
        queueWebhookRetry($payload);
    }
}
```

### 5. Validate Customer Identity

Always validate customer email matches:

```javascript
// Verify customer email before processing
if (customerEmail !== storedCustomerEmail) {
  showError('Email mismatch. Please use your registered email.');
  return;
}
```

### 6. Use Idempotency Keys

For critical operations, use idempotency keys to prevent duplicate processing:

```javascript
const idempotencyKey = generateUniqueId();
const result = await processNodopayPayment(orderData, idempotencyKey);
```

---

## Integration Checklist

Before going live:

- [ ] Business registered and approved
- [ ] API token obtained and secured
- [ ] Webhook URL configured and tested
- [ ] Credit check implemented
- [ ] Purchase endpoint integrated
- [ ] Error handling implemented
- [ ] Webhook handler implemented
- [ ] Tested with test customers
- [ ] Order status updates working
- [ ] Email notifications configured
- [ ] Logging and monitoring set up

---

## Support

For integration support:
- Email: integration-support@nodopay.com
- Documentation: https://docs.nodopay.com
- API Status: https://status.nodopay.com

---

## Quick Reference

### Base URL
```
https://api.nodopay.com/api
```

### Authentication Header
```
X-API-Token: nodo_biz_your_api_token_here
```

### Key Endpoints
- Check Credit: `POST /pay-with-nodopay/check-credit`
- Process Purchase: `POST /pay-with-nodopay/purchase`
- Get Customer: `GET /pay-with-nodopay/customer`

### Response Times
- Average: < 200ms
- Credit Check: < 100ms
- Purchase Processing: < 500ms

---

## Example: Complete Checkout Integration

```javascript
// Complete integration example
class NodopayCheckout {
  constructor(apiToken) {
    this.apiToken = apiToken;
    this.baseUrl = 'https://api.nodopay.com/api';
  }
  
  async initializeCheckout(accountNumber, orderTotal) {
    // Step 1: Check credit
    const creditCheck = await this.checkCredit(accountNumber, orderTotal);
    
    if (!creditCheck.has_credit) {
      return {
        available: false,
        message: `Insufficient credit. Available: ₦${creditCheck.available_credit}`,
        availableCredit: creditCheck.available_credit
      };
    }
    
    return {
      available: true,
      availableCredit: creditCheck.available_credit,
      creditLimit: creditCheck.credit_limit
    };
  }
  
  async processPayment(accountNumber, customerEmail, amount, orderId, items = []) {
    try {
      const response = await fetch(`${this.baseUrl}/pay-with-nodopay/purchase`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-API-Token': this.apiToken
        },
        body: JSON.stringify({
          account_number: accountNumber,
          customer_email: customerEmail,
          amount: amount,
          purchase_date: new Date().toISOString().split('T')[0],
          order_reference: orderId,
          items: items
        })
      });
      
      const data = await response.json();
      
      if (data.success) {
        return {
          success: true,
          invoiceId: data.invoice.invoice_id,
          dueDate: data.invoice.due_date,
          message: 'Payment processed successfully'
        };
      } else {
        return {
          success: false,
          message: data.message || 'Payment failed'
        };
      }
    } catch (error) {
      return {
        success: false,
        message: 'Network error. Please try again.'
      };
    }
  }
  
  async checkCredit(accountNumber, amount) {
    const response = await fetch(`${this.baseUrl}/pay-with-nodopay/check-credit`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Token': this.apiToken
      },
      body: JSON.stringify({
        account_number: accountNumber,
        amount: amount
      })
    });
    
    return await response.json();
  }
}

// Usage
const nodopay = new NodopayCheckout('nodo_biz_your_api_token_here');

// On checkout page load
const checkout = await nodopay.initializeCheckout(accountNumber, orderTotal);
if (checkout.available) {
  showNodopayButton();
}

// On "Pay with Nodopay" click
const result = await nodopay.processPayment(
  accountNumber,
  customerEmail,
  orderTotal,
  orderId,
  orderItems
);

if (result.success) {
  redirectToSuccessPage(result.invoiceId);
} else {
  showError(result.message);
}
```

---

## Version

**v1.0.0** - Complete integration guide

