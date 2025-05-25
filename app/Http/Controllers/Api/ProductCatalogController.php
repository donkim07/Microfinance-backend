<?php

namespace App\Http\Controllers\Api;

use App\Models\FinancialServiceProvider;
use App\Models\LoanProduct;
use App\Models\ProductTermCondition;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Str;

class ProductCatalogController extends ApiController
{
    /**
     * Process product catalog from FSP
     *
     * @param Request $request
     * @return Response
     */
    public function processProductCatalog(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'PRODUCT_DETAIL') {
                throw new Exception('Invalid message type. Expected PRODUCT_DETAIL', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Find or create FSP
            $fsp = FinancialServiceProvider::firstOrCreate(
                ['fsp_code' => $fspCode],
                ['fsp_name' => $data['Header']['Sender'] ?? 'FSP_' . $fspCode]
            );
            
            // Process each product
            if (isset($data['MessageDetails'])) {
                // Check if MessageDetails is an array of products or a single product
                $products = isset($data['MessageDetails'][0]) ? $data['MessageDetails'] : [$data['MessageDetails']];
                
                foreach ($products as $productData) {
                    $this->processProduct($fsp, $productData);
                }
            }
            
            return $this->xmlResponse(8000, 'Products processed successfully', $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process product decommission from FSP
     *
     * @param Request $request
     * @return Response
     */
    public function processProductDecommission(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'PRODUCT_DECOMMISSION') {
                throw new Exception('Invalid message type. Expected PRODUCT_DECOMMISSION', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Find FSP
            $fsp = FinancialServiceProvider::where('fsp_code', $fspCode)->first();
            
            if (!$fsp) {
                throw new Exception('FSP not found: ' . $fspCode, 404);
            }
            
            // Process each product code
            if (isset($data['MessageDetails'])) {
                // Check if MessageDetails is an array of products or a single product
                $products = isset($data['MessageDetails'][0]) ? $data['MessageDetails'] : [$data['MessageDetails']];
                
                foreach ($products as $productData) {
                    if (isset($productData['ProductCode'])) {
                        $product = LoanProduct::where('product_code', $productData['ProductCode'])
                            ->where('fsp_id', $fsp->id)
                            ->first();
                        
                        if ($product) {
                            $product->is_active = false;
                            $product->save();
                        }
                    }
                }
            }
            
            return $this->xmlResponse(8000, 'Products decommissioned successfully', $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process a single product
     *
     * @param FinancialServiceProvider $fsp
     * @param array $productData
     * @return void
     */
    private function processProduct(FinancialServiceProvider $fsp, array $productData): void
    {
        // Create or update product
        $product = LoanProduct::updateOrCreate(
            ['product_code' => $productData['ProductCode']],
            [
                'fsp_id' => $fsp->id,
                'product_name' => $productData['ProductName'],
                'product_description' => $productData['ProductDescription'] ?? null,
                'minimum_tenure' => $productData['MinimumTenure'],
                'maximum_tenure' => $productData['MaximumTenure'],
                'interest_rate' => $productData['InterestRate'],
                'processing_fee' => $productData['ProcessFee'] ?? null,
                'insurance' => $productData['Insurance'],
                'min_amount' => $productData['MinAmount'],
                'max_amount' => $productData['MaxAmount'],
                'repayment_type' => $productData['RepaymentType'] ?? null,
                'for_executive' => $productData['ForExecutive'] === 'true',
                'deduction_code' => $productData['DeductionCode'],
                'insurance_type' => $productData['InsuranceType'],
                'currency' => $productData['Currency'] ?? 'TZS',
                'is_active' => true
            ]
        );
        
        // Process terms and conditions if present
        if (isset($productData['TermsCondition'])) {
            $termsConditions = isset($productData['TermsCondition'][0]) 
                ? $productData['TermsCondition'] 
                : [$productData['TermsCondition']];
            
            foreach ($termsConditions as $tcData) {
                ProductTermCondition::updateOrCreate(
                    [
                        'loan_product_id' => $product->id,
                        'terms_condition_number' => $tcData['TermsConditionNumber']
                    ],
                    [
                        'description' => $tcData['Description'],
                        'tc_effective_date' => $tcData['TCEffectiveDate']
                    ]
                );
            }
        }
    }
} 