# Updated Business API Integration Workflow

## Overview

This document describes the updated workflow for business API integration where businesses create invoices using their API token, receive payment links, and get webhook notifications with automatic retry logic.

**Perfect for Third-Party Integration:**
- ✅ **No pre-registration required** - Just provide customer email and invoice details
- ✅ **Auto-creates customers** - System automatically creates customer records if email doesn't exist
- ✅ **Simple API** - Only requires `contact_email` and `amount` (other fields optional)
- ✅ **Flexible** - Can update customer info on subsequent invoices
- ✅ **Webhook notifications** - Real-time updates on invoice creation and payment status

---

## User Story

### As a Business
I want to create an invoice using my API token from the dashboard, so that:
- I receive a payment link with `https://fsscredit.foodstuff.store` as the base URL
- Payment status updates are sent to my callback URL (webhook)
- The customer receives an email notification
- Webhooks are retried every 1 hour if I don't respond with 200

---

## Complete Workflow

### Step 1: Business Creates Invoice

**Endpoint:** `POST /api/business/submit-invoice`

**Authentication:** Bearer token or `X-API-Token` header with business API token

**Request:**
```json
{
  "contact_email": "customer@example.com",
  "business_name": "ABC Company",
  "contact_name": "John Doe",
  "contact_phone": "+2348012345678",
  "address": "123 Main Street, Lagos",
  "amount": 50000.00,
    "callback_url": "https://yourbusiness.com/payment/success",
  "purchase_date": "2024-01-15",
  "description": "Purchase of goods",
  "items": [
    {
      "name": "Rice 50kg",
      "quantity": 10,
      "price": 5000.00,
      "description": "Premium rice",
      "uom": "bags"
    }
  ]
}
```

**Required Fields:**
- `contact_email` (string, email): Customer's email address - used to identify or create the customer
- `amount` (numeric, min: 0.01): Invoice amount

**Optional Fields:**
- `business_name` (string): Customer's business name (defaults to "Customer" if not provided)
- `contact_name` (string): Contact person's name
- `contact_phone` (string): Contact phone number
- `address` (string): Customer's address
- `callback_url` (string, URL): Where to redirect customer after payment (with payment status in query params). Defaults to business profile callback_url if not provided
- `purchase_date` (date, YYYY-MM-DD): Date of purchase (defaults to today)
- `due_date` (date, YYYY-MM-DD): Due date for payment (auto-calculated if not provided)
- `description` (string): Invoice description
- `items` (array): Array of purchased items
  - `name` (required): Item name
  - `quantity` (required): Quantity purchased (integer, min: 1)
  - `price` (required): Unit price (numeric, min: 0.01)
  - `description` (optional): Item description
  - `uom` (optional): Unit of Measure (e.g., "kg", "pieces", "liters", "boxes", "bags")

**Response:**
```json
{
  "success": true,
  "message": "Invoice created successfully",
  "invoice": {
    "invoice_id": "FSCREDIT-ABC123",
    "slug": "inv-abc123xyz456",
    "amount": "50000.00",
    "due_date": "2024-07-15",
    "status": "pending",
    "payment_link": "https://fsscredit.foodstuff.store/checkout/inv-abc123xyz456",
    "payment_link_expires_at": "2024-01-15T11:00:00Z",
    "callback_url": "https://yourbusiness.com/payment/success",
    "description": "Purchase of goods",
    "items": [...]
  },
  "customer": {
    "id": 1,
    "business_name": "ABC Company",
    "contact_email": "customer@example.com",
    "contact_name": "John Doe",
    "contact_phone": "+2348012345678",
    "is_linked": false,
    "is_new_customer": true
  },
  "transaction": {
    "transaction_reference": "TXN-2024-001",
    "status": "completed"
  }
}
```

