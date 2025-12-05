<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessCustomer;
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
            $business->refresh();
        }

        return response()->json([
            'business' => [
                'id' => $business->id,
                'business_name' => $business->business_name,
                'email' => $business->email,
                'api_token' => $business->api_token,
                'status' => $business->status,
                'approval_status' => $business->approval_status,
            ],
            'message' => 'Use the api_token field for API authentication (Bearer token or X-API-Token header)',
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
            ->with(['customer', 'businessCustomer', 'transactions'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Add items and description to each invoice
        $invoices->getCollection()->transform(function ($invoice) {
            $invoice->items = $invoice->getItems();
            $invoice->description = $invoice->getDescription();
            return $invoice;
        });

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
            'business_customer_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($business) {
                    $businessCustomer = BusinessCustomer::where('business_id', $business->id)
                        ->where('id', $value)
                        ->first();
                    if (!$businessCustomer) {
                        $fail('The selected business customer does not exist or does not belong to your business.');
                    }
                    if ($businessCustomer && $businessCustomer->status !== 'active') {
                        $fail('The selected business customer is not active.');
                    }
                },
            ],
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

        $businessCustomer = BusinessCustomer::where('business_id', $business->id)
            ->findOrFail($request->business_customer_id);

        // If business customer is linked to a main customer, check credit
        $mainCustomer = $businessCustomer->linkedCustomer;
        if ($mainCustomer) {
            if (!$this->invoiceService->hasAvailableCredit($mainCustomer, $request->amount)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer does not have sufficient credit',
                    'available_credit' => $mainCustomer->available_balance,
                ], 400);
            }
        }

        // Create invoice with business_customer_id (customer_id will be set when payment is made)
        $invoice = $this->invoiceService->createInvoiceForBusinessCustomer(
            $businessCustomer,
            $request->amount,
            $business->business_name,
            $request->purchase_date ? \Carbon\Carbon::parse($request->purchase_date) : null,
            $request->due_date ? \Carbon\Carbon::parse($request->due_date) : null,
            $business->id
        );

        $transaction = Transaction::create([
            'customer_id' => $mainCustomer?->id, // Will be null if not linked yet
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
            'message' => 'Invoice created successfully',
            'invoice' => [
                'invoice_id' => $invoice->invoice_id,
                'slug' => $invoice->slug,
                'amount' => $invoice->principal_amount,
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'status' => $invoice->status,
                'payment_link' => $invoice->slug ? url("/api/invoice/checkout/{$invoice->slug}") : null,
                'description' => $request->description,
                'items' => $request->items ?? [],
            ],
            'business_customer' => [
                'id' => $businessCustomer->id,
                'business_name' => $businessCustomer->business_name,
                'is_linked' => $businessCustomer->isLinked(),
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
            'customer_account_number' => 'required|string|size:16|exists:customers,account_number',
            'amount' => 'required|numeric|min:0.01',
        ]);

        $customer = Customer::where('account_number', $request->customer_account_number)->firstOrFail();
        $customer->updateBalances();

        $hasCredit = $this->invoiceService->hasAvailableCredit($customer, $request->amount);

        return response()->json([
            'success' => true,
            'has_credit' => $hasCredit,
            'customer' => [
                'account_number' => $customer->account_number,
                'business_name' => $customer->business_name,
            ],
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

    /**
     * Get all business customers
     */
    public function getCustomers(Request $request)
    {
        $business = $this->getBusiness($request);

        $query = BusinessCustomer::where('business_id', $business->id)
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('contact_phone', 'like', "%{$search}%")
                  ->orWhere('contact_email', 'like', "%{$search}%");
            });
        }

        $customers = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'customers' => $customers,
        ]);
    }

    /**
     * Create a new business customer
     */
    public function createCustomer(Request $request)
    {
        $business = $this->getBusiness($request);

        $request->validate([
            'business_name' => 'required|string|max:255',
            'address' => 'nullable|string',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'minimum_purchase_amount' => 'nullable|numeric|min:0',
            'payment_plan_duration' => 'nullable|integer|min:1|max:36',
            'registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,suspended',
        ]);

        // Check for duplicate business name for this business
        $existing = BusinessCustomer::where('business_id', $business->id)
            ->where('business_name', $request->business_name)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'A customer with this business name already exists',
            ], 422);
        }

        $customer = BusinessCustomer::create([
            'business_id' => $business->id,
            'business_name' => $request->business_name,
            'address' => $request->address,
            'contact_name' => $request->contact_name,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'minimum_purchase_amount' => $request->minimum_purchase_amount ?? 0,
            'payment_plan_duration' => $request->payment_plan_duration ?? 6,
            'registration_number' => $request->registration_number,
            'tax_id' => $request->tax_id,
            'notes' => $request->notes,
            'status' => $request->status ?? 'active',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business customer created successfully',
            'customer' => $customer,
        ], 201);
    }

    /**
     * Get a specific business customer
     */
    public function getCustomer(Request $request, $id)
    {
        $business = $this->getBusiness($request);

        $customer = BusinessCustomer::where('business_id', $business->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'customer' => $customer->load('linkedCustomer'),
        ]);
    }

    /**
     * Update a business customer
     */
    public function updateCustomer(Request $request, $id)
    {
        $business = $this->getBusiness($request);

        $customer = BusinessCustomer::where('business_id', $business->id)
            ->findOrFail($id);

        $request->validate([
            'business_name' => 'sometimes|required|string|max:255',
            'address' => 'nullable|string',
            'contact_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'minimum_purchase_amount' => 'nullable|numeric|min:0',
            'payment_plan_duration' => 'nullable|integer|min:1|max:36',
            'registration_number' => 'nullable|string|max:255',
            'tax_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|in:active,inactive,suspended',
        ]);

        // Check for duplicate business name if changing
        if ($request->has('business_name') && $request->business_name !== $customer->business_name) {
            $existing = BusinessCustomer::where('business_id', $business->id)
                ->where('business_name', $request->business_name)
                ->where('id', '!=', $customer->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'A customer with this business name already exists',
                ], 422);
            }
        }

        $customer->update($request->only([
            'business_name',
            'address',
            'contact_name',
            'contact_phone',
            'contact_email',
            'minimum_purchase_amount',
            'payment_plan_duration',
            'registration_number',
            'tax_id',
            'notes',
            'status',
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Business customer updated successfully',
            'customer' => $customer->fresh(),
        ]);
    }

    /**
     * Delete a business customer
     */
    public function deleteCustomer(Request $request, $id)
    {
        $business = $this->getBusiness($request);

        $customer = BusinessCustomer::where('business_id', $business->id)
            ->findOrFail($id);

        // Check if customer has invoices
        if ($customer->invoices()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete customer with existing invoices',
            ], 422);
        }

        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Business customer deleted successfully',
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

