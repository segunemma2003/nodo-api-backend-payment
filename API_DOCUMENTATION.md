# NODOPAY API Documentation

## Table of Contents
1. [Overview](#overview)
2. [Base URL](#base-url)
3. [Authentication](#authentication)
4. [Customer Panel API](#customer-panel-api)
5. [Business Panel API](#business-panel-api)
6. [Admin Panel API](#admin-panel-api)
7. [Payment Gateway Integration API](#payment-gateway-integration-api)
8. [Data Models](#data-models)
9. [Error Handling](#error-handling)

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

## Base URL

```
https://nodopay-api-0fbd4546e629.herokuapp.com/api
```

All endpoints are prefixed with `/api`.

---

## Authentication

### Customer Authentication
**Endpoint:** `POST /api/auth/customer/login`

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
    "credit_limit": "100000.00",
    "available_balance": "75000.00"
  },
  "token": "session_token_here"
}
```

### Business Authentication
**Endpoint:** `POST /api/auth/business/login`

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

### Admin Authentication
**Endpoint:** `POST /api/auth/admin/login`

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

### API Token Authentication (External Integration)
For external integrations, include API token in header:
```
X-API-Token: your_business_api_token_here
```

---

## Customer Panel API

### 1. Get Credit Overview
**GET** `/api/customer/credit-overview`

**Query Parameters:**
- `customer_id` (required): Customer ID

**Response (200 OK):**
```json
{
  "credit_limit": "100000.00",
  "current_balance": "25000.00",
  "available_balance": "75000.00"
}
```

### 2. Get All Invoices
**GET** `/api/customer/invoices`

**Query Parameters:**
- `customer_id` (required): Customer ID
- `status` (optional): Filter by status (pending, in_grace, overdue, paid)
- `page` (optional): Page number for pagination
- `per_page` (optional): Items per page (default: 20)

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "invoice_id": "INV-2024-001",
      "customer_id": 1,
      "supplier_id": 1,
      "amount": "50000.00",
      "purchase_date": "2024-01-15",
      "due_date": "2024-02-15",
      "grace_period_end_date": "2024-03-16",
      "status": "in_grace",
      "principal_amount": "50000.00",
      "interest_amount": "0.00",
      "total_amount": "50000.00",
      "paid_amount": "0.00",
      "remaining_balance": "50000.00",
      "supplier": {
        "id": 1,
        "business_name": "Foodstuff Store"
      }
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

### 3. Get Invoice Details
**GET** `/api/customer/invoices/{invoiceId}`

**Response (200 OK):**
```json
{
  "invoice": {
    "id": 1,
    "invoice_id": "INV-2024-001",
    "customer_id": 1,
    "supplier_id": 1,
    "amount": "50000.00",
    "purchase_date": "2024-01-15",
    "due_date": "2024-02-15",
    "grace_period_end_date": "2024-03-16",
    "status": "in_grace",
    "principal_amount": "50000.00",
    "interest_amount": "0.00",
    "total_amount": "50000.00",
    "paid_amount": "0.00",
    "remaining_balance": "50000.00",
    "supplier": {
      "id": 1,
      "business_name": "Foodstuff Store"
    }
  }
}
```

### 4. Get All Transactions
**GET** `/api/customer/transactions`

**Query Parameters:**
- `customer_id` (required): Customer ID
- `type` (optional): Filter by type (purchase, repayment, payout)
- `status` (optional): Filter by status (pending, completed, failed)
- `page` (optional): Page number
- `per_page` (optional): Items per page (default: 20)

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "transaction_reference": "TXN-2024-001",
      "customer_id": 1,
      "business_id": 1,
      "invoice_id": 1,
      "type": "purchase",
      "amount": "50000.00",
      "status": "completed",
      "description": "Purchase from Foodstuff Store",
      "metadata": {
        "items": [
          {
            "name": "Rice 50kg",
            "quantity": 10,
            "price": "5000.00",
            "description": "Premium rice"
          }
        ]
      },
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

### 5. Get Repayment Account
**GET** `/api/customer/repayment-account`

**Query Parameters:**
- `customer_id` (required): Customer ID

**Response (200 OK):**
```json
{
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Sterling Bank",
  "account_number": "1234567890123456"
}
```

### 6. Get Profile
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
    "username": "customer123",
    "phone": "08012345678",
    "address": "Lagos, Nigeria",
    "credit_limit": "100000.00",
    "current_balance": "25000.00",
    "available_balance": "75000.00",
    "virtual_account_number": "1234567890",
    "virtual_account_bank": "Sterling Bank",
    "kyc_documents": ["kyc_documents/customer_1/doc1.pdf"],
    "status": "active"
  }
}
```

### 7. Update Profile
**PUT** `/api/customer/profile`

**Query Parameters:**
- `customer_id` (required): Customer ID

**Request Body (all fields optional, only include fields to update):**
```json
{
  "business_name": "Updated Company Name",
  "email": "newemail@example.com",
  "username": "newusername",
  "password": "NewPassword123!",
  "phone": "08098765432",
  "address": "New Address",
  "kyc_documents": []
}
```

**Note:** For `kyc_documents`, send as multipart/form-data file uploads. New KYC documents will be added to existing ones.

**Response (200 OK):**
```json
{
  "message": "Profile updated successfully",
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "Updated Company Name",
    "email": "newemail@example.com",
    "username": "newusername",
    "phone": "08098765432",
    "address": "New Address",
    "kyc_documents": ["kyc_documents/customer_1/doc1.pdf", "kyc_documents/customer_1/doc2.pdf"]
  }
}
```

### 8. Change PIN
**POST** `/api/customer/change-pin`

**Query Parameters:**
- `customer_id` (required): Customer ID

**Request Body:**
```json
{
  "current_pin": "0000",
  "new_pin": "1234"
}
```

**Response (200 OK):**
```json
{
  "message": "PIN changed successfully"
}
```

**Note:** 
- Default PIN is `0000` and can only be used to change the PIN, not for payments
- New PIN must be 4 digits and cannot be `0000`
- After changing PIN, the new PIN must be used for all payment transactions

---

## Business Panel API

### 1. Get Dashboard
**GET** `/api/business/dashboard`

**Headers:**
- `Authorization: Bearer {token}` (from login)

**Response (200 OK):**
```json
{
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "business@example.com",
    "approval_status": "approved",
    "status": "active",
    "api_token": "nodo_biz_abc123..."
  },
  "total_revenue": "500000.00",
  "total_withdrawn": "200000.00",
  "available_balance": "300000.00",
  "pending_withdrawals": "50000.00",
  "total_invoices": 25,
  "pending_invoices": 5
}
```

### 2. Get All Invoices
**GET** `/api/business/invoices`

**Headers:**
- `Authorization: Bearer {token}`

**Query Parameters:**
- `status` (optional): Filter by status
- `page` (optional): Page number
- `per_page` (optional): Items per page

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "invoice_id": "INV-2024-001",
      "customer_id": 1,
      "supplier_id": 1,
      "amount": "50000.00",
      "status": "in_grace",
      "customer": {
        "id": 1,
        "business_name": "ABC Company",
        "account_number": "1234567890123456"
      }
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

### 3. Submit Invoice for Financing
**POST** `/api/business/submit-invoice`

**Headers:**
- `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "customer_account_number": "1234567890123456",
  "amount": "50000.00",
  "purchase_date": "2024-01-15",
  "due_date": "2024-02-15",
  "order_reference": "ORD-12345",
  "items": [
    {
      "name": "Rice 50kg",
      "quantity": 10,
      "price": "5000.00",
      "description": "Premium rice"
    }
  ]
}
```

**Required Fields:**
- `customer_account_number`: 16-digit customer account number
- `amount`: Invoice amount (numeric, min: 0.01)

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Invoice submitted and financed successfully",
  "invoice": {
    "invoice_id": "INV-2024-001",
    "amount": "50000.00",
    "due_date": "2024-02-15",
    "status": "pending"
  },
  "customer": {
    "account_number": "1234567890123456",
    "business_name": "ABC Company"
  },
  "transaction": {
    "transaction_reference": "TXN-2024-001",
    "status": "completed"
  }
}
```

### 4. Check Customer Credit
**POST** `/api/business/check-customer-credit`

**Headers:**
- `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "customer_account_number": "1234567890123456",
  "amount": "50000.00"
}
```

**Required Fields:**
- `customer_account_number`: 16-digit customer account number
- `amount`: Amount to check (numeric, min: 0.01)

**Response (200 OK):**
```json
{
  "success": true,
  "has_credit": true,
  "customer": {
    "account_number": "1234567890123456",
    "business_name": "ABC Company"
  },
  "available_credit": "75000.00",
  "current_balance": "25000.00",
  "credit_limit": "100000.00"
}
```

### 5. Get All Transactions
**GET** `/api/business/transactions`

**Headers:**
- `Authorization: Bearer {token}`

**Query Parameters:**
- `type` (optional): Filter by type
- `status` (optional): Filter by status
- `page` (optional): Page number
- `per_page` (optional): Items per page

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "transaction_reference": "TXN-2024-001",
      "customer_id": 1,
      "business_id": 1,
      "type": "purchase",
      "amount": "50000.00",
      "status": "completed",
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

### 6. Request Withdrawal
**POST** `/api/business/withdrawals/request`

**Headers:**
- `Authorization: Bearer {token}`

**Request Body:**
```json
{
  "amount": "50000.00",
  "bank_details": {
    "account_number": "1234567890",
    "account_name": "Foodstuff Store",
    "bank_name": "Sterling Bank",
    "bank_code": "232"
  }
}
```

**Response (201 Created):**
```json
{
  "message": "Withdrawal request submitted successfully",
  "withdrawal": {
    "id": 1,
    "withdrawal_reference": "WDR-2024-001",
    "amount": "50000.00",
    "status": "pending"
  }
}
```

### 7. Get All Withdrawals
**GET** `/api/business/withdrawals`

**Headers:**
- `Authorization: Bearer {token}`

**Query Parameters:**
- `status` (optional): Filter by status (pending, approved, rejected, processed)
- `page` (optional): Page number
- `per_page` (optional): Items per page

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "withdrawal_reference": "WDR-2024-001",
      "amount": "50000.00",
      "status": "pending",
      "bank_details": {
        "account_number": "1234567890",
        "account_name": "Foodstuff Store",
        "bank_name": "Sterling Bank"
      },
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

### 8. Get Profile
**GET** `/api/business/profile`

**Headers:**
- `Authorization: Bearer {token}`

**Response (200 OK):**
```json
{
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "business@example.com",
    "username": "business123",
    "phone": "08012345678",
    "address": "Lagos, Nigeria",
    "approval_status": "approved",
    "status": "active",
    "api_token": "nodo_biz_abc123...",
    "webhook_url": "https://example.com/webhook",
    "kyc_documents": ["kyc_documents/business_1/doc1.pdf"]
  }
}
```

### 9. Update Profile
**PUT** `/api/business/profile`

**Headers:**
- `Authorization: Bearer {token}`

**Request Body (all fields optional, only include fields to update):**
```json
{
  "business_name": "Updated Store Name",
  "email": "newemail@example.com",
  "username": "newusername",
  "password": "NewPassword123!",
  "phone": "08098765432",
  "address": "New Address",
  "webhook_url": "https://newwebhook.com/webhook",
  "kyc_documents": []
}
```

**Note:** For `kyc_documents`, send as multipart/form-data file uploads. New KYC documents will be added to existing ones.

**Response (200 OK):**
```json
{
  "message": "Profile updated successfully",
  "business": {
    "id": 1,
    "business_name": "Updated Store Name",
    "email": "newemail@example.com",
    "username": "newusername",
    "phone": "08098765432",
    "address": "New Address",
    "webhook_url": "https://newwebhook.com/webhook",
    "kyc_documents": ["kyc_documents/business_1/doc1.pdf", "kyc_documents/business_1/doc2.pdf"]
  }
}
```

### 10. Generate Invoice Link
**POST** `/api/business/invoices/{invoiceId}/generate-link`

**Headers:**
- `Authorization: Bearer {token}`

**Response (200 OK):**
```json
{
  "message": "Invoice link generated successfully",
  "invoice_link": "https://nodopay.com/checkout/inv-abc123xyz",
  "slug": "inv-abc123xyz",
  "invoice": {
    "id": 1,
    "invoice_id": "INV-2024-001",
    "amount": "50000.00",
    "status": "in_grace",
    "is_used": false
  }
}
```

**Note:** 
- Invoice links are one-time use only
- Link format: `{FRONTEND_URL}/checkout/{slug}`
- Once used for payment, the link cannot be reused
- Set `FRONTEND_URL` environment variable to configure the frontend URL

---

## Admin Panel API

### Customer Management

#### 1. Create Customer
**POST** `/api/admin/customers`

**Request Body:**
```json
{
  "business_name": "ABC Company",
  "email": "customer@example.com",
  "username": "customer123",
  "password": "Password123!",
  "phone": "08012345678",
  "address": "Lagos, Nigeria",
  "minimum_purchase_amount": 50000,
  "payment_plan_duration": 6,
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Sterling Bank",
  "kyc_documents": []
}
```

**Required Fields:**
- `business_name` (string, max 255)
- `email` (email, unique)
- `username` (string, unique)
- `password` (string, min 8 characters)
- `minimum_purchase_amount` (numeric, min 0)
- `payment_plan_duration` (integer, min 1)

**Optional Fields:**
- `phone` (string)
- `address` (string)
- `virtual_account_number` (string, unique) - Will be auto-filled from third-party API later
- `virtual_account_bank` (string) - Will be auto-filled from third-party API later
- `kyc_documents` (array of files: pdf, jpg, jpeg, png, max 10MB each)

**Response (201 Created):**
```json
{
  "message": "Customer created successfully",
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "username": "customer123",
    "credit_limit": "350000.00"
  }
}
```

**Note:** `account_number` is auto-generated (16-digit). Credit limit is calculated as: `minimum_purchase_amount × (payment_plan_duration + 1)`

#### 2. Get All Customers
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
      "credit_limit": "350000.00",
      "current_balance": "50000.00",
      "available_balance": "300000.00",
      "status": "active",
      "invoices_count": 5
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

#### 3. Get Customer Details
**GET** `/api/admin/customers/{id}`

**Response (200 OK):**
```json
{
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "username": "customer123",
    "phone": "08012345678",
    "address": "Lagos, Nigeria",
    "minimum_purchase_amount": "50000.00",
    "payment_plan_duration": 6,
    "credit_limit": "350000.00",
    "current_balance": "50000.00",
    "available_balance": "300000.00",
    "virtual_account_number": "1234567890",
    "virtual_account_bank": "Sterling Bank",
    "kyc_documents": ["kyc_documents/customer_1/doc1.pdf"],
    "status": "active",
    "invoices": [...],
    "payments": [...]
  }
}
```

#### 4. Update Customer
**PUT** `/api/admin/customers/{id}`

**Request Body (all fields optional, only include fields to update):**
```json
{
  "business_name": "Updated Company Name",
  "email": "newemail@example.com",
  "username": "newusername",
  "password": "NewPassword123!",
  "phone": "08098765432",
  "address": "New Address",
  "minimum_purchase_amount": 75000,
  "payment_plan_duration": 12,
  "virtual_account_number": "9876543210",
  "virtual_account_bank": "GTBank",
  "kyc_documents": []
}
```

**Response (200 OK):**
```json
{
  "message": "Customer updated successfully",
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "Updated Company Name",
    "email": "newemail@example.com",
    "credit_limit": "975000.00"
  }
}
```

**Note:** Updating `minimum_purchase_amount` or `payment_plan_duration` will automatically recalculate the credit limit.

#### 5. Update Credit Limit
**PATCH** `/api/admin/customers/{id}/credit-limit`

**Request Body:**
```json
{
  "credit_limit": 500000
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

#### 6. Update Customer Status
**PATCH** `/api/admin/customers/{id}/status`

**Request Body:**
```json
{
  "status": "suspended"
}
```

**Valid statuses:** `active`, `suspended`, `inactive`

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

### Business Management

#### 1. Create Business
**POST** `/api/admin/businesses`

**Request Body:**
```json
{
  "business_name": "Foodstuff Store",
  "email": "business@example.com",
  "username": "business123",
  "password": "Password123!",
  "phone": "08012345678",
  "address": "Lagos, Nigeria",
  "webhook_url": "https://example.com/webhook",
  "kyc_documents": []
}
```

**Required Fields:**
- `business_name` (string, max 255)
- `email` (email, unique)
- `username` (string, unique)
- `password` (string, min 8 characters)

**Optional Fields:**
- `phone` (string)
- `address` (string)
- `webhook_url` (valid URL)
- `kyc_documents` (array of files: pdf, jpg, jpeg, png, max 10MB each)

**Response (201 Created):**
```json
{
  "message": "Business created successfully",
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "business@example.com",
    "username": "business123",
    "approval_status": "pending"
  }
}
```

**Note:** New businesses are created with `approval_status: "pending"` and `status: "inactive"`. They need admin approval to become active.

#### 2. Get All Businesses
**GET** `/api/admin/businesses`

**Query Parameters:**
- `page` (optional): Page number
- `per_page` (optional): Items per page

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
      "invoices_count": 25
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

#### 3. Get Business Details
**GET** `/api/admin/businesses/{id}`

**Response (200 OK):**
```json
{
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "business@example.com",
    "username": "business123",
    "phone": "08012345678",
    "address": "Lagos, Nigeria",
    "approval_status": "approved",
    "status": "active",
    "api_token": "nodo_biz_abc123...",
    "webhook_url": "https://example.com/webhook",
    "kyc_documents": ["kyc_documents/business_1/doc1.pdf"],
    "invoices": [...],
    "transactions": [...]
  }
}
```

#### 4. Update Business
**PUT** `/api/admin/businesses/{id}`

**Request Body (all fields optional, only include fields to update):**
```json
{
  "business_name": "Updated Store Name",
  "email": "newemail@example.com",
  "username": "newusername",
  "password": "NewPassword123!",
  "phone": "08098765432",
  "address": "New Address",
  "webhook_url": "https://newwebhook.com/webhook",
  "kyc_documents": []
}
```

**Response (200 OK):**
```json
{
  "message": "Business updated successfully",
  "business": {
    "id": 1,
    "business_name": "Updated Store Name",
    "email": "newemail@example.com",
    "webhook_url": "https://newwebhook.com/webhook"
  }
}
```

#### 5. Approve/Reject Business
**PATCH** `/api/admin/businesses/{id}/approve`

**Request Body:**
```json
{
  "approval_status": "approved"
}
```

**Valid statuses:** `approved`, `rejected`

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

**Note:** When approved, the business status becomes `active` and an API token is automatically generated if not already present.

#### 6. Update Business Status
**PATCH** `/api/admin/businesses/{id}/status`

**Request Body:**
```json
{
  "status": "suspended"
}
```

**Valid statuses:** `active`, `suspended`, `inactive`

**Response (200 OK):**
```json
{
  "message": "Business status updated successfully",
  "business": {
    "id": 1,
    "status": "suspended"
  }
}
```

### Invoice Management

#### 1. Get All Invoices
**GET** `/api/admin/invoices`

**Query Parameters:**
- `status` (optional): Filter by status (pending, in_grace, overdue, paid)
- `customer_id` (optional): Filter by customer ID
- `page` (optional): Page number
- `per_page` (optional): Items per page

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "invoice_id": "INV-2024-001",
      "customer_id": 1,
      "supplier_id": 1,
      "amount": "50000.00",
      "status": "in_grace",
      "customer": {
        "id": 1,
        "business_name": "ABC Company",
        "account_number": "1234567890123456"
      }
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

#### 2. Update Invoice Status
**PATCH** `/api/admin/invoices/{id}/status`

**Request Body:**
```json
{
  "action": "approve"
}
```

**Valid actions:** `approve`, `decline`

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

#### 3. Mark Invoice as Paid
**PATCH** `/api/admin/invoices/{id}/mark-paid`

**Response (200 OK):**
```json
{
  "message": "Invoice marked as paid",
  "invoice": {
    "id": 1,
    "status": "paid",
    "paid_amount": "50000.00",
    "remaining_balance": "0.00"
  }
}
```

### Dashboard Statistics

#### Get Dashboard Stats
**GET** `/api/admin/dashboard/stats`

**Response (200 OK):**
```json
{
  "total_customers": 150,
  "active_customers": 120,
  "total_exposure": "50000000.00",
  "total_interest_earned": "2500000.00",
  "overdue_invoices": 25,
  "total_invoices": 500
}
```

### Withdrawal Management

#### 1. Get All Withdrawals
**GET** `/api/admin/withdrawals`

**Query Parameters:**
- `status` (optional): Filter by status (pending, approved, rejected, processed)
- `business_id` (optional): Filter by business ID
- `page` (optional): Page number
- `per_page` (optional): Items per page

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "withdrawal_reference": "WDR-2024-001",
      "business_id": 1,
      "amount": "50000.00",
      "status": "pending",
      "business": {
        "id": 1,
        "business_name": "Foodstuff Store"
      },
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

#### 2. Approve/Reject Withdrawal
**PATCH** `/api/admin/withdrawals/{id}/approve`

**Request Body:**
```json
{
  "action": "approve",
  "rejection_reason": null
}
```

**For rejection:**
```json
{
  "action": "reject",
  "rejection_reason": "Insufficient documentation"
}
```

**Response (200 OK):**
```json
{
  "message": "Withdrawal approved successfully",
  "withdrawal": {
    "id": 1,
    "status": "approved",
    "processed_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

#### 3. Process Withdrawal
**PATCH** `/api/admin/withdrawals/{id}/process`

**Note:** Withdrawal must be approved before processing.

**Response (200 OK):**
```json
{
  "message": "Withdrawal processed successfully",
  "withdrawal": {
    "id": 1,
    "status": "processed",
    "processed_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

## Payment Gateway Integration API

These endpoints are for external integrations (e.g., e-commerce platforms) to integrate Nodopay payment gateway.

**Authentication:** Include API token in header: `X-API-Token: {business_api_token}`

### 1. Check Customer Credit
**POST** `/api/pay-with-nodopay/check-credit`

**Headers:**
- `X-API-Token: {business_api_token}`

**Request Body:**
```json
{
  "account_number": "1234567890123456",
  "amount": 50000.00
}
```

**Response (200 OK):**
```json
{
  "has_sufficient_credit": true,
  "available_balance": "75000.00",
  "requested_amount": "50000.00",
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "ABC Company"
  }
}
```

### 2. Get Customer Details
**GET** `/api/pay-with-nodopay/customer?account_number=1234567890123456`

**Headers:**
- `X-API-Token: {business_api_token}`

**Response (200 OK):**
```json
{
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "credit_limit": "100000.00",
    "available_balance": "75000.00"
  }
}
```

### 3. Process Purchase Request
**POST** `/api/pay-with-nodopay/purchase`

**Headers:**
- `X-API-Token: {business_api_token}`

**Request Body:**
```json
{
  "account_number": "1234567890123456",
  "customer_email": "customer@example.com",
  "cvv": "123",
  "pin": "1234",
  "amount": 50000.00,
  "purchase_date": "2024-01-15",
  "order_reference": "ORD-12345",
  "items": [
    {
      "name": "Rice 50kg",
      "quantity": 10,
      "price": "5000.00",
      "description": "Premium rice"
    },
    {
      "name": "Beans 25kg",
      "quantity": 5,
      "price": "3000.00",
      "description": "Honey beans"
    }
  ]
}
```

**Required Fields:**
- `account_number`: 16-digit account number
- `customer_email`: Customer email (must match account)
- `cvv`: 3-digit CVV
- `pin`: 4-digit PIN (cannot be default 0000)
- `amount`: Purchase amount
- `items`: Array of purchased items

**Note:** 
- Both `cvv` and `pin` are required for all payments
- Default PIN `0000` cannot be used for payments, only for changing PIN
- Customer must have changed their PIN from default before making payments

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Purchase processed successfully",
  "invoice": {
    "id": 1,
    "invoice_id": "INV-2024-001",
    "amount": "50000.00",
    "status": "in_grace"
  },
  "transaction": {
    "id": 1,
    "transaction_reference": "TXN-2024-001",
    "amount": "50000.00",
    "status": "completed"
  },
  "payout": {
    "id": 1,
    "payout_reference": "PO-2024-001",
    "amount": "50000.00",
    "status": "completed"
  }
}
```

---

## Public Invoice Checkout API

These endpoints allow customers to view and pay invoices via unique invoice links generated by businesses.

### 1. Get Invoice by Slug
**GET** `/api/invoice/checkout/{slug}`

**Public endpoint - No authentication required**

**Response (200 OK):**
```json
{
  "invoice": {
    "id": 1,
    "invoice_id": "INV-2024-001",
    "amount": "50000.00",
    "remaining_balance": "50000.00",
    "status": "in_grace",
    "purchase_date": "2024-01-15",
    "due_date": "2024-02-15",
    "supplier": {
      "id": 1,
      "business_name": "Foodstuff Store"
    }
  }
}
```

**Error Response (400 Bad Request) - Link Already Used:**
```json
{
  "message": "This invoice link has already been used",
  "invoice": {
    "invoice_id": "INV-2024-001",
    "status": "paid"
  }
}
```

### 2. Pay Invoice via Link
**POST** `/api/invoice/checkout/{slug}/pay`

**Public endpoint - No authentication required**

**Request Body:**
```json
{
  "account_number": "1234567890123456",
  "cvv": "123",
  "pin": "1234"
}
```

**Required Fields:**
- `account_number`: 16-digit account number
- `cvv`: 3-digit CVV
- `pin`: 4-digit PIN (cannot be default 0000)

**Response (200 OK):**
```json
{
  "message": "Payment processed successfully",
  "invoice": {
    "id": 1,
    "invoice_id": "INV-2024-001",
    "status": "paid",
    "paid_amount": "50000.00",
    "remaining_balance": "0.00"
  }
}
```

**Error Responses:**

**Invalid CVV:**
```json
{
  "message": "Invalid CVV"
}
```

**Invalid PIN:**
```json
{
  "message": "Invalid PIN. Please use your payment PIN (not the default 0000)"
}
```

**Link Already Used:**
```json
{
  "message": "This invoice link has already been used"
}
```

**Invoice Not Belonging to Account:**
```json
{
  "message": "This invoice does not belong to the provided account"
}
```

**Note:**
- Invoice links are **one-time use only** - once used for payment, they cannot be reused
- Customer must provide their 16-digit `account_number`, 3-digit `cvv`, and 4-digit `pin`
- Default PIN `0000` cannot be used for payments
- The invoice must belong to the customer's account

---

## Data Models

### Customer
```json
{
  "id": 1,
  "account_number": "1234567890123456",
  "cvv": "123",
  "pin": "****",
  "business_name": "ABC Company",
  "email": "customer@example.com",
  "username": "customer123",
  "phone": "08012345678",
  "address": "Lagos, Nigeria",
  "minimum_purchase_amount": "50000.00",
  "payment_plan_duration": 6,
  "credit_limit": "350000.00",
  "current_balance": "50000.00",
  "available_balance": "300000.00",
  "virtual_account_number": "1234567890",
  "virtual_account_bank": "Sterling Bank",
  "kyc_documents": ["kyc_documents/customer_1/doc1.pdf"],
  "status": "active",
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

**Note:**
- `account_number`: Auto-generated 16-digit number
- `cvv`: Auto-generated 3-digit CVV (hidden in API responses)
- `pin`: 4-digit PIN (default: `0000`, hidden in API responses)
  - Default PIN `0000` can only be used to change PIN, not for payments
  - Customer must change PIN before making any payments

### Business
```json
{
  "id": 1,
  "business_name": "Foodstuff Store",
  "email": "business@example.com",
  "username": "business123",
  "phone": "08012345678",
  "address": "Lagos, Nigeria",
  "approval_status": "approved",
  "status": "active",
  "api_token": "nodo_biz_abc123...",
  "webhook_url": "https://example.com/webhook",
  "kyc_documents": ["kyc_documents/business_1/doc1.pdf"],
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T00:00:00.000000Z"
}
```

### Invoice
```json
{
  "id": 1,
  "invoice_id": "INV-2024-001",
  "slug": "inv-abc123xyz",
  "is_used": false,
  "customer_id": 1,
  "supplier_id": 1,
  "amount": "50000.00",
  "purchase_date": "2024-01-15",
  "due_date": "2024-02-15",
  "grace_period_end_date": "2024-03-16",
  "status": "in_grace",
  "principal_amount": "50000.00",
  "interest_amount": "0.00",
  "total_amount": "50000.00",
  "paid_amount": "0.00",
  "remaining_balance": "50000.00",
  "created_at": "2024-01-15T00:00:00.000000Z",
  "updated_at": "2024-01-15T00:00:00.000000Z"
}
```

**Note:**
- `slug`: Unique identifier for invoice checkout link (auto-generated when link is created)
- `is_used`: Boolean indicating if the invoice link has been used for payment (one-time use)

### Transaction
```json
{
  "id": 1,
  "transaction_reference": "TXN-2024-001",
  "customer_id": 1,
  "business_id": 1,
  "invoice_id": 1,
  "type": "purchase",
  "amount": "50000.00",
  "status": "completed",
  "description": "Purchase from Foodstuff Store",
  "metadata": {
    "items": [
      {
        "name": "Rice 50kg",
        "quantity": 10,
        "price": "5000.00",
        "description": "Premium rice"
      }
    ]
  },
  "created_at": "2024-01-15T00:00:00.000000Z",
  "updated_at": "2024-01-15T00:00:00.000000Z"
}
```

---

## Error Handling

All errors follow this format:

```json
{
  "message": "Error message here",
  "errors": {
    "field_name": ["Error message for this field"]
  }
}
```

### Common HTTP Status Codes

- `200 OK` - Request successful
- `201 Created` - Resource created successfully
- `400 Bad Request` - Invalid request data
- `401 Unauthorized` - Authentication required or invalid
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `422 Unprocessable Entity` - Validation errors
- `500 Internal Server Error` - Server error

### Example Error Response

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email has already been taken."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

---

## Notes

1. **Account Numbers:** Customer `account_number` is auto-generated as a 16-digit number when a customer is created.

2. **CVV and PIN:**
   - `cvv`: Auto-generated 3-digit CVV for each customer (required for all payments)
   - `pin`: 4-digit PIN (default: `0000`)
   - Default PIN `0000` can **only** be used to change the PIN, **not** for payments
   - Customers must change their PIN before making any payments
   - Both CVV and PIN are required for all payment transactions:
     - Third-party site payments (via Pay with Nodopay API)
     - Invoice link payments (via generated checkout links)

3. **Invoice Links:**
   - Businesses can generate unique invoice links for each invoice
   - Link format: `{FRONTEND_URL}/checkout/{slug}`
   - Links are **one-time use only** - once used for payment, they cannot be reused
   - Set `FRONTEND_URL` environment variable to configure the frontend URL
   - Customers can pay invoices via these links using their account number and PIN

2. **Virtual Accounts:** `virtual_account_number` and `virtual_account_bank` are optional during customer creation. They will be auto-filled from a third-party API integration in the future.

3. **Credit Limit Calculation:** Credit limit is automatically calculated as: `minimum_purchase_amount × (payment_plan_duration + 1)`

4. **Interest Calculation:** 
   - 30-day grace period after due date
   - 3.5% monthly interest after grace period
   - Formula: `Interest = Outstanding Amount × 3.5% × # months overdue after grace`

5. **Business Approval:** New businesses require admin approval before they can use the API. Once approved, they receive an API token.

6. **KYC Documents:** KYC documents are uploaded to S3 asynchronously via queue jobs to avoid API delays.

7. **Caching:** Frequently accessed data (customer credit, invoices, admin stats) is cached for performance.

8. **Webhooks:** Businesses can configure webhook URLs to receive notifications for payment events.

---

**API Base URL:** `https://nodopay-api-0fbd4546e629.herokuapp.com/api`
