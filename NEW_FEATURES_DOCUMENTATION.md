# New Features & API Updates Documentation

## Overview

This document outlines all new features, API endpoints, and changes made to the FSCredit API system.

---

## 1. Credit Repayment Tracking

### Overview
Added tracking for when credit used to pay invoices has been repaid by customers. Each invoice now has a separate status to track credit repayment.

### Database Changes
- Added `credit_repaid_status` field to `invoices` table (enum: `pending`, `partially_paid`, `fully_paid`)
- Added `credit_repaid_amount` field (decimal) to track amount of credit repaid
- Added `credit_repaid_at` timestamp field for when credit was fully repaid

### How It Works
1. When a customer pays an invoice via checkout (using credit), the invoice status becomes `paid` (business receives payment)
2. The `credit_repaid_status` is set to `pending` (customer owes the credit back)
3. When customer makes repayments, `credit_repaid_amount` is updated
4. Status changes to `partially_paid` when partial payment is made
5. Status changes to `fully_paid` when the full credit amount is repaid

### Invoice Response Fields
All invoice responses now include:
```json
{
  "credit_repaid_status": "pending", // or "partially_paid" or "fully_paid"
  "credit_repaid_amount": "0.00",
  "credit_repaid_at": null
}
```

---

## 2. Enhanced Transactions API Endpoints

### 2.1 Customer Transactions (Enhanced)
**Endpoint:** `GET /api/customer/transactions`

**Query Parameters:**
- `customer_id` (required): Customer ID
- `type` (optional): Filter by type (`transaction`, `payment`, `credit_adjustment`)
- `date_from` (optional): Filter from date (YYYY-MM-DD)
- `date_to` (optional): Filter to date (YYYY-MM-DD)
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 20)

**Response:**
Returns unified view of:
- Transactions (credit_purchase, repayment, etc.)
- Payments (customer repayments)
- Credit Limit Adjustments (admin credit additions/changes)

**Example Response:**
```json
{
  "success": true,
  "transactions": [
    {
      "id": 1,
      "reference": "TXN-ABC123",
      "type": "transaction",
      "transaction_type": "credit_purchase",
      "amount": "10000.00",
      "status": "completed",
      "description": "Purchase from Store",
      "business": {
        "id": 1,
        "business_name": "Store Name"
      },
      "invoice": {
        "id": 1,
        "invoice_id": "INV-123"
      },
      "metadata": {
        "items": [...]
      },
      "processed_at": "2024-01-15 10:00:00",
      "created_at": "2024-01-15 10:00:00"
    },
    {
      "id": 2,
      "reference": "PAY-XYZ789",
      "type": "payment",
      "transaction_type": "repayment",
      "amount": "5000.00",
      "status": "completed",
      "description": "Payment: PAY-XYZ789",
      "invoice": {
        "id": 1,
        "invoice_id": "INV-123"
      },
      "processed_at": "2024-01-20 14:30:00",
      "created_at": "2024-01-20 14:30:00"
    },
    {
      "id": 3,
      "reference": "CLA-00000001",
      "type": "credit_adjustment",
      "transaction_type": "credit_limit_adjustment",
      "amount": "50000.00",
      "adjustment_amount": "50000.00",
      "status": "completed",
      "description": "Credit added to wallet: 50000",
      "metadata": {
        "previous_credit_limit": "100000.00",
        "new_credit_limit": "150000.00"
      },
      "processed_at": "2024-01-10 09:00:00",
      "created_at": "2024-01-10 09:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 3,
    "last_page": 1,
    "from": 1,
    "to": 3
  }
}
```

### 2.2 Business Transactions (Enhanced)
**Endpoint:** `GET /api/business/transactions`

**Authentication:** Bearer token required (from business login)

**Query Parameters:**
- `type` (optional): Filter by type (`transaction`, `withdrawal`, `payout`)
- `date_from` (optional): Filter from date (YYYY-MM-DD)
- `date_to` (optional): Filter to date (YYYY-MM-DD)
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 20)

**Response:**
Returns unified view of:
- Transactions (credit_purchase, etc.)
- Withdrawals (business withdrawal requests)
- Payouts (business payouts from invoices)

