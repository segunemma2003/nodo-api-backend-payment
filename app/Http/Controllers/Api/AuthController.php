<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\CreateVirtualAccountJob;
use App\Models\Customer;
use App\Services\PaystackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected PaystackService $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Customer login
     */
    public function customerLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $customer = Customer::where('email', $request->email)->first();

        if (!$customer || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        // Check approval status - must be approved by admin
        if ($customer->approval_status !== 'approved') {
            throw ValidationException::withMessages([
                'email' => ['Your account is pending approval. Please wait for admin approval before logging in.'],
            ]);
        }

        if ($customer->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['Account is not active.'],
            ]);
        }

        // Generate API token (in production, use Laravel Sanctum or Passport)
        $token = bin2hex(random_bytes(32));

        return response()->json([
            'customer' => [
                'id' => $customer->id,
                'account_number' => $customer->account_number,
                'business_name' => $customer->business_name,
                'email' => $customer->email,
                'credit_limit' => $customer->credit_limit,
                'available_balance' => $customer->available_balance,
            ],
            'token' => $token,
        ]);
    }

    /**
     * Admin login
     */
    public function adminLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $admin = \App\Models\AdminUser::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            throw ValidationException::withMessages([
                'email' => ['Invalid credentials.'],
            ]);
        }

        $token = bin2hex(random_bytes(32));

        return response()->json([
            'admin' => $admin,
            'token' => $token,
        ]);
    }

    /**
     * Customer registration
     */
    public function customerRegister(Request $request)
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'username' => 'required|string|unique:customers,username',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'minimum_purchase_amount' => 'nullable|numeric|min:0',
            'payment_plan_duration' => 'nullable|integer|min:1|max:36',
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
            'minimum_purchase_amount' => $request->minimum_purchase_amount ?? 0,
            'payment_plan_duration' => $request->payment_plan_duration ?? 6,
            'approval_status' => 'pending', // Requires admin approval
            'status' => 'inactive', // Inactive until approved
        ]);

        // Dispatch job to create Paystack virtual account asynchronously
        // This doesn't block the registration process
        if ($this->paystackService->isConfigured()) {
            CreateVirtualAccountJob::dispatch($customer);
            Log::info('Virtual account creation job dispatched for customer', [
                'customer_id' => $customer->id,
            ]);
        } else {
            Log::info('Paystack not configured, skipping virtual account creation during registration', [
                'customer_id' => $customer->id,
            ]);
        }

        // Handle KYC documents if provided
        if ($request->hasFile('kyc_documents')) {
            $kycPaths = [];
            foreach ($request->file('kyc_documents') as $document) {
                $tempPath = $document->store('temp/kyc_documents', 'local');
                $s3Path = 'kyc_documents/customer_' . $customer->id . '/' . basename($tempPath);
                $kycPaths[] = $s3Path;
                \App\Jobs\UploadFileToS3Job::dispatch($tempPath, $s3Path, $customer, 'kyc_documents');
            }
            $customer->kyc_documents = $kycPaths;
            $customer->save();
        }

        // Credit limit will be set by admin when approved
        // Send notification to admin about new registration

        return response()->json([
            'success' => true,
            'message' => 'Registration successful. Your account is pending admin approval. You will be notified once approved.',
            'customer' => [
                'id' => $customer->id,
                'account_number' => $customer->account_number,
                'business_name' => $customer->business_name,
                'email' => $customer->email,
                'approval_status' => $customer->approval_status,
                'virtual_account_number' => $customer->virtual_account_number,
                'virtual_account_bank' => $customer->virtual_account_bank,
            ],
        ], 201);
    }
}

