<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Log an action in the audit log.
     *
     * @param string $action
     * @param string $modelType
     * @param int|null $modelId
     * @param string $description
     * @param array|null $oldValues
     * @param array|null $newValues
     * @param Request|null $request
     * @return \App\Models\AuditLog
     */
    public function log($action, $modelType, $modelId = null, $description = '', $oldValues = null, $newValues = null, Request $request = null)
    {
        if (!$request) {
            $request = request();
        }
        
        $userId = Auth::id() ?? null;
        
        // Filter sensitive data from values
        $oldValues = $oldValues ? $this->filterSensitiveData($oldValues) : null;
        $newValues = $newValues ? $this->filterSensitiveData($newValues) : null;
        
        return AuditLog::create([
            'user_id' => $userId,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'description' => $description,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
    
    /**
     * Log a create action.
     *
     * @param string $modelType
     * @param int $modelId
     * @param array $values
     * @param string $description
     * @param Request|null $request
     * @return \App\Models\AuditLog
     */
    public function logCreate($modelType, $modelId, $values, $description = '', Request $request = null)
    {
        return $this->log('CREATE', $modelType, $modelId, $description, null, $values, $request);
    }
    
    /**
     * Log an update action.
     *
     * @param string $modelType
     * @param int $modelId
     * @param array $oldValues
     * @param array $newValues
     * @param string $description
     * @param Request|null $request
     * @return \App\Models\AuditLog
     */
    public function logUpdate($modelType, $modelId, $oldValues, $newValues, $description = '', Request $request = null)
    {
        return $this->log('UPDATE', $modelType, $modelId, $description, $oldValues, $newValues, $request);
    }
    
    /**
     * Log a delete action.
     *
     * @param string $modelType
     * @param int $modelId
     * @param array $values
     * @param string $description
     * @param Request|null $request
     * @return \App\Models\AuditLog
     */
    public function logDelete($modelType, $modelId, $values, $description = '', Request $request = null)
    {
        return $this->log('DELETE', $modelType, $modelId, $description, $values, null, $request);
    }
    
    /**
     * Log a login action.
     *
     * @param int $userId
     * @param string $description
     * @param Request|null $request
     * @return \App\Models\AuditLog
     */
    public function logLogin($userId, $description = 'User logged in', Request $request = null)
    {
        return $this->log('LOGIN', 'User', $userId, $description, null, null, $request);
    }
    
    /**
     * Log a logout action.
     *
     * @param int $userId
     * @param string $description
     * @param Request|null $request
     * @return \App\Models\AuditLog
     */
    public function logLogout($userId, $description = 'User logged out', Request $request = null)
    {
        return $this->log('LOGOUT', 'User', $userId, $description, null, null, $request);
    }
    
    /**
     * Log an access action.
     *
     * @param int $userId
     * @param string $resource
     * @param string $description
     * @param Request|null $request
     * @return \App\Models\AuditLog
     */
    public function logAccess($userId, $resource, $description = '', Request $request = null)
    {
        return $this->log('ACCESS', $resource, null, $description, null, null, $request);
    }
    
    /**
     * Get audit logs for a specific model.
     *
     * @param string $modelType
     * @param int $modelId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsForModel($modelType, $modelId, $limit = 100)
    {
        return AuditLog::where('model_type', $modelType)
            ->where('model_id', $modelId)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get audit logs for a specific user.
     *
     * @param int $userId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsForUser($userId, $limit = 100)
    {
        return AuditLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get audit logs for a specific action.
     *
     * @param string $action
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsForAction($action, $limit = 100)
    {
        return AuditLog::where('action', $action)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get audit logs for a date range.
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsForDateRange($startDate, $endDate, $limit = 100)
    {
        return AuditLog::whereBetween('created_at', [$startDate, $endDate])
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Filter sensitive data from values.
     *
     * @param array $data
     * @return array
     */
    protected function filterSensitiveData($data)
    {
        if (!is_array($data)) {
            return $data;
        }
        
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
        
        foreach ($data as $key => $value) {
            if (in_array($key, $sensitiveFields)) {
                $data[$key] = '******';
            } elseif (is_array($value)) {
                $data[$key] = $this->filterSensitiveData($value);
            }
        }
        
        return $data;
    }
} 