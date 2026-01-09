# Business Panel API - Complete Documentation

## Base URL
```
https://nodopay-api-0fbd4546e629.herokuapp.com/api
```

---

## 🔐 Authentication

### Login to Get API Token

**Endpoint:** `POST /api/auth/business/login`

**Request Body:**
- `email` (required): Business email address
- `password` (required): Business password

**Response (200 OK):**
```json
{
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "your-business@example.com",
    "api_token": "fscredit_biz_abc123xyz456...",
    "status": "active",
    "approval_status": "approved"
  },
  "message": "Use the api_token field for API authentication (Bearer token or X-API-Token header)"
}
```

**⚠️ IMPORTANT:** 
- Copy the `api_token` value from the response
- **DO NOT** use any other token - only use the `api_token` field
- The `api_token` starts with `fscredit_biz_` prefix

### Authentication Methods

You can authenticate in **two ways**:

**Option 1: Bearer Token (Recommended)**
```
Authorization: Bearer fscredit_biz_abc123xyz456...
```

**Option 2: X-API-Token Header**
```
X-API-Token: fscredit_biz_abc123xyz456...
```

---

## 📋 All Business API Endpoints

All endpoints require authentication using the `api_token` from login.

### 1. Get Dashboard
**GET** `/api/business/dashboard`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

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
    "total_invoices": 25,
    "total_revenue": "500000.00",
    "total_withdrawn": "200000.00",
    "available_balance": "300000.00",
    "pending_invoices": 5,
    "paid_invoices": 20,
    "pending_withdrawals": 2
  }
}
```

---

### 2. Get All Invoices
**GET** `/api/business/invoices`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20)

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "invoice_id": "FSCREDIT-2024-001",
      "customer_id": 1,
      "supplier_id": 1,
      "principal_amount": "50000.00",
      "remaining_balance": "50000.00",
      "status": "in_grace",
      "purchase_date": "2024-01-15",
      "due_date": "2024-07-15",
      "payment_plan_duration": 6,
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

**Note:** Businesses only see `principal_amount` and `remaining_balance` (no interest). Interest is only visible to admin and customers.

---

### 3. Get Profile
**GET** `/api/business/profile`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**Response (200 OK):**
```json
{
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "business@example.com",
    "phone": "08012345678",
    "address": "Lagos, Nigeria",
    "approval_status": "approved",
    "api_token": "fscredit_biz_abc123...",
    "webhook_url": "https://example.com/webhook",
    "status": "active"
  }
}
```

---

### 4. Update Profile
**PUT** `/api/business/profile`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body (all fields optional):**
```json
{
  "business_name": "Updated Store Name",
  "email": "newemail@example.com",
  "username": "newusername",
  "password": "NewPassword123!",
  "phone": "08098765432",
  "address": "New Address",
  "webhook_url": "https://newwebhook.com/webhook"
}
```

**Note:** For `kyc_documents`, send as `multipart/form-data` with file uploads.

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
    "kyc_documents": ["kyc_documents/business_1/doc1.pdf"]
  }
}
```

---

### 5. Generate New API Token
**POST** `/api/business/generate-api-token`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**Response (200 OK):**
```json
{
  "message": "API token generated successfully",
  "api_token": "fscredit_biz_new_token_xyz789..."
}
```

**⚠️ Note:** This generates a NEW token. Your old token will stop working. Update your applications immediately.

---

## 👥 Business Customer Management

Business customers are customer records that businesses create and manage. These are separate from main customers (who have login accounts).

**Important Notes:**
- Business customers don't have login credentials (no account_number, CVV, PIN)
- Business customers are only visible to your business
- When a business customer pays an invoice, they are automatically linked to a main customer account

---

### 6. Get All Business Customers
**GET** `/api/business/customers`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**Query Parameters:**
- `status` (optional): Filter by status (active, inactive, suspended)
- `search` (optional): Search by business_name, contact_name, contact_phone, or contact_email
- `page` (optional): Page number for pagination
- `per_page` (optional): Items per page (default: 20)

