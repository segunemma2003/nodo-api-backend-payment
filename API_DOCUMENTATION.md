# NODOPAY API Documentation

## Table of Contents
1. [Overview](#overview)
2. [User Stories](#user-stories)
3. [Authentication](#authentication)
4. [Base URL](#base-url)
5. [API Endpoints](#api-endpoints)
   - [Authentication Endpoints](#authentication-endpoints)
   - [Customer Dashboard Endpoints](#customer-dashboard-endpoints)
   - [Business Dashboard Endpoints](#business-dashboard-endpoints)
   - [Admin Panel Endpoints](#admin-panel-endpoints)
   - [Payment Processing Endpoints](#payment-processing-endpoints)
6. [Data Models](#data-models)
7. [Error Handling](#error-handling)
8. [Rate Limiting](#rate-limiting)

---

## Overview

Nodopay is a financing platform that provides credit lines and invoice financing for businesses. This API enables:

- Customer credit management with 16-digit account numbers
- Business payment gateway integration
- Invoice financing with automatic payouts
- Repayment processing through virtual accounts
- Interest calculation (3.5% monthly after 30-day grace period)
- Complete admin panel for platform management

---

## User Stories

### Customer User Stories

**As a customer, I want to:**
- View my credit limit, current balance, and available credit
- See all my financed invoices with details
- Know when invoices are due and if they're overdue
- See how much interest I'm being charged
- View my repayment bank account details
- Track my payment history
- Receive email notifications for payments and reminders

**API Endpoints for Customers:**
- `GET /api/customer/credit-overview` - View credit information
- `GET /api/customer/invoices` - View all invoices
- `GET /api/customer/invoices/{invoiceId}` - View invoice details
- `GET /api/customer/transactions` - View all transactions
- `GET /api/customer/repayment-account` - View repayment account
- `GET /api/customer/profile` - View profile

### Business User Stories

**As a business owner, I want to:**
- Integrate Nodopay payment gateway into my platform
- Check if customers have available credit before processing orders
- Submit invoices for financing
- Receive automatic payments when customers use Nodopay
- View all invoices and transactions
- Get webhook notifications for payments
- Track my revenue from Nodopay transactions

**API Endpoints for Businesses:**
- `GET /api/business/dashboard` - View business dashboard
- `GET /api/business/invoices` - View all invoices
- `POST /api/business/submit-invoice` - Submit invoice for financing
- `POST /api/business/check-customer-credit` - Check customer credit
- `GET /api/business/transactions` - View transactions
- `POST /api/business/withdrawals/request` - Request withdrawal
- `GET /api/business/withdrawals` - View all withdrawals
- `GET /api/business/profile` - View profile

### Admin User Stories

**As an admin, I want to:**
- Create and manage customers
- Create and approve businesses
- View all financial activity
- Monitor overdue invoices and interest
- Adjust credit limits
- Suspend or activate accounts
- View platform statistics and reports
- Manually mark invoices as paid if needed

**API Endpoints for Admins:**
- `POST /api/admin/customers` - Create customer
- `GET /api/admin/customers` - List all customers
- `PATCH /api/admin/customers/{id}/credit-limit` - Adjust credit limit
- `POST /api/admin/businesses` - Create business
- `PATCH /api/admin/businesses/{id}/approve` - Approve business
- `GET /api/admin/dashboard/stats` - View statistics
- `GET /api/admin/withdrawals` - View all withdrawals
- `PATCH /api/admin/withdrawals/{id}/approve` - Approve/reject withdrawal
- `PATCH /api/admin/withdrawals/{id}/process` - Process withdrawal

---

## Authentication

### Customer Authentication
Customers authenticate using email/username and password.

**Endpoint**: `POST /api/auth/customer/login`

### Business Authentication
Businesses authenticate using email/username and password.

**Endpoint**: `POST /api/auth/business/login`

### Admin Authentication
Admin users authenticate using email and password.

**Endpoint**: `POST /api/auth/admin/login`

### API Token Authentication (External Integration)
For external integrations, include API token in header:
```
X-API-Token: your_business_api_token_here
```

---

## Base URL

```
https://your-domain.com/api
```

All endpoints are prefixed with `/api`.

---

## API Endpoints

### Authentication Endpoints

#### Customer Login
**POST** `/api/auth/customer/login`

**Request Body:**
```json
{
  "email": "customer@example.com",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "credit_limit": 100000.00,
    "available_balance": 75000.00
  },
  "token": "session_token_here"
}
```

#### Business Login
**POST** `/api/auth/business/login`

**Request Body:**
```json
{
  "email": "business@example.com",
  "password": "password123"
}
```

**Response (200 OK):**
```json
{
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "business@example.com",
    "api_token": "nodo_biz_abc123..."
  },
  "token": "session_token_here"
}
```

#### Admin Login
**POST** `/api/auth/admin/login`

**Request Body:**
```json
{
  "email": "admin@nodopay.com",
  "password": "admin_password"
}
```

**Response (200 OK):**
```json
{
  "admin": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@nodopay.com",
    "role": "admin"
  },
  "token": "admin_token_here"
}
```

---

### Customer Dashboard Endpoints

#### Get Credit Overview
**GET** `/api/customer/credit-overview`

**Query Parameters:**
- `customer_id` (required): Customer ID

**Response (200 OK):**
```json
{
  "credit_limit": 100000.00,
  "current_balance": 25000.00,
  "available_balance": 75000.00
}
```

**User Story**: Customer can view their credit limit, current balance, and available credit at a glance.

---

#### Get All Invoices
**GET** `/api/customer/invoices`

**Query Parameters:**
- `customer_id` (required): Customer ID

**Response (200 OK):**
```json
{
  "invoices": [
    {
      "invoice_id": "NODO-ABC123",
      "purchase_date": "2024-01-15",
      "due_date": "2024-04-15",
      "status": "in_grace",
      "principal_amount": 50000.00,
      "interest_amount": 0.00,
      "total_amount": 50000.00,
      "paid_amount": 0.00,
      "remaining_balance": 50000.00,
      "supplier_name": "Foodstuff Store",
      "months_overdue": 0
    }
  ]
}
```

**Invoice Statuses:**
- `pending`: Right after financing
- `in_grace`: After due date, within 30-day grace period
- `overdue`: Grace period ended, interest accruing
- `paid`: Payment received

**User Story**: Customer can see all their invoices with purchase dates, due dates, status, amounts, and interest charges.

---

#### Get Single Invoice
**GET** `/api/customer/invoices/{invoiceId}`

**Response (200 OK):**
```json
{
  "invoice": {
    "invoice_id": "NODO-ABC123",
    "purchase_date": "2024-01-15",
    "due_date": "2024-04-15",
    "grace_period_end_date": "2024-05-15",
    "status": "in_grace",
    "principal_amount": 50000.00,
    "interest_amount": 0.00,
    "total_amount": 50000.00,
    "paid_amount": 0.00,
    "remaining_balance": 50000.00,
    "supplier_name": "Foodstuff Store",
    "months_overdue": 0
  }
}
```

**User Story**: Customer can view detailed information about a specific invoice including grace period and interest details.

---

#### Get Customer Transactions
**GET** `/api/customer/transactions`

**Query Parameters:**
- `customer_id` (required): Customer ID

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "transaction_reference": "TXN-XYZ789",
      "customer_id": 1,
      "business_id": 1,
      "invoice_id": 1,
      "type": "credit_purchase",
      "amount": 50000.00,
      "status": "completed",
      "description": "Order #12345",
      "metadata": {
        "order_reference": "ORD-12345",
        "items": [
          {
            "name": "Product A",
            "quantity": 2,
            "price": 15000.00,
            "description": "High quality product"
          }
        ]
      },
      "processed_at": "2024-01-15T10:30:00Z",
      "business": {
        "business_name": "Foodstuff Store"
      },
      "invoice": {
        "invoice_id": "NODO-ABC123"
      }
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 50
}
```

**Transaction Types:**
- `credit_purchase`: Purchase made using Nodopay credit
- `repayment`: Customer repayment

**User Story**: Customer can view all their transactions including credit purchases and repayments with full details including items purchased.

---

#### Get Repayment Account
**GET** `/api/customer/repayment-account`

**Query Parameters:**
- `customer_id` (required): Customer ID

**Response (200 OK):**
```json
{
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Sterling Bank"
}
```

**User Story**: Customer can see their dedicated bank account details for making repayments.

---

#### Get Customer Profile
**GET** `/api/customer/profile`

**Query Parameters:**
- `customer_id` (required): Customer ID

**Response (200 OK):**
```json
{
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "phone": "+2341234567890",
    "address": "123 Business Street, Lagos",
    "credit_limit": 100000.00,
    "current_balance": 25000.00,
    "available_balance": 75000.00,
    "status": "active"
  }
}
```

**User Story**: Customer can view their complete profile including 16-digit account number and credit information.

---

### Business Dashboard Endpoints

#### Get Business Dashboard
**GET** `/api/business/dashboard`

**Query Parameters:**
- `business_id` (required): Business ID

**Response (200 OK):**
```json
{
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "business@example.com",
    "approval_status": "approved",
    "status": "active"
  },
  "statistics": {
    "total_invoices": 150,
    "total_revenue": 5000000.00,
    "total_withdrawn": 2000000.00,
    "available_balance": 3000000.00,
    "pending_invoices": 10,
    "paid_invoices": 140,
    "pending_withdrawals": 2
  }
}
```

**Balance Calculation:**
- `total_revenue`: Sum of all invoice principal amounts (excluding pending invoices)
- `total_withdrawn`: Sum of all approved/processed withdrawal amounts
- `available_balance`: Total revenue minus total withdrawn (available for withdrawal)

**User Story**: Business owner can see overview of their business including total revenue, withdrawn amounts, available balance, and invoice/withdrawal statistics.

---

#### Get Business Invoices
**GET** `/api/business/invoices`

**Query Parameters:**
- `business_id` (required): Business ID

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "invoice_id": "NODO-ABC123",
      "customer_id": 1,
      "principal_amount": 50000.00,
      "status": "paid",
      "customer": {
        "business_name": "ABC Company"
      }
    }
  ]
}
```

**User Story**: Business can view all invoices from their customers with customer details.

---

#### Submit Invoice for Financing
**POST** `/api/business/submit-invoice`

**Query Parameters:**
- `business_id` (required): Business ID

**Request Body:**
```json
{
  "customer_id": 1,
  "amount": 50000.00,
  "purchase_date": "2024-01-15",
  "due_date": "2024-04-15",
  "description": "Order #12345"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Invoice submitted and financed successfully",
  "invoice": {
    "invoice_id": "NODO-ABC123",
    "amount": 50000.00,
    "due_date": "2024-04-15",
    "status": "pending"
  },
  "transaction": {
    "transaction_reference": "TXN-XYZ789",
    "status": "completed"
  }
}
```

**User Story**: Business can submit an invoice for financing, and if customer has credit, the invoice is automatically created and business receives payment.

---

#### Check Customer Credit
**POST** `/api/business/check-customer-credit`

**Query Parameters:**
- `business_id` (required): Business ID

**Request Body:**
```json
{
  "customer_id": 1,
  "amount": 50000.00
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "has_credit": true,
  "available_credit": 75000.00,
  "current_balance": 25000.00,
  "credit_limit": 100000.00
}
```

**User Story**: Business can check if a customer has sufficient credit before processing an order.

---

#### Get Business Transactions
**GET** `/api/business/transactions`

**Query Parameters:**
- `business_id` (required): Business ID

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "transaction_reference": "TXN-XYZ789",
      "customer_id": 1,
      "invoice_id": 1,
      "type": "credit_purchase",
      "amount": 50000.00,
      "status": "completed",
      "customer": {
        "business_name": "ABC Company"
      }
    }
  ]
}
```

**User Story**: Business can view all transactions including credit purchases and see which customers made purchases.

---

#### Request Withdrawal
**POST** `/api/business/withdrawals/request`

**Query Parameters:**
- `business_id` (required): Business ID

**Request Body:**
```json
{
  "amount": 100000.00,
  "bank_name": "Sterling Bank",
  "account_number": "1234567890",
  "account_name": "Foodstuff Store Ltd",
  "notes": "Monthly withdrawal"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Withdrawal request submitted successfully",
  "withdrawal": {
    "id": 1,
    "withdrawal_reference": "WDR-ABC123",
    "amount": 100000.00,
    "status": "pending",
    "available_balance": 2900000.00
  }
}
```

**Response (400 Bad Request - Insufficient Balance):**
```json
{
  "success": false,
  "message": "Insufficient balance for withdrawal",
  "available_balance": 50000.00,
  "requested_amount": 100000.00
}
```

**User Story**: Business can request withdrawal of their available balance. System validates that requested amount doesn't exceed available balance. Withdrawal is created with 'pending' status and requires admin approval.

---

#### Get Business Withdrawals
**GET** `/api/business/withdrawals`

**Query Parameters:**
- `business_id` (required): Business ID

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "withdrawal_reference": "WDR-ABC123",
      "amount": 100000.00,
      "bank_name": "Sterling Bank",
      "account_number": "1234567890",
      "account_name": "Foodstuff Store Ltd",
      "status": "approved",
      "rejection_reason": null,
      "processed_at": "2024-01-20T10:30:00Z",
      "notes": "Monthly withdrawal",
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 5
}
```

**Withdrawal Statuses:**
- `pending`: Awaiting admin approval
- `approved`: Approved by admin, awaiting processing
- `rejected`: Rejected by admin
- `processed`: Payment processed and sent

**User Story**: Business can view all their withdrawal requests with status, bank details, and processing information.

---

#### Get Business Profile
**GET** `/api/business/profile`

**Query Parameters:**
- `business_id` (required): Business ID

**Response (200 OK):**
```json
{
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "business@example.com",
    "phone": "+2341234567890",
    "address": "123 Store Street",
    "approval_status": "approved",
    "api_token": "nodo_biz_abc123...",
    "webhook_url": "https://business.com/webhook",
    "status": "active"
  }
}
```

**User Story**: Business can view their profile including API token for integration and webhook URL.

---

#### Generate API Token
**POST** `/api/business/generate-api-token`

**Query Parameters:**
- `business_id` (required): Business ID

**Response (200 OK):**
```json
{
  "message": "API token generated successfully",
  "api_token": "nodo_biz_new_token_here"
}
```

**User Story**: Business can generate a new API token if they need to reset or regenerate their integration token.

---

### Admin Panel Endpoints

#### Create New Customer
**POST** `/api/admin/customers`

**Request Body:**
```json
{
  "business_name": "ABC Company",
  "email": "customer@example.com",
  "username": "abc_company",
  "password": "secure_password123",
  "phone": "+2341234567890",
  "address": "123 Business Street, Lagos",
  "minimum_purchase_amount": 20000.00,
  "payment_plan_duration": 3,
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Sterling Bank",
  "kyc_documents": ["file1.pdf", "file2.jpg"]
}
```

**Note**: `kyc_documents` should be sent as multipart/form-data files array.

**Response (201 Created):**
```json
{
  "message": "Customer created successfully",
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "username": "abc_company",
    "credit_limit": 80000.00
  }
}
```

**Credit Limit Formula**: `Credit Limit = Minimum Purchase Amount × (Payment Plan Duration + 1)`

**User Story**: Admin can create new customers with all required information. System automatically generates 16-digit account number and calculates credit limit. Customer receives email with login credentials.

---

#### Get All Customers
**GET** `/api/admin/customers`

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20)

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "account_number": "1234567890123456",
      "business_name": "ABC Company",
      "email": "customer@example.com",
      "credit_limit": 100000.00,
      "current_balance": 25000.00,
      "status": "active",
      "invoices_count": 5
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 50
}
```

**User Story**: Admin can view paginated list of all customers with their credit information and invoice counts.

---

#### Get Single Customer
**GET** `/api/admin/customers/{id}`

**Response (200 OK):**
```json
{
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "credit_limit": 100000.00,
    "current_balance": 25000.00,
    "available_balance": 75000.00,
    "invoices": [...],
    "payments": [...]
  }
}
```

**User Story**: Admin can view detailed customer information including all invoices and payment history.

---

#### Update Customer Credit Limit
**PATCH** `/api/admin/customers/{id}/credit-limit`

**Request Body:**
```json
{
  "credit_limit": 150000.00
}
```

**Response (200 OK):**
```json
{
  "message": "Credit limit updated successfully",
  "customer": {
    "id": 1,
    "credit_limit": 150000.00,
    "available_balance": 125000.00
  }
}
```

**User Story**: Admin can manually adjust a customer's credit limit if needed, and available balance updates automatically.

---

#### Update Customer Status
**PATCH** `/api/admin/customers/{id}/status`

**Request Body:**
```json
{
  "status": "suspended"
}
```

**Status Options**: `active`, `suspended`, `inactive`

**Response (200 OK):**
```json
{
  "message": "Customer status updated successfully",
  "customer": {
    "id": 1,
    "status": "suspended"
  }
}
```

**User Story**: Admin can suspend or activate customer accounts to control access to credit.

---

#### Create New Business
**POST** `/api/admin/businesses`

**Request Body:**
```json
{
  "business_name": "Foodstuff Store",
  "email": "business@example.com",
  "username": "foodstuff_store",
  "password": "secure_password123",
  "phone": "+2341234567890",
  "address": "123 Store Street",
  "webhook_url": "https://business.com/webhook",
  "kyc_documents": ["file1.pdf", "file2.jpg"]
}
```

**Response (201 Created):**
```json
{
  "message": "Business created successfully",
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "business@example.com",
    "username": "foodstuff_store",
    "approval_status": "pending"
  }
}
```

**User Story**: Admin can create new businesses. Business starts with `pending` approval status and receives email with login credentials. Admin must approve before business can use payment gateway.

---

#### Approve Business
**PATCH** `/api/admin/businesses/{id}/approve`

**Request Body:**
```json
{
  "approval_status": "approved"
}
```

**Status Options**: `approved`, `rejected`

**Response (200 OK):**
```json
{
  "message": "Business approval status updated successfully",
  "business": {
    "id": 1,
    "approval_status": "approved",
    "status": "active",
    "api_token": "nodo_biz_abc123..."
  }
}
```

**User Story**: Admin can approve or reject businesses. When approved, business status becomes active and API token is generated. Business receives email notification.

---

#### Get All Businesses
**GET** `/api/admin/businesses`

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "business_name": "Foodstuff Store",
      "email": "business@example.com",
      "approval_status": "approved",
      "status": "active",
      "invoices_count": 150
    }
  ]
}
```

**User Story**: Admin can view all businesses with their approval status and activity.

---

#### Get All Invoices
**GET** `/api/admin/invoices`

**Query Parameters:**
- `status` (optional): Filter by status (pending, in_grace, overdue, paid)
- `customer_id` (optional): Filter by customer ID
- `page` (optional): Page number

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "invoice_id": "NODO-ABC123",
      "customer_id": 1,
      "principal_amount": 50000.00,
      "status": "overdue",
      "interest_amount": 1750.00,
      "customer": {
        "business_name": "ABC Company"
      }
    }
  ]
}
```

**User Story**: Admin can view all invoices with filtering options to monitor overdue invoices and interest.

---

#### Approve/Decline Invoice Financing
**PATCH** `/api/admin/invoices/{id}/status`

**Request Body:**
```json
{
  "action": "approve"
}
```

**Action Options**: `approve`, `decline`

**Response (200 OK):**
```json
{
  "message": "Invoice status updated successfully",
  "invoice": {
    "id": 1,
    "status": "in_grace"
  }
}
```

**User Story**: Admin can manually approve or decline invoice financing if needed.

---

#### Manually Mark Invoice as Paid
**PATCH** `/api/admin/invoices/{id}/mark-paid`

**Response (200 OK):**
```json
{
  "message": "Invoice marked as paid",
  "invoice": {
    "id": 1,
    "status": "paid",
    "remaining_balance": 0.00
  }
}
```

**User Story**: Admin can manually mark invoices as paid if payment was received outside the system, and customer balances update automatically.

---

#### Get Dashboard Statistics
**GET** `/api/admin/dashboard/stats`

**Response (200 OK):**
```json
{
  "total_customers": 150,
  "active_customers": 120,
  "total_exposure": 5000000.00,
  "total_interest_earned": 87500.00,
  "overdue_invoices": 25,
  "total_invoices": 500
}
```

**User Story**: Admin can view platform-wide statistics including total exposure, interest earned, and overdue invoices for monitoring and reporting.

---

#### Get All Withdrawals
**GET** `/api/admin/withdrawals`

**Query Parameters:**
- `status` (optional): Filter by status (pending, approved, rejected, processed)
- `business_id` (optional): Filter by business ID
- `page` (optional): Page number

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "withdrawal_reference": "WDR-ABC123",
      "business_id": 1,
      "amount": 100000.00,
      "bank_name": "Sterling Bank",
      "account_number": "1234567890",
      "account_name": "Foodstuff Store Ltd",
      "status": "pending",
      "rejection_reason": null,
      "processed_at": null,
      "notes": "Monthly withdrawal",
      "business": {
        "id": 1,
        "business_name": "Foodstuff Store",
        "email": "business@example.com"
      },
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 10
}
```

