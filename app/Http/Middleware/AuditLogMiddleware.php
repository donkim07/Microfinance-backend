<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuditLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log write operations (POST, PUT, PATCH, DELETE)
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return $response;
        }

        // Skip logging for certain routes
        $excludedRoutes = [
            'login',
            'logout',
            '_ignition',
            'api/logs',
            'password/reset',
        ];

        foreach ($excludedRoutes as $route) {
            if ($request->is($route) || $request->is($route . '/*')) {
                return $response;
            }
        }

        // Get route action details
        $routeAction = $request->route() ? $request->route()->getActionName() : 'unknown';
        $parts = explode('@', $routeAction);
        $controller = isset($parts[0]) ? class_basename($parts[0]) : 'unknown';
        $method = $parts[1] ?? 'unknown';

        // Create audit log
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $method . ' (' . $request->method() . ')',
            'model_type' => $controller,
            'model_id' => $request->id ?? null,
            'description' => 'User performed ' . $method . ' action via ' . $request->method(),
            'old_values' => null,
            'new_values' => $this->filterSensitiveData($request->except(['_token', '_method', 'password', 'password_confirmation'])),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return $response;
    }

    /**
     * Filter sensitive data from the request.
     *
     * @param array $data
     * @return string
     */
    protected function filterSensitiveData($data)
    {
        // Remove sensitive fields
        $sensitiveFields = [
            'password', 
            'password_confirmation', 
            'secret', 
            'token', 
            'api_key', 
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
            'nin'
        ];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '******';
            }
        }

        return json_encode($data);
    }
}
