# Payment Link Expiration

## Overview

Payment links generated for invoices expire **30 minutes** after creation. This ensures security and prevents unauthorized access to old payment links.

---

## Expiration Behavior

### When Invoice is Created

1. **Slug Generated:** Unique payment link slug is generated
2. **Expiration Set:** `slug_expires_at` is set to **30 minutes** from creation time
3. **Payment Link:** `https://fsscredit.foodstuff.store/checkout/{slug}`

### When Customer Accesses Link

**Before Expiration (< 30 minutes):**
- ✅ Link is valid
- ✅ Customer can view invoice details
- ✅ Customer can make payment

**After Expiration (> 30 minutes):**
- ❌ Link is expired
- ❌ Customer receives error: "This payment link has expired. Please request a new payment link from the business."
- ❌ Customer cannot view invoice or make payment

---

## API Response

### Invoice Creation Response

When business creates invoice, response includes expiration time:

```json
{
  "success": true,
  "message": "Invoice created successfully",
  "invoice": {
    "invoice_id": "FSCREDIT-ABC123",
    "slug": "inv-abc123xyz456",
    "payment_link": "https://fsscredit.foodstuff.store/checkout/inv-abc123xyz456",
    "payment_link_expires_at": "2024-01-15T11:00:00Z",
    "amount": "50000.00",
    "status": "pending"
  }
}
```

### Expired Link Error Response

When customer tries to access expired link:

```json
{
  "message": "This payment link has expired. Please request a new payment link from the business.",
  "invoice": {
    "invoice_id": "FSCREDIT-ABC123",
    "status": "pending"
  },
  "expired_at": "2024-01-15T11:00:00Z"
}
```

---

## Regenerating Expired Links

### For Businesses

If payment link expires, business can regenerate it:

**Endpoint:** `POST /api/business/invoices/{invoiceId}/generate-link`

**Response:**
```json
{
  "message": "Invoice link generated successfully",
  "invoice_link": "https://fsscredit.foodstuff.store/checkout/inv-newslug123",
  "payment_link_expires_at": "2024-01-15T12:00:00Z",
  "slug": "inv-newslug123"
}
```

**Note:** When regenerating:
- New slug is generated
- New expiration time set (30 minutes from regeneration)
- `is_used` flag is reset to `false`
- Old link becomes invalid

---

## Webhook Payload

Webhook includes expiration time:

```json
{
  "event": "invoice.created",
  "timestamp": "2024-01-15T10:30:00Z",
  "data": {
    "invoice_id": "FSCREDIT-ABC123",
    "payment_link": "https://fsscredit.foodstuff.store/checkout/inv-abc123xyz456",
    "payment_link_expires_at": "2024-01-15T11:00:00Z",
    ...
  }
}
```

---

## Implementation Details

### Database Schema

**Field:** `slug_expires_at` (timestamp, nullable)

**Migration:**
```php
$table->timestamp('slug_expires_at')->nullable()->after('slug');
```

### Model Method

**Invoice Model:**
```php
public function isSlugExpired(): bool
{
    if (!$this->slug_expires_at) {
        return false; // No expiration set, link is valid
    }
    return now()->isAfter($this->slug_expires_at);
}
```

### Automatic Expiration Setting

When invoice is created via `InvoiceService::createInvoiceForBusinessCustomer()`:
- `slug_expires_at` is automatically set to `now()->addMinutes(30)`

---

## Best Practices

### For Businesses

1. **Share Link Immediately:** Send payment link to customer as soon as invoice is created
2. **Monitor Expiration:** Track `payment_link_expires_at` in your system
3. **Regenerate if Needed:** If link expires before customer pays, regenerate via API
4. **Set Reminders:** Consider sending reminder emails before link expires

### For Customers

1. **Pay Promptly:** Payment links expire in 30 minutes
2. **Request New Link:** If link expires, contact business for new payment link
3. **Check Expiration:** Expiration time is included in invoice email

---

## Configuration

Expiration time is **hardcoded to 30 minutes** and cannot be configured via environment variables.

To change expiration time, modify:
- `app/Services/InvoiceService.php` - Line 113: `Carbon::now()->addMinutes(30)`
- `app/Http/Controllers/Api/BusinessController.php` - Line with `addMinutes(30)`

---

## Testing

### Test Expired Link

1. Create invoice
2. Wait 30+ minutes (or manually set `slug_expires_at` to past time)
3. Try to access payment link
4. Should receive expiration error

### Test Valid Link

1. Create invoice
2. Access payment link immediately
3. Should work normally

---

**Last Updated:** 2024