**User Story**: Admin can view all withdrawal requests with filtering options to monitor pending withdrawals and track business payouts.

---

#### Approve/Reject Withdrawal
**PATCH** `/api/admin/withdrawals/{id}/approve`

**Request Body:**
```json
{
  "action": "approve"
}
```

**Or for rejection:**
```json
{
  "action": "reject",
  "rejection_reason": "Insufficient documentation"
}
```

**Response (200 OK - Approved):**
```json
{
  "message": "Withdrawal approved successfully",
  "withdrawal": {
    "id": 1,
    "withdrawal_reference": "WDR-ABC123",
    "amount": 100000.00,
    "status": "approved",
    "processed_at": "2024-01-20T10:30:00Z",
    "business": {
      "id": 1,
      "business_name": "Foodstuff Store"
    }
  }
}
```

**Response (200 OK - Rejected):**
```json
{
  "message": "Withdrawal rejected",
  "withdrawal": {
    "id": 1,
    "withdrawal_reference": "WDR-ABC123",
    "amount": 100000.00,
    "status": "rejected",
    "rejection_reason": "Insufficient documentation",
    "business": {
      "id": 1,
      "business_name": "Foodstuff Store"
    }
  }
}
```

**Response (400 Bad Request - Insufficient Balance):**
```json
{
  "message": "Insufficient balance for withdrawal",
  "available_balance": 50000.00,
  "requested_amount": 100000.00
}
```

