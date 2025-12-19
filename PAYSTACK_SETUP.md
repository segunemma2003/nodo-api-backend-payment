# Paystack Setup Guide

## Current Status

**⚠️ Paystack keys are NOT currently configured in your environment.**

## Quick Setup

### 1. Add Paystack Keys to .env

Add these lines to your `.env` file:

```env
PAYSTACK_SECRET_KEY=sk_test_xxxxxxxxxxxxx
PAYSTACK_PUBLIC_KEY=pk_test_xxxxxxxxxxxxx
```

**For Production:**
```env
PAYSTACK_SECRET_KEY=sk_live_xxxxxxxxxxxxx
PAYSTACK_PUBLIC_KEY=pk_live_xxxxxxxxxxxxx
```

### 2. Get Your Paystack Keys

1. Log in to your [Paystack Dashboard](https://dashboard.paystack.com)
2. Go to **Settings** → **API Keys & Webhooks**
3. Copy your **Secret Key** and **Public Key**
4. Add them to your `.env` file

### 3. Verify Configuration

Check if Paystack is configured:

```bash
# Via API endpoint
curl http://your-domain.com/api/paystack/status

# Or via tinker
php artisan tinker
>>> config('services.paystack.secret_key')
```

**Expected Response:**
```json
{
  "configured": true,
  "secret_key_set": true,
  "public_key_set": true,
  "message": "Paystack is properly configured"
}
```

## Features

### ✅ Automatic Virtual Account Creation

- **New Registrations**: Virtual accounts are created automatically via queue job (non-blocking)
- **Admin-Created Customers**: Virtual accounts are created automatically via queue job
- **Existing Customers**: Can generate virtual accounts manually via API

### ✅ Manual Generation for Existing Customers

**Endpoint:** `POST /api/customer/repayment-account/generate`

**Request:**
```bash
curl -X POST http://your-domain.com/api/customer/repayment-account/generate \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json"
```

**Response (Success - Job Queued):**
```json
{
  "success": true,
  "message": "Virtual account generation has been queued. Your virtual account will be created shortly. Please check your account details in a few moments.",
  "status": "queued",
  "customer_id": 1,
  "note": "You can check your virtual account status by calling GET /api/customer/repayment-account"
}
```

**Note:** The virtual account is created asynchronously via a queue job. Check the status after a few seconds using the GET endpoint.

**Response (If Paystack Not Configured):**
```json
{
  "success": false,
  "message": "Paystack is not configured. Please set PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY in your .env file and contact administrator.",
  "paystack_configured": false
}
```

### ✅ Check Virtual Account Status

**Endpoint:** `GET /api/customer/repayment-account`

**Response:**
```json
{
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Sterling Bank",
  "has_virtual_account": true
}
```

## Queue Worker

Make sure your queue worker is running to process virtual account creation jobs:

```bash
# Development
php artisan queue:work

# Production (with supervisor/systemd)
# Configure queue worker as a service
```

## Troubleshooting

### Virtual Account Not Created

1. **Check Paystack Configuration:**
   ```bash
   curl http://your-domain.com/api/paystack/status
   ```

2. **Check Queue Jobs:**
   ```bash
   php artisan queue:failed
   php artisan queue:retry all
   ```

3. **Check Logs:**
   ```bash
   tail -f storage/logs/laravel.log | grep -i paystack
   ```

### Paystack Keys Not Working

1. Verify keys are correct in `.env`
2. Clear config cache: `php artisan config:clear`
3. Restart queue worker if running
4. Check Paystack dashboard for API key status

## Next Steps

1. ✅ Add Paystack keys to `.env` file
2. ✅ Clear config cache: `php artisan config:clear`
3. ✅ Start queue worker: `php artisan queue:work`
4. ✅ Test virtual account creation
5. ✅ Configure webhook URL in Paystack dashboard: `https://your-domain.com/api/payments/webhook/paystack`