**Example Response:**
```json
{
  "success": true,
  "transactions": [
    {
      "id": 1,
      "reference": "TXN-ABC123",
      "type": "transaction",
      "transaction_type": "credit_purchase",
      "amount": "10000.00",
      "status": "completed",
      "description": "Purchase",
      "customer": {
        "id": 1,
        "business_name": "Customer Name",
        "account_number": "1234567890123456"
      },
      "invoice": {
        "id": 1,
        "invoice_id": "INV-123"
      },
      "created_at": "2024-01-15 10:00:00"
    },
    {
      "id": 2,
      "reference": "WDR-XYZ789",
      "type": "withdrawal",
      "transaction_type": "withdrawal",
      "amount": "5000.00",
      "status": "approved",
      "description": "Withdrawal: WDR-XYZ789",
      "metadata": {
        "bank_name": "Bank Name",
        "account_number": "1234567890",
        "account_name": "Account Name"
      },
      "created_at": "2024-01-20 14:30:00"
    },
    {
      "id": 3,
      "reference": "POUT-123456",
      "type": "payout",
      "transaction_type": "payout",
      "amount": "10000.00",
      "status": "completed",
      "description": "Payout: POUT-123456",
      "customer": {
        "id": 1,
        "business_name": "Customer Name",
        "account_number": "1234567890123456"
      },
      "invoice": {
        "id": 1,
        "invoice_id": "INV-123"
      },
      "created_at": "2024-01-18 12:00:00"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 3,
    "last_page": 1,
    "from": 1,
    "to": 3
  }
}
```

### 2.3 Admin Unified Transactions
**Endpoint:** `GET /api/admin/transactions/all`

**Authentication:** Admin authentication required

**Query Parameters:**
- `type` (optional): Filter by type (`transaction`, `payment`, `withdrawal`, `credit_adjustment`, `payout`)
- `customer_id` (optional): Filter by customer ID
- `business_id` (optional): Filter by business ID
- `date_from` (optional): Filter from date (YYYY-MM-DD)
- `date_to` (optional): Filter to date (YYYY-MM-DD)
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 50)

**Response:**
Returns unified view of ALL transaction types across the entire platform:
- Transactions (credit_purchase, repayment, payout, refund)
- Payments (customer repayments)
- Withdrawals (business withdrawals)
- Credit Limit Adjustments (admin credit additions/changes)
- Payouts (business payouts from invoices)

**Example Response:** Same structure as customer/business transactions but includes all types.

---

## 3. Admin: Add Credits to Customer Wallet

### Endpoint
**POST** `/api/admin/customers/{id}/add-credits`

**Authentication:** Admin authentication required

### Request Body
```json
{
  "amount": 50000.00,
  "reason": "Bonus credit for loyalty program"
}
```

**Required Fields:**
- `amount` (decimal, min: 0.01): Amount to add to customer's credit limit

**Optional Fields:**
- `reason` (string): Reason for adding credits

### Response (200 OK)
```json
{
  "success": true,
  "message": "Credits added to customer wallet successfully",
  "customer": {
    "id": 1,
    "business_name": "Customer Business Name",
    "account_number": "1234567890123456",
    "previous_credit_limit": "100000.00",
    "new_credit_limit": "150000.00",
    "amount_added": "50000.00",
    "available_balance": "150000.00"
  }
}
```

### Notes
- This endpoint increases the customer's credit limit by the specified amount
- The adjustment is tracked in the `credit_limit_adjustments` table
- The adjustment appears in customer's transaction history
- Customer balances are automatically updated

---

## 4. Credit Limit Adjustment Tracking

### Overview
All credit limit changes by admins are now tracked in a separate table for audit and transaction history purposes.

### Database Changes
- Created `credit_limit_adjustments` table
- Tracks: previous limit, new limit, adjustment amount, reason, admin user

### Automatic Tracking
Credit limit adjustments are automatically tracked when:
- Admin updates credit limit directly
- Admin approves customer and sets credit limit
- Admin adds credits to customer wallet
- Credit limit is recalculated due to minimum purchase/payment plan changes

### Model: CreditLimitAdjustment
```php
- customer_id
- previous_credit_limit
- new_credit_limit
- adjustment_amount (can be positive or negative)
- reason
- admin_user_id
- created_at
- updated_at
```