**Response (200 OK):**
```json
{
  "success": true,
  "customers": {
    "data": [
      {
        "id": 1,
        "business_name": "ABC Company",
        "address": "123 Main Street, Lagos",
        "contact_name": "John Doe",
        "contact_phone": "08012345678",
        "contact_email": "john@abccompany.com",
        "status": "active",
        "linked_customer_id": null,
        "linked_at": null,
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:00:00.000000Z"
      }
    ],
    "current_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

---

### 7. Create Business Customer
**POST** `/api/business/customers`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body:**
```json
{
  "business_name": "ABC Company",
  "address": "123 Main Street, Lagos, Nigeria",
  "contact_name": "John Doe",
  "contact_phone": "08012345678",
  "contact_email": "john@abccompany.com"
}
```

**Required Fields:**
- `business_name` (string, max 255): Customer's business name (must be unique per business)

**Optional Fields:**
- `address` (string): Physical address
- `contact_name` (string, max 255): Contact person name
- `contact_phone` (string, max 20): Contact phone number
- `contact_email` (email, max 255): Contact email address

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Business customer created successfully",
  "customer": {
    "id": 1,
    "business_id": 1,
    "business_name": "ABC Company",
    "address": "123 Main Street, Lagos, Nigeria",
    "contact_name": "John Doe",
    "contact_phone": "08012345678",
    "contact_email": "john@abccompany.com",
    "status": "active",
    "linked_customer_id": null,
    "linked_at": null,
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

**Error Response (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "A customer with this business name already exists"
}
```

---

### 8. Get Business Customer Details
**GET** `/api/business/customers/{id}`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**Response (200 OK):**
```json
{
  "success": true,
  "customer": {
    "id": 1,
    "business_id": 1,
    "business_name": "ABC Company",
    "address": "123 Main Street, Lagos",
    "contact_name": "John Doe",
    "contact_phone": "08012345678",
    "contact_email": "john@abccompany.com",
    "status": "active",
    "linked_customer_id": 5,
    "linked_at": "2024-01-20T14:30:00.000000Z",
    "linked_customer": {
      "id": 5,
      "account_number": "1234567890123456",
      "business_name": "ABC Company",
      "email": "customer@example.com"
    },
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

**Note:** If the business customer is linked to a main customer (after payment), `linked_customer` object will be included in the response.

---

### 9. Update Business Customer
**PUT** `/api/business/customers/{id}`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body (All fields optional, only include fields to update):**
```json
{
  "business_name": "Updated Company Name",
  "address": "Updated Address",
  "contact_name": "Updated Contact",
  "contact_phone": "08098765432",
  "contact_email": "updated@example.com"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Business customer updated successfully",
  "customer": {
    "id": 1,
    "business_name": "Updated Company Name",
    "contact_phone": "08098765432",
    "status": "active",
    ...
  }
}
```

---

### 10. Delete Business Customer
**DELETE** `/api/business/customers/{id}`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Business customer deleted successfully"
}
```

