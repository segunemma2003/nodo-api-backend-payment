# Business API Integration Guide - Complete Overview

## Table of Contents
1. [Overview](#overview)
2. [How It Works](#how-it-works)
3. [Business Integration Flow](#business-integration-flow)
4. [External Payment Flow](#external-payment-flow)
5. [API Endpoints](#api-endpoints)
6. [Integration Examples](#integration-examples)
7. [Webhooks](#webhooks)
8. [Complete Workflow Examples](#complete-workflow-examples)

---

## Overview

FSCredit provides a payment gateway that allows businesses to offer invoice financing to their customers. When customers choose "Pay with FSCredit", the system:

- ✅ Validates customer credit availability
- ✅ Creates invoice automatically
- ✅ Pays your business **immediately**
- ✅ Handles all repayment processing
- ✅ Manages interest calculations
- ✅ Sends real-time webhook notifications

**Key Benefits:**
- **Instant payment** to your business (no waiting for customer to pay)
- **No payment delays** - you get paid immediately
- **Automatic invoice management**
- **Customer credit validation** before checkout
- **Real-time webhook notifications** for all events

---

## How It Works

### Two Main Integration Methods:

#### 1. **Direct API Integration** (For External Businesses)
External businesses integrate FSCredit directly into their checkout system using API endpoints.

#### 2. **Business Dashboard Integration** (For Registered Businesses)
Registered businesses can create invoices and manage customers through the business dashboard API.

---

## Business Integration Flow

### Step 1: Business Registration & Approval

1. **Register Your Business**
   - Contact FSCredit admin to register
   - Provide business details and KYC documents
   - Set webhook URL (optional)

2. **Get Approved**
   - Admin reviews and approves your business
   - Business status changes to `approved` and `active`

3. **Get API Token**
   - Login: `POST /api/auth/business/login`
   - Receive your `api_token` (format: `fscredit_biz_xxxxxxxxxxxxx`)
   - Use this token for all API requests

### Step 2: Integrate FSCredit into Your Checkout

#### Option A: Check Credit Before Checkout (Recommended)

```javascript
// 1. Customer enters their 16-digit account number at checkout
const accountNumber = "1234567890123456";
const orderTotal = 50000.00;

// 2. Check if customer has sufficient credit
const response = await fetch('https://nodopay-api-0fbd4546e629.herokuapp.com/api/pay-with-fscredit/check-credit', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Token': 'fscredit_biz_your_api_token_here'
  },
  body: JSON.stringify({
    account_number: accountNumber,
    amount: orderTotal
  })
});

const data = await response.json();

if (data.has_credit) {
  // Show "Pay with FSCredit" button
  // Customer can proceed with FSCredit payment
} else {
  // Hide FSCredit option or show insufficient credit message
  alert(`Insufficient credit. Available: ₦${data.available_credit}`);
}
```

#### Option B: Process Purchase Directly

When customer clicks "Pay with FSCredit":

```javascript
// Process the purchase
const purchaseResponse = await fetch('https://nodopay-api-0fbd4546e629.herokuapp.com/api/pay-with-fscredit/purchase', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Token': 'fscredit_biz_your_api_token_here'
  },
  body: JSON.stringify({
    account_number: "1234567890123456",
    customer_email: "customer@example.com",
    cvv: "123",
    pin: "1234",
    amount: 50000.00,
    purchase_date: "2024-01-15",
    order_reference: "ORD-12345",
    items: [
      {
        name: "Rice 50kg",
        quantity: 10,
        price: 5000.00,
        description: "Premium rice",
        uom: "bags"
      },
      {
        name: "Beans 25kg",
        quantity: 5,
        price: 3000.00,
        description: "Premium beans",
        uom: "bags"
      }
    ]
  })
});

const result = await purchaseResponse.json();

if (result.success) {
  // ✅ Payment successful!
  // ✅ Your business has been paid immediately
  // ✅ Invoice created automatically
  // Update your order status to "paid"
  console.log('Invoice ID:', result.invoice.invoice_id);
  console.log('Order Reference:', result.order.order_reference);
} else {
  // Handle error
  console.error('Payment failed:', result.message);
}
```

### Step 3: Receive Webhook Notifications (Optional)

Configure your webhook URL to receive real-time notifications:

```javascript
// Your webhook endpoint receives POST requests
app.post('/webhook/fscredit', (req, res) => {
  const event = req.body.event; // e.g., 'invoice.created'
  const data = req.body.data;
  
  switch(event) {
    case 'invoice.created':
      // Invoice was created successfully
      // Your business has been paid
      updateOrderStatus(data.order_reference, 'paid');
      break;
      
    case 'invoice.paid':
      // Customer made a repayment
      // This doesn't affect your payment (you already got paid)
      break;
      
    case 'error':
      // Handle errors
      logError(data);
      break;
  }
  
  res.status(200).json({ received: true });
});
```

---

## External Payment Flow

### How Customers Pay Businesses Using FSCredit

#### Flow Diagram:

```
1. Customer Shopping Cart
   ↓
2. Customer Enters Account Number (16 digits)
   ↓
3. System Checks Credit Availability
   ↓
4. Customer Enters CVV & PIN
   ↓
5. Purchase Processed via API
   ↓
6. ✅ Business Gets Paid IMMEDIATELY
   ↓
7. Invoice Created for Customer
   ↓
8. Customer Repays Later (via virtual account)
```

### Detailed Steps:

#### Step 1: Customer at Checkout

Customer is at your checkout page and wants to pay with FSCredit:

1. **Customer enters their 16-digit account number**
   - This is their unique FSCredit account identifier
   - Format: `1234567890123456`

2. **Customer enters their email**
   - Must match the email on their FSCredit account

3. **Customer enters CVV (3 digits)**
   - Security verification

4. **Customer enters PIN (4 digits)**
   - Payment PIN (not the default 0000)

#### Step 2: Your System Processes Payment

Your backend calls the FSCredit API:

```php
<?php
// Example: PHP Integration

class FSCreditPayment {
    private $apiToken = 'fscredit_biz_your_api_token_here';
    private $baseUrl = 'https://nodopay-api-0fbd4546e629.herokuapp.com/api';
    
    public function processPayment($orderData) {
        $response = $this->makeRequest('POST', '/pay-with-fscredit/purchase', [
            'account_number' => $orderData['account_number'],
            'customer_email' => $orderData['customer_email'],
            'cvv' => $orderData['cvv'],
            'pin' => $orderData['pin'],
            'amount' => $orderData['total'],
            'purchase_date' => date('Y-m-d'),
            'order_reference' => $orderData['order_id'],
            'items' => $orderData['items']
        ]);
        
        if ($response['success']) {
            // ✅ Payment successful
            // ✅ Your business account has been credited
            // ✅ Invoice created for customer
            return [
                'status' => 'success',
                'invoice_id' => $response['invoice']['invoice_id'],
                'order_reference' => $response['order']['order_reference']
            ];
        } else {
            return [
                'status' => 'failed',
                'message' => $response['message']
            ];
        }
    }
    
    private function makeRequest($method, $endpoint, $data) {
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
$fscredit = new FSCreditPayment();

$result = $fscredit->processPayment([
    'account_number' => $_POST['account_number'],
    'customer_email' => $_POST['customer_email'],
    'cvv' => $_POST['cvv'],
    'pin' => $_POST['pin'],
    'total' => 50000.00,
    'order_id' => 'ORD-12345',
    'items' => [
        [
            'name' => 'Product A',
            'quantity' => 2,
            'price' => 15000.00,
            'description' => 'High quality product'
        ]
    ]
]);

if ($result['status'] === 'success') {
    // Update order status to paid
    // Send confirmation email to customer
    // Redirect to success page
}
```

#### Step 3: What Happens Behind the Scenes

1. **FSCredit Validates:**
   - Customer account exists
   - Email matches
   - CVV is correct
   - PIN is valid
   - Customer has sufficient credit
   - Customer account is approved and active

2. **FSCredit Creates Invoice:**
   - Invoice ID generated
   - Due date calculated (purchase_date + payment_plan_duration)
   - Interest calculated (if applicable)

3. **FSCredit Pays Your Business:**
   - **Your business account is credited IMMEDIATELY**
   - Payout is processed automatically
   - You can withdraw funds anytime

4. **Customer Gets Invoice:**
   - Invoice created in customer's account
   - Customer receives email notification
   - Customer repays later via virtual account

#### Step 4: Customer Repayment

- Customer repays via their virtual account (automatically processed)
- Customer can also pay via invoice link
- **Your business is not affected** - you already got paid!

---

## API Endpoints

### Base URL
```
https://nodopay-api-0fbd4546e629.herokuapp.com/api
```

### Authentication

All requests require your API token:

**Header Method (Recommended):**
```
X-API-Token: fscredit_biz_your_api_token_here
```

**Or Bearer Token:**
```
Authorization: Bearer fscredit_biz_your_api_token_here
```

### 1. Check Customer Credit

**Endpoint:** `POST /api/pay-with-fscredit/check-credit`

**Purpose:** Check if customer has sufficient credit before showing "Pay with FSCredit" option.

**Request:**
```json
{
  "account_number": "1234567890123456",
  "amount": 50000.00
}
```

**Response:**
```json
{
  "success": true,
  "has_credit": true,
  "available_credit": "75000.00",
  "current_balance": "25000.00",
  "credit_limit": "100000.00"
}
```

### 2. Process Purchase

**Endpoint:** `POST /api/pay-with-fscredit/purchase`

**Purpose:** Process payment when customer chooses "Pay with FSCredit".

**Request:**
```json
{
  "account_number": "1234567890123456",
  "customer_email": "customer@example.com",
  "cvv": "123",
  "pin": "1234",
  "amount": 50000.00,
  "purchase_date": "2024-01-15",
  "order_reference": "ORD-12345",
  "items": [
    {
      "name": "Product A",
      "quantity": 2,
      "price": 15000.00,
      "description": "High quality product",
      "uom": "pieces"
    }
  ]
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Purchase financed successfully",
  "invoice": {
    "invoice_id": "FSCREDIT-ABC123",
    "principal_amount": "50000.00",
    "interest_amount": "0.00",
    "total_amount": "50000.00",
    "due_date": "2024-07-15",
    "status": "in_grace"
  },
  "order": {
    "order_reference": "ORD-12345",
    "items": [...]
  },
  "customer": {
    "available_balance": "50000.00",
    "current_balance": "75000.00"
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "message": "Insufficient credit available",
  "available_credit": "25000.00"
}
```

### 3. Get Customer Details

**Endpoint:** `GET /api/pay-with-fscredit/customer`

**Purpose:** Verify customer details before processing payment.

**Request:**
```
GET /api/pay-with-fscredit/customer?account_number=1234567890123456&customer_email=customer@example.com
```

**Response:**
```json
{
  "success": true,
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "credit_limit": "100000.00",
    "available_balance": "75000.00",
    "status": "active"
  }
}
```

---

## Integration Examples

### JavaScript/React Example

```javascript
// FSCredit Integration Component
import React, { useState } from 'react';

function FSCreditCheckout({ orderTotal, onSuccess, onError }) {
  const [accountNumber, setAccountNumber] = useState('');
  const [email, setEmail] = useState('');
  const [cvv, setCvv] = useState('');
  const [pin, setPin] = useState('');
  const [loading, setLoading] = useState(false);
  const [hasCredit, setHasCredit] = useState(null);

  const API_TOKEN = 'fscredit_biz_your_api_token_here';
  const BASE_URL = 'https://nodopay-api-0fbd4546e629.herokuapp.com/api';

  // Check credit when account number is entered
  const checkCredit = async () => {
    if (accountNumber.length !== 16) return;

    try {
      const response = await fetch(`${BASE_URL}/pay-with-fscredit/check-credit`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-API-Token': API_TOKEN
        },
        body: JSON.stringify({
          account_number: accountNumber,
          amount: orderTotal
        })
      });

      const data = await response.json();
      setHasCredit(data.has_credit);
      
      if (!data.has_credit) {
        alert(`Insufficient credit. Available: ₦${data.available_credit}`);
      }
    } catch (error) {
      console.error('Credit check failed:', error);
    }
  };

  // Process payment
  const processPayment = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await fetch(`${BASE_URL}/pay-with-fscredit/purchase`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-API-Token': API_TOKEN
        },
        body: JSON.stringify({
          account_number: accountNumber,
          customer_email: email,
          cvv: cvv,
          pin: pin,
          amount: orderTotal,
          order_reference: `ORD-${Date.now()}`,
          items: getOrderItems() // Your order items
        })
      });

      const data = await response.json();

      if (data.success) {
        onSuccess(data);
      } else {
        onError(data.message);
      }
    } catch (error) {
      onError('Payment processing failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={processPayment}>
      <input
        type="text"
        placeholder="16-digit Account Number"
        value={accountNumber}
        onChange={(e) => {
          setAccountNumber(e.target.value);
          checkCredit();
        }}
        maxLength={16}
        required
      />
      
      <input
        type="email"
        placeholder="Email"
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        required
      />
      
      <input
        type="text"
        placeholder="CVV (3 digits)"
        value={cvv}
        onChange={(e) => setCvv(e.target.value)}
        maxLength={3}
        required
      />
      
      <input
        type="password"
        placeholder="PIN (4 digits)"
        value={pin}
        onChange={(e) => setPin(e.target.value)}
        maxLength={4}
        required
      />
      
      {hasCredit && (
        <button type="submit" disabled={loading}>
          {loading ? 'Processing...' : 'Pay with FSCredit'}
        </button>
      )}
    </form>
  );
}
```

### Python Example

```python
import requests

class FSCreditIntegration:
    def __init__(self, api_token):
        self.api_token = api_token
        self.base_url = 'https://nodopay-api-0fbd4546e629.herokuapp.com/api'
        self.headers = {
            'Content-Type': 'application/json',
            'X-API-Token': api_token
        }
    
    def check_credit(self, account_number, amount):
        """Check if customer has sufficient credit"""
        response = requests.post(
            f'{self.base_url}/pay-with-fscredit/check-credit',
            headers=self.headers,
            json={
                'account_number': account_number,
                'amount': amount
            }
        )
        return response.json()
    
    def process_purchase(self, account_number, customer_email, cvv, pin, 
                        amount, order_reference, items):
        """Process payment with FSCredit"""
        response = requests.post(
            f'{self.base_url}/pay-with-fscredit/purchase',
            headers=self.headers,
            json={
                'account_number': account_number,
                'customer_email': customer_email,
                'cvv': cvv,
                'pin': pin,
                'amount': amount,
                'purchase_date': None,  # Will use current date
                'order_reference': order_reference,
                'items': items
            }
        )
        return response.json()

# Usage
fscredit = FSCreditIntegration('fscredit_biz_your_api_token_here')

# Check credit
credit_check = fscredit.check_credit('1234567890123456', 50000.00)
if credit_check['has_credit']:
    # Process payment
    result = fscredit.process_purchase(
        account_number='1234567890123456',
        customer_email='customer@example.com',
        cvv='123',
        pin='1234',
        amount=50000.00,
        order_reference='ORD-12345',
        items=[
            {
                'name': 'Product A',
                'quantity': 2,
                'price': 15000.00,
                'description': 'High quality product'
            }
        ]
    )
    
    if result['success']:
        print(f"Invoice created: {result['invoice']['invoice_id']}")
        # Update order status to paid
    else:
        print(f"Payment failed: {result['message']}")
```

---

## Webhooks

### Setting Up Webhooks

Configure your webhook URL in your business profile to receive real-time notifications.

### Webhook Events

#### 1. Invoice Created
```json
{
  "event": "invoice.created",
  "data": {
    "invoice_id": "FSCREDIT-ABC123",
    "account_number": "1234567890123456",
    "customer_id": 1,
    "amount": "50000.00",
    "status": "in_grace",
    "due_date": "2024-07-15",
    "order_reference": "ORD-12345",
    "items": [...]
  }
}
```

#### 2. Error Notification
```json
{
  "event": "error",
  "data": {
    "error": "Purchase request failed",
    "message": "Insufficient credit available",
    "account_number": "1234567890123456",
    "amount": "50000.00"
  }
}
```

### Webhook Security

- Always verify webhook requests come from FSCredit
- Use HTTPS for webhook URLs
- Validate the webhook signature (if implemented)

---

## Complete Workflow Examples

### Example 1: E-commerce Integration

```
1. Customer adds items to cart (₦50,000)
   ↓
2. Customer goes to checkout
   ↓
3. Customer selects "Pay with FSCredit"
   ↓
4. Customer enters:
   - Account Number: 1234567890123456
   - Email: customer@example.com
   - CVV: 123
   - PIN: 1234
   ↓
5. Your system calls: POST /pay-with-fscredit/purchase
   ↓
6. FSCredit:
   - Validates customer
   - Checks credit
   - Creates invoice
   - Pays your business IMMEDIATELY
   ↓
7. Your system receives success response
   ↓
8. Your system:
   - Updates order status to "paid"
   - Sends confirmation email
   - Redirects to success page
   ↓
9. Customer receives invoice email
   ↓
10. Customer repays later (via virtual account)
```

### Example 2: B2B Invoice Financing

```
1. Business creates invoice for customer
   ↓
2. Customer wants to pay with FSCredit
   ↓
3. Business calls: POST /pay-with-fscredit/purchase
   ↓
4. FSCredit processes payment
   ↓
5. Business receives payment immediately
   ↓
6. Customer gets invoice to repay later
```

---

## Important Notes

### For Businesses:

1. **You Get Paid Immediately** - No waiting for customer to repay
2. **No Risk** - FSCredit handles all customer repayment
3. **Automatic Processing** - Everything is automated
4. **Webhook Notifications** - Real-time updates on all events

### For Customers:

1. **16-Digit Account Number** - Unique identifier (not customer ID)
2. **CVV & PIN Required** - Security verification
3. **Credit Limit** - Must have sufficient available credit
4. **Repayment** - Via virtual account or invoice link

### Security:

- ✅ Never expose API token in client-side code
- ✅ Always use HTTPS
- ✅ Validate all inputs
- ✅ Handle errors gracefully
- ✅ Log all transactions

---

## Support

For integration support:
- Email: support@foodstuff.store
- Documentation: See `PAYMENT_GATEWAY_INTEGRATION.md`
- API Base URL: `https://nodopay-api-0fbd4546e629.herokuapp.com/api`

---

**Last Updated:** 2024