**User Story**: Admin can approve or reject withdrawal requests. System validates available balance before approval. When approved, balance is deducted and withdrawal can be processed. When rejected, reason is recorded and business balance remains unchanged.

---

#### Process Withdrawal
**PATCH** `/api/admin/withdrawals/{id}/process`

**Response (200 OK):**
```json
{
  "message": "Withdrawal processed successfully",
  "withdrawal": {
    "id": 1,
    "withdrawal_reference": "WDR-ABC123",
    "amount": 100000.00,
    "status": "processed",
    "processed_at": "2024-01-20T10:30:00Z",
    "business": {
      "id": 1,
      "business_name": "Foodstuff Store"
    }
  }
}
```

**Response (400 Bad Request):**
```json
{
  "message": "Withdrawal must be approved before processing",
  "withdrawal": {
    "status": "pending"
  }
}
```

**User Story**: Admin can mark withdrawal as processed after payment has been sent to business bank account. Withdrawal must be approved before it can be processed.

---

### Payment Processing Endpoints

#### Payment Webhook
**POST** `/api/payments/webhook`

This endpoint receives payment notifications from virtual account providers (Paystack, Monnify, Sterling Digital Accounts, etc.).

**Request Body:**
```json
{
  "account_number": "1234567890123456",
  "amount": 25000.00,
  "transaction_reference": "TXN-ABC123",
  "payment_date": "2024-01-20T10:30:00Z"
}
```

