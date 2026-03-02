# Admin API - Create Customer Documentation

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

## 📝 Create Customer

**Endpoint:** `POST /api/admin/customers`

**Description:** Allows admin to create a new customer account. Admin-created customers are automatically approved and activated. Virtual accounts are created automatically via Paystack integration.

### Request

**Headers:**
```
Authorization: Bearer your_admin_token
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "business_name": "ABC Company",
  "email": "customer@example.com",
  "username": "customer123",
  "password": "SecurePassword123!",
  "phone": "08012345678",
  "address": "Lagos, Nigeria",
  "minimum_purchase_amount": 50000,
  "payment_plan_duration": 20,
  "payment_plan_duration_unit": "days",
  "virtual_account_number": null,
  "virtual_account_bank": null,
  "kyc_documents": []
}
```

**cURL Example:**
```bash
curl -X POST "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "username": "customer123",
    "password": "SecurePassword123!",
    "phone": "08012345678",
    "address": "Lagos, Nigeria",
    "minimum_purchase_amount": 50000,
    "payment_plan_duration": 20,
    "payment_plan_duration_unit": "days"
  }'
```

### Request Fields

#### Required Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `business_name` | string (max 255) | Customer's business name | `"ABC Company"` |
| `email` | email (unique) | Customer's email address | `"customer@example.com"` |
| `username` | string (unique) | Customer's username | `"customer123"` |
| `password` | string (min 8) | Customer's password | `"SecurePassword123!"` |
| `minimum_purchase_amount` | numeric (min 0) | Minimum purchase amount for credit limit calculation | `50000` |
| `payment_plan_duration` | integer (min 1) | Payment plan duration **in days** (default) or months | `20` |

#### Optional Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `phone` | string | Customer's phone number | `"08012345678"` |
| `address` | string | Customer's physical address | `"Lagos, Nigeria"` |
| `payment_plan_duration_unit` | enum (`days`, `months`) | Unit for `payment_plan_duration`. **Defaults to `days`** | `"days"` |
| `virtual_account_number` | string (unique) | Virtual account number (if manually provided). If not provided, will be auto-generated via Paystack | `null` |
| `virtual_account_bank` | string | Bank name for virtual account (if manually provided) | `null` |
| `kyc_documents` | array of files | KYC documents (pdf, jpg, jpeg, png, max 10MB each) | `[]` |

### ⚠️ Important Notes on Payment Plan Duration

1. **Default Unit is Days**: The `payment_plan_duration` field accepts **days** by default. If you don't specify `payment_plan_duration_unit`, it will be treated as days.

2. **Automatic Conversion**: Days are automatically converted to months for storage and calculations:
   - **Conversion Formula**: `months = days / 30` (rounded to 2 decimal places)
   - **Example**: 20 days = 0.67 months, 60 days = 2.00 months, 90 days = 3.00 months

3. **Using Months**: If you want to specify in months instead, set `payment_plan_duration_unit` to `"months"`:
   ```json
   {
     "payment_plan_duration": 6,
     "payment_plan_duration_unit": "months"
   }
   ```

4. **Storage**: The value is stored as **months** in the database (converted from days if needed).

5. **Credit Limit Calculation**: Credit limit is calculated as:
   ```
   credit_limit = minimum_purchase_amount × (payment_plan_duration_in_months + 1)
   ```
   Example: If `minimum_purchase_amount` = 50,000 and `payment_plan_duration` = 20 days (0.67 months):
   - Credit limit = 50,000 × (0.67 + 1) = 50,000 × 1.67 = **83,500**

### Response

#### Success Response (201 Created)

```json
{
  "message": "Customer created successfully",
  "customer": {
    "id": 1,
    "account_number": "1234567890123456",
    "business_name": "ABC Company",
    "email": "customer@example.com",
    "username": "customer123",
    "credit_limit": "83500.00"
  }
}
```

**Response Fields:**
- `id`: Internal customer ID
- `account_number`: Auto-generated 16-digit account number (unique identifier)
- `business_name`: Customer's business name
- `email`: Customer's email address
- `username`: Customer's username
- `credit_limit`: Calculated credit limit based on `minimum_purchase_amount` and `payment_plan_duration`

#### Error Responses

**Validation Error (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "Validation failed. Email: The email has already been taken. Username: The username has already been taken.",
  "errors": {
    "email": ["The email has already been taken."],
    "username": ["The username has already been taken."]
  }
}
```

**Unauthorized (401 Unauthorized):**
```json
{
  "message": "Unauthenticated"
}
```

**Missing Required Field (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "Validation failed. Business name: The business name field is required.",
  "errors": {
    "business_name": ["The business name field is required."]
  }
}
```

### 🎯 Key Features

1. **Auto-Approval**: Admin-created customers are automatically:
   - `approval_status` = `"approved"`
   - `status` = `"active"`
   - Customer can login immediately (no approval needed)