---

## 5. Interest Calculation & Payment Tracking

### Interest Calculation
- **Interest Rate:** 3.5% monthly
- **When Applied:** Interest starts immediately after the due date (not after grace period)
- **Grace Period:** 30 days after due date (for additional penalties, not to avoid initial interest)

### Payment Flow with Interest
1. When customer pays invoice via checkout:
   - Interest is calculated BEFORE payment (if invoice is overdue)
   - Interest amount is included in the total payment amount
   - Invoice status is updated to reflect interest
   
2. Payment tracking:
   - Transaction record created with type `repayment`
   - Payment record created with payment reference
   - Invoice `paid_amount` and `remaining_balance` updated
   - Customer balances updated

### Invoice Fields Related to Interest
```json
{
  "principal_amount": "10000.00",
  "interest_amount": "350.00",  // 3.5% monthly if overdue
  "total_amount": "10350.00",
  "paid_amount": "10350.00",
  "remaining_balance": "0.00"
}
```

---

## 6. Payment Tracking

### When Payments Are Tracked
1. **Invoice Checkout Payment:**
   - Transaction record created
   - Payment record created
   - Invoice linked to customer
   - Credit repayment status initialized

2. **Credit Repayment:**
   - Transaction record created
   - Payment record created
   - Credit repayment amount updated
   - Credit repayment status updated

### Transaction Types
- `credit_purchase`: Initial purchase using credit
- `repayment`: Customer repaying credit used
- `payout`: Business receiving payment from invoice
- `refund`: Refund transaction

### Payment Records
All payments create:
- **Payment record** in `payments` table
- **Transaction record** in `transactions` table
- **Balance updates** on customer/business accounts

---

## 7. Updated Invoice Response Structure

All invoice responses now include:
```json
{
  "id": 1,
  "invoice_id": "INV-123",
  "principal_amount": "10000.00",
  "interest_amount": "350.00",
  "total_amount": "10350.00",
  "paid_amount": "10000.00",
  "remaining_balance": "350.00",
  "status": "in_grace",
  "credit_repaid_status": "pending",  // NEW
  "credit_repaid_amount": "0.00",     // NEW
  "credit_repaid_at": null,           // NEW
  "description": "Invoice description",
  "items": [
    {
      "name": "Product Name",
      "quantity": 2,
      "price": "5000.00",
      "description": "Product description"
    }
  ],
  "due_date": "2024-02-15",
  "created_at": "2024-01-15 10:00:00"
}
```

---

## Summary of API Endpoints

### New Endpoints
1. `GET /api/admin/transactions/all` - Unified transactions view
2. `POST /api/admin/customers/{id}/add-credits` - Add credits to customer wallet

### Enhanced Endpoints
1. `GET /api/customer/transactions` - Now includes all transaction types
2. `GET /api/business/transactions` - Now includes all transaction types

### Updated Response Fields
- All invoice responses include `credit_repaid_status`, `credit_repaid_amount`, `credit_repaid_at`
- All transaction endpoints return unified format with consistent structure

---

## Migration Requirements

### Required Migrations
1. `2025_12_05_120005_add_credit_repaid_status_to_invoices_table.php`
2. `2025_12_05_120006_create_credit_limit_adjustments_table.php`

### To Apply Migrations
```bash
php artisan migrate
```

---

## Testing Checklist

- [ ] Customer can view all their transactions (transactions, payments, credit adjustments)
- [ ] Business can view all their transactions (transactions, withdrawals, payouts)
- [ ] Admin can view unified transactions across all types
- [ ] Admin can add credits to customer wallet
- [ ] Credit repayment status is tracked correctly
- [ ] Interest is calculated and included in payments
- [ ] Payment tracking works for invoice payments
- [ ] Credit limit adjustments are tracked

---

## Notes

1. **Interest Calculation:** Interest is applied immediately after due date, not after grace period
2. **Credit Repayment:** Separate from invoice payment status - tracks if customer has repaid the credit used
3. **Transaction History:** All financial activities are now tracked and visible in transaction endpoints
4. **Credit Additions:** Admin can add credits which increases credit limit and is tracked in history

