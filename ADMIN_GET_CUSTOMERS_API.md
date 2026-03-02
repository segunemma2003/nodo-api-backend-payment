# Admin API - Get Customers Documentation

## Base URL
```
https://nodopay-api-0fbd4546e629.herokuapp.com/api
```

---

## 🔐 Authentication

All admin endpoints require authentication via Bearer token in the Authorization header:
```
Authorization: Bearer your_admin_token
```

---

## 📋 Get All Customers

**Endpoint:** `GET /api/admin/customers`

**Description:** Retrieves a paginated list of all customers. Balances are automatically updated before returning to ensure accuracy.

### Request

**Headers:**
```
Authorization: Bearer your_admin_token
Accept: application/json
```

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `page` | integer | No | `1` | Page number for pagination |
| `per_page` | integer | No | `20` | Number of items per page |

**cURL Example:**
```bash
curl -X GET "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers?page=1&per_page=20" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Accept: application/json"
```

### Response

#### Success Response (200 OK)

```json
{
  "data": [
    {
      "id": 1,
      "account_number": "1234567890123456",
      "business_name": "ABC Company",
      "email": "customer@example.com",
      "username": "customer123",
      "phone": "08012345678",
      "address": "Lagos, Nigeria",
      "minimum_purchase_amount": "50000.00",
      "payment_plan_duration": "6.00",
      "credit_limit": "350000.00",
      "current_balance": "50000.00",
      "available_balance": "300000.00",
      "virtual_account_number": "1234567890",
      "virtual_account_bank": "Sterling Bank",
      "status": "active",
      "approval_status": "approved",
      "invoices_count": 5,
      "created_at": "2024-01-15T10:00:00.000000Z",
      "updated_at": "2024-01-20T14:30:00.000000Z"
    },
    {
      "id": 2,
      "account_number": "9876543210987654",
      "business_name": "XYZ Enterprises",
      "email": "xyz@example.com",
      "username": "xyzent",
      "phone": "08098765432",
      "address": "Abuja, Nigeria",
      "minimum_purchase_amount": "100000.00",
      "payment_plan_duration": "3.00",
      "credit_limit": "400000.00",
      "current_balance": "150000.00",
      "available_balance": "250000.00",
      "virtual_account_number": "9876543210",
      "virtual_account_bank": "GTBank",
      "status": "active",
      "approval_status": "approved",
      "invoices_count": 8,
      "created_at": "2024-01-10T08:00:00.000000Z",
      "updated_at": "2024-01-18T12:15:00.000000Z"
    }
  ],
  "current_page": 1,
  "per_page": 20,
  "total": 2,
  "last_page": 1,
  "from": 1,
  "to": 2
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Internal customer ID |
| `account_number` | string | 16-digit unique account number |
| `business_name` | string | Customer's business name |
| `email` | string | Customer's email address |
| `username` | string | Customer's username |
| `phone` | string | Customer's phone number (nullable) |
| `address` | string | Customer's physical address (nullable) |
| `minimum_purchase_amount` | decimal | Minimum purchase amount for credit limit calculation |
| `payment_plan_duration` | decimal | Payment plan duration in months (stored value) |
| `credit_limit` | decimal | Total credit limit available |
| `current_balance` | decimal | **Amount owed** (current outstanding balance) |
| `available_balance` | decimal | **Available credit** (credit_limit - current_balance) |
| `virtual_account_number` | string | Virtual account number for repayments (nullable) |
| `virtual_account_bank` | string | Bank name for virtual account (nullable) |
| `status` | enum | Account status: `active`, `suspended`, `inactive` |
| `approval_status` | enum | Approval status: `approved`, `pending`, `rejected` |
| `invoices_count` | integer | Total number of invoices for this customer |
| `created_at` | datetime | Account creation timestamp |
| `updated_at` | datetime | Last update timestamp |

### ⚠️ Important Notes

1. **Automatic Balance Updates**: Balances (`current_balance` and `available_balance`) are **automatically recalculated** before returning to ensure accuracy. This means:
   - `current_balance` = sum of all unpaid invoices + credit not repaid
   - `available_balance` = `credit_limit` - `current_balance`
   - Values are always up-to-date

2. **Amount Owed**: The `current_balance` field represents the **amount owed** by the customer.

3. **Pagination**: Results are paginated with 20 items per page by default. Use `page` and `per_page` query parameters to navigate.

4. **Caching**: Results are cached for 5 minutes (300 seconds) for performance. Balance updates happen after cache retrieval to ensure accuracy.

5. **Ordering**: Customers are ordered by `created_at` in descending order (newest first).

### Error Responses

**Unauthorized (401 Unauthorized):**
```json
{
  "message": "Unauthenticated"
}
```

**Invalid Page Number (422 Unprocessable Entity):**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "page": ["The page must be at least 1."]
  }
}
```

