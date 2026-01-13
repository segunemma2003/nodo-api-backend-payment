# Email Notifications Implementation

## Overview

This document describes the email notification system implemented to notify customers and the accounting team (`accounting@foodstuff.store` and `accountings@foodstuff.store`) about various events in the system.

## Email Recipients

### Accounting Team Emails
- `accounting@foodstuff.store`
- `accountings@foodstuff.store`

Both emails receive notifications for:
- Customer registrations
- Business registrations
- Business customer creation
- Invoice creation

## Notification Events

### 1. Business Customer Creation

**Trigger:** When a business creates a new business customer via `POST /api/business/customers`

**Recipients:**
- Business customer's email (`contact_email`) - if provided
- `accounting@foodstuff.store`
- `accountings@foodstuff.store`

**Notification Class:** `BusinessCustomerCreatedNotification`

**Location:** `app/Http/Controllers/Api/BusinessController::createCustomer()`

**Email Content:**
- Business customer details (name, contact info, address)
- Business that created the customer
- Notification that customer can receive invoices

---

### 2. Customer Registration

**Trigger:** When a customer registers via `POST /api/auth/customer/register`

**Recipients:**
- `accounting@foodstuff.store`
- `accountings@foodstuff.store`

**Notification Class:** `CustomerRegistrationNotification`

**Location:** `app/Http/Controllers/Api/AuthController::customerRegister()`

**Email Content:**
- Customer business name
- Account number
- Email, username, phone, address
- Minimum purchase amount
- Payment plan duration
- Approval status
- Link to view customer in admin panel

**Note:** Customer also receives a welcome email via `CustomerCreatedNotification` (when created by admin).

---

### 3. Business Registration

**Trigger:** When a business is created via `POST /api/admin/businesses`

**Recipients:**
- Business email (via `BusinessCreatedNotification`)
- `accounting@foodstuff.store`
- `accountings@foodstuff.store`

**Notification Classes:**
- `BusinessCreatedNotification` - sent to business
- `BusinessRegistrationNotification` - sent to accounting

**Location:** `app/Http/Controllers/Api/AdminController::createBusiness()`

**Email Content (to accounting):**
- Business name, email, username
- Phone, address, webhook URL
- Approval status and status
- Link to view business in admin panel

---

### 4. Invoice Creation

**Trigger:** When a business submits an invoice via `POST /api/business/submit-invoice`

**Recipients:**
- Customer email (if available - from main customer or business customer)
- `accounting@foodstuff.store`
- `accountings@foodstuff.store`

**Notification Class:** `InvoiceCreatedNotification`

**Location:** `app/Http/Controllers/Api/BusinessController::submitInvoice()`

**Email Content:**
- Customer details (name, account number, email)
- Invoice ID, amount, total amount
- Purchase date, due date
- Payment plan duration
- Invoice status
- Supplier information
- Payment link (if available)

---

## Notification Classes

All notification classes are located in `app/Notifications/`:

1. **BusinessCustomerCreatedNotification** - Business customer creation
2. **CustomerRegistrationNotification** - Customer registration/approval request
3. **BusinessRegistrationNotification** - Business registration/approval request
4. **InvoiceCreatedNotification** - Invoice creation

All notifications implement `ShouldQueue` for asynchronous processing.

## Helper Service

**AccountingNotificationService** (`app/Services/AccountingNotificationService.php`)

A helper service for sending emails to the accounting team. Currently not used directly, but available for future use.

## Error Handling

All email notifications are wrapped in try-catch blocks to prevent failures from affecting the main business logic. Errors are logged but do not interrupt the request processing.

**Example:**
```php
try {
    Notification::route('mail', 'accounting@foodstuff.store')
        ->notify(new CustomerRegistrationNotification($customer));
} catch (\Exception $e) {
    Log::warning('Failed to send email: ' . $e->getMessage(), [
        'customer_id' => $customer->id,
    ]);
}
```

## Email Configuration

Ensure your Laravel email configuration is properly set up in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@foodstuff.store
MAIL_FROM_NAME="${APP_NAME}"
```

## Testing

To test email notifications:

1. **Business Customer Creation:**
   ```bash
   POST /api/business/customers
   ```

2. **Customer Registration:**
   ```bash
   POST /api/auth/customer/register
   ```

3. **Business Registration:**
   ```bash
   POST /api/admin/businesses
   ```

4. **Invoice Creation:**
   ```bash
   POST /api/business/submit-invoice
   ```

## Queue Configuration

Since all notifications implement `ShouldQueue`, ensure your queue worker is running:

```bash
php artisan queue:work
```

Or use a queue driver like Redis, SQS, or database.

## Future Enhancements

Potential improvements:
- Add email templates for better formatting
- Add email preferences for customers
- Add bcc option for accounting emails
- Add email delivery tracking
- Add retry mechanism for failed emails
