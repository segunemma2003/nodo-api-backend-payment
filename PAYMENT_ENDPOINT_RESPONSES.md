# Payment Endpoint Responses

**Endpoint:** `POST /api/invoice/checkout/{slug}/pay`

**Request Body:**
```json
{
  "account_number": "1234567890123456",
  "cvv": "123",
  "pin": "1234"
}
```

---

## ✅ Success Response (200 OK)

**Response:**
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

**Fields:**
- `message`: Success message
- `invoice.id`: Internal invoice ID
- `invoice.invoice_id`: Public invoice ID
- `invoice.status`: Invoice status (will be "paid")
- `invoice.paid_amount`: Amount that was paid
- `invoice.remaining_balance`: Remaining balance (usually "0.00")
- `callback_url`: URL to redirect customer (includes payment status in query params)

---

## ❌ Error Responses

### 1. Validation Errors (422 Unprocessable Entity)

#### Missing Required Fields
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "account_number": ["The account number field is required."],
    "cvv": ["The cvv field is required."],
    "pin": ["The pin field is required."]
  }
}
```

#### Invalid Account Number Format
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "account_number": [
      "The account number must be 16 digits.",
      "The selected account number is invalid."
    ]
  }
}
```

#### Invalid CVV Format
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "cvv": ["The cvv must be 3 characters."]
  }
}
```

#### Invalid PIN Format
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "pin": ["The pin must be 4 characters."]
  }
}
```

---

### 2. Invoice Not Found (404 Not Found)

**Response:**
```json
{
  "message": "No query results for model [App\\Models\\Invoice] inv-newslug123"
}
```

**Cause:** Invoice with the provided slug doesn't exist.

---

### 3. Invoice Link Already Used (400 Bad Request)

**Response:**
```json
{
  "message": "This invoice link has already been used",
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invoice%20link%20already%20used"
}
```

**Cause:** The payment link has already been used to make a payment.

---

### 4. Payment Link Expired (400 Bad Request)

**Response:**
```json
{
  "message": "This payment link has expired. Please request a new payment link from the business.",
  "expired_at": "2024-01-15T11:00:00Z",
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Payment%20link%20expired"
}
```

**Cause:** Payment link expired (30 minutes after creation).

**Fields:**
- `expired_at`: ISO 8601 timestamp when the link expired
- `callback_url`: Includes failure reason in query params

---

### 5. Customer Not Found (404 Not Found)

**Response:**
```json
{
  "message": "No query results for model [App\\Models\\Customer] 1234567890123456"
}
```

**Cause:** Account number doesn't exist in the system.

---

### 6. Account Pending Approval (400 Bad Request)

**Response:**
```json
{
  "message": "Your account is pending approval. Please wait for admin approval before making payments.",
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Account%20pending%20approval"
}
```

**Cause:** Customer account `approval_status` is not `'approved'`.

**Note:** A webhook notification is sent to the business about this failure.

---

### 7. Account Not Active (400 Bad Request)

**Response:**
```json
{
  "message": "Your account is not active",
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Account%20not%20active"
}
```

**Cause:** Customer account `status` is not `'active'`.

**Note:** A webhook notification is sent to the business about this failure.

---

### 8. Invalid CVV (400 Bad Request)

**Response:**
```json
{
  "message": "Invalid CVV",
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invalid%20CVV"
}
```

**Cause:** CVV doesn't match the customer's stored CVV.

**Note:** A webhook notification is sent to the business about this failure.

---

### 9. Invalid PIN (400 Bad Request)

**Response:**
```json
{
  "message": "Invalid PIN. Please use your payment PIN (not the default 0000)",
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invalid%20PIN"
}
```

**Cause:** PIN doesn't match the customer's payment PIN (not the default PIN).

**Note:** A webhook notification is sent to the business about this failure.

---

### 10. Invoice Does Not Belong to Account (400 Bad Request)

**Response:**
```json
{
  "message": "This invoice does not belong to the provided account",
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invoice%20does%20not%20belong%20to%20account"
}
```

**Cause:** Invoice is already linked to a different customer and doesn't have a `business_customer_id`.

---

### 11. Invoice Already Paid (400 Bad Request)

**Response:**
```json
{
  "message": "Invoice is already paid",
  "invoice": {
    "id": 1,
    "invoice_id": "FSCREDIT-ABC123",
    "status": "paid"
  },
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invoice%20already%20paid"
}
```

**Cause:** Invoice status is already `'paid'`.

