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

        // Ensure business has an API token
        if (empty($business->api_token)) {
            $business->generateApiToken();
        }

        // Generate session token (for potential future session management)
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

    public function generateInvoiceLink(Request $request, $invoiceId)
    {
        $business = $this->getBusiness($request);

        $invoice = Invoice::where('id', $invoiceId)
            ->where('supplier_id', $business->id)
            ->firstOrFail();

        if (empty($invoice->slug)) {
            $invoice->slug = Invoice::generateSlug();
            $invoice->save();
        }

        $frontendUrl = env('FRONTEND_URL', env('APP_URL', 'https://nodopay.com'));
        $invoiceLink = rtrim($frontendUrl, '/') . '/checkout/' . $invoice->slug;

        return response()->json([
            'message' => 'Invoice link generated successfully',
            'invoice_link' => $invoiceLink,
            'slug' => $invoice->slug,
            'invoice' => [
                'id' => $invoice->id,
                'invoice_id' => $invoice->invoice_id,
                'amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'is_used' => $invoice->is_used,
            ],
        ]);
    }


    public function updateProfile(Request $request)
    {
        $business = $this->getBusiness($request);

        $request->validate([
            'business_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:businesses,email,' . $business->id,
            'username' => 'sometimes|string|unique:businesses,username,' . $business->id,
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
                \App\Jobs\UploadFileToS3Job::dispatch($tempPath, $s3Path, $business, 'kyc_documents');
            }
            $business->kyc_documents = $kycPaths;
        }

        $business->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'business' => [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'email' => $business->email,
                'username' => $business->username,
                'phone' => $business->phone,
                'address' => $business->address,
                'webhook_url' => $business->webhook_url,
                'kyc_documents' => $business->kyc_documents,
            ],
        ]);
    }

    protected function getBusiness(Request $request): Business
    {
        // Get business from request (set by BusinessAuth middleware)
        $business = $request->get('business') ?? $request->user();
        
        if (!$business instanceof Business) {
            abort(401, 'Unauthenticated');
        }

        return $business;
    }
}

