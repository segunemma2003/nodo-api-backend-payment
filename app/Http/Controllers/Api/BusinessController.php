<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Withdrawal;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class BusinessController extends Controller
{
    protected InvoiceService $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $business = Business::where('email', $request->email)->first();

        if (!$business || !Hash::check($request->password, $business->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        if ($business->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Business account is not active.'],
            ]);
        }

        $token = bin2hex(random_bytes(32));

        return response()->json([
            'business' => [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'email' => $business->email,
                'api_token' => $business->api_token,
            ],
            'token' => $token,
        ]);
    }

    public function getDashboard(Request $request)
    {
        $business = $this->getBusiness($request);

        $totalInvoices = $business->invoices()->count();
        $totalRevenue = $business->getTotalRevenue();
        $totalWithdrawn = $business->getTotalWithdrawn();
        $availableBalance = $business->getAvailableBalance();
        $pendingInvoices = $business->invoices()->where('status', 'pending')->count();
        $paidInvoices = $business->invoices()->where('status', 'paid')->count();
        $pendingWithdrawals = $business->withdrawals()->where('status', 'pending')->count();

        return response()->json([
            'business' => [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'email' => $business->email,
                'approval_status' => $business->approval_status,
                'status' => $business->status,
            ],
            'statistics' => [
                'total_invoices' => $totalInvoices,
                'total_revenue' => $totalRevenue,
                'total_withdrawn' => $totalWithdrawn,
                'available_balance' => $availableBalance,
                'pending_invoices' => $pendingInvoices,
                'paid_invoices' => $paidInvoices,
                'pending_withdrawals' => $pendingWithdrawals,
            ],
        ]);
    }

    public function getInvoices(Request $request)
    {
        $business = $this->getBusiness($request);

        $invoices = $business->invoices()
            ->with('customer')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($invoices);
    }

    public function getProfile(Request $request)
    {
        $business = $this->getBusiness($request);

        return response()->json([
            'business' => [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'email' => $business->email,
                'phone' => $business->phone,
                'address' => $business->address,
                'approval_status' => $business->approval_status,
                'api_token' => $business->api_token,
                'webhook_url' => $business->webhook_url,
                'status' => $business->status,
            ],
        ]);
    }

    public function generateApiToken(Request $request)
    {
        $business = $this->getBusiness($request);
        $token = $business->generateApiToken();

        return response()->json([
            'message' => 'API token generated successfully',
            'api_token' => $token,
        ]);
    }

    public function submitInvoice(Request $request)
    {
        $business = $this->getBusiness($request);

        if ($business->approval_status !== 'approved' || $business->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Business is not approved or inactive',
            ], 400);
        }

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
            'purchase_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'description' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.name' => 'required_with:items|string',
            'items.*.quantity' => 'required_with:items|integer|min:1',
            'items.*.price' => 'required_with:items|numeric|min:0.01',
            'items.*.description' => 'nullable|string',
        ]);

        $customer = Customer::findOrFail($request->customer_id);

        if (!$this->invoiceService->hasAvailableCredit($customer, $request->amount)) {
            return response()->json([
                'success' => false,
                'message' => 'Customer does not have sufficient credit',
                'available_credit' => $customer->available_balance,
            ], 400);
        }

        $invoice = $this->invoiceService->createInvoice(
            $customer,
            $request->amount,
            $business->business_name,
            $request->purchase_date ? \Carbon\Carbon::parse($request->purchase_date) : null,
            $request->due_date ? \Carbon\Carbon::parse($request->due_date) : null,
            $business->id
        );

        $transaction = Transaction::create([
            'customer_id' => $customer->id,
            'business_id' => $business->id,
            'invoice_id' => $invoice->id,
            'type' => 'credit_purchase',
            'amount' => $invoice->principal_amount,
            'metadata' => [
                'description' => $request->description,
                'items' => $request->items ?? [],
            ],
            'status' => 'completed',
            'description' => $request->description ?? "Invoice {$invoice->invoice_id}",
            'processed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice submitted and financed successfully',
            'invoice' => [
                'invoice_id' => $invoice->invoice_id,
                'amount' => $invoice->principal_amount,
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'status' => $invoice->status,
            ],
            'transaction' => [
                'transaction_reference' => $transaction->transaction_reference,
                'status' => $transaction->status,
            ],
        ], 201);
    }

    public function checkCustomerCredit(Request $request)
    {
        $business = $this->getBusiness($request);

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $customer = Customer::findOrFail($request->customer_id);
        $customer->updateBalances();

        $hasCredit = $this->invoiceService->hasAvailableCredit($customer, $request->amount);

        return response()->json([
            'success' => true,
            'has_credit' => $hasCredit,
            'available_credit' => $customer->available_balance,
            'current_balance' => $customer->current_balance,
            'credit_limit' => $customer->credit_limit,
        ]);
    }

    public function getTransactions(Request $request)
    {
        $business = $this->getBusiness($request);

        $transactions = $business->transactions()
            ->with(['customer', 'invoice'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transactions);
    }

    public function requestWithdrawal(Request $request)
    {
        $business = $this->getBusiness($request);

        if ($business->approval_status !== 'approved' || $business->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Business is not approved or inactive',
            ], 400);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'bank_name' => 'required|string|max:255',
            'account_number' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $availableBalance = $business->getAvailableBalance();

        if ($request->amount > $availableBalance) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance for withdrawal',
                'available_balance' => $availableBalance,
                'requested_amount' => $request->amount,
            ], 400);
        }

        $withdrawal = Withdrawal::create([
            'business_id' => $business->id,
            'amount' => $request->amount,
            'bank_name' => $request->bank_name,
            'account_number' => $request->account_number,
            'account_name' => $request->account_name,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Withdrawal request submitted successfully',
            'withdrawal' => [
                'id' => $withdrawal->id,
                'withdrawal_reference' => $withdrawal->withdrawal_reference,
                'amount' => $withdrawal->amount,
                'status' => $withdrawal->status,
                'available_balance' => $business->fresh()->getAvailableBalance(),
            ],
        ], 201);
    }

    public function getWithdrawals(Request $request)
    {
        $business = $this->getBusiness($request);

        $withdrawals = $business->withdrawals()
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($withdrawals);
    }

    protected function getBusiness(Request $request): Business
    {
        $businessId = $request->input('business_id') ?? $request->user()?->id;
        
        if (!$businessId) {
            abort(401, 'Unauthenticated');
        }

        return Business::findOrFail($businessId);
    }
}

