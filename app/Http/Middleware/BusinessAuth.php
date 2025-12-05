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
            $token = trim($matches[1]);
        }
        
        // Fallback to X-API-Token header or api_token input
        if (!$token) {
            $token = $request->header('X-API-Token') ?? $request->input('api_token');
            if ($token) {
                $token = trim($token);
            }
        }
        
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token is required. Please provide Bearer token in Authorization header or X-API-Token header.',
                'debug' => [
                    'auth_header_received' => $authHeader ? 'yes' : 'no',
                    'x_api_token_received' => $request->header('X-API-Token') ? 'yes' : 'no',
                ],
            ], 401);
        }

        // Trim whitespace and check token format
        $token = trim($token);
        
        // Check if business exists with this API token (regardless of status)
        $business = Business::where('api_token', $token)->first();

        if (!$business) {
            // Check if token looks like it might be formatted incorrectly
            $tokenPrefix = substr($token, 0, 9);
            $hint = '';
            if ($tokenPrefix !== 'nodo_biz_') {
                $hint = ' Token should start with "nodo_biz_".';
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid authentication token. Please use the api_token from your login response, not the session token.' . $hint,
                'debug' => [
                    'token_length' => strlen($token),
                    'token_prefix' => substr($token, 0, 20) . '...',
                ],
            ], 401);
        }

        // Check if business is active
        if ($business->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Business account is not active. Status: ' . $business->status . '. Please contact admin for approval.',
                'status' => $business->status,
                'approval_status' => $business->approval_status,
            ], 403);
        }

        // Attach business to request
        $request->merge(['business' => $business]);
        $request->setUserResolver(function () use ($business) {
            return $business;
        });

        return $next($request);
    }
}

