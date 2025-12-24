# Paystack Webhook Documentation

## Overview

Yes, there is a webhook endpoint that automatically captures payments made to customer virtual accounts and updates their repayment details in real-time.

## Webhook Endpoint

**URL:** `POST /api/payments/webhook/paystack`

**Configure in Paystack Dashboard:**
1. Go to Settings → API Keys & Webhooks
2. Add webhook URL: `https://yourdomain.com/api/payments/webhook/paystack`
3. Select events to listen for:
   - `charge.success` ✅ (Required)
   - `transfer.success` ✅ (Required)
   - `charge.failed` (Optional - for logging)
   - `transfer.failed` (Optional - for logging)

## How It Works

### 1. Payment Flow

```
Customer makes payment → Paystack processes → Webhook sent → System updates repayment details
```

### 2. What Gets Updated Automatically

When a payment is received via webhook, the system automatically:

1. **Finds the Customer**
   - Matches payment to customer by virtual account number
   - Falls back to email matching if needed

2. **Creates Payment Record**
   - Records payment amount, reference, and timestamp
   - Links payment to customer

3. **Updates Invoice Repayments**
   - Applies payment to unpaid invoices first
   - Then applies to paid invoices with outstanding credit
   - Updates invoice statuses (pending → paid, etc.)
   - Updates `credit_repaid_status` and `credit_repaid_amount`

4. **Updates Customer Balances**
   - Recalculates `current_balance`
   - Recalculates `available_balance`
   - Updates credit utilization

5. **Creates Transaction Record**
   - Logs transaction for audit trail
   - Links to invoice if applicable

6. **Sends Notifications**
   - Notifies customer of successful payment
   - Sends webhook to business/supplier if applicable

### 3. Payment Processing Logic

The `processRepayment()` method in `PaymentService` handles:

- **Priority Order:**
  1. Unpaid invoices (by due date)
  2. Paid invoices with outstanding credit repayment

- **Invoice Updates:**
  - `paid_amount` - increases
  - `remaining_balance` - decreases
  - `status` - updates (pending → paid, etc.)
  - `credit_repaid_amount` - increases
  - `credit_repaid_status` - updates (pending → partially_paid → fully_paid)

- **Customer Balance Updates:**
  - `current_balance` - total amount owed
  - `available_balance` - credit limit minus current balance

## Webhook Security

### Signature Verification

The webhook verifies Paystack's signature to ensure authenticity:

```php
$signature = $request->header('X-Paystack-Signature');
$payload = $request->getContent();
$isValid = $paystackService->verifyWebhookSignature($payload, $signature);
```

**Invalid signatures are rejected with 401 Unauthorized.**

## Webhook Events Handled

### ✅ charge.success
- Triggered when payment is successfully made to virtual account
- Processes payment and updates repayment details
- Returns payment confirmation

### ✅ transfer.success
- Triggered when transfer to virtual account succeeds
- Processes payment and updates repayment details
- Returns payment confirmation

### 📝 charge.failed / transfer.failed
- Logged for monitoring purposes
- No payment processing occurs
- Returns success to acknowledge receipt

## Example Webhook Payload

### charge.success Event

```json
{
  "event": "charge.success",
  "data": {
    "id": 1234567890,
    "reference": "T1234567890",
    "amount": 5000000,
    "currency": "NGN",
    "customer": {
      "id": 12345,
      "email": "customer@example.com",
      "customer_code": "CUS_xxxxx"
    },
    "authorization": {
      "account_number": "1234567890"
    },
    "paid_at": "2024-01-15T10:30:00.000Z",
    "created_at": "2024-01-15T10:29:45.000Z"
  }
}
```

### Response

```json
{
  "success": true,
  "message": "Payment processed successfully and repayment details updated",
  "payment": {
    "payment_reference": "PAY-xxxxx",
    "amount": "50000.00",
    "status": "completed",
    "transaction_reference": "T1234567890"
  },
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "available_balance": "450000.00",
    "current_balance": "50000.00"
  }
}
```

## Duplicate Prevention

The webhook checks for duplicate payments using `transaction_reference`:

- If payment with same reference exists → Returns existing payment (no duplicate processing)
- Prevents double-charging or duplicate updates

## Testing the Webhook

### 1. Using Paystack Test Mode

1. Use test keys in `.env`
2. Make test payment to virtual account
3. Check webhook logs: `storage/logs/laravel.log`
4. Verify payment appears in customer dashboard

### 2. Manual Testing

You can test the webhook manually (for development):

```bash
curl -X POST http://your-domain.com/api/payments/webhook/paystack \
  -H "Content-Type: application/json" \
  -H "X-Paystack-Signature: test_signature" \
  -d '{
    "event": "charge.success",
    "data": {
      "reference": "TEST_123",
      "amount": 5000000,
      "customer": {
        "email": "customer@example.com"
      },
      "paid_at": "2024-01-15T10:30:00.000Z"
    }
  }'
```

**Note:** Signature verification will fail in manual testing. For production, always use Paystack's webhook system.

## Monitoring

### Check Webhook Status

1. **Paystack Dashboard:**
   - Settings → API Keys & Webhooks
   - View webhook delivery logs
   - See success/failure rates

2. **Application Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i paystack
   ```

3. **Failed Payments:**
   - Check `failed_jobs` table if using queue
   - Review error logs for processing failures

## Troubleshooting

### Payment Not Processing

1. **Check Webhook URL:**
   - Verify URL is correct in Paystack dashboard
   - Ensure URL is publicly accessible (not localhost)

2. **Check Signature:**
   - Verify `PAYSTACK_SECRET_KEY` is correct
   - Signature mismatch will reject webhook

3. **Check Customer Matching:**
   - Verify customer has virtual account number
   - Check logs for "Customer not found" errors

4. **Check Payment Amount:**
   - Verify amount > 0
   - Check if amount is in kobo (Paystack format)

### Payment Processed But Not Showing

1. **Check Queue:**
   - Ensure queue worker is running
   - Check for failed jobs

2. **Check Cache:**
   - Clear customer cache: `php artisan cache:clear`
   - Refresh customer dashboard

3. **Check Database:**
   - Verify payment record exists in `payments` table
   - Check customer balances updated

## Dashboard Updates

After webhook processes payment:

- Customer dashboard automatically shows:
  - Updated balances
  - Payment history
  - Invoice repayment status
  - Transaction records

- Admin dashboard shows:
  - Updated customer balances
  - Payment records
  - Transaction history

## Summary

✅ **Webhook exists** at `/api/payments/webhook/paystack`  
✅ **Automatically processes** payments to virtual accounts  
✅ **Updates repayment details** including invoices and balances  
✅ **Prevents duplicates** using transaction references  
✅ **Secure** with signature verification  
✅ **Real-time** updates to customer dashboard  

The webhook is fully functional and ready to process payments once Paystack keys are configured and webhook URL is set in Paystack dashboard.