**Key Points:**
- ✅ **Uses customer email** (`contact_email`) instead of `business_customer_id` - easier for third-party integration
- ✅ **Auto-creates customer** if email doesn't exist - no need to pre-create customers
- ✅ **Updates customer info** if customer exists - optional fields update existing customer data
- ✅ **Payment link** (`payment_link`): URL where user makes payment - `https://fsscredit.foodstuff.store/checkout/{slug}`
- ✅ **Callback URL** (`callback_url`): URL where user will be redirected after payment (with payment status in query params)
- ✅ **Payment link expires in 30 minutes** from creation
- ✅ Invoice status is `pending` (doesn't affect customer balance yet)
- ✅ If link expires, business can regenerate it via `POST /api/business/invoices/{id}/generate-link`

**Important for Third Parties:**
- **`payment_link`**: Send this to your customer - this is where they make payment
- **`callback_url`**: This is where the user will be redirected after payment (you'll receive this in payment response with payment status appended)

---

### Step 2: Email Notifications Sent

**Automatically sent to:**
1. **Customer Email** (sent to `contact_email`):
   - Email includes invoice details, items table, and payment link
   - Payment link: `https://fsscredit.foodstuff.store/checkout/{slug}`
   - Clickable "Pay Invoice" button

2. **Accounting Team:**
   - `accounting@foodstuff.store`
   - `accountings@foodstuff.store`
   - Email includes full invoice details

**Email Content:**
- Invoice details (ID, amount, dates, status)
- Items/products in HTML table format
- Payment link with expiration time
- Clickable "Pay Invoice" button

**Note:** Email is automatically sent when invoice is created.

---

### Step 3: Customer Pays Invoice

**Customer accesses payment link:**
`https://fsscredit.foodstuff.store/checkout/inv-abc123xyz456`

**Customer provides:**
- Account number (16 digits)
- CVV (3 digits)
- PIN (4 digits)

**Payment is processed:**
- Invoice status changes to `paid`
- Customer balance is deducted
- Business receives payout (if applicable)
- **✅ Customer receives payment confirmation email** (PaymentSuccessNotification)
- **✅ Payment response includes `callback_url`** - Frontend redirects customer to business site after payment (with payment status in query params)

---

### Step 4: Payment Status Webhook Sent to Callback URL

**When:** Immediately after payment attempt (success or failure)

**Callback URL:** Business's `webhook_url` (configured in business profile/dashboard)

**Webhook Events:**
- `payment.succeeded` - When payment is successful
- `payment.failed` - When payment fails (invalid credentials, insufficient credit, etc.)

**Webhook Payload - Payment Succeeded:**
```json
{
  "event": "payment.succeeded",
  "timestamp": "2024-01-15T11:00:00Z",
  "data": {
    "invoice_id": "FSCREDIT-ABC123",
    "slug": "inv-abc123xyz456",
    "status": "succeeded",
    "amount": "50000.00",
    "total_amount": "50000.00",
    "paid_amount": "50000.00",
    "remaining_balance": "0.00",
    "customer_email": "customer@example.com",
    "customer_name": "ABC Company",
    "account_number": "1234567890123456",
    "payment_reference": "TXN-2024-001",
    "paid_at": "2024-01-15T11:00:00Z"
  }
}
```

**Webhook Payload - Payment Failed:**
```json
{
  "event": "payment.failed",
  "timestamp": "2024-01-15T11:00:00Z",
  "data": {
    "invoice_id": "FSCREDIT-ABC123",
    "slug": "inv-abc123xyz456",
    "status": "failed",
    "amount": "50000.00",
    "reason": "Invalid CVV",
    "customer_email": "customer@example.com",
    "customer_name": "ABC Company",
    "failed_at": "2024-01-15T11:00:00Z"
  }
}
```

**Failure Reasons:**
- `Invalid CVV` - Customer provided wrong CVV
- `Invalid PIN` - Customer provided wrong PIN
- `Account pending approval` - Customer account not yet approved
- `Account not active` - Customer account is inactive
- `Insufficient credit` - Customer doesn't have enough credit
- Other error messages from payment processing

**Webhook Headers:**
- `X-FSCredit-Signature`: HMAC SHA256 signature of the payload (verify this!)
- `X-FSCredit-Event`: Event type (`payment.succeeded` or `payment.failed`)
- `Content-Type`: `application/json`

**Business Response Handling:**
- ✅ **If business responds with HTTP 200:** Webhook marked as delivered, no more retries
- ❌ **If business doesn't respond with 200:** Webhook marked as failed, retry scheduled in 1 hour

**Important:** 
- Webhooks are **ONLY** sent for payment status (succeeded/failed). No webhook is sent when invoice is created.
- **Always verify the webhook signature** using your API token to ensure authenticity.

---

## Webhook Retry Logic

### How It Works

1. **Initial Webhook Send:**
   - Webhook is sent to business callback URL
   - Delivery record is created for tracking
   - Status: `pending`

2. **Business Response:**
   - **HTTP 200 Response:** 
     - Status changed to `delivered`
     - `delivered_at` timestamp set
     - **No more retries** - webhook is complete
   
   - **Non-200 Response or Error:**
     - Status changed to `failed`
     - `next_retry_at` set to 1 hour from now
     - Error message and HTTP status stored

3. **Automatic Retry:**
   - Scheduled job runs every hour: `webhooks:retry-failed`
   - Checks for failed webhooks where `next_retry_at <= now()`
   - Retries each failed webhook
   - If business responds with 200: marked as delivered, stops retrying
   - If still fails: `next_retry_at` updated to 1 hour later, continues retrying

### Retry Schedule

```
Attempt 1: Immediately (when payment status changes)
Attempt 2: 1 hour later (if attempt 1 failed)
Attempt 3: 2 hours later (if attempt 2 failed)
Attempt 4: 3 hours later (if attempt 3 failed)
...continues every hour until business responds with 200
```

### Webhook Delivery Status

**Status Values:**
- `pending`: Initial state, webhook being sent
- `delivered`: Business responded with HTTP 200, no more retries
- `failed`: Business didn't respond with 200, will retry

**Fields Tracked:**
- `attempts`: Number of delivery attempts
- `http_status`: HTTP response status code
- `response_body`: Response body from business
- `error_message`: Error message if failed
- `delivered_at`: Timestamp when successfully delivered
- `next_retry_at`: When to retry next (1 hour after last attempt)

---

## API Endpoints Summary

### 1. Create Invoice
**POST** `/api/business/submit-invoice`

**Required Parameters:**
- `contact_email` (string, email): Customer's email address
- `amount` (numeric): Invoice amount

**Optional Parameters:**
- `business_name` (string): Customer's business name
- `contact_name` (string): Contact person's name
- `contact_phone` (string): Contact phone number
- `address` (string): Customer's address
- `purchase_date` (date): Date of purchase
- `due_date` (date): Due date for payment
- `description` (string): Invoice description
- `items` (array): Array of purchased items

**Returns:**
- Invoice details
- **Payment link:** `https://fsscredit.foodstuff.store/checkout/{slug}` (expires in 30 minutes)
- Customer information (auto-created if email doesn't exist)
- Invoice status

**Triggers:**
- ✅ Email to customer (sent to `contact_email`)
- ✅ Email to accounting team
- ✅ Auto-creates customer if email doesn't exist
- ❌ **No webhook on invoice creation** - Webhooks are only sent for payment status

---

### 2. Customer Pays Invoice
**POST** `/api/invoice/checkout/{slug}/pay`

**Request:**
```json
{
  "account_number": "1234567890123456",
  "cvv": "123",
  "pin": "1234"
}
```

**Response (200 OK - Success):**
```json
{
  "message": "Payment processed successfully",
  "invoice": {
    "id": 1,
    "invoice_id": "FSCREDIT-ABC123",
    "status": "paid",
    "paid_amount": "50000.00",
    "remaining_balance": "0.00"
  },
  "callback_url": "https://yourbusiness.com/payment/success?status=succeeded&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456"
}
```

**Response (400 Bad Request - Error):**
```json
{
  "message": "Invalid CVV",
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invalid%20CVV"
}
```

**Important:** 
- All payment responses (both success and error) include `callback_url` field
- **Callback URL includes payment status as query parameters:**
  - `status`: `succeeded` or `failed`
  - `invoice_id`: Invoice ID (e.g., "FSCREDIT-ABC123")
  - `slug`: Payment link slug
  - `reason`: Failure reason (only for failed payments, URL encoded)
- Frontend should redirect user to `callback_url` after payment
- If business hasn't set a callback URL, this field will be `null`

**Triggers:**
- ✅ Invoice status updated to `paid` (if successful)
- ✅ Customer balance deducted (if successful)
- ✅ **Email to customer** - Payment confirmation email (PaymentSuccessNotification)
- ✅ **Webhook to business callback URL** with payment status:
  - `payment.succeeded` - if payment successful
  - `payment.failed` - if payment failed (with reason)
- ✅ **Callback URL in all responses** - Frontend can redirect customer to `callback_url` after payment (success or error, with payment status in query params)

---

## Webhook Events

**Important:** Webhooks are **ONLY** sent for payment status. No webhook is sent when invoice is created.

### 1. Payment Succeeded
**Event:** `payment.succeeded`

**Triggered:** When customer successfully pays invoice via payment link

**Payload:**
```json
{
  "event": "payment.succeeded",
  "timestamp": "2024-01-15T11:00:00Z",
  "data": {
    "invoice_id": "FSCREDIT-ABC123",
    "slug": "inv-abc123xyz456",
    "status": "succeeded",
    "amount": "50000.00",
    "total_amount": "50000.00",
    "paid_amount": "50000.00",
    "remaining_balance": "0.00",
    "customer_email": "customer@example.com",
    "customer_name": "ABC Company",
    "account_number": "1234567890123456",
    "payment_reference": "TXN-2024-001",
    "paid_at": "2024-01-15T11:00:00Z"
  }
}
```

---

### 2. Payment Failed
**Event:** `payment.failed`

**Triggered:** When payment attempt fails (invalid credentials, insufficient credit, etc.)

**Payload:**
```json
{
  "event": "payment.failed",
  "timestamp": "2024-01-15T11:00:00Z",
  "data": {
    "invoice_id": "FSCREDIT-ABC123",
    "slug": "inv-abc123xyz456",
    "status": "failed",
    "amount": "50000.00",
    "reason": "Invalid CVV",
    "customer_email": "customer@example.com",
    "customer_name": "ABC Company",
    "failed_at": "2024-01-15T11:00:00Z"
  }
}
```

**Common Failure Reasons:**
- `Invalid CVV` - Wrong CVV provided
- `Invalid PIN` - Wrong PIN provided
- `Account pending approval` - Customer account not approved
- `Account not active` - Customer account inactive
- `Insufficient credit` - Customer doesn't have enough credit
- Other processing errors

---

## Business Webhook Implementation

### Example Webhook Handler (PHP)

```php
<?php
// Your webhook endpoint: https://yourbusiness.com/webhook/fscredit

$payload = json_decode(file_get_contents('php://input'), true);

$event = $payload['event'] ?? null;
$data = $payload['data'] ?? [];

switch ($event) {
    case 'payment.succeeded':
        // Payment was successful
        $invoiceId = $data['invoice_id'];
        $status = $data['status']; // "succeeded"
        $paidAmount = $data['paid_amount'];
        $customerEmail = $data['customer_email'];
        
        // Update your system
        updateOrderStatus($invoiceId, 'paid');
        processPayment($invoiceId, $paidAmount);
        notifyCustomer($customerEmail, $invoiceId);
        
        // IMPORTANT: Respond with HTTP 200 to stop retries
        http_response_code(200);
        echo json_encode(['received' => true]);
        break;
        
    case 'payment.failed':
        // Payment failed
        $invoiceId = $data['invoice_id'];
        $reason = $data['reason'];
        $customerEmail = $data['customer_email'];
        
        // Update your system
        updateOrderStatus($invoiceId, 'payment_failed');
        logPaymentFailure($invoiceId, $reason);
        notifyCustomerOfFailure($customerEmail, $invoiceId, $reason);
        
        // IMPORTANT: Respond with HTTP 200 to stop retries
        http_response_code(200);
        echo json_encode(['received' => true]);
        break;
        
    default:
        // Unknown event
        http_response_code(200); // Still respond with 200
        echo json_encode(['received' => true]);
}
```

### Example Webhook Handler (Node.js/Express)

```javascript
const crypto = require('crypto');

app.post('/webhook/fscredit', (req, res) => {
  // Verify webhook signature
  const payload = JSON.stringify(req.body);
  const receivedSignature = req.headers['x-fscredit-signature'];
  const expectedSignature = crypto
    .createHmac('sha256', yourApiToken)
    .update(payload)
    .digest('hex');

  if (receivedSignature !== expectedSignature) {
    return res.status(401).json({ error: 'Invalid webhook signature' });
  }

  const { event, data } = req.body;
  
  switch (event) {
    case 'payment.succeeded':
      // Handle successful payment
      console.log('Payment succeeded:', data.invoice_id);
      console.log('Amount paid:', data.paid_amount);
      console.log('Customer:', data.customer_email);
      
      // Update your system
      updateOrderStatus(data.invoice_id, 'paid');
      processPayment(data.invoice_id, data.paid_amount);
      
      // IMPORTANT: Respond with HTTP 200 to stop retries
      res.status(200).json({ received: true });
      break;
      
    case 'payment.failed':
      // Handle payment failure
      console.log('Payment failed:', data.invoice_id);
      console.log('Reason:', data.reason);
      console.log('Customer:', data.customer_email);
      
      // Update your system
      updateOrderStatus(data.invoice_id, 'payment_failed');
      logPaymentFailure(data.invoice_id, data.reason);
      
      // IMPORTANT: Respond with HTTP 200 to stop retries
      res.status(200).json({ received: true });
      break;
      
    default:
      // Unknown event - still respond with 200
      res.status(200).json({ received: true });
  }
});
```

---

## Important Notes

### Payment Link Format
- **Base URL:** `https://fsscredit.foodstuff.store`
- **Format:** `https://fsscredit.foodstuff.store/checkout/{slug}`
- **Example:** `https://fsscredit.foodstuff.store/checkout/inv-abc123xyz456`
- **Expiration:** Payment links expire **30 minutes** after invoice creation
- **Expired Links:** If link expires, customer will receive error message. Business can regenerate link via API

### Webhook Retry Behavior
- ✅ **Business responds with 200:** Webhook stops retrying immediately
- ❌ **Business doesn't respond with 200:** Webhook retries every 1 hour
- 🔄 **Retries continue** until business responds with 200
- 📊 **All attempts tracked** for retry management

### Email Notifications
- ✅ **When Invoice is Created:**
  - Customer receives email (sent to `contact_email`)
  - Email includes invoice details, items table, and payment link
  - Accounting team receives email (`accounting@foodstuff.store` and `accountings@foodstuff.store`)
- ✅ **When Payment is Done:**
  - Customer receives payment confirmation email
  - Email confirms successful payment and invoice details

### Webhook Security

**Webhook Signature Authentication:**
- ✅ **HMAC SHA256 Signature** - All webhooks include `X-FSCredit-Signature` header
- ✅ **Signature Generation** - Signature is generated using: `HMAC-SHA256(payload, api_token)`
- ✅ **Verification Required** - Businesses MUST verify the signature to ensure webhook authenticity
- ✅ **API Token** - Uses the same API token generated in business dashboard

**How to Verify Webhook Signature:**
```php
// PHP Example
$payload = file_get_contents('php://input');
$receivedSignature = $_SERVER['HTTP_X_FSCREDIT_SIGNATURE'] ?? '';
$expectedSignature = hash_hmac('sha256', $payload, $yourApiToken);

if (!hash_equals($expectedSignature, $receivedSignature)) {
    http_response_code(401);
    die('Invalid signature');
}
```

```javascript
// Node.js Example
const crypto = require('crypto');
const payload = JSON.stringify(req.body);
const receivedSignature = req.headers['x-fscredit-signature'];
const expectedSignature = crypto
    .createHmac('sha256', yourApiToken)
    .update(payload)
    .digest('hex');

if (receivedSignature !== expectedSignature) {
    return res.status(401).json({ error: 'Invalid signature' });
}
```

**Security Best Practices:**
- Always verify webhook signature before processing
- Use HTTPS for webhook URLs
- Log all webhook deliveries for audit
- Never expose your API token in client-side code

---

## Scheduled Command

**Command:** `php artisan webhooks:retry-failed`

**Schedule:** Runs every hour automatically

**What it does:**
- Finds all failed webhooks where `next_retry_at <= now()`
- Retries each webhook
- Updates status based on response
- Schedules next retry if still failed

**To run manually:**
```bash
php artisan webhooks:retry-failed
```

---

## Configuration

### Environment Variables

```env
# Frontend URL for payment links
FRONTEND_URL=https://fsscredit.foodstuff.store

# If not set, defaults to https://fsscredit.foodstuff.store
```

### Business Profile Settings

Businesses can configure:
- **Callback URL (Webhook URL):** Set in business profile/dashboard - this is where payment status webhooks are sent
- **Redirect URL:** Set in business profile/dashboard - default URL to redirect customers after successful payment
- **API Token:** Generated automatically when business is approved

**Setting Callback URL:**
- Businesses set their `webhook_url` in their profile/dashboard
- This URL receives webhooks for payment status (succeeded/failed)
- URL must be HTTPS and publicly accessible
- Business must respond with HTTP 200 to stop retries
- **Must verify webhook signature** using API token

**Setting Callback URL (Post-Payment Redirect):**
- **Callback URL** is where the user gets redirected AFTER making payment
- Businesses can set default `callback_url` in their profile/dashboard
- Can also pass `callback_url` per invoice when creating (overrides default)
- **All payment responses** (success and error) include `callback_url` with payment status as query parameters
- Frontend redirects customer to this URL after payment
- **Payment status is automatically added** to callback URL as query parameters:
  - Success: `?status=succeeded&invoice_id=XXX&slug=YYY`
  - Failure: `?status=failed&invoice_id=XXX&slug=YYY&reason=ZZZ`
- Business site can read query parameters to know payment result
- If no callback URL is set, `callback_url` will be `null` - frontend can show message instead

**Note:** `redirect_url` (if still used) is for the payment link itself, while `callback_url` is where user goes after payment.

**Example Callback URLs:**

**Success:**
```
https://yourbusiness.com/payment/success?status=succeeded&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456
```

**Failure:**
```
https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invalid%20CVV
```

**Business Site Implementation:**
```javascript
// On your payment success/failure page (callback URL)
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status'); // 'succeeded' or 'failed'
const invoiceId = urlParams.get('invoice_id');
const slug = urlParams.get('slug');
const reason = urlParams.get('reason'); // Only for failures

if (status === 'succeeded') {
  showSuccessMessage(`Payment successful for invoice ${invoiceId}`);
  // Update order status, send confirmation, etc.
} else if (status === 'failed') {
  showErrorMessage(`Payment failed: ${reason}`);
  // Handle failed payment, allow retry, etc.
}
```

---

## Testing

### Test Invoice Creation

```bash
curl -X POST https://nodopay-api-0fbd4546e629.herokuapp.com/api/business/submit-invoice \
  -H "Authorization: Bearer fscredit_biz_your_token" \
  -H "Content-Type: application/json" \
  -d '{
    "contact_email": "customer@example.com",
    "business_name": "ABC Company",
    "amount": 50000.00,
    "items": [
      {
        "name": "Test Product",
        "quantity": 1,
        "price": 50000.00
      }
    ]
  }'
```

### Test Webhook Endpoint

Set up a test webhook URL (e.g., using webhook.site or ngrok):

1. Get test webhook URL: `https://webhook.site/unique-id`
2. Update business webhook URL via API or dashboard
3. Create invoice
4. Check webhook.site for incoming webhook
5. Respond with HTTP 200
6. Verify webhook stops retrying

---

## Summary

✅ **Invoice Creation:**
- Business creates invoice using API token
- Response includes payment link: `https://fsscredit.foodstuff.store/checkout/{slug}`
- Customer receives email with invoice details
- Accounting team receives email
- Webhook sent to business callback URL

✅ **Payment Processing:**
- Customer pays via payment link
- Invoice status updated to `paid`
- Webhook sent to business with payment status

✅ **Webhook Retry:**
- If business responds with 200: stops retrying
- If business doesn't respond with 200: retries every 1 hour
- Continues until business responds with 200

---

**Base URL:** `https://nodopay-api-0fbd4546e629.herokuapp.com/api`  
**Frontend URL:** `https://fsscredit.foodstuff.store`