**Note**: The `account_number` is the 16-digit unique account number assigned to each customer, not the virtual account number.

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Payment processed successfully",
  "payment": {
    "payment_reference": "PAY-XYZ789",
    "amount": 25000.00,
    "status": "completed"
  },
  "customer": {
    "available_balance": 100000.00,
    "current_balance": 0.00
  }
}
```

**Payment Processing Logic:**
1. Identifies customer by 16-digit account number
2. Applies payment to oldest unpaid invoices first (FIFO)
3. Marks invoices as paid if fully paid
4. Updates customer balances automatically
5. Recalculates available credit
6. Sends email notifications

**User Story**: When customer pays to their virtual account, payment provider sends webhook. System automatically processes payment, updates invoices, and adjusts balances.

---

#### Record Payment (Manual)
**POST** `/api/payments/record`

Manually record a payment (for admin use).

**Request Body:**
```json
{
  "customer_id": 1,
  "amount": 25000.00,
  "transaction_reference": "TXN-ABC123",
  "notes": "Bank transfer"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Payment recorded successfully",
  "payment": {
    "id": 1,
    "payment_reference": "PAY-XYZ789",
    "amount": 25000.00,
    "status": "completed"
  }
}
```

**User Story**: Admin can manually record payments if needed, and system processes them the same way as automatic payments.

---

#### Get Payment History
**GET** `/api/payments/history/{customerId}`

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "payment_reference": "PAY-XYZ789",
      "amount": 25000.00,
      "status": "completed",
      "paid_at": "2024-01-20T10:30:00Z"
    }
  ]
}
```

