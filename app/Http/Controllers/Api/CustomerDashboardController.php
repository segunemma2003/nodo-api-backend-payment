<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CreateVirtualAccountJob;
use App\Models\CreditLimitAdjustment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\InterestService;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CustomerDashboardController extends Controller
{
    protected InterestService $interestService;
    protected PaystackService $paystackService;

    public function __construct(InterestService $interestService, PaystackService $paystackService)
    {
        $this->interestService = $interestService;
        $this->paystackService = $paystackService;
    }

    /**
     * Get customer credit overview
     */
    public function getCreditOverview(Request $request)
    {
        $customer = $this->getCustomer($request);
        $customer->updateBalances();

        $cacheKey = 'customer_credit_' . $customer->id;
        $data = Cache::remember($cacheKey, 60, function () use ($customer) {
            return [
                'credit_limit' => $customer->credit_limit,
                'current_balance' => $customer->current_balance,
                'available_balance' => $customer->available_balance,
            ];
        });

        return response()->json($data);
    }

    /**
     * Get customer dashboard with detailed statistics
     */
    public function getDashboard(Request $request)
    {
        $customer = $this->getCustomer($request);
        
        // Update invoice interest first (only for unpaid invoices)
        $this->interestService->updateAllInvoices();
        
        // Then update balances (this calculates remaining_balance for paid invoices)
        $customer->updateBalances();

        // Invoice Statistics
        $totalInvoices = $customer->invoices()->count();
        $paidInvoices = $customer->invoices()->where('status', 'paid')->count();
        $pendingInvoices = $customer->invoices()->where('status', 'pending')->count();
        $inGraceInvoices = $customer->invoices()->where('status', 'in_grace')->count();
        $overdueInvoices = $customer->invoices()->where('status', 'overdue')->count();

        // Amount Statistics
        $totalAmountOwed = $customer->invoices()
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'pending')
            ->sum('remaining_balance');
        
        $totalAmountPaid = $customer->invoices()
            ->where('status', 'paid')
            ->sum('paid_amount');

        $totalPrincipalPaid = $customer->invoices()
            ->where('status', 'paid')
            ->sum('principal_amount');

        $totalInterestPaid = $customer->invoices()
            ->where('status', 'paid')
            ->sum('interest_amount');

        // Credit Statistics
        $creditUtilizationPercent = $customer->credit_limit > 0 
            ? round(($customer->current_balance / $customer->credit_limit) * 100, 2)
            : 0;

        // Recent Transactions (last 5)
        $recentTransactions = Transaction::where('customer_id', $customer->id)
            ->with(['business', 'invoice'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($txn) {
                return [
                    'id' => $txn->id,
                    'reference' => $txn->transaction_reference,
                    'type' => $txn->type,
                    'amount' => $txn->amount,
                    'status' => $txn->status,
                    'description' => $txn->description,
                    'business_name' => $txn->business?->business_name,
                    'created_at' => $txn->created_at->format('Y-m-d H:i:s'),
                ];
            });

        // Upcoming Due Dates (next 5)
        $upcomingDueDates = $customer->invoices()
            ->where('status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->where('due_date', '>=', now())
            ->orderBy('due_date', 'asc')
            ->limit(5)
            ->get()
            ->map(function ($invoice) {
                return [
                    'invoice_id' => $invoice->invoice_id,
                    'due_date' => $invoice->due_date->format('Y-m-d'),
                    'remaining_balance' => $invoice->remaining_balance,
                    'total_amount' => $invoice->total_amount,
                    'supplier_name' => $invoice->supplier_name,
                    'days_until_due' => now()->diffInDays($invoice->due_date, false),
                ];
            });

        // Payment Statistics
        $totalPayments = Payment::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->count();
        
        $pendingPaymentConfirmations = Payment::where('customer_id', $customer->id)
            ->where('admin_confirmation_status', 'pending')
            ->count();

        $totalPaidAmount = Payment::where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->sum('amount');

        // Credit Repayment Statistics
        $creditFullyRepaid = $customer->invoices()
            ->where('credit_repaid_status', 'fully_paid')
            ->count();
        
        $creditPartiallyRepaid = $customer->invoices()
            ->where('credit_repaid_status', 'partially_paid')
            ->count();
        
        $creditPendingRepayment = $customer->invoices()
            ->where('credit_repaid_status', 'pending')
            ->orWhereNull('credit_repaid_status')
            ->count();

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'business_name' => $customer->business_name,
                'account_number' => $customer->account_number,
            ],
            'credit' => [
                'credit_limit' => $customer->credit_limit,
                'current_balance' => $customer->current_balance,
                'available_balance' => $customer->available_balance,
                'credit_utilization_percent' => $creditUtilizationPercent,
            ],
            'invoice_statistics' => [
                'total_invoices' => $totalInvoices,
                'paid_invoices' => $paidInvoices,
                'pending_invoices' => $pendingInvoices,
                'in_grace_invoices' => $inGraceInvoices,
                'overdue_invoices' => $overdueInvoices,
            ],
            'amount_statistics' => [
                'total_amount_owed' => (string) $totalAmountOwed,
                'total_amount_paid' => (string) $totalAmountPaid,
                'total_principal_paid' => (string) $totalPrincipalPaid,
                'total_interest_paid' => (string) $totalInterestPaid,
            ],
            'payment_statistics' => [
                'total_payments' => $totalPayments,
                'pending_confirmations' => $pendingPaymentConfirmations,
                'total_paid_amount' => (string) $totalPaidAmount,
            ],
            'credit_repayment_statistics' => [
                'fully_repaid' => $creditFullyRepaid,
                'partially_repaid' => $creditPartiallyRepaid,
                'pending_repayment' => $creditPendingRepayment,
            ],
            'recent_transactions' => $recentTransactions,
            'upcoming_due_dates' => $upcomingDueDates,
        ]);
    }

    /**
     * Get all customer invoices
     */
    public function getInvoices(Request $request)
    {
        $customer = $this->getCustomer($request);
        
        // Clear cache to ensure latest data including credit repayment status
        $cacheKey = 'customer_invoices_' . $customer->id;
        Cache::forget($cacheKey);
        
        $this->interestService->updateAllInvoices();

        $invoices = $customer->invoices()
            ->with('transactions')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($invoice) {
                return [
                    'invoice_id' => $invoice->invoice_id,
                    'purchase_date' => $invoice->purchase_date->format('Y-m-d'),
                    'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null,
                    'grace_period_end_date' => $invoice->grace_period_end_date ? $invoice->grace_period_end_date->format('Y-m-d') : null,
                    'status' => $invoice->status,
                    'principal_amount' => $invoice->principal_amount,
                    'interest_amount' => $invoice->interest_amount,
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $invoice->paid_amount,
                    'remaining_balance' => $invoice->remaining_balance,
                    'supplier_name' => $invoice->supplier_name,
                    'months_overdue' => $invoice->months_overdue,
                    'description' => $invoice->getDescription(),
                    'items' => $invoice->getItems(),
                    'credit_repaid_status' => $invoice->credit_repaid_status ?? 'pending',
                    'credit_repaid_amount' => $invoice->credit_repaid_amount ?? '0.00',
                    'credit_repaid_at' => $invoice->credit_repaid_at ? $invoice->credit_repaid_at->format('Y-m-d H:i:s') : null,
                ];
        });

        return response()->json([
            'invoices' => $invoices,
        ]);
    }

    /**
     * Get single invoice details
     */
    public function getInvoice(Request $request, $invoiceId)
    {
        $customer = $this->getCustomer($request);
        
        $invoice = $customer->invoices()
            ->with('transactions')
            ->where('invoice_id', $invoiceId)
            ->firstOrFail();

        $this->interestService->updateInvoiceStatus($invoice);
        $invoice->refresh();

        return response()->json([
            'invoice' => [
                'invoice_id' => $invoice->invoice_id,
                'purchase_date' => $invoice->purchase_date->format('Y-m-d'),
                'due_date' => $invoice->due_date ? $invoice->due_date->format('Y-m-d') : null,
                'grace_period_end_date' => $invoice->grace_period_end_date ? $invoice->grace_period_end_date->format('Y-m-d') : null,
                'status' => $invoice->status,
                'principal_amount' => $invoice->principal_amount,
                'interest_amount' => $invoice->interest_amount,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'remaining_balance' => $invoice->remaining_balance,
                'supplier_name' => $invoice->supplier_name,
                'months_overdue' => $invoice->months_overdue,
                'description' => $invoice->getDescription(),
                'items' => $invoice->getItems(),
                'credit_repaid_status' => $invoice->credit_repaid_status ?? 'pending',
                'credit_repaid_amount' => $invoice->credit_repaid_amount ?? '0.00',
                'credit_repaid_at' => $invoice->credit_repaid_at ? $invoice->credit_repaid_at->format('Y-m-d H:i:s') : null,
            ],
        ]);
    }

    /**
     * Get repayment bank account details
     */
    public function getRepaymentAccount(Request $request)
    {
        $customer = $this->getCustomer($request);
        
        $refresh = $request->get('refresh', false);
        
        if ($refresh && $this->paystackService->isConfigured() && !empty($customer->paystack_customer_code)) {
            try {
                $accountDetails = $this->paystackService->fetchDedicatedAccountByCustomer($customer->paystack_customer_code);
                
                if ($accountDetails && isset($accountDetails['account_number'])) {
                    $customer->virtual_account_number = $accountDetails['account_number'] ?? null;
                    $customer->virtual_account_bank = $accountDetails['bank']['name'] ?? null;
                    $customer->paystack_dedicated_account_id = $accountDetails['id'] ?? null;
                    $customer->save();
                    
                    Log::info('Virtual account refreshed from Paystack', [
                        'customer_id' => $customer->id,
                        'virtual_account_number' => $customer->virtual_account_number,
                    ]);
                }
            } catch (\Exception $e) {
                Log::warning('Failed to refresh virtual account from Paystack', [
                    'customer_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'virtual_account_number' => $customer->virtual_account_number,
            'virtual_account_bank' => $customer->virtual_account_bank,
            'has_virtual_account' => !empty($customer->virtual_account_number),
            'status' => !empty($customer->virtual_account_number) ? 'active' : 'pending',
        ]);
    }
    
    /**
     * Refresh virtual account from Paystack
     * Fetches the latest account details from Paystack and updates the customer record
     */
    public function refreshVirtualAccount(Request $request)
    {
        $customer = $this->getCustomer($request);

        if (!$this->paystackService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Paystack is not configured.',
            ], 503);
        }

        if (empty($customer->paystack_customer_code)) {
            return response()->json([
                'success' => false,
                'message' => 'Customer does not have a Paystack customer code. Please generate a virtual account first.',
            ], 400);
        }

        try {
            $accountDetails = $this->paystackService->fetchDedicatedAccountByCustomer($customer->paystack_customer_code);
            
            if (!$accountDetails || !isset($accountDetails['account_number'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Virtual account not yet available. It may still be in progress. Please try again in a few moments.',
                    'status' => 'pending',
                ], 202);
            }

            $customer->virtual_account_number = $accountDetails['account_number'] ?? null;
            $customer->virtual_account_bank = $accountDetails['bank']['name'] ?? null;
            $customer->paystack_dedicated_account_id = $accountDetails['id'] ?? null;
            $customer->save();

            Log::info('Virtual account refreshed from Paystack', [
                'customer_id' => $customer->id,
                'virtual_account_number' => $customer->virtual_account_number,
            ]);

            Cache::forget('customer_credit_' . $customer->id);
            Cache::forget('customer_invoices_' . $customer->id);

            return response()->json([
                'success' => true,
                'message' => 'Virtual account refreshed successfully',
                'virtual_account_number' => $customer->virtual_account_number,
                'virtual_account_bank' => $customer->virtual_account_bank,
                'has_virtual_account' => true,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to refresh virtual account from Paystack', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh virtual account: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate virtual account for existing customer
     * Dispatches a queue job to create the virtual account asynchronously
     */
    public function generateVirtualAccount(Request $request)
    {
        $customer = $this->getCustomer($request);

        // Check if customer already has a virtual account
        if (!empty($customer->virtual_account_number)) {
            return response()->json([
                'success' => false,
                'message' => 'Virtual account already exists for this customer',
                'virtual_account_number' => $customer->virtual_account_number,
                'virtual_account_bank' => $customer->virtual_account_bank,
            ], 400);
        }

        // Check if Paystack is configured
        if (!$this->paystackService->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Paystack is not configured. Please set PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY in your .env file and contact administrator.',
                'paystack_configured' => false,
            ], 503);
        }

        // Dispatch job to create virtual account asynchronously
        // This doesn't block the request
        CreateVirtualAccountJob::dispatch($customer);

        Log::info('Virtual account generation job dispatched for existing customer', [
            'customer_id' => $customer->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Virtual account generation has been queued. Your virtual account will be created shortly. Please check your account details in a few moments.',
            'status' => 'queued',
            'customer_id' => $customer->id,
            'note' => 'You can check your virtual account status by calling GET /api/customer/repayment-account',
        ], 202); // 202 Accepted - request accepted for processing
    }

    /**
     * Get customer transactions
     */
    public function getTransactions(Request $request)
    {
        $customer = $this->getCustomer($request);
        $type = $request->get('type'); // Filter by type: transaction, payment, credit_adjustment
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');
        $perPage = $request->get('per_page', 20);

        $allTransactions = collect();

        // 1. Get all Transactions (credit_purchase, repayment, etc.)
        if (!$type || $type === 'transaction') {
            $txns = Transaction::where('customer_id', $customer->id)
            ->with(['business', 'invoice'])
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
                        'transaction_type' => $txn->type,
                        'amount' => $txn->amount,
                        'status' => $txn->status,
                        'description' => $txn->description,
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

            $allTransactions = $allTransactions->merge($txns);
        }

        // 2. Get all Payments (repayments)
        if (!$type || $type === 'payment') {
            $payments = Payment::where('customer_id', $customer->id)
                ->with(['invoice'])
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

            $allTransactions = $allTransactions->merge($payments);
        }

        // 3. Get all Credit Limit Adjustments (admin credit additions/changes)
        if (!$type || $type === 'credit_adjustment') {
            $adjustments = CreditLimitAdjustment::where('customer_id', $customer->id)
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
                        'amount' => abs($adjustment->adjustment_amount),
                        'adjustment_amount' => $adjustment->adjustment_amount, // Can be positive or negative
                        'status' => 'completed',
                        'description' => $adjustment->reason ?? 'Credit limit adjustment',
                        'business' => null,
                        'invoice' => null,
                        'metadata' => [
                            'previous_credit_limit' => $adjustment->previous_credit_limit,
                            'new_credit_limit' => $adjustment->new_credit_limit,
                        ],
                        'processed_at' => $adjustment->created_at->format('Y-m-d H:i:s'),
                        'created_at' => $adjustment->created_at->format('Y-m-d H:i:s'),
                    ];
                });

            $allTransactions = $allTransactions->merge($adjustments);
        }

        // Sort all transactions by created_at (most recent first)
        $allTransactions = $allTransactions->sortByDesc('created_at')->values();

        // Paginate manually
        $currentPage = $request->get('page', 1);
        $total = $allTransactions->count();
        $perPage = (int) $perPage;
        $offset = ($currentPage - 1) * $perPage;
        $items = $allTransactions->slice($offset, $perPage)->values();

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
     * Get customer profile
     */
    public function getProfile(Request $request)
    {
        $customer = $this->getCustomer($request);
        $customer->updateBalances();

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'account_number' => $customer->account_number,
                'cvv' => $customer->cvv,
                'business_name' => $customer->business_name,
                'email' => $customer->email,
                'username' => $customer->username,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'credit_limit' => $customer->credit_limit,
                'current_balance' => $customer->current_balance,
                'available_balance' => $customer->available_balance,
                'virtual_account_number' => $customer->virtual_account_number,
                'virtual_account_bank' => $customer->virtual_account_bank,
                'kyc_documents' => $customer->kyc_documents,
                'status' => $customer->status,
            ],
        ]);
    }

    /**
     * Update customer profile
     */
    public function updateProfile(Request $request)
    {
        $customer = $this->getCustomer($request);

        $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:customers,email,' . $customer->id,
            'username' => 'sometimes|string|unique:customers,username,' . $customer->id,
            'password' => 'sometimes|string|min:8',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
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

        if ($request->hasFile('kyc_documents')) {
            $kycPaths = $customer->kyc_documents ?? [];
            foreach ($request->file('kyc_documents') as $document) {
                $tempPath = $document->store('temp/kyc_documents', 'local');
                $s3Path = 'kyc_documents/customer_' . $customer->id . '/' . basename($tempPath);
                $kycPaths[] = $s3Path;
                \App\Jobs\UploadFileToS3Job::dispatch($tempPath, $s3Path, $customer, 'kyc_documents');
            }
            $customer->kyc_documents = $kycPaths;
        }

        $customer->save();
        Cache::forget('customer_credit_' . $customer->id);
        Cache::forget('customer_invoices_' . $customer->id);

        return response()->json([
            'message' => 'Profile updated successfully',
            'customer' => [
                'id' => $customer->id,
                'account_number' => $customer->account_number,
                'business_name' => $customer->business_name,
                'email' => $customer->email,
                'username' => $customer->username,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'kyc_documents' => $customer->kyc_documents,
            ],
        ]);
    }

    /**
     * Change customer PIN
     */
    public function changePin(Request $request)
    {
        $customer = $this->getCustomer($request);

        $request->validate([
            'current_pin' => 'required|string|size:4',
            'new_pin' => 'required|string|size:4|regex:/^[0-9]{4}$/',
        ]);

        if (!$customer->verifyPinForChange($request->current_pin)) {
            return response()->json([
                'message' => 'Invalid current PIN',
            ], 400);
        }

        if ($request->new_pin === '0000') {
            return response()->json([
                'message' => 'New PIN cannot be the default PIN (0000)',
            ], 400);
        }

        $customer->pin = $request->new_pin;
        $customer->save();

        return response()->json([
            'message' => 'PIN changed successfully',
        ]);
    }

    /**
     * Submit payment claim (customer marks that they have paid)
     */
    public function submitPaymentClaim(Request $request)
    {
        $customer = $this->getCustomer($request);

        $request->validate([
            'invoice_id' => 'nullable|exists:invoices,id',
            'amount' => 'required|numeric|min:0.01',
            'transaction_reference' => 'required|string',
            'payment_method' => 'nullable|string',
            'payment_proof_url' => 'nullable|url',
            'notes' => 'nullable|string',
        ]);

        // Verify invoice belongs to customer if provided
        if ($request->has('invoice_id')) {
            $invoice = $customer->invoices()->findOrFail($request->invoice_id);
            
            // Check if amount is valid for this invoice
            if ($request->amount > $invoice->remaining_balance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment amount exceeds invoice remaining balance',
                    'remaining_balance' => $invoice->remaining_balance,
                ], 400);
            }
        }

        // Create payment claim with pending confirmation
        $payment = Payment::create([
            'customer_id' => $customer->id,
            'invoice_id' => $request->invoice_id ?? null,
            'amount' => $request->amount,
            'payment_type' => 'repayment',
            'status' => 'pending', // Payment not processed yet
            'admin_confirmation_status' => 'pending', // Waiting for admin confirmation
            'payment_method' => $request->payment_method,
            'transaction_reference' => $request->transaction_reference,
            'payment_proof_url' => $request->payment_proof_url,
            'notes' => $request->notes,
        ]);

        Cache::forget('customer_invoices_' . $customer->id);
        Cache::forget('customer_credit_' . $customer->id);

        return response()->json([
            'success' => true,
            'message' => 'Payment claim submitted successfully. Awaiting admin confirmation.',
            'payment' => [
                'id' => $payment->id,
                'payment_reference' => $payment->payment_reference,
                'amount' => $payment->amount,
                'admin_confirmation_status' => $payment->admin_confirmation_status,
                'status' => $payment->status,
            ],
        ], 201);
    }

    /**
     * Helper to get customer from request
     */
    protected function getCustomer(Request $request): Customer
    {
        // In production, get from authenticated user or token
        $customerId = $request->input('customer_id') ?? $request->user()?->id;
        
        if (!$customerId) {
            abort(401, 'Unauthenticated');
        }

        return Customer::findOrFail($customerId);
    }
}

