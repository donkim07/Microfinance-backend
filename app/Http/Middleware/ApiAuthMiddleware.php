<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class ApiAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check for API key in request
        $apiKey = $request->header('X-API-KEY');
        
        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key is missing.',
                'error_code' => 'MISSING_API_KEY'
            ], 401);
        }

        // Check if API key is valid (using config for now, could use database in the future)
        $validApiKey = Config::get('services.ess_utumishi.api_key');
        
        if ($apiKey !== $validApiKey) {
            $this->logApiRequest($request, 'invalid_api_key');
            
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key.',
                'error_code' => 'INVALID_API_KEY'
            ], 401);
        }

        // Check rate limiting
        // Could implement rate limiting here

        // Log the API request
        $this->logApiRequest($request, 'success');

        return $next($request);
    }

    /**
     * Log API request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $status
     * @return void
     */
    protected function logApiRequest(Request $request, string $status): void
    {
        ApiLog::create([
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_data' => json_encode($request->except(['password', 'api_key'])),
            'status' => $status,
        ]);
    }
}
