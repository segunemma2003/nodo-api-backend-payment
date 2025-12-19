# Paystack Integration - Frontend API Documentation

## Base URL
```
https://nodopay-api-0fbd4546e629.herokuapp.com/api
```

---

## Table of Contents
1. [Virtual Account Management](#virtual-account-management)
2. [Payment Processing](#payment-processing)
3. [Payment Status & History](#payment-status--history)
4. [Webhook Configuration](#webhook-configuration)
5. [Error Handling](#error-handling)

---

## Virtual Account Management

### 1. Get Customer Virtual Account Details

**Endpoint:** `GET /api/customer/repayment-account`

**Authentication:** Required (Customer token)

**Request:**
```javascript
fetch('https://nodopay-api-0fbd4546e629.herokuapp.com/api/customer/repayment-account', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer YOUR_CUSTOMER_TOKEN',
    'Content-Type': 'application/json'
  }
})
```

**Response (200 OK):**
```json
{
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Wema Bank",
  "has_virtual_account": true
}
```

**Response (No Virtual Account):**
```json
{
  "virtual_account_number": null,
  "virtual_account_bank": null,
  "has_virtual_account": false
}
```

---

### 2. Generate Virtual Account (Existing Customers)

**Endpoint:** `POST /api/customer/repayment-account/generate`

**Authentication:** Required (Customer token)

**Request:**
```javascript
fetch('https://nodopay-api-0fbd4546e629.herokuapp.com/api/customer/repayment-account/generate', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_CUSTOMER_TOKEN',
    'Content-Type': 'application/json'
  }
})
```

**Response (202 Accepted - Queued):**
```json
{
  "success": true,
  "message": "Virtual account generation has been queued. Your virtual account will be created shortly. Please check your account details in a few moments.",
  "status": "queued",
  "customer_id": 1,
  "note": "You can check your virtual account status by calling GET /api/customer/repayment-account"
}
```

**Response (400 - Already Exists):**
```json
{
  "success": false,
  "message": "Virtual account already exists for this customer",
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Wema Bank"
}
```

**Response (503 - Paystack Not Configured):**
```json
{
  "success": false,
  "message": "Paystack is not configured. Please set PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY in your .env file and contact administrator.",
  "paystack_configured": false
}
```

**Frontend Implementation:**
```javascript
async function generateVirtualAccount() {
  try {
    const response = await fetch('/api/customer/repayment-account/generate', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${customerToken}`,
        'Content-Type': 'application/json'
      }
    });

    const data = await response.json();

    if (response.status === 202) {
      // Show success message
      showNotification('Virtual account generation started. Please check back in a few moments.');
      
      // Poll for virtual account status
      setTimeout(() => {
        checkVirtualAccountStatus();
      }, 5000);
    } else if (response.status === 400) {
      // Already exists
      showNotification('Virtual account already exists');
      updateUI(data);
    } else {
      // Error
      showError(data.message);
    }
  } catch (error) {
    showError('Failed to generate virtual account');
  }
}

async function checkVirtualAccountStatus() {
  const response = await fetch('/api/customer/repayment-account', {
    headers: {
      'Authorization': `Bearer ${customerToken}`
    }
  });
  
  const data = await response.json();
  
  if (data.has_virtual_account) {
    // Virtual account created, update UI
    displayVirtualAccount(data);
  } else {
    // Still processing, check again in 5 seconds
    setTimeout(checkVirtualAccountStatus, 5000);
  }
}
```

---

## Payment Processing

### 1. Payment Webhook (Backend Only)

**Endpoint:** `POST /api/payments/webhook/paystack`

**Note:** This is handled automatically by the backend when Paystack sends webhooks. Frontend doesn't need to call this directly.

**Configuration:** Set webhook URL in Paystack dashboard:
```
https://nodopay-api-0fbd4546e629.herokuapp.com/api/payments/webhook/paystack
```

---

### 2. Get Payment History

**Endpoint:** `GET /api/payments/history/{customerId}`

**Authentication:** Required (Admin token)

**Request:**
```javascript
fetch('https://nodopay-api-0fbd4546e629.herokuapp.com/api/payments/history/1', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer ADMIN_TOKEN',
    'Content-Type': 'application/json'
  }
})
```

**Response (200 OK):**
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "payment_reference": "PAY-xxxxx",
      "customer_id": 1,
      "invoice_id": 5,
      "amount": "50000.00",
      "payment_type": "repayment",
      "status": "completed",
      "transaction_reference": "T1234567890",
      "paid_at": "2024-01-15T10:30:00.000000Z",
      "created_at": "2024-01-15T10:30:00.000000Z"
    }
  ],
  "per_page": 20,
  "total": 1
}
```

