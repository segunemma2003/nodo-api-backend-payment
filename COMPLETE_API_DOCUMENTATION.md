# Complete API Documentation - All New Features & Updates

## Table of Contents
1. [Overview](#overview)
2. [Credit Repayment Tracking](#credit-repayment-tracking)
3. [Enhanced Transaction APIs](#enhanced-transaction-apis)
4. [Admin Credit Management](#admin-credit-management)
5. [Interest Calculation](#interest-calculation)
6. [Payment Tracking](#payment-tracking)
7. [API Endpoints Reference](#api-endpoints-reference)
8. [Response Examples](#response-examples)

---

## Overview

This document covers all new features and updates to the Nodopay API, including:
- Credit repayment status tracking on invoices
- Enhanced transaction endpoints (customer, business, admin)
- Admin ability to add credits to customer wallets
- Complete payment and interest tracking
- Unified transaction views across all user types

---

## Credit Repayment Tracking

### Overview
Added comprehensive tracking for when credit used to pay invoices has been repaid by customers. Each invoice now has a separate status to track credit repayment independently from invoice payment status.

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

## Enhanced Transaction APIs

### 1. Customer Transactions (Enhanced)
**Endpoint:** `GET /api/customer/transactions`

**Authentication:** Customer authentication via `customer_id` parameter

**Query Parameters:**
- `customer_id` (required): Customer ID
- `type` (optional): Filter by type (`transaction`, `payment`, `credit_adjustment`)
- `date_from` (optional): Filter from date (YYYY-MM-DD)
- `date_to` (optional): Filter to date (YYYY-MM-DD)
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 20)

**Returns:**
- **Transactions:** Credit purchases, repayments, etc.
- **Payments:** Customer repayment records
- **Credit Adjustments:** Admin credit limit changes/additions

**Example Request:**
```bash
curl -X GET "https://nodopay-api-0fbd4546e629.herokuapp.com/api/customer/transactions?customer_id=1&type=transaction&date_from=2024-01-01" \
  -H "Content-Type: application/json"
```

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

---

### 2. Business Transactions (Enhanced)
**Endpoint:** `GET /api/business/transactions`

**Authentication:** Bearer token required (from business login)

**Query Parameters:**
- `type` (optional): Filter by type (`transaction`, `withdrawal`, `payout`)
- `date_from` (optional): Filter from date (YYYY-MM-DD)
- `date_to` (optional): Filter to date (YYYY-MM-DD)
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 20)

**Returns:**
- **Transactions:** Credit purchases, etc.
- **Withdrawals:** Business withdrawal requests
- **Payouts:** Business payouts from invoices

**Example Request:**
```bash
curl -X GET "https://nodopay-api-0fbd4546e629.herokuapp.com/api/business/transactions?type=transaction" \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Content-Type: application/json"
```

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

---

### 3. Admin Unified Transactions
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

**Returns:** Unified view of ALL transaction types across the entire platform:
- Transactions (credit_purchase, repayment, payout, refund)
- Payments (customer repayments)
- Withdrawals (business withdrawals)
- Credit Limit Adjustments (admin credit additions/changes)
- Payouts (business payouts from invoices)

**Example Request:**
```bash
curl -X GET "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/transactions/all?customer_id=1&type=transaction" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json"
```

**Example Response:** Same structure as customer/business transactions but includes all types.

---

## Admin Credit Management

### 1. Add Credits to Customer Wallet
**Endpoint:** `POST /api/admin/customers/{id}/add-credits`

**Authentication:** Admin authentication required

**Request Body:**
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

**Example Request:**
```bash
curl -X POST "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers/1/add-credits" \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 50000.00,
    "reason": "Bonus credit for loyalty program"
  }'
```

**Response (200 OK):**
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

**Notes:**
- This endpoint increases the customer's credit limit by the specified amount
- The adjustment is tracked in the `credit_limit_adjustments` table
- The adjustment appears in customer's transaction history
- Customer balances are automatically updated

---

### 2. Update Credit Limit
**Endpoint:** `PATCH /api/admin/customers/{id}/credit-limit`

**Authentication:** Admin authentication required

**Request Body:**
```json
{
  "credit_limit": 500000,
  "reason": "Credit limit adjustment"
}
```

**Response (200 OK):**
```json
{
  "message": "Credit limit updated successfully",
  "customer": {
    "id": 1,
    "credit_limit": "500000.00",
    "available_balance": "450000.00"
  }
}
```

---

## Interest Calculation

### Overview
- **Interest Rate:** 3.5% monthly
- **When Applied:** Interest starts immediately after the due date (not after grace period)
- **Grace Period:** 30 days after due date (for additional penalties, not to avoid initial interest)

### Payment Flow with Interest
1. When customer pays invoice via checkout:
   - Interest is calculated **BEFORE** payment (if invoice is overdue)
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

## Payment Tracking

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

## Updated Invoice Response Structure

### Customer Invoice API
**Endpoints:**
- `GET /api/customer/invoices` - Get all invoices
- `GET /api/customer/invoices/{invoiceId}` - Get single invoice

**All invoice responses now include:**
```json
{
  "invoice_id": "INV-123",
  "purchase_date": "2024-01-15",
  "due_date": "2024-02-15",
  "grace_period_end_date": "2024-03-16",
  "status": "in_grace",
  "principal_amount": "10000.00",
  "interest_amount": "350.00",
  "total_amount": "10350.00",
  "paid_amount": "10000.00",
  "remaining_balance": "350.00",
  "supplier_name": "Store Name",
  "months_overdue": 1,
  "description": "Invoice description",
  "items": [
    {
      "name": "Product Name",
      "quantity": 2,
      "price": "5000.00",
      "description": "Product description"
    }
  ],
  "credit_repaid_status": "pending",      // NEW FIELD
  "credit_repaid_amount": "0.00",         // NEW FIELD
  "credit_repaid_at": null                // NEW FIELD
}
```

### Credit Repayment Status Values
- `pending`: Credit has not been repaid yet
- `partially_paid`: Some of the credit has been repaid
- `fully_paid`: All credit has been repaid

---

## API Endpoints Reference

### Customer Endpoints

#### Get All Transactions (Enhanced)
```
GET /api/customer/transactions
```
**Query Parameters:**
- `customer_id` (required)
- `type` (optional): transaction, payment, credit_adjustment
- `date_from` (optional)
- `date_to` (optional)
- `page` (optional)
- `per_page` (optional)

#### Get All Invoices (Updated)
```
GET /api/customer/invoices
```
**Now includes:** `credit_repaid_status`, `credit_repaid_amount`, `credit_repaid_at`

#### Get Invoice Details (Updated)
```
GET /api/customer/invoices/{invoiceId}
```
**Now includes:** `credit_repaid_status`, `credit_repaid_amount`, `credit_repaid_at`

---

### Business Endpoints

#### Get All Transactions (Enhanced)
```
GET /api/business/transactions
```
**Authentication:** Bearer token required

**Query Parameters:**
- `type` (optional): transaction, withdrawal, payout
- `date_from` (optional)
- `date_to` (optional)
- `page` (optional)
- `per_page` (optional)

---

### Admin Endpoints

#### Get All Transactions (Unified)
```
GET /api/admin/transactions/all
```
**Authentication:** Admin authentication required

**Query Parameters:**
- `type` (optional): transaction, payment, withdrawal, credit_adjustment, payout
- `customer_id` (optional)
- `business_id` (optional)
- `date_from` (optional)
- `date_to` (optional)
- `page` (optional)
- `per_page` (optional)

#### Add Credits to Customer Wallet
```
POST /api/admin/customers/{id}/add-credits
```
**Authentication:** Admin authentication required

**Request Body:**
```json
{
  "amount": 50000.00,
  "reason": "Optional reason"
}
```

#### Update Credit Limit
```
PATCH /api/admin/customers/{id}/credit-limit
```
**Authentication:** Admin authentication required

**Request Body:**
```json
{
  "credit_limit": 500000,
  "reason": "Optional reason"
}
```

---

## Response Examples

### Complete Invoice Response with Credit Repayment
```json
{
  "invoice": {
    "invoice_id": "INV-123",
    "purchase_date": "2024-01-15",
    "due_date": "2024-02-15",
    "grace_period_end_date": "2024-03-16",
    "status": "paid",
    "principal_amount": "10000.00",
    "interest_amount": "350.00",
    "total_amount": "10350.00",
    "paid_amount": "10350.00",
    "remaining_balance": "0.00",
    "supplier_name": "Store Name",
    "months_overdue": 1,
    "description": "Invoice description",
    "items": [
      {
        "name": "Product Name",
        "quantity": 2,
        "price": "5000.00",
        "description": "Product description"
      }
    ],
    "credit_repaid_status": "partially_paid",
    "credit_repaid_amount": "5000.00",
    "credit_repaid_at": null
  }
}
```

### Transaction Response Example
```json
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
    "items": [
      {
        "name": "Product",
        "quantity": 1,
        "price": "10000.00"
      }
    ]
  },
  "processed_at": "2024-01-15 10:00:00",
  "created_at": "2024-01-15 10:00:00"
}
```

---

## Migration Requirements

### Required Migrations
1. `2025_12_05_120005_add_credit_repaid_status_to_invoices_table.php`
   - Adds `credit_repaid_status`, `credit_repaid_amount`, `credit_repaid_at` to invoices table

2. `2025_12_05_120006_create_credit_limit_adjustments_table.php`
   - Creates `credit_limit_adjustments` table for tracking credit limit changes

### To Apply Migrations
```bash
php artisan migrate
```

---

## Summary

### New Features
1. ✅ Credit repayment tracking on all invoices
2. ✅ Enhanced customer transactions endpoint (all types)
3. ✅ Enhanced business transactions endpoint (all types)
4. ✅ Admin unified transactions endpoint (all types)
5. ✅ Admin ability to add credits to customer wallets
6. ✅ Automatic credit limit adjustment tracking
7. ✅ Interest calculation included in payments
8. ✅ Complete payment tracking

### Updated Endpoints
- `GET /api/customer/transactions` - Now includes all transaction types
- `GET /api/business/transactions` - Now includes all transaction types
- `GET /api/customer/invoices` - Now includes credit repayment status
- `GET /api/customer/invoices/{id}` - Now includes credit repayment status

### New Endpoints
- `GET /api/admin/transactions/all` - Unified transactions view
- `POST /api/admin/customers/{id}/add-credits` - Add credits to wallet

---

## Notes

1. **Interest Calculation:** Interest is applied immediately after due date, not after grace period
2. **Credit Repayment:** Separate from invoice payment status - tracks if customer has repaid the credit used
3. **Transaction History:** All financial activities are now tracked and visible in transaction endpoints
4. **Credit Additions:** Admin can add credits which increases credit limit and is tracked in history
5. **Cache:** Invoice cache is automatically cleared to ensure latest credit repayment status is shown

---

**Last Updated:** December 2024
**Version:** 2.0