**Error Response (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "Cannot delete customer with existing invoices"
}
```

**Note:** You cannot delete a business customer that has invoices. You can change their status to 'inactive' instead.

---

### 11. Submit Invoice for Financing
**POST** `/api/business/submit-invoice`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body:**
```json
{
  "business_customer_id": 1,
  "amount": "50000.00",
  "purchase_date": "2024-01-15",
  "description": "Purchase of goods",
  "items": [
    {
      "name": "Rice 50kg",
      "quantity": 10,
      "price": "5000.00",
      "description": "Premium rice",
      "uom": "bags"
    }
  ]
}
```

**Required Fields:**
- `business_customer_id` (integer): ID of your business customer
- `amount`: Invoice amount (numeric, min: 0.01)

**Optional Fields:**
- `purchase_date`: Date of purchase (YYYY-MM-DD)
- `description`: Description of the invoice
- `items`: Array of purchased items
  - `name` (required): Item name
  - `quantity` (required): Quantity purchased (integer, min: 1)
  - `price` (required): Unit price (numeric, min: 0.01)
  - `description` (optional): Item description
  - `uom` (optional): Unit of Measure (e.g., "kg", "pieces", "liters", "boxes", "bags")

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Invoice created successfully",
  "invoice": {
    "invoice_id": "FSCREDIT-2024-001",
    "slug": "inv-abc123xyz456",
    "amount": "50000.00",
    "due_date": "2024-07-15",
    "payment_plan_duration": 6,
    "status": "pending",
    "payment_link": "https://nodopay-api-0fbd4546e629.herokuapp.com/api/invoice/checkout/inv-abc123xyz456"
  },
  "business_customer": {
    "id": 1,
    "business_name": "ABC Company",
    "is_linked": false
  },
  "transaction": {
    "transaction_reference": "TXN-2024-001",
    "status": "completed"
  }
}
```

**Important Notes:**
- Invoice is created with status `'pending'` and does NOT affect customer balance yet
- A payment link (slug) is automatically generated
- Share the `payment_link` with your customer to collect payment
- If the business customer is linked to a main customer, credit will be checked
- Due date is calculated as purchase_date + payment_plan_duration months

**Error Response (400 Bad Request):**
```json
{
  "success": false,
  "message": "Business is not approved or inactive"
}
```

**Error Response (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "The selected business customer does not exist or does not belong to your business."
}
```

**Error Response (400 Bad Request - Insufficient Credit):**
```json
{
  "success": false,
  "message": "Customer does not have sufficient credit",
  "available_credit": "25000.00"
}
```

**Note:** This error only appears if the business customer is already linked to a main customer account.

---

### 12. Check Customer Credit
**POST** `/api/business/check-customer-credit`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body:**
```json
{
  "customer_account_number": "1234567890123456",
  "amount": "50000.00"
}
```

**Required Fields:**
- `customer_account_number`: 16-digit customer account number (main customer)
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

---

### 13. Get All Transactions
**GET** `/api/business/transactions`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**Query Parameters:**
- `page` (optional): Page number (default: 1)
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
      "type": "credit_purchase",
      "amount": "50000.00",
      "status": "completed",
      "customer": {
        "id": 1,
        "business_name": "ABC Company"
      },
      "invoice": {
        "id": 1,
        "invoice_id": "FSCREDIT-2024-001"
      },
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

---

### 14. Request Withdrawal
**POST** `/api/business/withdrawals/request`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body:**
```json
{
  "amount": "50000.00",
  "bank_name": "Sterling Bank",
  "account_number": "1234567890",
  "account_name": "Foodstuff Store",
  "notes": "Monthly withdrawal"
}
```

**Required Fields:**
- `amount`: Withdrawal amount (numeric, min: 0.01)
- `bank_name`: Bank name
- `account_number`: Bank account number
- `account_name`: Account holder name

**Optional Fields:**
- `notes`: Additional notes

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Withdrawal request submitted successfully",
  "withdrawal": {
    "id": 1,
    "withdrawal_reference": "WDR-2024-001",
    "amount": "50000.00",
    "status": "pending",
    "available_balance": "250000.00"
  }
}
```

**Error Response (400 Bad Request):**
```json
{
  "success": false,
  "message": "Insufficient balance for withdrawal",
  "available_balance": "30000.00",
  "requested_amount": "50000.00"
}
```

---

### 15. Get All Withdrawals
**GET** `/api/business/withdrawals`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**Query Parameters:**
- `page` (optional): Page number (default: 1)
- `per_page` (optional): Items per page (default: 20)

**Response (200 OK):**
```json
{
  "data": [
    {
      "id": 1,
      "withdrawal_reference": "WDR-2024-001",
      "amount": "50000.00",
      "status": "pending",
      "bank_name": "Sterling Bank",
      "account_number": "1234567890",
      "account_name": "Foodstuff Store",
      "created_at": "2024-01-15T10:00:00.000000Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 1
}
```

