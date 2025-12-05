<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BusinessAuth
{
    /**
     * Handle an incoming request.
     * 
     * Validates Bearer token from Authorization header or api_token from request
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = null;
        
        // Check Authorization Bearer token
        $authHeader = $request->header('Authorization');
        if ($authHeader && preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        }
        
        // Fallback to X-API-Token header or api_token input
        if (!$token) {
            $token = $request->header('X-API-Token') ?? $request->input('api_token');
        }
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token is required. Please provide Bearer token in Authorization header or X-API-Token header.',
            ], 401);
        }

        // Check Business API token
        $business = Business::where('api_token', $token)
            ->where('status', 'active')
            ->first();

        if (!$business) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or inactive authentication token',
            ], 401);
        }

        // Attach business to request
        $request->merge(['business' => $business]);
        $request->setUserResolver(function () use ($business) {
            return $business;
        });

        return $next($request);
    }
}