---

## 📋 Get Single Customer Details

**Endpoint:** `GET /api/admin/customers/{id}`

**Description:** Retrieves detailed information about a specific customer, including all invoices and payments. Balance is automatically updated before returning.

### Request

**Headers:**
```
Authorization: Bearer your_admin_token
Accept: application/json
```

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Customer ID |

**cURL Example:**
```bash
curl -X GET "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers/1" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Accept: application/json"
```

### Response

#### Success Response (200 OK)

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
    "payment_plan_duration": "6.00",
    "credit_limit": "350000.00",
    "current_balance": "50000.00",
    "available_balance": "300000.00",
    "virtual_account_number": "1234567890",
    "virtual_account_bank": "Sterling Bank",
    "paystack_customer_code": "CUS_xxxxx",
    "paystack_dedicated_account_id": "1234567890",
    "kyc_documents": [
      "kyc_documents/customer_1/doc1.pdf",
      "kyc_documents/customer_1/doc2.jpg"
    ],
    "status": "active",
    "approval_status": "approved",
    "created_at": "2024-01-15T10:00:00.000000Z",
    "updated_at": "2024-01-20T14:30:00.000000Z",
    "invoices": [
      {
        "id": 1,
        "invoice_id": "INV-2024-001",
        "customer_id": 1,
        "supplier_id": 1,
        "supplier_name": "Foodstuff Store",
        "principal_amount": "50000.00",
        "interest_amount": "0.00",
        "total_amount": "50000.00",
        "paid_amount": "0.00",
        "remaining_balance": "50000.00",
        "purchase_date": "2024-01-15",
        "due_date": "2024-02-15",
        "grace_period_end_date": "2024-03-16",
        "payment_plan_duration": 6,
        "status": "in_grace",
        "notes": null,
        "created_at": "2024-01-15T10:00:00.000000Z",
        "updated_at": "2024-01-15T10:00:00.000000Z"
      }
    ],
    "payments": [
      {
        "id": 1,
        "customer_id": 1,
        "invoice_id": 1,
        "amount": "25000.00",
        "payment_method": "bank_transfer",
        "payment_reference": "TXN-2024-001",
        "status": "completed",
        "created_at": "2024-01-20T14:30:00.000000Z",
        "updated_at": "2024-01-20T14:30:00.000000Z"
      }
    ]
  }
}
```

### Response Fields

**Customer Object Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Internal customer ID |
| `account_number` | string | 16-digit unique account number |
| `business_name` | string | Customer's business name |
| `email` | string | Customer's email address |
| `username` | string | Customer's username |
| `phone` | string | Customer's phone number (nullable) |
| `address` | string | Customer's physical address (nullable) |
| `minimum_purchase_amount` | decimal | Minimum purchase amount |
| `payment_plan_duration` | decimal | Payment plan duration in months |
| `credit_limit` | decimal | Total credit limit |
| `current_balance` | decimal | **Amount owed** (current outstanding balance) |
| `available_balance` | decimal | **Available credit** (credit_limit - current_balance) |
| `virtual_account_number` | string | Virtual account number (nullable) |
| `virtual_account_bank` | string | Bank name for virtual account (nullable) |
| `paystack_customer_code` | string | Paystack customer code (nullable) |
| `paystack_dedicated_account_id` | string | Paystack dedicated account ID (nullable) |
| `kyc_documents` | array | Array of KYC document paths (nullable) |
| `status` | enum | Account status: `active`, `suspended`, `inactive` |
| `approval_status` | enum | Approval status: `approved`, `pending`, `rejected` |
| `invoices` | array | Array of all invoices for this customer |
| `payments` | array | Array of all payments made by this customer |
| `created_at` | datetime | Account creation timestamp |
| `updated_at` | datetime | Last update timestamp |

**Invoice Object Fields (within invoices array):**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Invoice ID |
| `invoice_id` | string | Invoice identifier (e.g., "INV-2024-001") |
| `customer_id` | integer | Customer ID |
| `supplier_id` | integer | Supplier/Business ID (nullable) |
| `supplier_name` | string | Supplier/Business name |
| `principal_amount` | decimal | Original invoice amount |
| `interest_amount` | decimal | Accrued interest |
| `total_amount` | decimal | Total amount (principal + interest) |
| `paid_amount` | decimal | Amount already paid |
| `remaining_balance` | decimal | Amount still owed |
| `purchase_date` | date | Date of purchase |
| `due_date` | date | Payment due date |
| `grace_period_end_date` | date | End of grace period |
| `payment_plan_duration` | integer | Payment plan duration in months |
| `status` | enum | Invoice status: `pending`, `in_grace`, `overdue`, `paid` |
| `notes` | string | Additional notes (nullable) |

**Payment Object Fields (within payments array):**

| Field | Type | Description |
|-------|------|-------------|
| `id` | integer | Payment ID |
| `customer_id` | integer | Customer ID |
| `invoice_id` | integer | Invoice ID |
| `amount` | decimal | Payment amount |
| `payment_method` | string | Payment method (e.g., "bank_transfer") |
| `payment_reference` | string | Payment reference number |
| `status` | enum | Payment status: `pending`, `completed`, `failed` |

### ⚠️ Important Notes

1. **Automatic Balance Update**: The customer's balance is **automatically recalculated** before returning:
   - `current_balance` = sum of all unpaid invoices + credit not repaid
   - `available_balance` = `credit_limit` - `current_balance`
   - Balance is always up-to-date

2. **Amount Owed**: The `current_balance` field represents the **amount owed** by the customer.

3. **Related Data**: The response includes:
   - All invoices associated with the customer
   - All payments made by the customer
   - Full customer profile information

4. **Cache Invalidation**: The customer cache is cleared after balance update to ensure subsequent requests get fresh data.

### Error Responses

**Customer Not Found (404 Not Found):**
```json
{
  "message": "No query results for model [App\\Models\\Customer] 999"
}
```

**Unauthorized (401 Unauthorized):**
```json
{
  "message": "Unauthenticated"
}
```

---

## 📊 Complete Example Flow

### Example 1: Get All Customers (First Page)

```bash
curl -X GET "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers?page=1&per_page=20" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Accept: application/json"
```

### Example 2: Get All Customers (Second Page)

```bash
curl -X GET "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers?page=2&per_page=20" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Accept: application/json"
```

### Example 3: Get Single Customer Details

```bash
curl -X GET "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers/1" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Accept: application/json"
```

### Example 4: Get Customer with Custom Page Size

```bash
curl -X GET "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers?page=1&per_page=50" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Accept: application/json"
```

---

## 🔍 Understanding Balance Fields

### Current Balance (Amount Owed)

The `current_balance` represents the **total amount owed** by the customer. It includes:
- Sum of all unpaid invoices (`remaining_balance`)
- Credit not yet repaid from paid invoices

**Formula:**
```
current_balance = unpaid_invoices_total + credit_not_repaid
```

### Available Balance

The `available_balance` represents the **remaining credit available** for the customer to use.

**Formula:**
```
available_balance = credit_limit - current_balance
```

**Example:**
- Credit Limit: ₦350,000
- Current Balance (Amount Owed): ₦50,000
- Available Balance: ₦350,000 - ₦50,000 = **₦300,000**

### Credit Limit

The `credit_limit` is the **maximum credit** the customer can use. It's calculated as:
```
credit_limit = minimum_purchase_amount × (payment_plan_duration_in_months + 1)
```

---

## 📋 Related Endpoints

- **Create Customer**: `POST /api/admin/customers` (See `ADMIN_CREATE_CUSTOMER_API.md`)
- **Update Customer**: `PUT /api/admin/customers/{id}`
- **Update Credit Limit**: `PATCH /api/admin/customers/{id}/credit-limit`
- **Update Customer Status**: `PATCH /api/admin/customers/{id}/status`
- **Update Customer Owed Amount**: `PATCH /api/admin/customers/{id}/owed-amount` (See `ADMIN_UPDATE_CUSTOMER_OWED_AMOUNT_API.md`)
- **Add Credits to Customer**: `POST /api/admin/customers/{id}/add-credits`

---

## 🚨 Error Handling

### Common Errors

1. **Unauthorized Access**:
   ```json
   {
     "message": "Unauthenticated"
   }
   ```
   **Solution:** Include valid Bearer token in Authorization header

2. **Customer Not Found**:
   ```json
   {
     "message": "No query results for model [App\\Models\\Customer] 999"
   }
   ```
   **Solution:** Verify the customer ID exists

3. **Invalid Page Number**:
   ```json
   {
     "message": "The given data was invalid.",
     "errors": {
       "page": ["The page must be at least 1."]
     }
   }
   ```
   **Solution:** Use a valid page number (≥ 1)

---

## 📝 Summary

- **Get All Customers**: Returns paginated list with balances automatically updated
- **Get Single Customer**: Returns detailed customer info with invoices and payments, balance automatically updated
- **Balance Fields**: 
  - `current_balance` = **Amount owed**
  - `available_balance` = **Available credit**
  - `credit_limit` = **Total credit limit**
- **Automatic Updates**: Balances are recalculated before returning to ensure accuracy
- **Pagination**: Default 20 items per page, customizable via query parameters

---

**Base URL:** `https://nodopay-api-0fbd4546e629.herokuapp.com/api`
