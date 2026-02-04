# Invoice Generation Errors

Possible errors that can occur **during invoice generation** when calling `POST /api/pay-with-fscredit/create-invoice-link`.

All these errors return **500 Internal Server Error** with the following format:

```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "Detailed error message here"
}
```

---

## 1. BusinessCustomer Creation Failure

**Location:** Line 331 - `BusinessCustomer::create()`

**Possible Errors:**

### a) Database Connection Error
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[HY000] [2002] Connection refused"
}
```
**Cause:** Database server is unreachable or connection pool exhausted.

### b) Foreign Key Constraint Violation
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails"
}
```
**Cause:** `business_id` doesn't exist in `businesses` table (shouldn't happen if business was validated).

### c) Unique Constraint Violation
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'customer@example.com' for key 'business_customers_business_id_contact_email_unique'"
}
```
**Cause:** Business customer with same `business_id` and `contact_email` already exists (shouldn't happen due to `firstOrCreate` logic, but possible in race conditions).

### d) Column Size/Type Mismatch
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[22001]: String data, right truncated: 1406 Data too long for column 'contact_email'"
}
```
**Cause:** Data exceeds column size limits.

---

## 2. Date Parsing Failure

**Location:** Line 373-375 - `Carbon::parse()`

**Possible Errors:**

### a) Invalid Date Format
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "DateTime::__construct(): Failed to parse time string (invalid-date) at position 0"
}
```
**Cause:** `purchase_date` passed validation but Carbon cannot parse it (edge case).

### b) Invalid Date Value
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "DateTime::__construct(): Failed to parse time string (2024-13-45) at position 0"
}
```
**Cause:** Date values are out of valid range (e.g., month > 12, day > 31).

---

## 3. Invoice Creation Failure

**Location:** Line 378 - `createInvoiceForBusinessCustomer()` method

**Possible Errors:**

### a) Database Transaction Failure
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock"
}
```
**Cause:** Database deadlock during transaction.

### b) Foreign Key Constraint Violation
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`invoices`.`business_customer_id`)"
}
```
**Cause:** 
- `business_customer_id` doesn't exist (shouldn't happen, but possible if customer was deleted between creation and invoice creation)
- `supplier_id` doesn't exist in `businesses` table

### c) Unique Constraint Violation - Invoice ID
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'FSCREDIT-ABC123' for key 'invoices_invoice_id_unique'"
}
```
**Cause:** Invoice ID generation created a duplicate (very rare, but possible in high concurrency).

### d) Unique Constraint Violation - Slug
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'inv-abc123xyz456' for key 'invoices_slug_unique'"
}
```
**Cause:** Slug generation created a duplicate (extremely rare due to `generateSlug()` loop, but possible in race conditions).

### e) Slug Generation Infinite Loop (Theoretical)
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "Maximum execution time exceeded"
}
```
**Cause:** `Invoice::generateSlug()` cannot find a unique slug after many attempts (extremely rare, would require millions of invoices with same slug pattern).

### f) Database Column Constraint
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[22003]: Numeric value out of range: 1264 Out of range value for column 'principal_amount'"
}
```
**Cause:** Amount exceeds database column precision (decimal(15,2) = max 999,999,999,999,999.99).

### g) Required Field Missing
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[HY000]: General error: 1364 Field 'business_customer_id' doesn't have a default value"
}
```
**Cause:** Required field is null (shouldn't happen due to validation, but possible if model changes).

---

## 4. Transaction Record Creation Failure

**Location:** Line 390 - `Transaction::create()`

**Possible Errors:**

### a) Foreign Key Constraint Violation
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails (`transactions`.`invoice_id`)"
}
```
**Cause:** Invoice was deleted between creation and transaction creation (shouldn't happen, but possible in edge cases).

### b) JSON Metadata Serialization Failure
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[22032]: Incorrect JSON text: The JSON text is invalid"
}
```
**Cause:** `metadata` field contains invalid JSON (shouldn't happen as it's created from array).

### c) Database Connection Lost
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[HY000] [2006] MySQL server has gone away"
}
```
**Cause:** Database connection was lost between invoice creation and transaction creation.

---

## 5. Database Transaction Rollback

**Location:** Inside `createInvoiceForBusinessCustomer()` - `DB::rollBack()`

**Possible Errors:**

All errors in the invoice creation transaction (lines 117-135) will trigger a rollback, and the exception will be re-thrown to the main catch block.