2. **Automatic Virtual Account Creation**:
   - If `virtual_account_number` is not provided, the system automatically creates a Paystack virtual account
   - Virtual account creation runs **asynchronously** in the background (doesn't block customer creation)
   - Virtual account details will be available within a few moments after creation
   - If Paystack is not configured, virtual account creation is skipped (logged but doesn't fail customer creation)

3. **Credit Limit Calculation**:
   - Automatically calculated based on `minimum_purchase_amount` and `payment_plan_duration`
   - Formula: `credit_limit = minimum_purchase_amount × (payment_plan_duration_in_months + 1)`
   - Updated automatically if `minimum_purchase_amount` or `payment_plan_duration` changes

4. **KYC Documents**:
   - Upload multiple KYC documents (pdf, jpg, jpeg, png)
   - Each file max 10MB
   - Files are uploaded to S3 asynchronously
   - Documents are stored in `kyc_documents/customer_{id}/` path

5. **Email Notifications**:
   - Customer receives welcome email with password
   - Accounting team receives notification about new customer

### 📊 Examples

#### Example 1: Create Customer with 20 Days Payment Plan

```bash
curl -X POST "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "Tech Solutions Ltd",
    "email": "tech@example.com",
    "username": "techsolutions",
    "password": "SecurePass123!",
    "phone": "08012345678",
    "minimum_purchase_amount": 100000,
    "payment_plan_duration": 20
  }'
```

**Result:**
- `payment_plan_duration` = 20 days
- Converted to months: 20 / 30 = 0.67 months
- Credit limit = 100,000 × (0.67 + 1) = **167,000**

#### Example 2: Create Customer with 6 Months Payment Plan

```bash
curl -X POST "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "Food Distributors",
    "email": "food@example.com",
    "username": "fooddist",
    "password": "SecurePass123!",
    "minimum_purchase_amount": 50000,
    "payment_plan_duration": 6,
    "payment_plan_duration_unit": "months"
  }'
```

**Result:**
- `payment_plan_duration` = 6 months (no conversion needed)
- Credit limit = 50,000 × (6 + 1) = **350,000**

#### Example 3: Create Customer with Manual Virtual Account

```bash
curl -X POST "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Content-Type: application/json" \
  -d '{
    "business_name": "Retail Store",
    "email": "retail@example.com",
    "username": "retailstore",
    "password": "SecurePass123!",
    "minimum_purchase_amount": 75000,
    "payment_plan_duration": 60,
    "virtual_account_number": "9876543210",
    "virtual_account_bank": "Sterling Bank"
  }'
```

**Result:**
- Uses manually provided virtual account
- Paystack virtual account creation is skipped
- `payment_plan_duration` = 60 days = 2.00 months
- Credit limit = 75,000 × (2 + 1) = **225,000**

### 🔄 Update Customer

To update a customer (including `payment_plan_duration`), use:
**Endpoint:** `PUT /api/admin/customers/{id}`

The same rules apply:
- `payment_plan_duration` accepts **days** by default
- `payment_plan_duration_unit` can be set to `"months"` if needed
- Days are automatically converted to months for storage

**Example:**
```bash
curl -X PUT "https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/customers/1" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Content-Type: application/json" \
  -d '{
    "payment_plan_duration": 30,
    "payment_plan_duration_unit": "days"
  }'
```

### 📋 Related Endpoints

- **Get Customer**: `GET /api/admin/customers/{id}`
- **Get All Customers**: `GET /api/admin/customers`
- **Update Customer**: `PUT /api/admin/customers/{id}`
- **Update Credit Limit**: `PATCH /api/admin/customers/{id}/credit-limit`
- **Update Customer Status**: `PATCH /api/admin/customers/{id}/status`
- **Update Customer Owed Amount**: `PATCH /api/admin/customers/{id}/owed-amount`

---

## 🚨 Error Handling

### Common Errors

1. **Email Already Exists**:
   ```json
   {
     "success": false,
     "message": "Validation failed. Email: The email has already been taken.",
     "errors": {
       "email": ["The email has already been taken."]
     }
   }
   ```

2. **Username Already Exists**:
   ```json
   {
     "success": false,
     "message": "Validation failed. Username: The username has already been taken.",
     "errors": {
       "username": ["The username has already been taken."]
     }
   }
   ```

3. **Invalid Payment Plan Duration**:
   ```json
   {
     "success": false,
     "message": "Validation failed. Payment plan duration: The payment plan duration must be at least 1.",
     "errors": {
       "payment_plan_duration": ["The payment plan duration must be at least 1."]
     }
   }
   ```

4. **Invalid Payment Plan Duration Unit**:
   ```json
   {
     "success": false,
     "message": "Validation failed. Payment plan duration unit: The selected payment plan duration unit is invalid.",
     "errors": {
       "payment_plan_duration_unit": ["The selected payment plan duration unit is invalid."]
     }
   }
   ```

---

## 📝 Summary

- **Payment Plan Duration**: Accepts **days** by default (or months if `payment_plan_duration_unit` = `"months"`)
- **Conversion**: Days are automatically converted to months (30 days = 1 month) for storage
- **Credit Limit**: Calculated as `minimum_purchase_amount × (payment_plan_duration_in_months + 1)`
- **Auto-Approval**: Admin-created customers are automatically approved and activated
- **Virtual Account**: Automatically created via Paystack (if not manually provided)
- **Storage**: Payment plan duration is stored as **months** in the database

---

**Base URL:** `https://nodopay-api-0fbd4546e629.herokuapp.com/api`
