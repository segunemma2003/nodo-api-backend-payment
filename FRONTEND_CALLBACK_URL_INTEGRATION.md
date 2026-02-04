# Frontend Callback URL Integration Guide

This guide explains how to implement callback URL redirect functionality in the FSCredit frontend after payment processing.

## Overview

When a user completes payment (success or failure), the API returns a `callback_url` in the response. The frontend should redirect the user to this URL, which includes payment status as query parameters.

## Payment Flow

1. User fills payment form on FSCredit frontend
2. Frontend calls payment API: `POST /api/invoice/checkout/{slug}/pay`
3. API returns response with `callback_url` (includes payment status in query params)
4. Frontend redirects user to `callback_url`
5. User lands on business site with payment status in URL

---

## API Response Format

### Success Response (200 OK)

```json
{
  "message": "Payment processed successfully",
  "invoice": {
    "id": 1,
    "invoice_id": "FSCREDIT-ABC123",
    "status": "paid",
    "paid_amount": "50000.00",
    "remaining_balance": "0.00"
  },
  "callback_url": "https://yourbusiness.com/payment/success?status=succeeded&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456"
}
```

### Error Response (400 Bad Request)

```json
{
  "message": "Invalid CVV",
  "callback_url": "https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invalid%20CVV"
}
```

**Important:**
- `callback_url` is included in **ALL** payment responses (success and error)
- If business hasn't set a callback URL, `callback_url` will be `null`
- Always check if `callback_url` exists before redirecting

---

## Implementation Examples

### React/Next.js Example

```jsx
import React, { useState } from 'react';
import axios from 'axios';

function PaymentForm({ invoiceSlug }) {
  const [accountNumber, setAccountNumber] = useState('');
  const [cvv, setCvv] = useState('');
  const [pin, setPin] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handlePayment = async (e) => {
    e.preventDefault();
    setLoading(true);
    setError('');

    try {
      const response = await axios.post(
        `/api/invoice/checkout/${invoiceSlug}/pay`,
        {
          account_number: accountNumber,
          cvv: cvv,
          pin: pin,
        }
      );

      // Payment successful
      if (response.data.callback_url) {
        // Redirect to business callback URL
        window.location.href = response.data.callback_url;
      } else {
        // No callback URL set - show success message
        alert('Payment processed successfully!');
        // Optionally redirect to a default success page
      }
    } catch (err) {
      const errorData = err.response?.data;
      
      if (errorData?.callback_url) {
        // Even on error, redirect to callback URL (includes failure status)
        window.location.href = errorData.callback_url;
      } else {
        // No callback URL - show error message
        setError(errorData?.message || 'Payment failed. Please try again.');
        setLoading(false);
      }
    }
  };

  return (
    <form onSubmit={handlePayment}>
      <input
        type="text"
        value={accountNumber}
        onChange={(e) => setAccountNumber(e.target.value)}
        placeholder="Account Number"
        required
      />
      <input
        type="text"
        value={cvv}
        onChange={(e) => setCvv(e.target.value)}
        placeholder="CVV"
        required
      />
      <input
        type="password"
        value={pin}
        onChange={(e) => setPin(e.target.value)}
        placeholder="PIN"
        required
      />
      <button type="submit" disabled={loading}>
        {loading ? 'Processing...' : 'Pay Invoice'}
      </button>
      {error && <p style={{ color: 'red' }}>{error}</p>}
    </form>
  );
}

export default PaymentForm;
```

### Vue.js Example

```vue
<template>
  <form @submit.prevent="handlePayment">
    <input
      v-model="accountNumber"
      type="text"
      placeholder="Account Number"
      required
    />
    <input
      v-model="cvv"
      type="text"
      placeholder="CVV"
      required
    />
    <input
      v-model="pin"
      type="password"
      placeholder="PIN"
      required
    />
    <button type="submit" :disabled="loading">
      {{ loading ? 'Processing...' : 'Pay Invoice' }}
    </button>
    <p v-if="error" style="color: red">{{ error }}</p>
  </form>
</template>

<script>
import axios from 'axios';

export default {
  name: 'PaymentForm',
  props: {
    invoiceSlug: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      accountNumber: '',
      cvv: '',
      pin: '',
      loading: false,
      error: '',
    };
  },
  methods: {
    async handlePayment() {
      this.loading = true;
      this.error = '';

      try {
        const response = await axios.post(
          `/api/invoice/checkout/${this.invoiceSlug}/pay`,
          {
            account_number: this.accountNumber,
            cvv: this.cvv,
            pin: this.pin,
          }
        );

        // Payment successful
        if (response.data.callback_url) {
          window.location.href = response.data.callback_url;
        } else {
          alert('Payment processed successfully!');
        }
      } catch (err) {
        const errorData = err.response?.data;

        if (errorData?.callback_url) {
          // Redirect even on error (includes failure status)
          window.location.href = errorData.callback_url;
        } else {
          this.error = errorData?.message || 'Payment failed. Please try again.';
          this.loading = false;
        }
      }
    },
  },
};
</script>
```