**User Story**: Admin or customer can view payment history for tracking and reconciliation.

---

## Data Models

### Customer
```json
{
  "id": 1,
  "account_number": "1234567890123456",
  "business_name": "ABC Company",
  "email": "customer@example.com",
  "username": "abc_company",
  "phone": "+2341234567890",
  "address": "123 Business Street",
  "minimum_purchase_amount": 20000.00,
  "payment_plan_duration": 3,
  "credit_limit": 80000.00,
  "current_balance": 25000.00,
  "available_balance": 55000.00,
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Sterling Bank",
  "kyc_documents": ["path/to/doc1.pdf", "path/to/doc2.jpg"],
  "status": "active"
}
```

### Business
```json
{
  "id": 1,
  "business_name": "Foodstuff Store",
  "email": "business@example.com",
  "username": "foodstuff_store",
  "phone": "+2341234567890",
  "address": "123 Store Street",
  "approval_status": "approved",
  "api_token": "nodo_biz_abc123...",
  "webhook_url": "https://business.com/webhook",
  "status": "active"
}
```

### Invoice
```json
{
  "id": 1,
  "invoice_id": "NODO-ABC123",
  "customer_id": 1,
  "supplier_id": 1,
  "supplier_name": "Foodstuff Store",
  "principal_amount": 50000.00,
  "interest_amount": 1750.00,
  "total_amount": 51750.00,
  "paid_amount": 0.00,
  "remaining_balance": 51750.00,
  "purchase_date": "2024-01-15",
  "due_date": "2024-04-15",
  "grace_period_end_date": "2024-05-15",
  "status": "overdue",
  "months_overdue": 2
}
```