---

## Payment Status & History

### 1. Get Customer Transactions (Includes Payments)

**Endpoint:** `GET /api/customer/transactions`

**Authentication:** Required (Customer token)

**Query Parameters:**
- `type` (optional): Filter by type - `transaction`, `payment`, or `credit_adjustment`
- `date_from` (optional): Filter from date (YYYY-MM-DD)
- `date_to` (optional): Filter to date (YYYY-MM-DD)
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20)

**Request:**
```javascript
fetch('https://nodopay-api-0fbd4546e629.herokuapp.com/api/customer/transactions?type=payment&page=1', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer YOUR_CUSTOMER_TOKEN',
    'Content-Type': 'application/json'
  }
})
```

**Response (200 OK):**
```json
{
  "success": true,
  "transactions": [
    {
      "id": 1,
      "reference": "PAY-xxxxx",
      "type": "payment",
      "transaction_type": "repayment",
      "amount": "50000.00",
      "status": "completed",
      "description": "Payment: PAY-xxxxx",
      "invoice": {
        "id": 5,
        "invoice_id": "INV-001"
      },
      "processed_at": "2024-01-15 10:30:00",
      "created_at": "2024-01-15 10:30:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  }
}
```

---

### 2. Get Customer Dashboard (Includes Payment Stats)

**Endpoint:** `GET /api/customer/dashboard`

**Authentication:** Required (Customer token)

**Request:**
```javascript
fetch('https://nodopay-api-0fbd4546e629.herokuapp.com/api/customer/dashboard', {
  method: 'GET',
  headers: {
    'Authorization': 'Bearer YOUR_CUSTOMER_TOKEN',
    'Content-Type': 'application/json'
  }
})
```

**Response (200 OK):**
```json
{
  "customer": {
    "id": 1,
    "business_name": "ABC Company",
    "account_number": "1234567890123456"
  },
  "credit": {
    "credit_limit": "100000.00",
    "current_balance": "50000.00",
    "available_balance": "50000.00",
    "credit_utilization_percent": 50
  },
  "payment_statistics": {
    "total_payments": 5,
    "pending_confirmations": 0,
    "total_paid_amount": "250000.00"
  },
  "recent_transactions": [
    {
      "id": 1,
      "reference": "PAY-xxxxx",
      "type": "transaction",
      "amount": "50000.00",
      "status": "completed",
      "description": "Repayment for invoice INV-001",
      "created_at": "2024-01-15 10:30:00"
    }
  ]
}
```

---

## Admin Endpoints (Virtual Account Management)

### 1. Generate Virtual Account for Specific Customer

**Endpoint:** `POST /api/admin/customers/{id}/generate-virtual-account`

**Authentication:** Required (Admin token)

**Request:**
```javascript
fetch('https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers/1/generate-virtual-account', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ADMIN_TOKEN',
    'Content-Type': 'application/json'
  }
})
```

**Response (202 Accepted):**
```json
{
  "success": true,
  "message": "Virtual account generation has been queued. It will be created shortly.",
  "status": "queued",
  "customer_id": 1
}
```

