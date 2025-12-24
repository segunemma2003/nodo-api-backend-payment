<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaystackService;
use Illuminate\Http\Request;

class PaystackController extends Controller
{
    protected PaystackService $paystackService;

    public function __construct(PaystackService $paystackService)
    {
        $this->paystackService = $paystackService;
    }

    /**
     * Check Paystack configuration status
     * This endpoint can be used to verify if Paystack is properly configured
     */
    public function checkConfiguration(Request $request)
    {
        $isConfigured = $this->paystackService->isConfigured();
        $secretKeySet = !empty(config('services.paystack.secret_key'));
        $publicKeySet = !empty(config('services.paystack.public_key'));

        return response()->json([
            'configured' => $isConfigured,
            'secret_key_set' => $secretKeySet,
            'public_key_set' => $publicKeySet,
            'message' => $isConfigured 
                ? 'Paystack is properly configured' 
                : 'Paystack is not configured. Please set PAYSTACK_SECRET_KEY and PAYSTACK_PUBLIC_KEY in your .env file.',
        ]);
    }
}





