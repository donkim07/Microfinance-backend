<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;

class ApiService
{
    /**
     * Base URL for the ESS_UTUMISHI API
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * API key for authentication
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Create a new API service instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->baseUrl = Config::get('services.ess_utumishi.base_url');
        $this->apiKey = Config::get('services.ess_utumishi.api_key');
    }

    /**
     * Make a GET request to the API.
     *
     * @param string $endpoint
     * @param array $params
     * @return mixed
     */
    public function get($endpoint, $params = [])
    {
        return $this->request('GET', $endpoint, $params);
    }

    /**
     * Make a POST request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @return mixed
     */
    public function post($endpoint, $data = [])
    {
        return $this->request('POST', $endpoint, $data);
    }

    /**
     * Make a PUT request to the API.
     *
     * @param string $endpoint
     * @param array $data
     * @return mixed
     */
    public function put($endpoint, $data = [])
    {
        return $this->request('PUT', $endpoint, $data);
    }

    /**
     * Make a DELETE request to the API.
     *
     * @param string $endpoint
     * @param array $params
     * @return mixed
     */
    public function delete($endpoint, $params = [])
    {
        return $this->request('DELETE', $endpoint, $params);
    }

    /**
     * Make a request to the API.
     *
     * @param string $method
     * @param string $endpoint
     * @param array $data
     * @return mixed
     */
    protected function request($method, $endpoint, $data = [])
    {
        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        
        try {
            $response = Http::withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Accept' => 'application/json',
            ])->$method($url, $data);
            
            if ($response->successful()) {
                return $response->json();
            }
            
            Log::error("ESS_UTUMISHI API Error: {$response->status()}", [
                'method' => $method,
                'url' => $url,
                'response' => $response->body(),
            ]);
            
            return [
                'success' => false,
                'message' => 'API request failed: ' . $response->status(),
                'error' => $response->json() ?? $response->body(),
            ];
        } catch (\Exception $e) {
            Log::error("ESS_UTUMISHI API Exception: {$e->getMessage()}", [
                'method' => $method,
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);
            
            return [
                'success' => false,
                'message' => 'API request exception: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }
    
    /**
     * Validate a bank account.
     *
     * @param string $accountNumber
     * @param string $bankCode
     * @return mixed
     */
    public function validateBankAccount($accountNumber, $bankCode)
    {
        return $this->post('validate-account', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ]);
    }
    
    /**
     * Fetch product catalog.
     *
     * @return mixed
     */
    public function getProductCatalog()
    {
        return $this->get('product-catalog');
    }
    
    /**
     * Submit new loan application.
     *
     * @param array $loanData
     * @return mixed
     */
    public function submitLoanApplication($loanData)
    {
        return $this->post('loan-applications', $loanData);
    }
    
    /**
     * Submit loan repayment.
     *
     * @param string $loanReference
     * @param array $repaymentData
     * @return mixed
     */
    public function submitLoanRepayment($loanReference, $repaymentData)
    {
        return $this->post("loans/{$loanReference}/repayments", $repaymentData);
    }
    
    /**
     * Get loan status.
     *
     * @param string $loanReference
     * @return mixed
     */
    public function getLoanStatus($loanReference)
    {
        return $this->get("loans/{$loanReference}/status");
    }
    
    /**
     * Submit loan restructure request.
     *
     * @param string $loanReference
     * @param array $restructureData
     * @return mixed
     */
    public function submitLoanRestructure($loanReference, $restructureData)
    {
        return $this->post("loans/{$loanReference}/restructure", $restructureData);
    }
    
    /**
     * Submit loan takeover request.
     *
     * @param string $loanReference
     * @param array $takeoverData
     * @return mixed
     */
    public function submitLoanTakeover($loanReference, $takeoverData)
    {
        return $this->post("loans/{$loanReference}/takeover", $takeoverData);
    }
    
    /**
     * Submit loan default notification.
     *
     * @param string $loanReference
     * @param array $defaultData
     * @return mixed
     */
    public function submitLoanDefault($loanReference, $defaultData)
    {
        return $this->post("loans/{$loanReference}/default", $defaultData);
    }
    
    /**
     * Set up salary deduction.
     *
     * @param string $employeeId
     * @param array $deductionData
     * @return mixed
     */
    public function setupDeduction($employeeId, $deductionData)
    {
        return $this->post("employees/{$employeeId}/deductions", $deductionData);
    }
    
    /**
     * Get bank branches.
     *
     * @param string $bankCode
     * @return mixed
     */
    public function getBankBranches($bankCode)
    {
        return $this->get("banks/{$bankCode}/branches");
    }
} 