**Withdrawal Statuses:**
- `pending`: Awaiting admin approval
- `approved`: Approved by admin, awaiting processing
- `rejected`: Rejected by admin
- `processed`: Successfully processed

---

### 16. Generate Invoice Payment Link
**POST** `/api/business/invoices/{invoiceId}/generate-link`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**URL Parameters:**
- `invoiceId`: Invoice ID

**Response (200 OK):**
```json
{
  "message": "Invoice link generated successfully",
  "invoice_link": "https://fscredit.com/checkout/inv-abc123xyz",
  "slug": "inv-abc123xyz",
  "invoice": {
    "id": 67,
    "invoice_id": "FSCREDIT-694C576779853",
    "amount": "160000.00",
    "status": "pending",
    "is_used": false,
    "purchase_date": "2025-12-25",
    "due_date": "2026-01-25"
  },
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "foodstuff@example.com",
    "phone": "08012345678",
    "address": "123 Market Street, Lagos"
  },
  "customer": {
    "id": 1,
    "business_name": "ABC Company",
    "contact_name": "John Doe",
    "contact_email": "john@example.com",
    "contact_phone": "08098765432",
    "address": "456 Business Avenue",
    "has_fscredit_account": true
  }
}
```

**Note:** 
- Invoice links are one-time use only
- Link format: `{FRONTEND_URL}/checkout/{slug}`
- Once used for payment, the link cannot be reused

---

## 📦 Product Management

### 17. Get All Products
**GET** `/api/business/products`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**Query Parameters (Optional):**
- `is_active` (boolean): Filter by active status (true/false)
- `search` (string): Search by name, SKU, or description

**Response (200 OK):**
```json
{
  "success": true,
  "products": [
    {
      "id": 1,
      "business_id": 1,
      "name": "Premium Rice 50kg",
      "description": "High quality premium rice",
      "sku": "RICE-50KG-001",
      "price": "5000.00",
      "unit_of_measure": "bag",
      "is_active": true,
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-15T10:00:00.000000Z"
    },
    {
      "id": 2,
      "business_id": 1,
      "name": "Cooking Oil 5L",
      "description": "Premium cooking oil",
      "sku": "OIL-5L-001",
      "price": "3000.00",
      "unit_of_measure": "bottle",
      "is_active": true,
      "created_at": "2024-01-15T11:00:00.000000Z",
      "updated_at": "2024-01-15T11:00:00.000000Z"
    }
  ],
  "count": 2
}
```

---

### 18. Create Single Product
**POST** `/api/business/products`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body:**
```json
{
  "name": "Premium Rice 50kg",
  "description": "High quality premium rice",
  "sku": "RICE-50KG-001",
  "price": 5000.00,
  "unit_of_measure": "bag",
  "is_active": true
}
```

**Required Fields:**
- `name` (string, max 255): Product name
- `price` (numeric, min: 0.01): Product price