**Note:** The invoice is marked as `is_used = true` when this error occurs.

---

### 12. Payment Processing Failed (400 Bad Request)

**Response:**
```json
{
  "message": "Payment processing failed: [Error details]",
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=[URL%20encoded%20error%20message]"
}
```

**Possible Error Messages:**
- `"Insufficient credit available"`
- `"Database connection error"`
- `"Transaction processing failed"`
- Any exception thrown during `PaymentService::processInvoicePayment()`

**Cause:** Exception occurred during payment processing (credit check, balance update, transaction creation, etc.).

**Note:** A webhook notification is sent to the business about this failure.

---

## Callback URL Format

All responses (success and error) include a `callback_url` field that contains payment status as query parameters.

### Success Callback URL
```
https://yourbusiness.com/payment/success?status=succeeded&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456
```

**Query Parameters:**
- `status`: `succeeded`
- `invoice_id`: Invoice ID (e.g., "FSCREDIT-ABC123")
- `slug`: Payment link slug (e.g., "inv-abc123xyz456")

### Failure Callback URL
```
https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invalid%20CVV
```

**Query Parameters:**
- `status`: `failed`
- `invoice_id`: Invoice ID
- `slug`: Payment link slug
- `reason`: Failure reason (URL encoded)

**Note:** If no `callback_url` is set by the business, the field will be `null`.

---

## Response Status Codes Summary

| Status Code | Meaning | Example |
|------------|---------|---------|
| **200** | Payment processed successfully | Payment completed |
| **400** | Payment failed (business logic error) | Invalid CVV, expired link, etc. |
| **404** | Resource not found | Invoice or customer not found |
| **422** | Validation error | Missing or invalid fields |
| **500** | Server error | Database error, exception |

---

## Frontend Implementation Example

```javascript
async function payInvoice(slug, accountNumber, cvv, pin) {
  try {
    const response = await fetch(`/api/invoice/checkout/${slug}/pay`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        account_number: accountNumber,
        cvv: cvv,
        pin: pin,
      }),
    });

    const data = await response.json();

    if (response.ok) {
      // Payment successful
      if (data.callback_url) {
        // Redirect to business callback URL
        window.location.href = data.callback_url;
      } else {
        // Show success message
        showSuccessMessage('Payment processed successfully!');
      }
    } else {
      // Payment failed
      if (data.callback_url) {
        // Redirect to callback URL (includes failure status)
        window.location.href = data.callback_url;
      } else {
        // Show error message
        showErrorMessage(data.message);
      }
    }
  } catch (error) {
    console.error('Payment error:', error);
    showErrorMessage('An error occurred while processing payment');
  }
}
```

---

## Webhook Notifications

### Payment Success Webhook
Sent automatically by `PaymentService::processInvoicePayment()` when payment succeeds.

**Event:** `payment.succeeded`

**Payload:**
```json
{
  "event": "payment.succeeded",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "invoice_id": "FSCREDIT-ABC123",
    "slug": "inv-abc123xyz456",
    "status": "succeeded",
    "amount": 50000.00,
    "total_amount": 50000.00,
    "paid_amount": 50000.00,
    "remaining_balance": "0.00",
    "customer_email": "customer@example.com",
    "customer_name": "ABC Company",
    "account_number": "1234567890123456",
    "payment_reference": "PAY-xxxxx",
    "paid_at": "2024-01-15T10:30:00Z"
  }
}
```

### Payment Failure Webhook
Sent for the following failure scenarios:
- Account pending approval
- Account not active
- Invalid CVV
- Invalid PIN
- Payment processing exception

**Event:** `payment.failed`

**Payload:**
```json
{
  "event": "payment.failed",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "invoice_id": "FSCREDIT-ABC123",
    "slug": "inv-abc123xyz456",
    "status": "failed",
    "amount": 50000.00,
    "reason": "Invalid CVV",
    "customer_email": "customer@example.com",
    "customer_name": "ABC Company",
    "failed_at": "2024-01-15T10:30:00Z"
  }
}
```

---

## Important Notes

1. **All responses include `callback_url`** (can be `null` if not set by business)
2. **Callback URL includes payment status** as query parameters
3. **Frontend should redirect** to `callback_url` if it exists (both success and error)
4. **Webhook notifications** are sent for payment failures (except validation errors)
5. **Invoice is marked as `is_used = true`** after successful payment or if already paid
6. **Payment link expires** 30 minutes after invoice creation
7. **Customer account must be approved and active** to make payments

---

**Last Updated:** 2024
