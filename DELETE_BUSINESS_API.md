# Delete Business API Documentation

## Base URL
```
https://nodopay-api-0fbd4546e629.herokuapp.com/api
```

---

## Delete Business

**Endpoint:** `DELETE /api/admin/businesses/{id}`

**Method:** `DELETE`

**Authentication:** Admin access required

**Description:** Permanently deletes a business from the system. The business can only be deleted if it has no related records (invoices, business customers, transactions, withdrawals, or products).

---

### URL Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | The unique identifier of the business to delete |

---

### Request

**No request body required.**

**Example Request:**
```bash
curl -X DELETE https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/businesses/1 \
  -H "Content-Type: application/json"
```

---

### Success Response

**Status Code:** `200 OK`

**Response Body:**
```json
{
  "success": true,
  "message": "Business deleted successfully"
}
```

**Example:**
```bash
curl -X DELETE https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/businesses/1
```

**Response:**
```json
{
  "success": true,
  "message": "Business deleted successfully"
}
```

---

### Error Responses

#### 1. Business Not Found

**Status Code:** `404 Not Found`

**Response Body:**
```json
{
  "message": "No query results for model [App\\Models\\Business] {id}"
}
```

**Example:**
```bash
curl -X DELETE https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/businesses/999
```

---

#### 2. Business Has Related Records

**Status Code:** `422 Unprocessable Entity`

**Response Body:**
```json
{
  "success": false,
  "message": "Cannot delete business with existing related records",
  "details": {
    "has_invoices": true,
    "has_business_customers": false,
    "has_transactions": true,
    "has_withdrawals": false,
    "has_products": false
  }
}
```

**Description:** This error occurs when the business has one or more of the following:
- **has_invoices**: Business has created invoices
- **has_business_customers**: Business has business customers
- **has_transactions**: Business has transaction records
- **has_withdrawals**: Business has withdrawal requests
- **has_products**: Business has products

**Example:**
```bash
curl -X DELETE https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/businesses/1
```

**Response:**
```json
{
  "success": false,
  "message": "Cannot delete business with existing related records",
  "details": {
    "has_invoices": true,
    "has_business_customers": false,
    "has_transactions": true,
    "has_withdrawals": false,
    "has_products": false
  }
}
```

**Resolution:** Before deleting a business, you must:
1. Delete or reassign all related invoices
2. Delete or reassign all business customers
3. Handle all transactions (they cannot be deleted, but you may need to archive them)
4. Process or cancel all withdrawal requests
5. Delete all products

---

### Business Deletion Rules

A business **CANNOT** be deleted if it has:

1. **Invoices** - Any invoices created by the business
2. **Business Customers** - Any customers created by the business
3. **Transactions** - Any transaction records associated with the business
4. **Withdrawals** - Any withdrawal requests (pending, approved, or processed)
5. **Products** - Any products created by the business

A business **CAN** be deleted if:
- It has no related records in any of the above categories
- It's a newly created business with no activity

---

### Audit Logging

When a business is successfully deleted, the system logs:
- Business ID
- Business name
- Admin user ID (if available)
- Timestamp

**Log Entry Example:**
```
Business deleted by admin
{
  "business_id": 1,
  "business_name": "Foodstuff Store",
  "admin_id": 5
}
```

---

### Related Endpoints

- **Get Business:** `GET /api/admin/businesses/{id}` - View business details before deletion
- **Update Business:** `PUT /api/admin/businesses/{id}` - Update business information
- **Update Business Status:** `PATCH /api/admin/businesses/{id}/status` - Suspend or deactivate instead of deleting
- **Get All Businesses:** `GET /api/admin/businesses` - List all businesses

---

### Best Practices

1. **Check Before Deleting:** Always retrieve the business details first to see what related records exist
   ```bash
   GET /api/admin/businesses/{id}
   ```

2. **Use Status Update Instead:** If you want to disable a business temporarily, use the status update endpoint instead:
   ```bash
   PATCH /api/admin/businesses/{id}/status
   {
     "status": "suspended"
   }
   ```

3. **Clean Up Related Data:** Before deleting, ensure all related data is handled appropriately:
   - Archive or reassign invoices
   - Delete or reassign business customers
   - Process or cancel withdrawals
   - Delete products

4. **Verify Deletion:** After deletion, verify the business no longer exists:
   ```bash
   GET /api/admin/businesses/{id}
   # Should return 404 Not Found
   ```

---

### Example Workflow

**Step 1: Check Business Details**
```bash
curl -X GET https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/businesses/1
```

**Step 2: Check for Related Records**
Review the response to see if the business has:
- Invoices count
- Business customers
- Transactions
- Withdrawals
- Products

**Step 3: Clean Up (if needed)**
- Delete products: `DELETE /api/business/products/{id}`
- Process withdrawals: `PATCH /api/admin/withdrawals/{id}/process`
- Handle invoices and business customers as needed

**Step 4: Delete Business**
```bash
curl -X DELETE https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/businesses/1
```

**Step 5: Verify Deletion**
```bash
curl -X GET https://nodopay-api-0fbd4546e629.herokuapp.com/api/admin/businesses/1
# Should return 404
```

---

### Notes

- **Permanent Action:** Deletion is permanent and cannot be undone
- **Cascade Behavior:** The system does NOT automatically delete related records. You must handle them manually
- **Soft Delete:** This is a hard delete. If you need to preserve data, consider using the status update endpoint to set status to "inactive" instead
- **Authorization:** Only admin users can delete businesses
- **Logging:** All deletions are logged for audit purposes

---

### Status Codes Summary

| Status Code | Description |
|-------------|-------------|
| `200 OK` | Business deleted successfully |
| `404 Not Found` | Business with the specified ID does not exist |
| `422 Unprocessable Entity` | Business cannot be deleted due to existing related records |
| `401 Unauthorized` | Authentication required |
| `500 Internal Server Error` | Server error during deletion |

---

**Base URL:** `https://nodopay-api-0fbd4546e629.herokuapp.com/api`
