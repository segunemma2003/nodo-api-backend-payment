# NodoPay User Stories

## Overview
NodoPay is a credit financing platform that connects businesses, customers, and administrators. The system supports two types of customers:
1. **Main Customers** - Registered users with login credentials (require admin approval)
2. **Business Customers** - Customer records created by businesses (no login, just for invoice management)

---

## User Story 1: Main Customer Registration & Approval

### As a Customer
I want to register for a NodoPay account so that I can access credit financing services.

**Acceptance Criteria:**
- Customer can register with business_name, email, username, password
- Registration creates account with `approval_status = 'pending'` and `status = 'inactive'`
- Customer receives confirmation that account is pending approval
- Account number and CVV are auto-generated
- Customer **cannot login** until admin approves account

**API Endpoint:**
- `POST /api/auth/customer/register`

**Admin Action Required:**
- Admin reviews registration and KYC documents
- Admin approves/rejects using `PATCH /api/admin/customers/{id}/approval`
- On approval, admin sets credit limit and account becomes active
- Customer can now login

---

## User Story 2: Business Creates Customer Record

### As a Business
I want to create customer records in my system so that I can generate invoices for them.

**Acceptance Criteria:**
- Business can create customer records with business_name, contact details, verification info
- Each business has their own separate customer list
- Business customers don't have login credentials
- Business can view, update, and manage their customers
- Business customer record includes: business_name, address, contact_name, contact_phone, minimum_purchase_amount, payment_plan_duration, registration_number, tax_id, etc.

**API Endpoints:**
- `GET /api/business/customers` - List all business customers
- `POST /api/business/customers` - Create new business customer
- `GET /api/business/customers/{id}` - Get specific customer
- `PUT /api/business/customers/{id}` - Update customer
- `DELETE /api/business/customers/{id}` - Delete customer

**Business Customer vs Main Customer:**
- Business customers are just records for invoice generation
- They don't have account_number, CVV, PIN, or login credentials
- They can be linked to main customers when invoices are paid

---

## User Story 3: Business Creates Invoice for Their Customer

### As a Business
I want to create an invoice for one of my business customers so that they can pay later.

**Acceptance Criteria:**
- Business selects a business customer (from their customer list)
- Business creates invoice with amount, purchase_date, due_date, items
- Invoice is created with `business_customer_id` (required) and `customer_id = null`
- Invoice has status `'pending'` and does NOT affect customer balance yet
- Invoice automatically generates a payment link (slug)
- Business receives payment link to share with customer

**API Endpoint:**
- `POST /api/business/submit-invoice`
  - Requires: `business_customer_id` (not customer_account_number)

**Key Changes:**
- Changed from using `customer_account_number` to `business_customer_id`
- Invoice is created without linking to main customer initially
- Payment link is auto-generated

---

## User Story 4: Customer Pays Business-Generated Invoice

### As a Customer
I want to pay an invoice sent by a business so that I can complete my purchase.

**Acceptance Criteria:**
- Customer receives payment link from business
- Customer accesses invoice details via payment link
- If customer doesn't have main account, they must register first
- Customer provides account_number, CVV, and PIN to pay
- Payment links business customer to main customer account
- Invoice `customer_id` is set during payment
- Invoice appears in customer's invoice list after payment
- Customer balance is deducted when payment is made (not when invoice is created)
- Business receives payout when payment is completed

**Payment Flow:**
1. Customer clicks payment link: `/api/invoice/checkout/{slug}`
2. System checks if invoice exists and is not already used
3. Customer provides account_number, CVV, PIN
4. System validates credentials
5. **If invoice has `business_customer_id`:**
   - Links business customer to main customer account
   - Sets invoice `customer_id` to main customer ID
6. Processes payment and updates balances
7. Invoice now appears in customer's invoice list
8. Business receives payout notification

**API Endpoints:**
- `GET /api/invoice/checkout/{slug}` - Get invoice details
- `POST /api/invoice/checkout/{slug}/pay` - Process payment

**Important Notes:**
- Invoice must be paid with main customer account (account_number, CVV, PIN)
- Business customer is automatically linked to main customer during payment
- Invoice appears in main customer's invoice list after payment
- Balance deduction happens during payment, not invoice creation

---

## User Story 5: Customer Views Their Invoices

### As a Customer
I want to view all my invoices including those from businesses so that I can track my purchases.