**Response (400 - Already Exists):**
```json
{
  "success": false,
  "message": "Customer already has a virtual account",
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Wema Bank"
}
```

---

### 2. Generate Virtual Accounts for All Customers (Bulk)

**Endpoint:** `POST /api/admin/customers/generate-virtual-accounts-all`

**Authentication:** Required (Admin token)

**Request:**
```javascript
fetch('https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers/generate-virtual-accounts-all', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer ADMIN_TOKEN',
    'Content-Type': 'application/json'
  }
})
```

**Response (202 Accepted):**
```json
{
  "success": true,
  "message": "Virtual account generation queued for 25 customer(s). They will be created shortly.",
  "total_customers": 25,
  "jobs_queued": 25,
  "status": "queued"
}
```

**Frontend Implementation:**
```javascript
async function generateVirtualAccountsForAll() {
  if (!confirm('This will generate virtual accounts for all customers without one. Continue?')) {
    return;
  }

  try {
    const response = await fetch('/api/admin/customers/generate-virtual-accounts-all', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${adminToken}`,
        'Content-Type': 'application/json'
      }
    });

    const data = await response.json();

    if (response.status === 202) {
      showSuccess(`Queued virtual account generation for ${data.jobs_queued} customers`);
    } else {
      showError(data.message);
    }
  } catch (error) {
    showError('Failed to queue virtual account generation');
  }
}
```

---

## Webhook Configuration

### Paystack Webhook Setup

1. **Login to Paystack Dashboard**
   - Go to https://dashboard.paystack.com
   - Navigate to Settings → API Keys & Webhooks

2. **Add Webhook URL:**
   ```
   https://nodopay-api-0fbd4546e629.herokuapp.com/api/payments/webhook/paystack
   ```

3. **Select Events to Listen For:**
   - ✅ `charge.success` (Required)
   - ✅ `transfer.success` (Required)
   - ⚪ `charge.failed` (Optional - for logging)
   - ⚪ `transfer.failed` (Optional - for logging)

4. **Save Webhook Configuration**

**Note:** The webhook automatically processes payments and updates customer balances when payments are made to virtual accounts.

---

## Payment Flow

### How Payments Work

1. **Customer Makes Payment**
   - Customer transfers money to their virtual account number
   - Payment is made via bank transfer, USSD, or mobile app

2. **Paystack Processes Payment**
   - Paystack receives the payment
   - Paystack sends webhook to your backend

3. **Backend Processes Payment**
   - Webhook endpoint receives payment notification
   - System finds customer by virtual account number
   - Payment is applied to invoices automatically
   - Customer balances are updated
   - Transaction records are created

4. **Dashboard Updates**
   - Customer dashboard automatically reflects updated balances
   - Payment appears in transaction history
   - Invoice statuses are updated

### Frontend Payment Status Check

```javascript
// Poll for payment status after customer makes payment
async function checkPaymentStatus(customerId) {
  const response = await fetch(`/api/customer/dashboard`, {
    headers: {
      'Authorization': `Bearer ${customerToken}`
    }
  });
  
  const data = await response.json();
  
  // Check if payment was processed
  const recentPayment = data.recent_transactions.find(
    txn => txn.type === 'payment' && txn.status === 'completed'
  );
  
  if (recentPayment) {
    // Payment processed, update UI
    updatePaymentStatus(recentPayment);
  }
}
```

---

## Error Handling

### Common Error Responses

#### 1. Virtual Account Not Found
```json
{
  "virtual_account_number": null,
  "virtual_account_bank": null,
  "has_virtual_account": false
}
```
**Action:** Call generate endpoint to create virtual account

#### 2. Paystack Not Configured
```json
{
  "success": false,
  "message": "Paystack is not configured...",
  "paystack_configured": false
}
```
**Action:** Contact administrator to configure Paystack keys

#### 3. Virtual Account Already Exists
```json
{
  "success": false,
  "message": "Virtual account already exists for this customer",
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Wema Bank"
}
```
**Action:** Use existing virtual account details

#### 4. Payment Processing Error
```json
{
  "error": "Customer not found"
}
```
**Action:** Verify customer exists and has virtual account

---

## Frontend Best Practices

### 1. Virtual Account Display

```javascript
function displayVirtualAccount(virtualAccount) {
  if (virtualAccount.has_virtual_account) {
    return `
      <div class="virtual-account-card">
        <h3>Your Repayment Account</h3>
        <div class="account-details">
          <p><strong>Account Number:</strong> ${virtualAccount.virtual_account_number}</p>
          <p><strong>Bank:</strong> ${virtualAccount.virtual_account_bank}</p>
        </div>
        <p class="info">Transfer money to this account to make repayments</p>
      </div>
    `;
  } else {
    return `
      <div class="no-account-card">
        <p>You don't have a virtual account yet.</p>
        <button onclick="generateVirtualAccount()">Generate Virtual Account</button>
      </div>
    `;
  }
}
```

### 2. Payment Status Polling

```javascript
// After customer initiates payment, poll for status
let pollCount = 0;
const maxPolls = 12; // 1 minute (5 seconds * 12)

