<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenAuth
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-API-Token') ?? $request->input('api_token');
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'API token is required',
            ], 401);
        }

        // Check Business API token first
        $business = Business::where('api_token', $token)
            ->where('status', 'active')
            ->first();

        if ($business) {
            $request->merge(['api_token_model' => $business, 'business' => $business]);
            return $next($request);
        }

        // Check ApiToken model
        $apiToken = ApiToken::where('token', $token)
            ->where('status', 'active')
            ->first();

        if ($apiToken) {
            // Update last used timestamp
            $apiToken->last_used_at = now();
            $apiToken->save();

            // Attach API token to request for use in controllers
            $request->merge(['api_token_model' => $apiToken]);
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or inactive API token',
        ], 401);
    }
}

