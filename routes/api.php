<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusinessController;
use App\Http\Controllers\Api\CustomerDashboardController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\PayWithNodopayController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaystackController;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\InvoiceCheckoutController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication Routes
Route::prefix('auth')->group(function () {
    Route::post('/customer/register', [AuthController::class, 'customerRegister']);
    Route::post('/customer/login', [AuthController::class, 'customerLogin']);
    Route::post('/business/login', [BusinessController::class, 'login']);
    Route::post('/admin/login', [AuthController::class, 'adminLogin']);
});

// Customer Dashboard Routes
Route::prefix('customer')->group(function () {
    Route::get('/credit-overview', [CustomerDashboardController::class, 'getCreditOverview']);
    Route::get('/dashboard', [CustomerDashboardController::class, 'getDashboard']);
    Route::get('/invoices', [CustomerDashboardController::class, 'getInvoices']);
    Route::get('/invoices/{invoiceId}', [CustomerDashboardController::class, 'getInvoice']);
    Route::get('/transactions', [CustomerDashboardController::class, 'getTransactions']);
    Route::get('/repayment-account', [CustomerDashboardController::class, 'getRepaymentAccount']);
    Route::post('/repayment-account/generate', [CustomerDashboardController::class, 'generateVirtualAccount']);
    Route::post('/repayment-account/refresh', [CustomerDashboardController::class, 'refreshVirtualAccount']);
    Route::post('/submit-payment', [CustomerDashboardController::class, 'submitPaymentClaim']);
    Route::get('/profile', [CustomerDashboardController::class, 'getProfile']);
    Route::put('/profile', [CustomerDashboardController::class, 'updateProfile']);
    Route::post('/change-pin', [CustomerDashboardController::class, 'changePin']);
});

// Business Dashboard Routes
Route::prefix('business')->middleware('business.auth')->group(function () {
    Route::get('/dashboard', [BusinessController::class, 'getDashboard']);
    Route::get('/invoices', [BusinessController::class, 'getInvoices']);
    Route::get('/profile', [BusinessController::class, 'getProfile']);
    Route::put('/profile', [BusinessController::class, 'updateProfile']);
    Route::post('/generate-api-token', [BusinessController::class, 'generateApiToken']);
    
    // Business Customer Management
    Route::get('/customers', [BusinessController::class, 'getCustomers']);
    Route::post('/customers', [BusinessController::class, 'createCustomer']);
    Route::get('/customers/{id}', [BusinessController::class, 'getCustomer']);
    Route::put('/customers/{id}', [BusinessController::class, 'updateCustomer']);
    Route::delete('/customers/{id}', [BusinessController::class, 'deleteCustomer']);
    
    // Invoice Management
    Route::post('/submit-invoice', [BusinessController::class, 'submitInvoice']);
    Route::post('/check-customer-credit', [BusinessController::class, 'checkCustomerCredit']);
    Route::post('/invoices/{invoiceId}/generate-link', [BusinessController::class, 'generateInvoiceLink']);
    
    Route::get('/transactions', [BusinessController::class, 'getTransactions']);
    Route::post('/withdrawals/request', [BusinessController::class, 'requestWithdrawal']);
    Route::get('/withdrawals', [BusinessController::class, 'getWithdrawals']);
});

// Admin Panel Routes
Route::prefix('admin')->group(function () {
    // Customer Management (Main Customers)
    Route::post('/customers', [AdminController::class, 'createCustomer']);
    Route::get('/customers', [AdminController::class, 'getCustomers']);
    Route::get('/customers/{id}', [AdminController::class, 'getCustomer']);
    Route::put('/customers/{id}', [AdminController::class, 'updateCustomer']);
    Route::patch('/customers/{id}/credit-limit', [AdminController::class, 'updateCreditLimit']);
    Route::post('/customers/{id}/add-credits', [AdminController::class, 'addCreditsToCustomer']);
    Route::patch('/customers/{id}/status', [AdminController::class, 'updateCustomerStatus']);
    Route::patch('/customers/{id}/approval', [AdminController::class, 'updateCustomerApproval']);
    Route::post('/customers/{id}/generate-virtual-account', [AdminController::class, 'generateVirtualAccount']);
    Route::post('/customers/generate-virtual-accounts-all', [AdminController::class, 'generateVirtualAccountsForAll']);
    
    // Business Customer Management (Customers created by businesses)
    Route::get('/business-customers', [AdminController::class, 'getBusinessCustomers']);
    Route::get('/business-customers/{id}', [AdminController::class, 'getBusinessCustomer']);
    
    // Invoice Management
    Route::get('/invoices', [AdminController::class, 'getAllInvoices']);
    Route::patch('/invoices/{id}/status', [AdminController::class, 'updateInvoiceStatus']);
    Route::patch('/invoices/{id}/mark-paid', [AdminController::class, 'markInvoicePaid']);
    
    // Business Management
    Route::post('/businesses', [AdminController::class, 'createBusiness']);
    Route::get('/businesses', [AdminController::class, 'getBusinesses']);
    Route::get('/businesses/{id}', [AdminController::class, 'getBusiness']);
    Route::put('/businesses/{id}', [AdminController::class, 'updateBusiness']);
    Route::patch('/businesses/{id}/approve', [AdminController::class, 'approveBusiness']);
    Route::patch('/businesses/{id}/status', [AdminController::class, 'updateBusinessStatus']);
    
    // Dashboard Statistics
    Route::get('/dashboard/stats', [AdminController::class, 'getDashboardStats']);
    
    // Withdrawal Management
    Route::get('/withdrawals', [AdminController::class, 'getAllWithdrawals']);
    Route::patch('/withdrawals/{id}/approve', [AdminController::class, 'approveWithdrawal']);
    Route::patch('/withdrawals/{id}/process', [AdminController::class, 'processWithdrawal']);
    
    // Unified Transactions (All transaction types)
    Route::get('/transactions/all', [AdminController::class, 'getAllTransactions']);
    
    // Payment Confirmation Management
    Route::get('/payments/pending', [AdminController::class, 'getPendingPayments']);
    Route::patch('/payments/{id}/confirm', [AdminController::class, 'confirmPayment']);
    Route::patch('/payments/{id}/reject', [AdminController::class, 'rejectPayment']);
});

// Pay with Nodopay API (External Integration)
Route::prefix('pay-with-nodopay')->middleware('api.token')->group(function () {
    Route::post('/purchase', [PayWithNodopayController::class, 'purchaseRequest']);
    Route::post('/check-credit', [PayWithNodopayController::class, 'checkCredit']);
    Route::get('/customer', [PayWithNodopayController::class, 'getCustomerDetails']);
});

// Payment Processing Routes
Route::prefix('payments')->group(function () {
    Route::post('/webhook', [PaymentController::class, 'paymentWebhook']);
    Route::post('/webhook/paystack', [PaymentController::class, 'paystackWebhook']); // Dedicated Paystack webhook
    Route::post('/record', [PaymentController::class, 'recordPayment']);
    Route::get('/history/{customerId}', [PaymentController::class, 'getPaymentHistory']);
});

// Paystack Configuration Check (for admin/testing)
Route::get('/paystack/status', [PaystackController::class, 'checkConfiguration']);

// Public Invoice Checkout Routes
Route::prefix('invoice')->group(function () {
    Route::get('/checkout/{slug}', [InvoiceCheckoutController::class, 'getInvoiceBySlug']);
    Route::post('/checkout/{slug}/pay', [InvoiceCheckoutController::class, 'payInvoice']);
});

// Test Routes
Route::get('/test/s3', [TestController::class, 'testS3']);