### Transaction
```json
{
  "id": 1,
  "transaction_reference": "TXN-XYZ789",
  "customer_id": 1,
  "business_id": 1,
  "invoice_id": 1,
  "type": "credit_purchase",
  "amount": 50000.00,
  "status": "completed",
  "description": "Order #12345",
  "metadata": {
    "order_reference": "ORD-12345",
    "items": [
      {
        "name": "Product A",
        "quantity": 2,
        "price": 15000.00,
        "description": "High quality product"
      },
      {
        "name": "Product B",
        "quantity": 1,
        "price": 20000.00,
        "description": "Premium product"
      }
    ]
  },
  "processed_at": "2024-01-15T10:30:00Z"
}
```

---

## Interest & Invoice Logic

### Grace Period
- After invoice due date → 30-day grace period
- No interest charged during grace period
- Status: `in_grace`

### Interest Calculation
- **Monthly interest rate:** 3.5%
- **Formula:** `Interest = Outstanding Amount × 3.5% × # months overdue after grace`
- Interest starts accruing after grace period ends
- Status changes to `overdue` after grace period

### Invoice Status Automation
1. **Pending** → Right after financing
2. **In Grace Period** → After due date, within 30 days
3. **Overdue** → Grace period ended, interest accruing
4. **Paid** → Payment received, balance cleared