**Common scenarios:**
- Any database error during `Invoice::create()`
- Foreign key violations
- Unique constraint violations
- Database connection loss during transaction

---

## Error Handling Flow

```
1. Request validated ✅
2. Business validated ✅
3. BusinessCustomer created/updated ✅
4. Date parsed ✅
5. Invoice creation starts (DB transaction begins)
   ├─ Slug generation
   ├─ Invoice::create() ← Can fail here
   └─ DB::commit() ← Or fail here
6. Transaction::create() ← Can fail here
7. Webhook send (non-blocking, errors logged but don't fail request)
```

---

## Most Common Errors in Production

### 1. Database Connection Issues
- **Frequency:** Medium
- **Cause:** Database server overload, network issues
- **Solution:** Retry with exponential backoff

### 2. Foreign Key Constraint Violations
- **Frequency:** Low
- **Cause:** Data inconsistency, concurrent deletions
- **Solution:** Check data integrity, add proper locking

### 3. Unique Constraint Violations (Slug)
- **Frequency:** Very Low
- **Cause:** Race condition in slug generation
- **Solution:** Add database-level unique constraint, retry logic

### 4. Transaction Deadlocks
- **Frequency:** Low (in high concurrency)
- **Cause:** Multiple simultaneous invoice creations
- **Solution:** Retry transaction, add proper indexing

---

## Error Response Examples

### Example 1: Database Connection Lost
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[HY000] [2006] MySQL server has gone away"
}
```

### Example 2: Foreign Key Violation
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row: a foreign key constraint fails"
}
```

### Example 3: Unique Constraint Violation
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'inv-abc123xyz456' for key 'invoices_slug_unique'"
}
```

### Example 4: Transaction Deadlock
```json
{
  "success": false,
  "message": "Failed to create invoice link",
  "error": "SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction"
}
```

---

## Best Practices for Handling These Errors

### 1. Retry Logic for Transient Errors
```javascript
async function createInvoiceWithRetry(data, maxRetries = 3) {
  for (let i = 0; i < maxRetries; i++) {
    try {
      const response = await fetch('/api/pay-with-fscredit/create-invoice-link', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-API-Token': apiToken
        },
        body: JSON.stringify(data)
      });
      
      if (response.ok) {
        return await response.json();
      }
      
      const error = await response.json();
      
      // Retry on database connection errors or deadlocks
      if (response.status === 500 && 
          (error.error.includes('Connection') || 
           error.error.includes('Deadlock') ||
           error.error.includes('try restarting transaction'))) {
        if (i < maxRetries - 1) {
          await sleep(1000 * Math.pow(2, i)); // Exponential backoff
          continue;
        }
      }
      
      throw error;
    } catch (error) {
      if (i === maxRetries - 1) throw error;
      await sleep(1000 * Math.pow(2, i));
    }
  }
}
```

### 2. Log Errors for Monitoring
```javascript
if (response.status === 500) {
  const error = await response.json();
  console.error('Invoice generation failed:', {
    error: error.error,
    request: data,
    timestamp: new Date().toISOString()
  });
  // Send to error tracking service (Sentry, etc.)
}
```

### 3. User-Friendly Error Messages
```javascript
function getErrorMessage(error) {
  if (error.error.includes('Connection')) {
    return 'Service temporarily unavailable. Please try again in a moment.';
  }
  if (error.error.includes('Deadlock')) {
    return 'Request is being processed. Please try again.';
  }
  if (error.error.includes('Duplicate')) {
    return 'Invoice already exists. Please check your records.';
  }
  return 'Failed to create invoice. Please contact support.';
}
```

---

## Database Constraints Reference

### Invoices Table
- `invoice_id` - UNIQUE
- `slug` - UNIQUE
- `business_customer_id` - FOREIGN KEY (nullable)
- `customer_id` - FOREIGN KEY (nullable)
- `supplier_id` - FOREIGN KEY (nullable)

### Business Customers Table
- `business_id` + `contact_email` - UNIQUE (composite)

### Transactions Table
- `invoice_id` - FOREIGN KEY
- `customer_id` - FOREIGN KEY (nullable)
- `business_id` - FOREIGN KEY (nullable)

---

**Note:** All these errors are caught by the main `catch (\Exception $e)` block at line 435, which logs the error and returns a 500 response. The error message in production may be sanitized for security.
