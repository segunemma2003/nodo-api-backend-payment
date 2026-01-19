# Payment Response Examples

## Successful Payment Response

**Endpoint:** `POST /api/invoice/checkout/{slug}/pay`

**Request:**
```json
{
  "account_number": "1234567890123456",
  "cvv": "123",
  "pin": "1234"
}
```

**Response (200 OK):**
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

**Note:** The `callback_url` includes payment status as query parameters:
- `status=succeeded` - Payment was successful
- `invoice_id=FSCREDIT-ABC123` - Invoice ID
- `slug=inv-abc123xyz456` - Payment link slug

**Response Fields:**
- `message`: Success message
- `invoice.id`: Internal invoice ID
- `invoice.invoice_id`: Public invoice ID (e.g., "FSCREDIT-ABC123")
- `invoice.status`: Invoice status (will be "paid" after successful payment)
- `invoice.paid_amount`: Amount that was paid
- `invoice.remaining_balance`: Remaining balance (usually "0.00" after full payment)
- `callback_url`: URL to redirect customer after payment (can be `null` if not set)

---

## Error Responses

### Invalid CVV (400 Bad Request)
```json
{
  "message": "Invalid CVV",
  "redirect_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invalid%20CVV"
}
```

**Note:** Error responses include failure reason in the callback URL:
- `status=failed` - Payment failed
- `invoice_id=FSCREDIT-ABC123` - Invoice ID
- `slug=inv-abc123xyz456` - Payment link slug
- `reason=Invalid%20CVV` - Failure reason (URL encoded)

### Invalid PIN (400 Bad Request)
```json
{
  "message": "Invalid PIN. Please use your payment PIN (not the default 0000)",
  "redirect_url": "https://yourbusiness.com/payment/success"
}
```

### Account Pending Approval (400 Bad Request)
```json
{
  "message": "Your account is pending approval. Please wait for admin approval before making payments.",
  "redirect_url": "https://yourbusiness.com/payment/success"
}
```

### Account Not Active (400 Bad Request)
```json
{
  "message": "Your account is not active",
  "redirect_url": "https://yourbusiness.com/payment/success"
}
```

### Payment Link Expired (400 Bad Request)
```json
{
  "message": "This payment link has expired. Please request a new payment link from the business.",
  "expired_at": "2024-01-15T11:00:00Z",
  "redirect_url": "https://yourbusiness.com/payment/success"
}
```

### Invoice Already Paid (400 Bad Request)
```json
{
  "message": "Invoice is already paid",
  "invoice": {
    "id": 1,
    "invoice_id": "FSCREDIT-ABC123",
    "status": "paid"
  },
  "redirect_url": "https://yourbusiness.com/payment/success"
}
```

### Invoice Link Already Used (400 Bad Request)
```json
{
  "message": "This invoice link has already been used",
  "redirect_url": "https://yourbusiness.com/payment/success"
}
```

### Invoice Does Not Belong to Account (400 Bad Request)
```json
{
  "message": "This invoice does not belong to the provided account",
  "redirect_url": "https://yourbusiness.com/payment/success"
}
```

**Note:** All error responses include `callback_url` (can be `null` if not set by business). Frontend can redirect to this URL even on errors if needed.

---

## Frontend Implementation Example

### JavaScript/React Example

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
      console.log('Payment successful:', data);
      
      // Redirect to business site if callback_url is provided
      if (data.callback_url) {
        window.location.href = data.callback_url;
      } else {
        // Show success message if no redirect URL
        showSuccessMessage('Payment processed successfully!');
      }
    } else {
      // Payment failed
      console.error('Payment failed:', data.message);
      showErrorMessage(data.message);
    }
  } catch (error) {
    console.error('Payment error:', error);
    showErrorMessage('An error occurred while processing payment');
  }
}
```

### Vue.js Example

```javascript
async payInvoice() {
  try {
    const response = await this.$http.post(
      `/api/invoice/checkout/${this.slug}/pay`,
      {
        account_number: this.accountNumber,
        cvv: this.cvv,
        pin: this.pin,
      }
    );

    if (response.data.callback_url) {
      // Redirect to business site
      window.location.href = response.data.callback_url;
    } else {
      // Show success message
      this.showSuccess('Payment processed successfully!');
    }
  } catch (error) {
    if (error.response) {
      this.showError(error.response.data.message);
    } else {
      this.showError('An error occurred while processing payment');
    }
  }
}
```

---

## Response Status Codes

- **200 OK**: Payment processed successfully
- **400 Bad Request**: Payment failed (invalid credentials, expired link, etc.)
- **404 Not Found**: Invoice not found
- **422 Unprocessable Entity**: Validation errors
- **500 Internal Server Error**: Server error

---

## Notes

1. **Callback URL with Payment Status**: 
   - **All payment responses** (success and error) include `callback_url` field
   - **Callback URL includes payment status** as query parameters:
     - Success: `?status=succeeded&invoice_id=XXX&slug=YYY`
     - Failure: `?status=failed&invoice_id=XXX&slug=YYY&reason=ZZZ`
   - Business site can read query parameters to know payment result
   - If business set `callback_url` in profile or when creating invoice, it will be used
   - If no `callback_url` is set, the field will be `null`
   - Frontend should handle both cases appropriately

2. **Query Parameters in Callback URL:**
   - `status`: Payment status (`succeeded` or `failed`)
   - `invoice_id`: Invoice ID (e.g., "FSCREDIT-ABC123")
   - `slug`: Payment link slug (e.g., "inv-abc123xyz456")
   - `reason`: Failure reason (only for failed payments, URL encoded)

3. **Business Site Implementation:**
   ```javascript
   // Read payment status from URL
   const params = new URLSearchParams(window.location.search);
   const status = params.get('status');
   const invoiceId = params.get('invoice_id');
   const reason = params.get('reason');
   
   if (status === 'succeeded') {
     // Show success message
   } else if (status === 'failed') {
     // Show error message with reason
   }
   ```

2. **Invoice Status**: 
   - After successful payment, `status` will be `"paid"`
   - `remaining_balance` will be `"0.00"` if full payment was made

3. **Email Notification**: 
   - Customer automatically receives payment confirmation email
   - Business receives webhook notification

4. **Webhook**: 
   - Business callback URL receives `payment.succeeded` webhook
   - Webhook includes full payment details