### Vanilla JavaScript Example

```javascript
async function processPayment(invoiceSlug, accountNumber, cvv, pin) {
  const loadingIndicator = document.getElementById('loading');
  const errorMessage = document.getElementById('error');
  
  loadingIndicator.style.display = 'block';
  errorMessage.textContent = '';

  try {
    const response = await fetch(`/api/invoice/checkout/${invoiceSlug}/pay`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        account_number: accountNumber,
        cvv: cvv,
        pin: pin,
      }),
    });

    const data = await response.json();

    if (response.ok) {
      // Payment successful
      if (data.callback_url) {
        window.location.href = data.callback_url;
      } else {
        alert('Payment processed successfully!');
        // Optionally redirect to default success page
        // window.location.href = '/payment/success';
      }
    } else {
      // Payment failed
      if (data.callback_url) {
        // Redirect to callback URL (includes failure status)
        window.location.href = data.callback_url;
      } else {
        errorMessage.textContent = data.message || 'Payment failed. Please try again.';
        loadingIndicator.style.display = 'none';
      }
    }
  } catch (error) {
    errorMessage.textContent = 'An error occurred. Please try again.';
    loadingIndicator.style.display = 'none';
  }
}

// Usage
document.getElementById('paymentForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const accountNumber = document.getElementById('accountNumber').value;
  const cvv = document.getElementById('cvv').value;
  const pin = document.getElementById('pin').value;
  const invoiceSlug = window.location.pathname.split('/').pop();
  
  processPayment(invoiceSlug, accountNumber, cvv, pin);
});
```

### TypeScript Example

```typescript
interface PaymentResponse {
  message: string;
  invoice?: {
    id: number;
    invoice_id: string;
    status: string;
    paid_amount: string;
    remaining_balance: string;
  };
  callback_url: string | null;
}

interface PaymentError {
  message: string;
  callback_url?: string | null;
}

async function processPayment(
  invoiceSlug: string,
  accountNumber: string,
  cvv: string,
  pin: string
): Promise<void> {
  try {
    const response = await fetch(`/api/invoice/checkout/${invoiceSlug}/pay`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        account_number: accountNumber,
        cvv: cvv,
        pin: pin,
      }),
    });

    const data: PaymentResponse | PaymentError = await response.json();

    // Check if callback_url exists (works for both success and error)
    if (data.callback_url) {
      window.location.href = data.callback_url;
    } else if (response.ok) {
      // Success but no callback URL
      alert('Payment processed successfully!');
    } else {
      // Error and no callback URL
      const error = data as PaymentError;
      throw new Error(error.message || 'Payment failed');
    }
  } catch (error) {
    console.error('Payment error:', error);
    // Handle error (show message, etc.)
  }
}
```

---

## Key Implementation Points

### 1. Always Check for `callback_url`

```javascript
if (data.callback_url) {
  window.location.href = data.callback_url;
} else {
  // Handle case where no callback URL is set
  showMessage('Payment processed successfully!');
}
```

### 2. Handle Both Success and Error Responses

The `callback_url` is included in **both** success (200) and error (400) responses. Always check for it:

```javascript
try {
  const response = await fetch(...);
  const data = await response.json();
  
  if (data.callback_url) {
    // Redirect regardless of success/error
    window.location.href = data.callback_url;
  }
} catch (error) {
  // Handle network errors
}
```

### 3. Handle Null Callback URL

If the business hasn't set a callback URL, `callback_url` will be `null`. Handle this gracefully:

```javascript
if (data.callback_url) {
  window.location.href = data.callback_url;
} else {
  // Show success/error message on current page
  // Or redirect to a default success/error page
  if (response.ok) {
    window.location.href = '/payment/success';
  } else {
    showErrorMessage(data.message);
  }
}
```

### 4. Preserve User Experience

Consider showing a loading state before redirect:

```javascript
setLoading(true);
try {
  const response = await fetch(...);
  const data = await response.json();
  
  if (data.callback_url) {
    // Small delay to show success state before redirect
    setTimeout(() => {
      window.location.href = data.callback_url;
    }, 500);
  }
} finally {
  setLoading(false);
}
```

---

## Callback URL Format

The `callback_url` includes payment status as query parameters:

### Success URL
```
https://yourbusiness.com/payment/success?status=succeeded&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456
```

### Failure URL
```
https://yourbusiness.com/payment/success?status=failed&invoice_id=FSCREDIT-ABC123&slug=inv-abc123xyz456&reason=Invalid%20CVV
```

