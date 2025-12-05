<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Get the Origin header - if it exists, we'll echo it back (allows any origin)
        // If no Origin header, allow all origins with '*'
        $origin = $request->headers->get('Origin');
        $allowOrigin = $origin ?: '*';
        
        // If we're using a specific origin, we can use credentials
        // If using '*', we cannot use credentials
        $useCredentials = (bool) $origin;

        // Handle preflight OPTIONS request
        if ($request->getMethod() === 'OPTIONS') {
            $response = response('', 200)
                ->header('Access-Control-Allow-Origin', $allowOrigin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Token, X-Requested-With, Accept, Origin')
                ->header('Access-Control-Expose-Headers', '*')
                ->header('Access-Control-Max-Age', '86400');
            
            if ($useCredentials) {
                $response->header('Access-Control-Allow-Credentials', 'true');
            }
            
            return $response;
        }

        try {
            $response = $next($request);
        } catch (\Exception $e) {
            // Handle exceptions and ensure CORS headers are still set
            $response = response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }

        // Always set CORS headers on all responses (including redirects, errors, and exceptions)
        $response->headers->set('Access-Control-Allow-Origin', $allowOrigin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Token, X-Requested-With, Accept, Origin');
        $response->headers->set('Access-Control-Expose-Headers', '*');
        $response->headers->set('Access-Control-Max-Age', '86400');
        
        if ($useCredentials) {
            $response->headers->set('Access-Control-Allow-Credentials', 'true');
        }

        // For redirect responses, prevent redirects that would break CORS
        // Instead, return a JSON response with the redirect URL
        if ($response->isRedirection()) {
            $location = $response->headers->get('Location');
            
            // If redirecting to a different origin (like localhost), return error instead
            if ($location && parse_url($location, PHP_URL_HOST) !== parse_url($request->url(), PHP_URL_HOST)) {
                $errorResponse = response()->json([
                    'success' => false,
                    'message' => 'Unexpected redirect detected',
                    'redirect_url' => $location,
                ], 500);
                
                $errorResponse->headers->set('Access-Control-Allow-Origin', $allowOrigin);
                $errorResponse->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $errorResponse->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-API-Token, X-Requested-With, Accept, Origin');
                
                if ($useCredentials) {
                    $errorResponse->headers->set('Access-Control-Allow-Credentials', 'true');
                }
                
                return $errorResponse;
            }
        }

        return $response;
    }
}