function pollPaymentStatus() {
  if (pollCount >= maxPolls) {
    showMessage('Payment is being processed. Please check back later.');
    return;
  }

  checkPaymentStatus()
    .then(data => {
      if (data.payment_processed) {
        showSuccess('Payment received and processed!');
        refreshDashboard();
      } else {
        pollCount++;
        setTimeout(pollPaymentStatus, 5000);
      }
    })
    .catch(error => {
      console.error('Error checking payment status:', error);
      pollCount++;
      setTimeout(pollPaymentStatus, 5000);
    });
}
```

### 3. Real-time Updates (Optional)

If using WebSockets or Server-Sent Events:
```javascript
// Listen for payment updates
const eventSource = new EventSource('/api/customer/payment-updates');

eventSource.onmessage = (event) => {
  const payment = JSON.parse(event.data);
  if (payment.status === 'completed') {
    showNotification(`Payment of ₦${payment.amount} received!`);
    refreshDashboard();
  }
};
```

---

## Testing

### Test Virtual Account Generation

```javascript
// Test endpoint
async function testVirtualAccountGeneration() {
  const response = await fetch('/api/customer/repayment-account/generate', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${testToken}`,
      'Content-Type': 'application/json'
    }
  });
  
  console.log('Status:', response.status);
  console.log('Response:', await response.json());
}
```

### Test Payment Status

```javascript
// Check if payment was processed
async function testPaymentStatus() {
  const response = await fetch('/api/customer/transactions?type=payment', {
    headers: {
      'Authorization': `Bearer ${testToken}`
    }
  });
  
  const data = await response.json();
  console.log('Recent Payments:', data.transactions);
}
```

---

## Summary

### Key Endpoints for Frontend

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/customer/repayment-account` | GET | Get virtual account details |
| `/api/customer/repayment-account/generate` | POST | Generate virtual account |
| `/api/customer/transactions` | GET | Get payment history |
| `/api/customer/dashboard` | GET | Get payment statistics |
| `/api/admin/customers/{id}/generate-virtual-account` | POST | Admin: Generate for specific customer |
| `/api/admin/customers/generate-virtual-accounts-all` | POST | Admin: Generate for all customers |

### Important Notes

1. **Virtual accounts are created asynchronously** - Use polling or check status after a few seconds
2. **Payments are processed automatically** via webhook - No frontend action needed
3. **All virtual account operations use queue jobs** - They don't block the API
4. **Check `has_virtual_account` flag** before displaying account details
5. **Payment status updates in real-time** via webhook processing

---

## Support

For issues or questions:
- Check Paystack dashboard for webhook delivery status
- Review application logs for processing errors
- Verify Paystack keys are configured correctly
- Ensure queue worker is running on server

