<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
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
        $cacheKey = 'customer_invoices_' . $customer->id;

        $invoices = Cache::remember($cacheKey, 120, function () use ($customer) {
            $this->interestService->updateAllInvoices();

            return $customer->invoices()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($invoice) {
                return [
                    'invoice_id' => $invoice->invoice_id,
                    'purchase_date' => $invoice->purchase_date->format('Y-m-d'),
                    'due_date' => $invoice->due_date->format('Y-m-d'),
                    'status' => $invoice->status,
                    'principal_amount' => $invoice->principal_amount,
                    'interest_amount' => $invoice->interest_amount,
                    'total_amount' => $invoice->total_amount,
                    'paid_amount' => $invoice->paid_amount,
                    'remaining_balance' => $invoice->remaining_balance,
                    'supplier_name' => $invoice->supplier_name,
                    'months_overdue' => $invoice->months_overdue,
                ];
            });
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
            ->where('invoice_id', $invoiceId)
            ->firstOrFail();

        $this->interestService->updateInvoiceStatus($invoice);
        $invoice->refresh();

        return response()->json([
            'invoice' => [
                'invoice_id' => $invoice->invoice_id,
                'purchase_date' => $invoice->purchase_date->format('Y-m-d'),
                'due_date' => $invoice->due_date->format('Y-m-d'),
                'grace_period_end_date' => $invoice->grace_period_end_date->format('Y-m-d'),
                'status' => $invoice->status,
                'principal_amount' => $invoice->principal_amount,
                'interest_amount' => $invoice->interest_amount,
                'total_amount' => $invoice->total_amount,
                'paid_amount' => $invoice->paid_amount,
                'remaining_balance' => $invoice->remaining_balance,
                'supplier_name' => $invoice->supplier_name,
                'months_overdue' => $invoice->months_overdue,
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

        $transactions = $customer->transactions()
            ->with(['business', 'invoice'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($transactions);
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

