# Admin API: Update Customer Owed Amount

## Overview
This API endpoint allows admins to directly update the total amount owed by a customer **without needing to specify an invoice**. This is useful for:
- **Adjusting balances** (increase or decrease the amount owed)
- **Applying discounts** or corrections
- **Adding additional charges** or fees
- **Correcting payment records**

**Important:** The system automatically recalculates customer balances (`current_balance` and `available_balance`) after any update. An adjustment invoice is created automatically behind the scenes to track the change.

## Endpoint

```
PATCH /api/admin/customers/{customerId}/owed-amount
```

## Authentication
Admin authentication is required. Include your admin token in the request headers.

## Request Headers

```
Authorization: Bearer {admin_token}
Content-Type: application/json
Accept: application/json
```

## Request Parameters

### Path Parameters
- `customerId` (integer, required): The ID of the customer

### Request Body
The request body is JSON and accepts the following fields:

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `amount_owed` | number | Yes | The new total amount the customer owes. Must be >= 0 |
| `reason` | string | No | Reason for the adjustment (max 500 characters) |

**Note:** This sets the customer's total owed amount directly. The system will automatically create an adjustment invoice to track the difference.

## Request Examples

### Example 1: Set Customer Owed Amount

```javascript
// Using fetch - Set the total amount the customer owes
const response = await fetch('https://api.example.com/api/admin/customers/123/owed-amount', {
  method: 'PATCH',
  headers: {
    'Authorization': 'Bearer your_admin_token',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    amount_owed: 5000.00,
    reason: 'Applied discount for early payment'
  })
});

const data = await response.json();
```

### Example 2: Increase Amount Owed

```javascript
// Increase the total amount owed (add fees or charges)
const response = await fetch('https://api.example.com/api/admin/customers/123/owed-amount', {
  method: 'PATCH',
  headers: {
    'Authorization': 'Bearer your_admin_token',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    amount_owed: 8000.00, // Customer now owes 8000 total
    reason: 'Added late payment fees'
  })
});

const data = await response.json();
```

### Example 3: Decrease Amount Owed

```javascript
// Decrease the total amount owed (apply discount)
const response = await fetch('https://api.example.com/api/admin/customers/123/owed-amount', {
  method: 'PATCH',
  headers: {
    'Authorization': 'Bearer your_admin_token',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    amount_owed: 2000.00, // Customer now owes 2000 total
    reason: 'Applied discount'
  })
});

const data = await response.json();
```

## Response

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Customer owed amount updated successfully",
  "customer": {
    "id": 123,
    "account_number": "1234567890123456",
    "business_name": "Customer Business Name",
    "credit_limit": "2000000.00",
    "previous_current_balance": "10000.00",
    "new_current_balance": "5000.00",
    "new_available_balance": "1995000.00",
    "amount_owed": "5000.00"
  }
}
```

### Error Responses

#### 400 Bad Request - Validation Error

```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "amount_owed": [
      "The amount owed must be at least 0."
    ]
  }
}
```

#### 404 Not Found - Customer Not Found

```json
{
  "success": false,
  "message": "No query results for model [App\\Models\\Customer] 123"
}
```

#### 500 Internal Server Error

```json
{
  "success": false,
  "message": "Failed to update customer owed amount: {error_message}"
}
```

## Frontend Integration Example (React)

```jsx
import React, { useState } from 'react';

function UpdateOwedAmountForm({ customerId, onSuccess }) {
  const [amountOwed, setAmountOwed] = useState('');
  const [reason, setReason] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    try {
      const token = localStorage.getItem('admin_token'); // Get from your auth system
      
      const body = {
        amount_owed: parseFloat(amountOwed),
      };
      if (reason) body.reason = reason;

      const response = await fetch(
        `/api/admin/customers/${customerId}/owed-amount`,
        {
          method: 'PATCH',
          headers: {
            'Authorization': `Bearer ${token}`,
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(body)
        }
      );

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.message || 'Failed to update owed amount');
      }

      if (onSuccess) {
        onSuccess(data);
      }

      // Reset form
      setAmountOwed('');
      setReason('');
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      <div>
        <label>
          Total Amount Owed:
          <input
            type="number"
            step="0.01"
            min="0"
            value={amountOwed}
            onChange={(e) => setAmountOwed(e.target.value)}
            placeholder="Enter total amount customer owes"
            required
          />
        </label>
      </div>

      <div>
        <label>
          Reason (Optional):
          <textarea
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Enter reason for adjustment"
            maxLength={500}
          />
        </label>
      </div>

      {error && <div className="error">{error}</div>}

      <button type="submit" disabled={loading}>
        {loading ? 'Updating...' : 'Update Owed Amount'}
      </button>
    </form>
  );
}

export default UpdateOwedAmountForm;
```

## Important Notes

1. **Simple Direct Update**: This endpoint allows you to directly set the total amount a customer owes **without needing to specify an invoice**. Just provide the total amount owed.

2. **Automatic Adjustment Invoice**: Behind the scenes, the system creates or updates an "Admin Adjustment" invoice to track the difference between the previous and new amounts. This ensures proper audit trails.

3. **Increase or Decrease**: You can **increase** or **decrease** the total amount owed:
   - **Decrease**: Set `amount_owed` to a lower value (e.g., apply discount)
   - **Increase**: Set `amount_owed` to a higher value (e.g., add fees, charges)
   - Example: If customer currently owes 10,000 and you set `amount_owed` to 5,000, the system automatically creates an adjustment for -5,000

4. **Automatic Balance Recalculation & Available Balance**: After updating, the customer's `current_balance` and `available_balance` are **automatically recalculated**:
   - `current_balance` = total of all unpaid invoices + credit not repaid
   - `available_balance` = `credit_limit` - `current_balance`
   - **YES, the amount owed IS deducted from available_balance automatically**
   - Example: If credit_limit is 2,000,000 and amount_owed is 5,000, then:
     - `current_balance` = 5,000
     - `available_balance` = 2,000,000 - 5,000 = **1,995,000** ✅

5. **Available Balance in Admin Customer API**: The `available_balance` is **included** in the admin customer API response. When you call:
   - `GET /api/admin/customers/{id}` - Returns full customer object including `available_balance`
   - The balance is automatically recalculated before returning, so it's always up-to-date

6. **No Invoice Needed**: Unlike the invoice-specific endpoint, you don't need to know which invoice to update. Just set the total amount the customer should owe, and the system handles the rest.

7. **Audit Trail**: All adjustments are logged with the admin user ID, previous balance, new balance, difference, and reason for audit purposes.

8. **Cache Invalidation**: Customer and invoice caches are automatically cleared after updates to ensure data consistency.

9. **Repayment API**: For processing actual customer payments, use the repayment API endpoints (already implemented). This endpoint is specifically for admin adjustments to total owed amounts.

## Testing with cURL

```bash
# Set customer total owed amount to 5000
curl -X PATCH "https://api.example.com/api/admin/customers/123/owed-amount" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "amount_owed": 5000.00,
    "reason": "Applied discount"
  }'

# Increase amount owed to 8000
curl -X PATCH "https://api.example.com/api/admin/customers/123/owed-amount" \
  -H "Authorization: Bearer your_admin_token" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "amount_owed": 8000.00,
    "reason": "Added late payment fees"
  }'
```
