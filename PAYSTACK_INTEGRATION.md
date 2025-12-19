# Paystack Virtual Account Integration

## Overview

This integration automatically creates Paystack virtual accounts for customers when they register, and processes payments made to those virtual accounts.

## Setup

### 1. Environment Variables

Add the following to your `.env` file:

```env
PAYSTACK_SECRET_KEY=sk_test_xxxxxxxxxxxxx
PAYSTACK_PUBLIC_KEY=pk_test_xxxxxxxxxxxxx
```

**Note:** Use `sk_live_` and `pk_live_` for production.

### 2. Webhook Configuration

Configure the webhook URL in your Paystack dashboard:

**Webhook URL:** `https://yourdomain.com/api/payments/webhook/paystack`

**Events to listen for:**
- `charge.success` - When a payment is successfully made to a virtual account
- `transfer.success` - When a transfer is successful

## How It Works

### 1. Customer Registration

When a customer registers (either through `/api/auth/customer/register` or admin creates them), the system:

1. Creates the customer record immediately
2. Dispatches a background job to create a Paystack dedicated virtual account (doesn't block registration)
3. Returns the registration response immediately (virtual account may not be ready yet)
4. The virtual account is created asynchronously and stored when ready

**Note:** Virtual account creation is done in a queue to ensure fast registration. The virtual account will be available shortly after registration. Customers can check their virtual account status via the dashboard or generate it manually if needed.

### 2. Payment Processing

When a customer makes a payment to their virtual account:

1. Paystack sends a webhook to `/api/payments/webhook/paystack`
2. The system verifies the webhook signature
3. Finds the customer by virtual account number
4. Processes the payment using `PaymentService`
5. Updates customer balances and invoice statuses
6. Creates transaction records

### 3. Dashboard Display

The customer dashboard shows:
- Virtual account number
- Virtual account bank
- Payment history
- Updated balances after payments

**Endpoint:** `GET /api/customer/repayment-account`

## API Endpoints

### Customer Registration
- **POST** `/api/auth/customer/register`
  - Dispatches background job to create virtual account (non-blocking)
  - Virtual account will be created asynchronously
  - Customer can check status or generate manually if needed

### Generate Virtual Account (Existing Customers)
- **POST** `/api/customer/repayment-account/generate`
  - Dispatches a queue job to create virtual account asynchronously (non-blocking)
  - Returns immediately with status "queued"
  - Virtual account will be created in the background
  - Customer can check status via GET `/api/customer/repayment-account`

### Get Virtual Account Details
- **GET** `/api/customer/repayment-account`
  - Returns customer's virtual account details

### Paystack Webhook
- **POST** `/api/payments/webhook/paystack`
  - Receives payment notifications from Paystack
  - Automatically processes payments and updates dashboard

## Error Handling

- If virtual account creation fails during registration, the customer is still created (virtual account can be created later manually)
- All errors are logged for debugging
- Webhook signature verification prevents unauthorized requests

## Queue Configuration

Virtual account creation runs in a background queue. Make sure your queue worker is running:

```bash
php artisan queue:work
```

Or for production with supervisor/process manager.

## Testing

1. Register a new customer
2. Wait a few seconds for the queue job to process (or check queue status)
3. Check customer profile/dashboard to see virtual account details
4. Alternatively, use the generate endpoint for immediate creation
5. Make a test payment to the virtual account
6. Verify the webhook is received and payment is processed
7. Check the dashboard to see updated balances

## Troubleshooting

### Virtual Account Not Created

- Check Paystack API keys are correct
- Verify customer email and phone are valid
- Check application logs for errors

### Webhook Not Working

- Verify webhook URL is correctly configured in Paystack dashboard
- Check that webhook signature verification is working
- Review application logs for webhook processing errors

### Payments Not Showing on Dashboard

- Verify webhook is being received (check logs)
- Ensure customer is found by virtual account number
- Check that payment processing completed successfully