**Query Parameters:**
- `status`: `succeeded` or `failed`
- `invoice_id`: Invoice ID (e.g., "FSCREDIT-ABC123")
- `slug`: Payment link slug (e.g., "inv-abc123xyz456")
- `reason`: Failure reason (only for failed payments, URL encoded)

---

## Error Handling

### Network Errors

```javascript
try {
  const response = await fetch(...);
  // Handle response
} catch (error) {
  if (error instanceof TypeError) {
    // Network error
    showError('Network error. Please check your connection.');
  } else {
    // Other errors
    showError('An unexpected error occurred.');
  }
}
```

### API Errors

```javascript
const response = await fetch(...);
if (!response.ok) {
  const errorData = await response.json();
  
  if (errorData.callback_url) {
    // Redirect to callback URL (includes error status)
    window.location.href = errorData.callback_url;
  } else {
    // Show error message
    showError(errorData.message);
  }
}
```

---

## Testing Scenarios

### Test Case 1: Successful Payment with Callback URL

1. User submits payment form
2. API returns 200 with `callback_url`
3. Frontend redirects to `callback_url`
4. User lands on business site with `?status=succeeded&invoice_id=XXX&slug=YYY`

### Test Case 2: Failed Payment with Callback URL

1. User submits payment with invalid credentials
2. API returns 400 with `callback_url` (includes failure status)
3. Frontend redirects to `callback_url`
4. User lands on business site with `?status=failed&invoice_id=XXX&slug=YYY&reason=ZZZ`

### Test Case 3: Payment with No Callback URL

1. Business hasn't set callback URL
2. API returns response with `callback_url: null`
3. Frontend shows success/error message on current page
4. User doesn't get redirected

### Test Case 4: Network Error

1. Network request fails
2. Frontend catches error
3. Shows error message to user
4. User can retry payment

---

## Best Practices

### 1. Always Redirect When Callback URL Exists

```javascript
// ✅ Good
if (data.callback_url) {
  window.location.href = data.callback_url;
}

// ❌ Bad - Don't ignore callback URL
if (response.ok && data.callback_url) {
  // Only redirect on success
}
```

### 2. Handle Null Callback URL Gracefully

```javascript
// ✅ Good
if (data.callback_url) {
  window.location.href = data.callback_url;
} else {
  // Show message or redirect to default page
  showMessage('Payment processed successfully!');
}

// ❌ Bad - Assumes callback URL always exists
window.location.href = data.callback_url; // Will fail if null
```

### 3. Show Loading State

```javascript
// ✅ Good
setLoading(true);
try {
  const response = await fetch(...);
  // Handle response
} finally {
  setLoading(false);
}
```

### 4. Clear Form After Redirect

```javascript
if (data.callback_url) {
  // Clear sensitive data before redirect
  setAccountNumber('');
  setCvv('');
  setPin('');
  
  // Then redirect
  window.location.href = data.callback_url;
}
```

---

## Security Considerations

### 1. Never Log Sensitive Data

```javascript
// ❌ Bad
console.log('CVV:', cvv);
console.log('PIN:', pin);

// ✅ Good
console.log('Payment processing...');
```

### 2. Clear Sensitive Data After Payment

```javascript
if (data.callback_url) {
  // Clear form data
  setAccountNumber('');
  setCvv('');
  setPin('');
  
  window.location.href = data.callback_url;
}
```

### 3. Use HTTPS

Always ensure payment API calls are made over HTTPS:

```javascript
const apiUrl = process.env.REACT_APP_API_URL || 'https://fsscredit.foodstuff.store';
```

---

## API Endpoint

**Payment Endpoint:**
```
POST /api/invoice/checkout/{slug}/pay
```

**Base URL:**
```
https://fsscredit.foodstuff.store
```

**Full URL Example:**
```
https://fsscredit.foodstuff.store/api/invoice/checkout/inv-abc123xyz456/pay
```

---

## Summary

1. ✅ **Always check for `callback_url`** in payment responses (both success and error)
2. ✅ **Redirect user** to `callback_url` if it exists
3. ✅ **Handle null callback URL** gracefully (show message or default redirect)
4. ✅ **Include loading states** for better UX
5. ✅ **Clear sensitive data** before redirect
6. ✅ **Test all scenarios** (success, failure, no callback URL, network errors)

The callback URL includes payment status as query parameters, so the business site can immediately determine payment result without additional API calls.

---

## Questions or Issues?

If you encounter any issues implementing this, please check:
1. API response format matches expected structure
2. `callback_url` is being checked in both success and error cases
3. Null `callback_url` is handled properly
4. Network errors are caught and handled

For API documentation, see: `UPDATED_BUSINESS_WORKFLOW.md`
