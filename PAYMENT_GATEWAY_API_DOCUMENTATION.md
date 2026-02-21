# Payment Gateway API Documentation
## Direct Payment Processing with Customer Email

This API allows payment gateways to process payments directly from customer FSCredit balance using only the customer's email address.

---

## Endpoint

**POST** `/api/pay-with-fscredit/process-direct-payment`

**Base URL:** `https://your-domain.com/api`

---

## Authentication

This endpoint requires business API token authentication.

### Headers
```
Authorization: Bearer {api_token}
```
OR
```
X-API-Token: {api_token}
```

**Where to get API Token:**
- Login to business dashboard: `POST /api/business/login`
- Response includes `api_token` field
- Use this token for all API requests

---

## Request Body

### Required Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `customer_email` | string (email) | Customer's registered email address | `customer@example.com` |
| `amount` | number | Purchase amount (principal) | `10000.00` |
| `items` | array | Array of items/products purchased | See Items Structure below |

### Optional Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `purchase_date` | string (date) | Purchase date (ISO format or YYYY-MM-DD). Defaults to current date | `2024-01-15` |
| `order_reference` | string | Your order/invoice reference number | `ORD-12345` |

### Items Structure

Each item in the `items` array must contain:

| Field | Type | Required | Description | Example |
|-------|------|----------|-------------|---------|
| `name` | string | Yes | Item/product name | `"Rice 50kg Bag"` |
| `quantity` | integer | Yes | Quantity purchased (min: 1) | `2` |
| `price` | number | Yes | Unit price (min: 0.01) | `5000.00` |
| `description` | string | No | Item description | `"Premium long grain rice"` |
| `uom` | string | No | Unit of measure (e.g., "kg", "pieces", "liters") | `"bags"` |

---

## Request Example

```json
{
  "customer_email": "customer@example.com",
  "amount": 10000.00,
  "purchase_date": "2024-01-15",
  "order_reference": "ORD-12345",
  "items": [
    {
      "name": "Rice 50kg Bag",
      "quantity": 2,
      "price": 5000.00,
      "description": "Premium long grain rice",
      "uom": "bags"
    }
  ]
}
```

---

## Response

### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Payment processed successfully",
  "invoice": {
    "invoice_id": "FSCREDIT-ABC123XYZ",
    "principal_amount": 10000.00,
    "interest_amount": 2100.00,
    "total_amount": 12100.00,
    "due_date": "2024-07-15",
    "grace_period_end_date": "2024-08-15",
    "status": "paid",
    "payment_plan_duration": 6,
    "interest_rate": "21%"
  },
  "order": {
    "order_reference": "ORD-12345",
    "items": [
      {
        "name": "Rice 50kg Bag",
        "quantity": 2,
        "price": 5000.00,
        "description": "Premium long grain rice",
        "uom": "bags"
      }
    ]
  },
  "customer": {
    "account_number": "1234567890123456",
    "email": "customer@example.com",
    "available_balance": 87800.00,
    "current_balance": 32100.00,
    "credit_limit": 120000.00
  }
}
```

### Error Responses

#### 401 Unauthorized - Invalid/Missing API Token
```json
{
  "success": false,
  "message": "API token is required. Please provide Bearer token in Authorization header or X-API-Token header."
}
```

#### 400 Bad Request - Business Not Approved/Inactive
```json
{
  "success": false,
  "message": "Business is not approved or inactive",
  "details": {
    "error": "BUSINESS_NOT_APPROVED_OR_INACTIVE",
    "approval_status": "pending",
    "status": "inactive"
  }
}
```

#### 404 Not Found - Customer Not Found
```json
{
  "success": false,
  "message": "Customer not found with the provided email",
  "details": {
    "error": "CUSTOMER_NOT_FOUND",
    "email": "customer@example.com"
  }
}
```

#### 400 Bad Request - Customer Not Approved
```json
{
  "success": false,
  "message": "Customer account is pending approval",
  "details": {
    "error": "CUSTOMER_NOT_APPROVED",
    "approval_status": "pending"
  }
}
```

#### 400 Bad Request - Customer Inactive
```json
{
  "success": false,
  "message": "Customer account is not active",
  "details": {
    "error": "CUSTOMER_INACTIVE",
    "status": "suspended"
  }
}
```

#### 400 Bad Request - Insufficient Balance
```json
{
  "success": false,
  "message": "Insufficient credit available",
  "details": {
    "error": "INSUFFICIENT_BALANCE",
    "available_balance": 5000.00,
    "requested_amount": 10000.00,
    "credit_limit": 100000.00,
    "current_balance": 95000.00
  }
}
```

#### 500 Internal Server Error
```json
{
  "success": false,
  "message": "Failed to process payment",
  "details": {
    "error": "PROCESSING_ERROR",
    "error_message": "Database connection failed"
  }
}
```

---

## How It Works

### Payment Flow

1. **Platform Lends Money**: FSCredit platform pays the business the principal amount immediately
2. **Customer Balance Deducted**: Customer's available credit balance is reduced by the total amount (principal + interest)
3. **Customer Owes**: Customer owes the full amount (principal + interest) to FSCredit, to be repaid later
4. **Business Receives**: Business receives the principal amount and can withdraw it immediately

### Interest Calculation

- **Interest Rate**: 3.5% per month
- **Calculation**: `Interest = Principal Amount × 3.5% × Payment Plan Duration (months)`
- **Payment Plan Duration**: Retrieved from customer's account (default: 6 months)

**Example:**
- Principal: ₦10,000
- Payment Plan Duration: 6 months
- Interest: ₦10,000 × 0.035 × 6 = ₦2,100
- Total Amount: ₦12,100

### Invoice Status

- **Business View**: Invoice status is `"paid"` (FSCredit paid them)
- **Customer View**: Invoice shows as "paid by FSCredit" but customer still owes the full amount

---

## Webhooks

The API automatically sends webhooks to your business webhook URL (configured in business dashboard).

### Success Webhook: `payment.succeeded`

**Event:** `payment.succeeded`

**Payload:**
```json
{
  "event": "payment.succeeded",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "invoice_id": "FSCREDIT-ABC123XYZ",
    "status": "succeeded",
    "amount": 10000.00,
    "interest_amount": 2100.00,
    "total_amount": 12100.00,
    "paid_amount": 10000.00,
    "remaining_balance": 12100.00,
    "customer_email": "customer@example.com",
    "customer_name": "ABC Company",
    "account_number": "1234567890123456",
    "order_reference": "ORD-12345",
    "items": [
      {
        "name": "Rice 50kg Bag",
        "quantity": 2,
        "price": 5000.00,
        "description": "Premium long grain rice",
        "uom": "bags"
      }
    ],
    "paid_at": "2024-01-15T10:30:00Z"
  }
}
```

### Failure Webhook: `payment.failed`

**Event:** `payment.failed`

**Payload:**
```json
{
  "event": "payment.failed",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "status": "failed",
    "amount": 10000.00,
    "reason": "Insufficient credit available",
    "customer_email": "customer@example.com",
    "account_number": "1234567890123456",
    "available_balance": 5000.00,
    "order_reference": "ORD-12345",
    "failed_at": "2024-01-15T10:30:00Z"
  }
}
```

**Failure Reasons:**
- `CUSTOMER_NOT_FOUND` - Customer email not found in system
- `CUSTOMER_NOT_APPROVED` - Customer account pending approval
- `CUSTOMER_INACTIVE` - Customer account is inactive
- `INSUFFICIENT_BALANCE` - Customer doesn't have enough credit
- `PROCESSING_ERROR` - System error during processing

---

## Email Notifications

### Customer Email
- Sent to customer's registered email
- Includes: Invoice details, items purchased, interest breakdown, due date, amount owed to FSCredit

### Business Email
- Sent to business email
- Includes: Invoice details, items purchased, payment confirmation

---

## Example Integration (cURL)

```bash
curl -X POST https://your-domain.com/api/pay-with-fscredit/process-direct-payment \
  -H "Authorization: Bearer your_api_token_here" \
  -H "Content-Type: application/json" \
  -d '{
    "customer_email": "customer@example.com",
    "amount": 10000.00,
    "purchase_date": "2024-01-15",
    "order_reference": "ORD-12345",
    "items": [
      {
        "name": "Rice 50kg Bag",
        "quantity": 2,
        "price": 5000.00,
        "description": "Premium long grain rice",
        "uom": "bags"
      }
    ]
  }'
