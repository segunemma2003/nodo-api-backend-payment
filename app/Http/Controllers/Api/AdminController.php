<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\UploadFileToS3Job;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Withdrawal;
use App\Notifications\BusinessApprovedNotification;
use App\Notifications\BusinessCreatedNotification;
use App\Notifications\CustomerCreatedNotification;
use App\Services\CreditLimitService;
use App\Services\InterestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
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
        ]);

        $customer = Customer::findOrFail($id);
        $customer->credit_limit = $request->credit_limit;
        $customer->updateBalances();

        return response()->json([
            'message' => 'Credit limit updated successfully',
            'customer' => $customer,
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

        return response()->json([
            'message' => 'Customer status updated successfully',
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
        Cache::forget('admin_customers_page_*');

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

        $query = Invoice::with('customer')
            ->orderBy('created_at', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $invoices = $query->paginate(20);

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

        $business->notify(new BusinessCreatedNotification($request->password));

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
}
