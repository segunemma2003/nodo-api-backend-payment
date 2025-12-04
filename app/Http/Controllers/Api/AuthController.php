<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
}

