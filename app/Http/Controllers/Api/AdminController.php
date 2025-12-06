<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\UploadFileToS3Job;
use App\Models\Business;
use App\Models\BusinessCustomer;
use App\Models\CreditLimitAdjustment;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Transaction;
use App\Models\Withdrawal;
use App\Notifications\BusinessApprovedNotification;
use App\Notifications\BusinessCreatedNotification;
use App\Notifications\CustomerCreatedNotification;
use App\Services\CreditLimitService;
use App\Services\InterestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    protected CreditLimitService $creditLimitService;
    protected InterestService $interestService;

    public function __construct(CreditLimitService $creditLimitService, InterestService $interestService)
    {
        $this->creditLimitService = $creditLimitService;
        $this->interestService = $interestService;
    }

    public function createCustomer(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'username' => 'required|string|unique:customers,username',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'minimum_purchase_amount' => 'required|numeric|min:0',
            'payment_plan_duration' => 'required|integer|min:1',
            'virtual_account_number' => 'nullable|string|unique:customers,virtual_account_number',
            'virtual_account_bank' => 'nullable|string',
            'kyc_documents' => 'nullable|array',
            'kyc_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $customer = Customer::create([
            'business_name' => $request->business_name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
            'minimum_purchase_amount' => $request->minimum_purchase_amount,
            'payment_plan_duration' => $request->payment_plan_duration,
            'virtual_account_number' => $request->virtual_account_number,
            'virtual_account_bank' => $request->virtual_account_bank,
            'approval_status' => 'approved', // Admin-created customers are auto-approved
            'status' => 'active', // Admin-created customers are auto-active
        ]);

        // Calculate and set credit limit
        $this->creditLimitService->updateCustomerCreditLimit($customer);

        if ($request->hasFile('kyc_documents')) {
            $kycPaths = [];
            foreach ($request->file('kyc_documents') as $document) {
                $tempPath = $document->store('temp/kyc_documents', 'local');
                $s3Path = 'kyc_documents/customer_' . $customer->id . '/' . basename($tempPath);
                $kycPaths[] = $s3Path;
                UploadFileToS3Job::dispatch($tempPath, $s3Path, $customer, 'kyc_documents');
            }
            $customer->kyc_documents = $kycPaths;
            $customer->save();
        }

        $customer->notify(new CustomerCreatedNotification($request->password));

        // Clear customer list caches since we added a new customer
        for ($page = 1; $page <= 20; $page++) {
            Cache::forget('admin_customers_page_' . $page);
        }

        return response()->json([
            'message' => 'Customer created successfully',
            'customer' => [
                'id' => $customer->id,
                'account_number' => $customer->account_number,
                'business_name' => $customer->business_name,
                'email' => $customer->email,
                'username' => $customer->username,
                'credit_limit' => $customer->credit_limit,
            ],
        ], 201);
    }

    public function getCustomers(Request $request)
    {
        $page = $request->get('page', 1);
        $cacheKey = 'admin_customers_page_' . $page;

        $customers = Cache::remember($cacheKey, 300, function () {
            return Customer::withCount('invoices')
                ->orderBy('created_at', 'desc')
                ->paginate(20);
        });

        return response()->json($customers);
    }

    public function getCustomer($id)
    {
        $cacheKey = 'admin_customer_' . $id;

        $customer = Cache::remember($cacheKey, 180, function () use ($id) {
            return Customer::with(['invoices', 'payments'])
                ->findOrFail($id);
        });

        $customer->updateBalances();
        Cache::forget($cacheKey);

        return response()->json([
            'customer' => $customer,
        ]);
    }

    public function updateCreditLimit(Request $request, $id)
    {
        $request->validate([
            'credit_limit' => 'required|numeric|min:0',
            'reason' => 'nullable|string',
        ]);

        $customer = Customer::findOrFail($id);
        $previousCreditLimit = $customer->credit_limit;
        $customer->credit_limit = $request->credit_limit;
        $customer->updateBalances();

        // Track credit limit adjustment
        $adjustmentAmount = $request->credit_limit - $previousCreditLimit;
        if ($adjustmentAmount != 0) {
            \App\Models\CreditLimitAdjustment::create([
                'customer_id' => $customer->id,
                'previous_credit_limit' => $previousCreditLimit,
                'new_credit_limit' => $request->credit_limit,
                'adjustment_amount' => $adjustmentAmount,
                'reason' => $request->reason ?? 'Credit limit adjustment',
                'admin_user_id' => $request->user()?->id, // Admin who made the change
            ]);
        }

        return response()->json([
            'message' => 'Credit limit updated successfully',
            'customer' => $customer,
        ]);
    }

    /**
     * Add credits to customer wallet (increase credit limit)
     */
    public function addCreditsToCustomer(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string',
        ]);

        $customer = Customer::findOrFail($id);
        $previousCreditLimit = $customer->credit_limit;
        $newCreditLimit = $previousCreditLimit + $request->amount;
        
        $customer->credit_limit = $newCreditLimit;
        $customer->updateBalances();

        // Track credit limit adjustment
        CreditLimitAdjustment::create([
            'customer_id' => $customer->id,
            'previous_credit_limit' => $previousCreditLimit,
            'new_credit_limit' => $newCreditLimit,
            'adjustment_amount' => $request->amount, // Positive amount (addition)
            'reason' => $request->reason ?? "Credit added to wallet: {$request->amount}",
            'admin_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Credits added to customer wallet successfully',
            'customer' => [
                'id' => $customer->id,
                'business_name' => $customer->business_name,
                'account_number' => $customer->account_number,
                'previous_credit_limit' => $previousCreditLimit,
                'new_credit_limit' => $newCreditLimit,
                'amount_added' => $request->amount,
                'available_balance' => $customer->available_balance,
            ],
        ]);
    }

    public function updateCustomerStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,suspended,inactive',
        ]);

        $customer = Customer::findOrFail($id);
        $customer->status = $request->status;
        $customer->save();

        // Clear caches
        Cache::forget('admin_customer_' . $id);
        for ($page = 1; $page <= 20; $page++) {
            Cache::forget('admin_customers_page_' . $page);
        }

        return response()->json([
            'message' => 'Customer status updated successfully',
            'customer' => $customer,
        ]);
    }

    /**
     * Approve or reject customer registration
     */
    public function updateCustomerApproval(Request $request, $id)
    {
        $request->validate([
            'approval_status' => 'required|in:approved,rejected',
            'credit_limit' => 'nullable|numeric|min:0', // Optional: set credit limit on approval
        ]);

        $customer = Customer::findOrFail($id);

        if ($customer->approval_status === 'approved') {
            return response()->json([
                'message' => 'Customer is already approved',
                'customer' => $customer,
            ], 400);
        }

        $customer->approval_status = $request->approval_status;

        if ($request->approval_status === 'approved') {
            // Set status to active when approved
            $customer->status = 'active';
            
            // Set credit limit if provided, otherwise calculate it
            $previousCreditLimit = $customer->credit_limit;
            if ($request->has('credit_limit')) {
                $customer->credit_limit = $request->credit_limit;
            } else {
                // Calculate credit limit: minimum_purchase_amount × (payment_plan_duration + 1)
                $this->creditLimitService->updateCustomerCreditLimit($customer);
            }
            
            // Track credit limit adjustment on approval
            if ($previousCreditLimit != $customer->credit_limit) {
                CreditLimitAdjustment::create([
                    'customer_id' => $customer->id,
                    'previous_credit_limit' => $previousCreditLimit,
                    'new_credit_limit' => $customer->credit_limit,
                    'adjustment_amount' => $customer->credit_limit - $previousCreditLimit,
                    'reason' => 'Credit limit set on account approval',
                    'admin_user_id' => $request->user()?->id,
                ]);
            }

            // Send notification to customer about approval
            // $customer->notify(new CustomerApprovedNotification());
        } else {
            // If rejected, keep status as inactive
            $customer->status = 'inactive';
        }

        $customer->save();

        // Clear caches
        Cache::forget('admin_customer_' . $id);
        for ($page = 1; $page <= 20; $page++) {
            Cache::forget('admin_customers_page_' . $page);
        }

        return response()->json([
            'message' => 'Customer approval status updated successfully',
            'customer' => $customer,
        ]);
    }

    public function updateCustomer(Request $request, $id)
    {
        $customer = Customer::findOrFail($id);

        $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:customers,email,' . $id,
            'username' => 'sometimes|string|unique:customers,username,' . $id,
            'password' => 'sometimes|string|min:8',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'minimum_purchase_amount' => 'sometimes|numeric|min:0',
            'payment_plan_duration' => 'sometimes|integer|min:1',
            'virtual_account_number' => 'nullable|string|unique:customers,virtual_account_number,' . $id,
            'virtual_account_bank' => 'nullable|string',
            'kyc_documents' => 'nullable|array',
            'kyc_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->has('business_name')) {
            $customer->business_name = $request->business_name;
        }
        if ($request->has('email')) {
            $customer->email = $request->email;
        }
        if ($request->has('username')) {
            $customer->username = $request->username;
        }
        if ($request->has('password')) {
            $customer->password = Hash::make($request->password);
        }
        if ($request->has('phone')) {
            $customer->phone = $request->phone;
        }
        if ($request->has('address')) {
            $customer->address = $request->address;
        }
        if ($request->has('minimum_purchase_amount')) {
            $customer->minimum_purchase_amount = $request->minimum_purchase_amount;
        }
        if ($request->has('payment_plan_duration')) {
            $customer->payment_plan_duration = $request->payment_plan_duration;
        }
        if ($request->has('virtual_account_number')) {
            $customer->virtual_account_number = $request->virtual_account_number;
        }
        if ($request->has('virtual_account_bank')) {
            $customer->virtual_account_bank = $request->virtual_account_bank;
        }

        if ($request->has('minimum_purchase_amount') || $request->has('payment_plan_duration')) {
            $this->creditLimitService->updateCustomerCreditLimit($customer);
        }

        if ($request->hasFile('kyc_documents')) {
            $kycPaths = $customer->kyc_documents ?? [];
            foreach ($request->file('kyc_documents') as $document) {
                $tempPath = $document->store('temp/kyc_documents', 'local');
                $s3Path = 'kyc_documents/customer_' . $customer->id . '/' . basename($tempPath);
                $kycPaths[] = $s3Path;
                UploadFileToS3Job::dispatch($tempPath, $s3Path, $customer, 'kyc_documents');
            }
            $customer->kyc_documents = $kycPaths;
        }

        $customer->save();
        Cache::forget('admin_customer_' . $id);
        // Clear all customer list page caches (clear first 20 pages as reasonable limit)
        for ($page = 1; $page <= 20; $page++) {
            Cache::forget('admin_customers_page_' . $page);
        }

        return response()->json([
            'message' => 'Customer updated successfully',
            'customer' => $customer,
        ]);
    }

    public function updateBusiness(Request $request, $id)
    {
        $business = Business::findOrFail($id);

        $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:businesses,email,' . $id,
            'username' => 'sometimes|string|unique:businesses,username,' . $id,
            'password' => 'sometimes|string|min:8',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'webhook_url' => 'nullable|url',
            'kyc_documents' => 'nullable|array',
            'kyc_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($request->has('business_name')) {
            $business->business_name = $request->business_name;
        }
        if ($request->has('email')) {
            $business->email = $request->email;
        }
        if ($request->has('username')) {
            $business->username = $request->username;
        }
        if ($request->has('password')) {
            $business->password = Hash::make($request->password);
        }
        if ($request->has('phone')) {
            $business->phone = $request->phone;
        }
        if ($request->has('address')) {
            $business->address = $request->address;
        }
        if ($request->has('webhook_url')) {
            $business->webhook_url = $request->webhook_url;
        }

        if ($request->hasFile('kyc_documents')) {
            $kycPaths = $business->kyc_documents ?? [];
            foreach ($request->file('kyc_documents') as $document) {
                $tempPath = $document->store('temp/kyc_documents', 'local');
                $s3Path = 'kyc_documents/business_' . $business->id . '/' . basename($tempPath);
                $kycPaths[] = $s3Path;
                UploadFileToS3Job::dispatch($tempPath, $s3Path, $business, 'kyc_documents');
            }
            $business->kyc_documents = $kycPaths;
        }

        $business->save();

        return response()->json([
            'message' => 'Business updated successfully',
            'business' => $business,
        ]);
    }

    public function getAllInvoices(Request $request)
    {
        $this->interestService->updateAllInvoices();

        $query = Invoice::with(['customer', 'businessCustomer', 'supplier', 'transactions'])
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $invoices = $query->paginate(20);

        // Add items and description to each invoice
        $invoices->getCollection()->transform(function ($invoice) {
            $invoice->items = $invoice->getItems();
            $invoice->description = $invoice->getDescription();
            return $invoice;
        });

        return response()->json($invoices);
    }

    public function updateInvoiceStatus(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,decline',
        ]);

        $invoice = Invoice::findOrFail($id);

        if ($request->action === 'approve' && $invoice->status === 'pending') {
            $invoice->status = 'in_grace';
            $invoice->save();
        }

        return response()->json([
            'message' => 'Invoice status updated successfully',
            'invoice' => $invoice,
        ]);
    }

    public function markInvoicePaid(Request $request, $id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'paid';
        $invoice->paid_amount = $invoice->total_amount;
        $invoice->remaining_balance = 0;
        $invoice->save();

        $invoice->customer->updateBalances();

        return response()->json([
            'message' => 'Invoice marked as paid',
            'invoice' => $invoice,
        ]);
    }

    public function getDashboardStats()
    {
        $cacheKey = 'admin_dashboard_stats';

        $stats = Cache::remember($cacheKey, 60, function () {
            $this->interestService->updateAllInvoices();

            return [
                'total_customers' => Customer::count(),
                'active_customers' => Customer::where('status', 'active')->count(),
                'total_exposure' => Customer::sum('current_balance'),
                'total_interest_earned' => Invoice::sum('interest_amount'),
                'overdue_invoices' => Invoice::where('status', 'overdue')->count(),
                'total_invoices' => Invoice::count(),
            ];
        });

        return response()->json($stats);
    }

    public function createBusiness(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'email' => 'required|email|unique:businesses,email',
            'username' => 'required|string|unique:businesses,username',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'webhook_url' => 'nullable|url',
            'kyc_documents' => 'nullable|array',
            'kyc_documents.*' => 'file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $business = Business::create([
            'business_name' => $request->business_name,
            'email' => $request->email,
            'username' => $request->username,
            'password' => Hash::make($request->password),
            'phone' => $request->phone,
            'address' => $request->address,
            'approval_status' => 'pending',
            'status' => 'inactive',
            'webhook_url' => $request->webhook_url,
        ]);

        if ($request->hasFile('kyc_documents')) {
            $kycPaths = [];
            foreach ($request->file('kyc_documents') as $document) {
                $tempPath = $document->store('temp/kyc_documents', 'local');
                $s3Path = 'kyc_documents/business_' . $business->id . '/' . basename($tempPath);
                $kycPaths[] = $s3Path;
                UploadFileToS3Job::dispatch($tempPath, $s3Path, $business, 'kyc_documents');
            }
            $business->kyc_documents = $kycPaths;
            $business->save();
        }

        // Send notification (non-blocking - wrapped in try-catch to prevent failures)
        try {
            $business->notify(new BusinessCreatedNotification($request->password));
        } catch (\Exception $e) {
            // Log the error but don't fail the request
            \Log::warning('Failed to send business creation notification: ' . $e->getMessage(), [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Business created successfully',
            'business' => [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'email' => $business->email,
                'username' => $business->username,
                'approval_status' => $business->approval_status,
            ],
        ], 201);
    }

    public function getBusinesses(Request $request)
    {
        $businesses = Business::withCount('invoices')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($businesses);
    }

    public function getBusiness($id)
    {
        $business = Business::with(['invoices', 'transactions'])
            ->findOrFail($id);

        return response()->json([
            'business' => $business,
        ]);
    }

    public function approveBusiness(Request $request, $id)
    {
        $request->validate([
            'approval_status' => 'required|in:approved,rejected',
        ]);

        $business = Business::findOrFail($id);
        $business->approval_status = $request->approval_status;
        
        if ($request->approval_status === 'approved') {
            $business->status = 'active';
            if (!$business->api_token) {
                $business->generateApiToken();
            }
            $business->save();
            $business->notify(new BusinessApprovedNotification());
        } else {
            $business->status = 'inactive';
            $business->save();
        }

        return response()->json([
            'message' => 'Business approval status updated successfully',
            'business' => $business,
        ]);
    }

    public function updateBusinessStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:active,suspended,inactive',
        ]);

        $business = Business::findOrFail($id);
        $business->status = $request->status;
        $business->save();

        return response()->json([
            'message' => 'Business status updated successfully',
            'business' => $business,
        ]);
    }

    public function getAllWithdrawals(Request $request)
    {
        $query = Withdrawal::with('business')
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        $withdrawals = $query->paginate(20);

        return response()->json($withdrawals);
    }

    public function approveWithdrawal(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'rejection_reason' => 'required_if:action,reject|string|nullable',
        ]);

        $withdrawal = Withdrawal::with('business')->findOrFail($id);

        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'message' => 'Withdrawal request has already been processed',
                'withdrawal' => $withdrawal,
            ], 400);
        }

        if ($request->action === 'approve') {
            $availableBalance = $withdrawal->business->getAvailableBalance();

            if ($withdrawal->amount > $availableBalance) {
                return response()->json([
                    'message' => 'Insufficient balance for withdrawal',
                    'available_balance' => $availableBalance,
                    'requested_amount' => $withdrawal->amount,
                ], 400);
            }

            $withdrawal->status = 'approved';
            $withdrawal->processed_at = now();
            $withdrawal->save();

            Cache::forget('admin_dashboard_stats');

            return response()->json([
                'message' => 'Withdrawal approved successfully',
                'withdrawal' => $withdrawal->load('business'),
            ]);
        } else {
            $withdrawal->status = 'rejected';
            $withdrawal->rejection_reason = $request->rejection_reason;
            $withdrawal->save();

            return response()->json([
                'message' => 'Withdrawal rejected',
                'withdrawal' => $withdrawal->load('business'),
            ]);
        }
    }

    public function processWithdrawal(Request $request, $id)
    {
        $withdrawal = Withdrawal::with('business')->findOrFail($id);

        if ($withdrawal->status !== 'approved') {
            return response()->json([
                'message' => 'Withdrawal must be approved before processing',
                'withdrawal' => $withdrawal,
            ], 400);
        }

        $withdrawal->status = 'processed';
        $withdrawal->processed_at = now();
        $withdrawal->save();

        Cache::forget('admin_dashboard_stats');

        return response()->json([
            'message' => 'Withdrawal processed successfully',
            'withdrawal' => $withdrawal->load('business'),
        ]);
    }

    /**
     * Get all business customers (separate from main customers)
     */
    public function getBusinessCustomers(Request $request)
    {
        $query = BusinessCustomer::with(['business', 'linkedCustomer'])
            ->orderBy('created_at', 'desc');

        // Filter by business
        if ($request->has('business_id')) {
            $query->where('business_id', $request->business_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by linked status
        if ($request->has('linked')) {
            if ($request->linked === 'true') {
                $query->whereNotNull('linked_customer_id');
            } else {
                $query->whereNull('linked_customer_id');
            }
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('contact_phone', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%")
                  ->orWhere('registration_number', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'business_customers' => $customers,
        ]);
    }

    /**
     * Get a specific business customer
     */
    public function getBusinessCustomer($id)
    {
        $businessCustomer = BusinessCustomer::with(['business', 'linkedCustomer', 'invoices'])
            ->findOrFail($id);

        return response()->json([
            'business_customer' => $businessCustomer,
        ]);
    }

    /**
     * Get all transactions (unified view of all transaction types)
     * Includes: Transactions, Payments, Withdrawals, Credit Limit Adjustments, Payouts
     */
    public function getAllTransactions(Request $request)
    {
        $perPage = $request->get('per_page', 50);
        $type = $request->get('type'); // Filter by type: transaction, payment, withdrawal, credit_adjustment, payout
        $customerId = $request->get('customer_id');
        $businessId = $request->get('business_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $transactions = collect();

        // 1. Get all Transactions (credit_purchase, repayment, payout, refund)
        if (!$type || $type === 'transaction') {
            $txns = Transaction::with(['customer', 'business', 'invoice'])
                ->when($customerId, function ($q) use ($customerId) {
                    $q->where('customer_id', $customerId);
                })
                ->when($businessId, function ($q) use ($businessId) {
                    $q->where('business_id', $businessId);
                })
                ->when($dateFrom, function ($q) use ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                })
                ->when($dateTo, function ($q) use ($dateTo) {
                    $q->whereDate('created_at', '<=', $dateTo);
                })
                ->get()
                ->map(function ($txn) {
                    return [
                        'id' => $txn->id,
                        'reference' => $txn->transaction_reference,
                        'type' => 'transaction',
                        'transaction_type' => $txn->type, // credit_purchase, repayment, payout, refund
                        'amount' => $txn->amount,
                        'status' => $txn->status,
                        'description' => $txn->description,
                        'customer' => $txn->customer ? [
                            'id' => $txn->customer->id,
                            'business_name' => $txn->customer->business_name,
                            'account_number' => $txn->customer->account_number,
                        ] : null,
                        'business' => $txn->business ? [
                            'id' => $txn->business->id,
                            'business_name' => $txn->business->business_name,
                        ] : null,
                        'invoice' => $txn->invoice ? [
                            'id' => $txn->invoice->id,
                            'invoice_id' => $txn->invoice->invoice_id,
                        ] : null,
                        'metadata' => $txn->metadata,
                        'processed_at' => $txn->processed_at?->format('Y-m-d H:i:s'),
                        'created_at' => $txn->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            $transactions = $transactions->merge($txns);
        }

        // 2. Get all Payments (customer repayments)
        if (!$type || $type === 'payment') {
            $payments = Payment::with(['customer', 'invoice'])
                ->when($customerId, function ($q) use ($customerId) {
                    $q->where('customer_id', $customerId);
                })
                ->when($dateFrom, function ($q) use ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                })
                ->when($dateTo, function ($q) use ($dateTo) {
                    $q->whereDate('created_at', '<=', $dateTo);
                })
                ->get()
                ->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'reference' => $payment->payment_reference,
                        'type' => 'payment',
                        'transaction_type' => 'repayment',
                        'amount' => $payment->amount,
                        'status' => $payment->status,
                        'description' => "Payment: {$payment->payment_reference}",
                        'customer' => $payment->customer ? [
                            'id' => $payment->customer->id,
                            'business_name' => $payment->customer->business_name,
                            'account_number' => $payment->customer->account_number,
                        ] : null,
                        'business' => null,
                        'invoice' => $payment->invoice ? [
                            'id' => $payment->invoice->id,
                            'invoice_id' => $payment->invoice->invoice_id,
                        ] : null,
                        'metadata' => null,
                        'processed_at' => $payment->paid_at?->format('Y-m-d H:i:s'),
                        'created_at' => $payment->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            $transactions = $transactions->merge($payments);
        }

        // 3. Get all Withdrawals (business withdrawals)
        if (!$type || $type === 'withdrawal') {
            $withdrawals = Withdrawal::with(['business'])
                ->when($businessId, function ($q) use ($businessId) {
                    $q->where('business_id', $businessId);
                })
                ->when($dateFrom, function ($q) use ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                })
                ->when($dateTo, function ($q) use ($dateTo) {
                    $q->whereDate('created_at', '<=', $dateTo);
                })
                ->get()
                ->map(function ($withdrawal) {
                    return [
                        'id' => $withdrawal->id,
                        'reference' => $withdrawal->withdrawal_reference,
                        'type' => 'withdrawal',
                        'transaction_type' => 'withdrawal',
                        'amount' => $withdrawal->amount,
                        'status' => $withdrawal->status,
                        'description' => "Withdrawal: {$withdrawal->withdrawal_reference}",
                        'customer' => null,
                        'business' => $withdrawal->business ? [
                            'id' => $withdrawal->business->id,
                            'business_name' => $withdrawal->business->business_name,
                        ] : null,
                        'invoice' => null,
                        'metadata' => [
                            'bank_name' => $withdrawal->bank_name,
                            'account_number' => $withdrawal->account_number,
                            'account_name' => $withdrawal->account_name,
                        ],
                        'processed_at' => $withdrawal->processed_at?->format('Y-m-d H:i:s'),
                        'created_at' => $withdrawal->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            $transactions = $transactions->merge($withdrawals);
        }

        // 4. Get all Credit Limit Adjustments (admin credit limit changes)
        if (!$type || $type === 'credit_adjustment') {
            $adjustments = CreditLimitAdjustment::with(['customer', 'adminUser'])
                ->when($customerId, function ($q) use ($customerId) {
                    $q->where('customer_id', $customerId);
                })
                ->when($dateFrom, function ($q) use ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                })
                ->when($dateTo, function ($q) use ($dateTo) {
                    $q->whereDate('created_at', '<=', $dateTo);
                })
                ->get()
                ->map(function ($adjustment) {
                    return [
                        'id' => $adjustment->id,
                        'reference' => 'CLA-' . str_pad($adjustment->id, 8, '0', STR_PAD_LEFT),
                        'type' => 'credit_adjustment',
                        'transaction_type' => 'credit_limit_adjustment',
                        'amount' => abs($adjustment->adjustment_amount), // Absolute value for display
                        'adjustment_amount' => $adjustment->adjustment_amount, // Can be positive or negative
                        'status' => 'completed',
                        'description' => $adjustment->reason ?? 'Credit limit adjustment',
                        'customer' => $adjustment->customer ? [
                            'id' => $adjustment->customer->id,
                            'business_name' => $adjustment->customer->business_name,
                            'account_number' => $adjustment->customer->account_number,
                        ] : null,
                        'business' => null,
                        'invoice' => null,
                        'metadata' => [
                            'previous_credit_limit' => $adjustment->previous_credit_limit,
                            'new_credit_limit' => $adjustment->new_credit_limit,
                            'admin_user_id' => $adjustment->admin_user_id,
                        ],
                        'processed_at' => $adjustment->created_at->format('Y-m-d H:i:s'),
                        'created_at' => $adjustment->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            $transactions = $transactions->merge($adjustments);
        }

        // 5. Get all Payouts (business payouts from invoices)
        if (!$type || $type === 'payout') {
            $payouts = Payout::with(['invoice.customer', 'invoice.supplier'])
                ->when($dateFrom, function ($q) use ($dateFrom) {
                    $q->whereDate('created_at', '>=', $dateFrom);
                })
                ->when($dateTo, function ($q) use ($dateTo) {
                    $q->whereDate('created_at', '<=', $dateTo);
                })
                ->get()
                ->map(function ($payout) {
                    return [
                        'id' => $payout->id,
                        'reference' => $payout->payout_reference,
                        'type' => 'payout',
                        'transaction_type' => 'payout',
                        'amount' => $payout->amount,
                        'status' => $payout->status,
                        'description' => "Payout: {$payout->payout_reference}",
                        'customer' => $payout->invoice && $payout->invoice->customer ? [
                            'id' => $payout->invoice->customer->id,
                            'business_name' => $payout->invoice->customer->business_name,
                            'account_number' => $payout->invoice->customer->account_number,
                        ] : null,
                        'business' => $payout->invoice && $payout->invoice->supplier ? [
                            'id' => $payout->invoice->supplier->id,
                            'business_name' => $payout->invoice->supplier->business_name,
                        ] : null,
                        'invoice' => $payout->invoice ? [
                            'id' => $payout->invoice->id,
                            'invoice_id' => $payout->invoice->invoice_id,
                        ] : null,
                        'metadata' => [
                            'supplier_name' => $payout->supplier_name,
                        ],
                        'processed_at' => $payout->paid_at?->format('Y-m-d H:i:s'),
                        'created_at' => $payout->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            $transactions = $transactions->merge($payouts);
        }

        // Sort all transactions by created_at (most recent first)
        $transactions = $transactions->sortByDesc('created_at')->values();

        // Paginate manually
        $currentPage = $request->get('page', 1);
        $total = $transactions->count();
        $perPage = (int) $perPage;
        $offset = ($currentPage - 1) * $perPage;
        $items = $transactions->slice($offset, $perPage)->values();

        return response()->json([
            'success' => true,
            'transactions' => $items,
            'pagination' => [
                'current_page' => (int) $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => (int) ceil($total / $perPage),
                'from' => $offset + 1,
                'to' => min($offset + $perPage, $total),
            ],
        ]);
    }

    /**
     * Get all pending payment confirmations
     */
    public function getPendingPayments(Request $request)
    {
        $payments = Payment::with(['customer', 'invoice'])
            ->where('admin_confirmation_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'payments' => $payments,
        ]);
    }

    /**
     * Confirm customer payment claim
     */
    public function confirmPayment(Request $request, $id)
    {
        $payment = Payment::with(['customer', 'invoice'])->findOrFail($id);

        if ($payment->admin_confirmation_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Payment has already been {$payment->admin_confirmation_status}",
            ], 400);
        }

        DB::beginTransaction();
        try {
            // Update payment confirmation status
            $payment->admin_confirmation_status = 'confirmed';
            $payment->admin_confirmed_by = $request->user()?->id;
            $payment->admin_confirmed_at = now();
            $payment->status = 'completed';
            $payment->paid_at = now();
            $payment->save();

            $customer = $payment->customer;

            // Process the payment if invoice is specified
            if ($payment->invoice_id) {
                $invoice = $payment->invoice;
                
                // Calculate interest if needed
                if ($invoice->due_date && $invoice->status !== 'paid') {
                    $this->interestService->updateInvoiceStatus($invoice);
                    $invoice->refresh();
                }

                // Apply payment to invoice
                $invoice->paid_amount += $payment->amount;
                $invoice->remaining_balance -= $payment->amount;

                // Check if this is credit repayment (invoice already paid to business)
                if ($invoice->status === 'paid') {
                    // This payment is repaying the credit used
                    $remainingCreditToRepay = $invoice->total_amount - ($invoice->credit_repaid_amount ?? 0);
                    $paymentAmount = min($payment->amount, $remainingCreditToRepay);
                    
                    $invoice->credit_repaid_amount = ($invoice->credit_repaid_amount ?? 0) + $paymentAmount;
                    
                    if ($invoice->credit_repaid_amount >= $invoice->total_amount) {
                        $invoice->credit_repaid_status = 'fully_paid';
                        $invoice->credit_repaid_at = now();
                        $invoice->credit_repaid_amount = $invoice->total_amount;
                    } elseif ($invoice->credit_repaid_amount > 0) {
                        $invoice->credit_repaid_status = 'partially_paid';
                    }
                } else {
                    // Invoice is being paid for the first time
                    if ($invoice->remaining_balance <= 0) {
                        $invoice->status = 'paid';
                        $invoice->remaining_balance = 0;
                        if ($invoice->credit_repaid_status === null) {
                            $invoice->credit_repaid_status = 'pending';
                            $invoice->credit_repaid_amount = 0;
                        }
                    } else {
                        if ($invoice->status === 'pending') {
                            $invoice->status = 'in_grace';
                        }
                    }
                }

                $invoice->save();

                // Process payout to business if invoice is fully paid
                if ($invoice->status === 'paid' && $invoice->supplier_id && !$invoice->payouts()->exists()) {
                    $payoutService = app(\App\Services\PaymentService::class);
                    $payoutService->processPayout($invoice);
                }
            } else {
                // General repayment - apply to invoices in order
                $this->processGeneralRepayment($customer, $payment->amount, $payment);
            }

            // Update customer balances
            $customer->updateBalances();

            // Create transaction record
            Transaction::create([
                'customer_id' => $customer->id,
                'invoice_id' => $payment->invoice_id,
                'type' => 'repayment',
                'amount' => $payment->amount,
                'status' => 'completed',
                'description' => $payment->invoice_id 
                    ? "Payment confirmed for invoice {$payment->invoice->invoice_id}"
                    : "Payment confirmed: {$payment->payment_reference}",
                'processed_at' => now(),
            ]);

            Cache::forget('customer_credit_' . $customer->id);
            Cache::forget('customer_invoices_' . $customer->id);
            Cache::forget('admin_customer_' . $customer->id);
            Cache::forget('admin_dashboard_stats');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed and processed successfully',
                'payment' => $payment->load(['customer', 'invoice']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment confirmation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Payment confirmation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject customer payment claim
     */
    public function rejectPayment(Request $request, $id)
    {
        $request->validate([
            'rejection_reason' => 'required|string',
        ]);

        $payment = Payment::with(['customer', 'invoice'])->findOrFail($id);

        if ($payment->admin_confirmation_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => "Payment has already been {$payment->admin_confirmation_status}",
            ], 400);
        }

        $payment->admin_confirmation_status = 'rejected';
        $payment->admin_confirmed_by = $request->user()?->id;
        $payment->admin_confirmed_at = now();
        $payment->admin_rejection_reason = $request->rejection_reason;
        $payment->save();

        Cache::forget('customer_invoices_' . $payment->customer_id);
        Cache::forget('customer_credit_' . $payment->customer_id);

        return response()->json([
            'success' => true,
            'message' => 'Payment claim rejected',
            'payment' => $payment,
        ]);
    }

    /**
     * Process general repayment (when no specific invoice is provided)
     */
    protected function processGeneralRepayment(Customer $customer, float $amount, Payment $payment)
    {
        $invoices = $customer->invoices()
            ->where(function ($query) {
                $query->where('status', '!=', 'paid')
                    ->orWhere(function ($q) {
                        $q->where('status', 'paid')
                          ->where(function ($subQ) {
                              $subQ->whereNull('credit_repaid_status')
                                   ->orWhere('credit_repaid_status', '!=', 'fully_paid');
                          });
                    });
            })
            ->orderByRaw('due_date IS NULL, due_date ASC')
            ->get();

        $remainingAmount = $amount;

        foreach ($invoices as $invoice) {
            if ($remainingAmount <= 0) {
                break;
            }

            if ($invoice->status === 'paid') {
                // Repaying credit
                $remainingCreditToRepay = $invoice->total_amount - ($invoice->credit_repaid_amount ?? 0);
                $paymentAmount = min($remainingAmount, $remainingCreditToRepay);
                
                if ($paymentAmount > 0) {
                    $invoice->credit_repaid_amount = ($invoice->credit_repaid_amount ?? 0) + $paymentAmount;
                    
                    if ($invoice->credit_repaid_amount >= $invoice->total_amount) {
                        $invoice->credit_repaid_status = 'fully_paid';
                        $invoice->credit_repaid_at = now();
                        $invoice->credit_repaid_amount = $invoice->total_amount;
                    } elseif ($invoice->credit_repaid_amount > 0) {
                        $invoice->credit_repaid_status = 'partially_paid';
                    }
                    
                    $invoice->save();
                    $remainingAmount -= $paymentAmount;
                }
            } else {
                // Paying invoice
                $paymentAmount = min($remainingAmount, $invoice->remaining_balance);
                
                if ($invoice->due_date && $invoice->status !== 'paid') {
                    $this->interestService->updateInvoiceStatus($invoice);
                    $invoice->refresh();
                }
                
                $invoice->paid_amount += $paymentAmount;
                $invoice->remaining_balance -= $paymentAmount;

                if ($invoice->remaining_balance <= 0) {
                    $invoice->status = 'paid';
                    $invoice->remaining_balance = 0;
                    if ($invoice->credit_repaid_status === null) {
                        $invoice->credit_repaid_status = 'pending';
                        $invoice->credit_repaid_amount = 0;
                    }
                } else {
                    if ($invoice->status === 'pending') {
                        $invoice->status = 'in_grace';
                    }
                }

                $invoice->save();
                $payment->invoice_id = $invoice->id;
                $payment->save();
                $remainingAmount -= $paymentAmount;
            }
        }
    }
}