**Acceptance Criteria:**
- Customer can see all invoices where `customer_id` matches their account
- Includes invoices created directly by admin
- **Includes invoices created by businesses that were paid with this account**
- Invoices show supplier_name (business name)
- Customer can filter by status (pending, in_grace, overdue, paid)
- Customer can view invoice details including items

**API Endpoint:**
- `GET /api/customer/invoices?customer_id={id}`

**Invoice Sources:**
1. **Direct Invoices:** Created by admin, immediately linked to customer
2. **Business Invoices:** Created by business, linked when customer pays
   - Invoice starts with `business_customer_id` and `customer_id = null`
   - After payment, `customer_id` is set
   - Invoice appears in customer's list

---

## User Story 6: Admin Manages Customer Approvals

### As an Admin
I want to approve or reject customer registrations so that only verified customers can access the platform.

**Acceptance Criteria:**
- Admin can view all pending customer registrations
- Admin can approve or reject customers
- On approval, admin can set credit limit
- Approved customers can login
- Rejected customers cannot login

**API Endpoint:**
- `PATCH /api/admin/customers/{id}/approval`
  - Body: `{ "approval_status": "approved", "credit_limit": 350000 }`

**Approval Status Flow:**
- `pending` → Registration submitted, cannot login
- `approved` → Can login (if status is also 'active')
- `rejected` → Cannot login

---

## User Story 7: Admin Views Business Customers

### As an Admin
I want to view all business customers across all businesses for oversight and management.

**Acceptance Criteria:**
- Admin can view all business customers from all businesses
- Can filter by business, status, linked status
- Can see which business customers are linked to main customers
- Can view business customer details and their invoices

**API Endpoints:**
- `GET /api/admin/business-customers` - List all business customers
- `GET /api/admin/business-customers/{id}` - Get specific business customer

**Admin View:**
- Separate from main customers (`/api/admin/customers`)
- Shows business customers created by businesses
- Includes link status (linked to main customer or not)

---

## Key System Behaviors

### Invoice Creation Flow
1. **Business creates invoice:**
   - Uses `business_customer_id`
   - `customer_id = null`
   - Status: `pending`
   - Balance: NOT affected

2. **Customer pays invoice:**
   - Provides main customer account credentials
   - System links business customer to main customer
   - Sets invoice `customer_id`
   - Updates balances
   - Invoice appears in customer's list

### Balance Deduction Timing
- **Invoice Creation:** Balance is NOT deducted
- **Payment:** Balance IS deducted when payment is made
- Only invoices with status `'in_grace'`, `'overdue'`, etc. (not `'pending'`) affect balance

### Customer Types
- **Main Customers:**
  - Have login credentials (email, username, password)
  - Have account_number, CVV, PIN
  - Require admin approval
  - Can login and access dashboard
  - Can pay invoices

- **Business Customers:**
  - No login credentials
  - No account_number, CVV, PIN
  - Created by businesses for invoice management
  - Can be linked to main customers when invoices are paid
  - Visible to admin for oversight

---

## API Summary

### Customer Registration & Authentication
- `POST /api/auth/customer/register` - Register new customer (requires approval)
- `POST /api/auth/customer/login` - Login (only if approved)

### Business Customer Management (Business)
- `GET /api/business/customers` - List business customers
- `POST /api/business/customers` - Create business customer
- `GET /api/business/customers/{id}` - Get business customer
- `PUT /api/business/customers/{id}` - Update business customer
- `DELETE /api/business/customers/{id}` - Delete business customer

### Invoice Creation (Business)
- `POST /api/business/submit-invoice` - Create invoice (uses `business_customer_id`)

### Invoice Payment
- `GET /api/invoice/checkout/{slug}` - Get invoice details
- `POST /api/invoice/checkout/{slug}/pay` - Pay invoice (links to main customer)

### Customer Dashboard
- `GET /api/customer/invoices` - View all invoices (including business invoices)

### Admin Customer Management
- `GET /api/admin/customers` - List main customers
- `PATCH /api/admin/customers/{id}/approval` - Approve/reject customer
- `GET /api/admin/business-customers` - List business customers

---

## Data Flow Diagram

```
Business Creates Customer Record
         ↓
Business Creates Invoice (business_customer_id, customer_id=null)
         ↓
Customer Receives Payment Link
         ↓
Customer Registers (if needed) → Admin Approves
         ↓
Customer Pays Invoice (account_number, CVV, PIN)
         ↓
System Links Business Customer → Main Customer
         ↓
Invoice customer_id is set
         ↓
Invoice Appears in Customer's Invoice List
         ↓
Balance is Deducted
         ↓
Business Receives Payout
```