```

---

## Example Integration (PHP)

```php
<?php

$apiToken = 'your_api_token_here';
$apiUrl = 'https://your-domain.com/api/pay-with-fscredit/process-direct-payment';

$data = [
    'customer_email' => 'customer@example.com',
    'amount' => 10000.00,
    'purchase_date' => '2024-01-15',
    'order_reference' => 'ORD-12345',
    'items' => [
        [
            'name' => 'Rice 50kg Bag',
            'quantity' => 2,
            'price' => 5000.00,
            'description' => 'Premium long grain rice',
            'uom' => 'bags'
        ]
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiToken,
    'Content-Type: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($httpCode === 201 && $result['success']) {
    echo "Payment successful! Invoice ID: " . $result['invoice']['invoice_id'];
} else {
    echo "Payment failed: " . $result['message'];
    if (isset($result['details'])) {
        echo "\nError: " . $result['details']['error'];
    }
}
```

---

## Example Integration (JavaScript/Node.js)

```javascript
const axios = require('axios');

const apiToken = 'your_api_token_here';
const apiUrl = 'https://your-domain.com/api/pay-with-fscredit/process-direct-payment';

const data = {
  customer_email: 'customer@example.com',
  amount: 10000.00,
  purchase_date: '2024-01-15',
  order_reference: 'ORD-12345',
  items: [
    {
      name: 'Rice 50kg Bag',
      quantity: 2,
      price: 5000.00,
      description: 'Premium long grain rice',
      uom: 'bags'
    }
  ]
};

axios.post(apiUrl, data, {
  headers: {
    'Authorization': `Bearer ${apiToken}`,
    'Content-Type': 'application/json'
  }
})
.then(response => {
  if (response.status === 201 && response.data.success) {
    console.log('Payment successful!', response.data);
    console.log('Invoice ID:', response.data.invoice.invoice_id);
  }
})
.catch(error => {
  if (error.response) {
    console.error('Payment failed:', error.response.data.message);
    if (error.response.data.details) {
      console.error('Error code:', error.response.data.details.error);
    }
  } else {
    console.error('Request error:', error.message);
  }
});
```

---

## Important Notes

1. **Payment is Immediate**: The payment is processed immediately when the API is called. There's no pending state.

2. **Customer Balance**: Customer's available balance is reduced by the **total amount** (principal + interest), not just the principal.

3. **Business Receives**: Business receives the **principal amount** immediately and can withdraw it.

4. **Customer Owes**: Customer owes the **full amount** (principal + interest) to FSCredit and must repay it by the due date.

5. **Webhooks**: Always implement webhook handling to receive payment status updates, even if the API call succeeds (webhooks provide confirmation).

6. **Error Handling**: Always check the `success` field in the response and handle all error cases appropriately.

7. **Idempotency**: The API is not idempotent. Each call creates a new invoice. Use `order_reference` to track your orders.

8. **Rate Limiting**: Be aware of rate limits. Contact support for higher limits if needed.

---

## Support

For API support or questions:
- Email: support@fsscredit.com
- Documentation: https://docs.fsscredit.com
- Dashboard: https://dashboard.fsscredit.com

---

## Changelog

### Version 1.0.0 (2024-01-15)
- Initial release
- Direct payment processing with customer email
- Automatic interest calculation
- Webhook support for success/failure
- Email notifications to customer and business