### Example Interest Calculation
```
Principal Amount: ₦50,000
Due Date: April 15, 2024
Grace Period End: May 15, 2024
Current Date: July 15, 2024

Months Overdue: 2 months (after grace period)
Interest = ₦50,000 × 3.5% × 2 = ₦3,500
Total Amount = ₦50,000 + ₦3,500 = ₦53,500
```

---

## Error Handling

All errors follow a consistent format:

**400 Bad Request:**
```json
{
  "message": "Validation failed",
  "errors": {
    "email": ["The email field is required."],
    "amount": ["The amount must be at least 0.01."]
  }
}
```

**401 Unauthorized:**
```json
{
  "message": "Unauthenticated"
}
```

**404 Not Found:**
```json
{
  "message": "Resource not found"
}
```

**500 Internal Server Error:**
```json
{
  "message": "Server error",
  "error": "Error details"
}
```

---

## Rate Limiting

API requests are rate-limited:
- **Customer endpoints:** 60 requests per minute
- **Business endpoints:** 100 requests per minute
- **Admin endpoints:** 120 requests per minute
- **Pay with Nodopay API:** 100 requests per minute

Rate limit headers:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1640995200
```

---

## Platform Workflows

### Financing Workflow
1. Customer buys goods on business platform
2. Customer selects "Pay with Nodopay"
3. Business calls `POST /api/pay-with-nodopay/purchase`
4. System checks credit availability
5. If approved, invoice created automatically
6. Nodopay automatically pays business
7. Customer sees invoice in dashboard
8. Credit limit adjusts automatically

### Repayment Workflow
1. Customer pays to virtual account
2. Payment provider sends webhook to `POST /api/payments/webhook`
3. System identifies customer by 16-digit account number
4. Payment applied to oldest invoices first
5. Invoices marked as paid if fully paid
6. Customer balance updated automatically
7. Available credit increases automatically

---

## Support

For API support:
- Email: api-support@nodopay.com
- Documentation: https://docs.nodopay.com
- Status Page: https://status.nodopay.com

---

## Version

**v1.0.0** - Complete API with all features implemented

