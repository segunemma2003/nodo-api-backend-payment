# Business Panel API - Complete Documentation

## Base URL
```
https://nodopay-api-0fbd4546e629.herokuapp.com/api
```

---

## 🔐 Authentication

### Step 1: Login to Get API Token

**Endpoint:** `POST /api/auth/business/login`

**Request:**
```bash
curl -X POST https://nodopay-api-0fbd4546e629.herokuapp.com/api/auth/business/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "your-business@example.com",
    "password": "your-password"
  }'
```

**Response (200 OK):**
```json
{
  "business": {
    "id": 1,
    "business_name": "Foodstuff Store",
    "email": "your-business@example.com",
    "api_token": "nodo_biz_abc123xyz456...",
    "status": "active",
    "approval_status": "approved"
  },
  "message": "Use the api_token field for API authentication (Bearer token or X-API-Token header)"
}
```

**⚠️ IMPORTANT:** 
- Copy the `api_token` value from the response
- **DO NOT** use any other token - only use the `api_token` field
- The `api_token` starts with `nodo_biz_` prefix

### Step 2: Use API Token for Authentication

You can authenticate in **two ways**:

#### Option 1: Bearer Token (Recommended)
```bash
curl -X GET https://nodopay-api-0fbd4546e629.herokuapp.com/api/business/dashboard \
  -H "Authorization: Bearer nodo_biz_abc123xyz456..."
```

#### Option 2: X-API-Token Header
```bash
curl -X GET https://nodopay-api-0fbd4546e629.herokuapp.com/api/business/dashboard \
  -H "X-API-Token: nodo_biz_abc123xyz456..."
```

---

## 🚨 Troubleshooting 401 Errors

### Common Causes & Solutions:

#### 1. **Business Not Approved**
**Error:** `403 Forbidden` with message "Business account is not active"

**Solution:**
- Contact admin to approve your business account
- Your business must have:
  - `status = 'active'`
  - `approval_status = 'approved'`

**Check your status:**
```bash
# Login first to see your status
POST /api/auth/business/login
# Response will show: "status" and "approval_status"
```

#### 2. **Wrong Token Format**
**Error:** `401 Unauthorized` - "Invalid authentication token"

**Solution:**
- ✅ Use the `api_token` from login response (starts with `nodo_biz_`)
- ❌ Don't use session tokens or random strings
- ❌ Don't use tokens from other APIs

**Correct format:**
```
Bearer nodo_biz_abc123xyz456def789...
```

#### 3. **Token Not in Database**
**Error:** `401 Unauthorized` - "Invalid authentication token"

**Solution:**
- Login again to generate a new API token
- The token should be automatically generated on login
- If missing, login will create one automatically

#### 4. **Missing Authorization Header**
**Error:** `401 Unauthorized` - "Authentication token is required"

**Solution:**
- Make sure you include the header:
  - `Authorization: Bearer YOUR_TOKEN` OR
  - `X-API-Token: YOUR_TOKEN`
- Check for typos in header name (case-sensitive)

#### 5. **Business Status Not Active**
**Error:** `403 Forbidden`

**Check status:**
```bash
# Login and check the response
POST /api/auth/business/login
```

**If status is not 'active':**
- New businesses are created with `status: 'inactive'`
- Admin must approve the business first
- Once approved, status changes to `'active'` and `api_token` is generated

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
    "api_token": "nodo_biz_abc123...",
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
  "api_token": "nodo_biz_new_token_xyz789..."
}
```

**⚠️ Note:** This generates a NEW token. Your old token will stop working. Update your applications immediately.

---

### 6. Submit Invoice for Financing
**POST** `/api/business/submit-invoice`

**Headers:**
```
Authorization: Bearer YOUR_API_TOKEN
Content-Type: application/json
```

**Request Body:**
```json
{
  "customer_account_number": "1234567890123456",
  "amount": "50000.00",
  "purchase_date": "2024-01-15",
  "due_date": "2024-02-15",
  "description": "Purchase of goods",
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

**Optional Fields:**
- `purchase_date`: Date of purchase (YYYY-MM-DD)
- `due_date`: Due date for payment (YYYY-MM-DD)
- `description`: Description of the invoice
- `items`: Array of purchased items

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

**Error Response (400 Bad Request):**
```json
{
  "success": false,
  "message": "Customer does not have sufficient credit",
  "available_credit": "25000.00"
}
```

---

### 7. Check Customer Credit
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

---

### 8. Get All Transactions
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
        "invoice_id": "INV-2024-001"
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

### 9. Request Withdrawal
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

### 10. Get All Withdrawals
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

### 11. Generate Invoice Link
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

---

## 🔍 Quick Diagnostic Checklist

If you're getting 401 errors, check these in order:

- [ ] **Step 1:** Did you login first? `POST /api/auth/business/login`
- [ ] **Step 2:** Did you copy the `api_token` from the login response?
- [ ] **Step 3:** Is your business status `'active'`? (Check login response)
- [ ] **Step 4:** Is your approval_status `'approved'`? (Check login response)
- [ ] **Step 5:** Are you using `Authorization: Bearer YOUR_TOKEN` header correctly?
- [ ] **Step 6:** Is there a space after "Bearer"? Format: `Bearer nodo_biz_...`
- [ ] **Step 7:** Is your token the full value starting with `nodo_biz_`?
- [ ] **Step 8:** Did you log in recently? (Token should exist in database)

---

## 📝 Example cURL Requests

### Complete Authentication Flow

```bash
# 1. Login
curl -X POST https://nodopay-api-0fbd4546e629.herokuapp.com/api/auth/business/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "business@example.com",
    "password": "password123"
  }'

# Response will include api_token - copy it!

# 2. Use the api_token for authenticated requests
export API_TOKEN="nodo_biz_abc123xyz456..."

# 3. Get Dashboard
curl -X GET https://nodopay-api-0fbd4546e629.herokuapp.com/api/business/dashboard \
  -H "Authorization: Bearer $API_TOKEN"

# 4. Get Invoices
curl -X GET https://nodopay-api-0fbd4546e629.herokuapp.com/api/business/invoices \
  -H "Authorization: Bearer $API_TOKEN"

# 5. Get Profile
curl -X GET https://nodopay-api-0fbd4546e629.herokuapp.com/api/business/profile \
  -H "Authorization: Bearer $API_TOKEN"
```

---

## ⚠️ Important Notes

1. **Token Format:** API tokens start with `nodo_biz_` prefix
2. **Token Lifetime:** Tokens don't expire, but can be regenerated
3. **Business Status:** Must be `'active'` to access API endpoints
4. **Approval Status:** Must be `'approved'` to submit invoices and request withdrawals
5. **Token Security:** Keep your API token secure and never expose it in client-side code
6. **Header Format:** Use `Authorization: Bearer TOKEN` (with space after Bearer)

---

## 🆘 Still Getting 401?

1. **Verify Business Status:**
   ```bash
   POST /api/auth/business/login
   # Check "status" and "approval_status" in response
   ```

2. **Generate New Token:**
   ```bash
   # If you're authenticated, generate a new token
   POST /api/business/generate-api-token
   Authorization: Bearer YOUR_CURRENT_TOKEN
   ```

3. **Contact Admin:**
   - If `status != 'active'` or `approval_status != 'approved'`
   - Your business needs admin approval first

4. **Check Token Format:**
   - Should start with `nodo_biz_`
   - Should be at least 64 characters long
   - No spaces or special characters (except underscore)

---

**Base URL:** `https://nodopay-api-0fbd4546e629.herokuapp.com/api`

