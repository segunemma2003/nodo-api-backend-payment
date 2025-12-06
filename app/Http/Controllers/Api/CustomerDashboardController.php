<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CreditLimitAdjustment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Transaction;
use App\Services\InterestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class CustomerDashboardController extends Controller
{
    protected InterestService $interestService;

    public function __construct(InterestService $interestService)
    {
        $this->interestService = $interestService;
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

        return response()->json([
            'virtual_account_number' => $customer->virtual_account_number,
            'virtual_account_bank' => $customer->virtual_account_bank,
        ]);
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