**Optional Fields:**
- `description` (string): Product description
- `sku` (string, max 255): Stock Keeping Unit (must be unique per business)
- `unit_of_measure` (string, max 50): Unit of measure (e.g., "bag", "kg", "pieces", "liters", "bottles")
- `is_active` (boolean): Whether the product is active (default: true)

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Product created successfully",
  "product": {
    "id": 1,
    "business_id": 1,
    "name": "Premium Rice 50kg",
    "description": "High quality premium rice",
    "sku": "RICE-50KG-001",
    "price": "5000.00",
    "unit_of_measure": "bag",
    "is_active": true,
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

**Error Response (422 Validation Error):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "sku": ["The sku has already been taken."]
  }
}
```

---

### 19. Create Products in Bulk
**POST** `/api/business/products/bulk`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body:**
```json
{
  "products": [
    {
      "name": "Premium Rice 50kg",
      "description": "High quality premium rice",
      "sku": "RICE-50KG-001",
      "price": 5000.00,
      "unit_of_measure": "bag",
      "is_active": true
    },
    {
      "name": "Cooking Oil 5L",
      "description": "Premium cooking oil",
      "sku": "OIL-5L-001",
      "price": 3000.00,
      "unit_of_measure": "bottle",
      "is_active": true
    },
    {
      "name": "Wheat Flour 25kg",
      "description": "Premium wheat flour",
      "sku": "FLOUR-25KG-001",
      "price": 4500.00,
      "unit_of_measure": "bag"
    }
  ]
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "2 products created successfully",
  "count": 2
}
```

**Error Response (422 Validation Error):**
```json
{
  "success": false,
  "message": "Validation errors found",
  "errors": [
    "Duplicate SKU 'RICE-50KG-001' found in batch (product index: 1)",
    "SKU 'OIL-5L-001' already exists (product index: 2)"
  ]
}
```

**⚠️ Important Notes:**
- Maximum 100 products per bulk request
- SKU must be unique within your business (cannot duplicate existing SKUs)
- SKU cannot be duplicated within the same batch
- All products are validated before any are created (transactional)
- If any product fails validation, no products are created

**Required Fields per Product:**
- `name` (string, max 255): Product name
- `price` (numeric, min: 0.01): Product price

**Optional Fields per Product:**
- `description` (string): Product description
- `sku` (string, max 255): Stock Keeping Unit (must be unique per business)
- `unit_of_measure` (string, max 50): Unit of measure
- `is_active` (boolean): Whether the product is active (default: true)

---

### 20. Get Single Product
**GET** `/api/business/products/{id}`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**URL Parameters:**
- `id`: Product ID

**Response (200 OK):**
```json
{
  "success": true,
  "product": {
    "id": 1,
    "business_id": 1,
    "name": "Premium Rice 50kg",
    "description": "High quality premium rice",
    "sku": "RICE-50KG-001",
    "price": "5000.00",
    "unit_of_measure": "bag",
    "is_active": true,
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T10:00:00.000000Z"
  }
}
```

---

### 21. Update Product
**PUT** `/api/business/products/{id}`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**URL Parameters:**
- `id`: Product ID

**Request Body (all fields optional, only include fields to update):**
```json
{
  "name": "Updated Product Name",
  "description": "Updated description",
  "sku": "NEW-SKU-001",
  "price": 5500.00,
  "unit_of_measure": "bag",
  "is_active": false
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Product updated successfully",
  "product": {
    "id": 1,
    "business_id": 1,
    "name": "Premium Rice 50kg",
    "description": "High quality premium rice",
    "sku": "RICE-50KG-001",
    "price": "5500.00",
    "unit_of_measure": "bag",
    "is_active": false,
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-15T12:00:00.000000Z"
  }
}
```

---

### 22. Delete Product
**DELETE** `/api/business/products/{id}`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
```

**URL Parameters:**
- `id`: Product ID

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Product deleted successfully"
}
```

**⚠️ Important Notes:**
- You can only manage products that belong to your business
- Deleting a product is permanent and cannot be undone
- Consider setting `is_active: false` instead of deleting if you want to keep historical data

---

## ⚠️ Important Notes

1. **Token Format:** API tokens start with `fscredit_biz_` prefix
2. **Token Lifetime:** Tokens don't expire, but can be regenerated
3. **Business Status:** Must be `'active'` to access API endpoints
4. **Approval Status:** Must be `'approved'` to submit invoices and request withdrawals
5. **Token Security:** Keep your API token secure and never expose it in client-side code
6. **Header Format:** Use `Authorization: Bearer TOKEN` (with space after Bearer)
7. **Invoice Interest:** Businesses only see principal amounts. Interest is only visible to admin and customers.
8. **Payment Plan Duration:** Invoices include `payment_plan_duration` in months, used to calculate due dates and interest.

---

**Base URL:** `https://nodopay-api-0fbd4546e629.herokuapp.com/api`




