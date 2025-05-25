<?php

namespace App\Http\Controllers\Api;

use App\Models\Employee;
use App\Models\FinancialServiceProvider;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Deduction;
use App\Models\LoanRepayment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Exception;
use Illuminate\Support\Str;

class LoanController extends ApiController
{
    /**
     * Calculate possible loan charges
     *
     * @param Request $request
     * @return Response
     */
    public function calculateLoanCharges(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'LOAN_CHARGES_REQUEST') {
                throw new Exception('Invalid message type. Expected LOAN_CHARGES_REQUEST', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Find FSP
            $fsp = FinancialServiceProvider::where('fsp_code', $fspCode)->first();
            if (!$fsp) {
                throw new Exception('FSP not found: ' . $fspCode, 404);
            }
            
            // Process loan charges calculation
            $details = $data['MessageDetails'];
            $productCode = $details['ProductCode'];
            
            // Find the product
            $product = LoanProduct::where('product_code', $productCode)
                ->where('fsp_id', $fsp->id)
                ->first();
            
            if (!$product) {
                throw new Exception('Product not found: ' . $productCode, 404);
            }
            
            // Get parameters for calculation
            $requestedAmount = $details['RequestedAmount'] ?? 0;
            $desiredDeductibleAmount = $details['DesiredDeductibleAmount'] ?? 0;
            $tenure = $details['Tenure'] ?? $product->minimum_tenure;
            
            // Calculate charges
            $interestRateAmount = ($requestedAmount * $product->interest_rate / 100) * ($tenure / 12);
            $processingFees = $product->processing_fee ? ($requestedAmount * $product->processing_fee / 100) : 0;
            $insurance = $requestedAmount * $product->insurance / 100;
            
            // Calculate loan amount and monthly return
            $netLoanAmount = $requestedAmount - $processingFees - $insurance;
            $totalAmountToPay = $requestedAmount + $interestRateAmount;
            $monthlyReturnAmount = $totalAmountToPay / $tenure;
            
            // Calculate maximum eligible amount based on the one-third rule
            $deductibleAmount = $details['DeductibleAmount'] ?? 0;
            $eligibleAmount = $deductibleAmount * 12; // Simple estimation for demo
            
            // Build response
            $responseData = [
                'Data' => [
                    'Header' => [
                        'Sender' => 'ESS_UTUMISHI',
                        'Receiver' => $data['Header']['Sender'],
                        'FSPCode' => $fspCode,
                        'MsgId' => $messageId,
                        'MessageType' => 'LOAN_CHARGES_RESPONSE'
                    ],
                    'MessageDetails' => [
                        'DesiredDeductibleAmount' => number_format($desiredDeductibleAmount, 2, '.', ''),
                        'TotalInsurance' => number_format($insurance, 2, '.', ''),
                        'TotalProcessingFees' => number_format($processingFees, 2, '.', ''),
                        'TotalInterestRateAmount' => number_format($interestRateAmount, 2, '.', ''),
                        'OtherCharges' => '0.00',
                        'NetLoanAmount' => number_format($netLoanAmount, 2, '.', ''),
                        'TotalAmountToPay' => number_format($totalAmountToPay, 2, '.', ''),
                        'Tenure' => $tenure,
                        'EligibleAmount' => number_format($eligibleAmount, 2, '.', ''),
                        'MonthlyReturnAmount' => number_format($monthlyReturnAmount, 2, '.', '')
                    ]
                ],
                'Signature' => 'Signature'
            ];
            
            $xml = $this->xmlService->generateXml($responseData);
            
            return response($xml)
                ->header('Content-Type', 'application/xml');
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process loan offer request
     *
     * @param Request $request
     * @return Response
     */
    public function processLoanOfferRequest(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'LOAN_OFFER_REQUEST') {
                throw new Exception('Invalid message type. Expected LOAN_OFFER_REQUEST', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Find FSP
            $fsp = FinancialServiceProvider::where('fsp_code', $fspCode)->first();
            if (!$fsp) {
                throw new Exception('FSP not found: ' . $fspCode, 404);
            }
            
            // Process employee details and create/update employee record
            $details = $data['MessageDetails'];
            $checkNumber = $details['CheckNumber'];
            
            // Create or update employee
            $employee = Employee::updateOrCreate(
                ['check_number' => $checkNumber],
                [
                    'first_name' => $details['FirstName'],
                    'middle_name' => $details['MiddleName'] ?? null,
                    'last_name' => $details['LastName'],
                    'sex' => $details['Sex'],
                    'employment_date' => $details['EmploymentDate'],
                    'marital_status' => $details['MaritalStatus'],
                    'confirmation_date' => $details['ConfirmationDate'] ?? null,
                    'bank_account_number' => $details['BankAccountNumber'],
                    'nearest_branch_name' => $details['NearestBranchName'] ?? null,
                    'nearest_branch_code' => $details['NearestBranchCode'] ?? null,
                    'vote_code' => $details['VoteCode'],
                    'vote_name' => $details['VoteName'],
                    'nin' => $details['NIN'],
                    'designation_code' => $details['DesignationCode'],
                    'designation_name' => $details['DesignationName'],
                    'basic_salary' => $details['BasicSalary'],
                    'net_salary' => $details['NetSalary'],
                    'one_third_amount' => $details['OneThirdAmount'],
                    'retirement_date' => $details['RetirementDate'],
                    'terms_of_employment' => $details['TermsOfEmployment'],
                    'physical_address' => $details['PhysicalAddress'] ?? null,
                    'telephone_number' => $details['TelephoneNumber'] ?? null,
                    'email_address' => $details['EmailAddress'],
                    'mobile_number' => $details['MobileNumber'],
                    'swift_code' => $details['SwiftCode'] ?? null,
                    'funding' => $details['Funding'] ?? null,
                    'contract_start_date' => $details['ContractStartDate'] ?? null,
                    'contract_end_date' => $details['ContractEndDate'] ?? null,
                ]
            );
            
            // Find the product
            $productCode = $details['ProductCode'];
            $product = LoanProduct::where('product_code', $productCode)
                ->where('fsp_id', $fsp->id)
                ->first();
            
            if (!$product) {
                throw new Exception('Product not found: ' . $productCode, 404);
            }
            
            // Create loan application
            $applicationNumber = $details['ApplicationNumber'];
            $loanApplication = LoanApplication::create([
                'application_number' => $applicationNumber,
                'employee_id' => $employee->id,
                'loan_product_id' => $product->id,
                'loan_type' => 'NEW_LOAN',
                'requested_amount' => $details['RequestedAmount'],
                'desired_deductible_amount' => $details['DesiredDeductibleAmount'],
                'tenure' => $details['Tenure'],
                'interest_rate' => $details['InterestRate'],
                'processing_fee' => $details['ProcessingFee'] ?? null,
                'insurance' => $details['Insurance'],
                'loan_purpose' => $details['LoanPurpose'],
                'status' => 'LOAN_OFFER_AT_FSP',
            ]);
            
            return $this->xmlResponse(8000, 'Loan offer request processed successfully', $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process loan initial approval notification
     *
     * @param Request $request
     * @return Response
     */
    public function processLoanInitialApproval(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'LOAN_INITIAL_APPROVAL_NOTIFICATION') {
                throw new Exception('Invalid message type. Expected LOAN_INITIAL_APPROVAL_NOTIFICATION', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Process approval details
            $details = $data['MessageDetails'];
            $applicationNumber = $details['ApplicationNumber'];
            $approval = $details['Approval'];
            
            // Find the loan application
            $loanApplication = LoanApplication::where('application_number', $applicationNumber)->first();
            
            if (!$loanApplication) {
                throw new Exception('Loan application not found: ' . $applicationNumber, 404);
            }
            
            // Update loan application
            if ($approval === 'APPROVED') {
                $loanApplication->status = 'LOAN_OFFER_AT_EMPLOYEE';
                $loanApplication->fsp_reference_number = $details['FSPReferenceNumber'] ?? null;
                $loanApplication->loan_number = $details['LoanNumber'] ?? null;
                $loanApplication->total_amount_to_pay = $details['TotalAmountToPay'] ?? null;
                $loanApplication->other_charges = $details['OtherCharges'] ?? null;
                $loanApplication->fsp_approval_date = now();
            } else {
                $loanApplication->status = 'FSP_REJECTED';
                $loanApplication->rejection_reason = $details['Reason'] ?? 'No reason provided';
            }
            
            $loanApplication->save();
            
            return $this->xmlResponse(8000, 'Loan initial approval notification processed successfully', $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process loan final approval
     *
     * @param Request $request
     * @return Response
     */
    public function processLoanFinalApproval(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Check if this is coming from the employer (ESS)
            if (isset($data['Header']['Sender']) && $data['Header']['Sender'] !== 'ESS_UTUMISHI') {
                throw new Exception('Unauthorized sender', 401);
            }
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'LOAN_FINAL_APPROVAL_NOTIFICATION') {
                throw new Exception('Invalid message type. Expected LOAN_FINAL_APPROVAL_NOTIFICATION', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Process approval details
            $details = $data['MessageDetails'];
            $applicationNumber = $details['ApplicationNumber'];
            $approval = $details['Approval'];
            
            // Find the loan application
            $loanApplication = LoanApplication::where('application_number', $applicationNumber)->first();
            
            if (!$loanApplication) {
                throw new Exception('Loan application not found: ' . $applicationNumber, 404);
            }
            
            // Update loan application
            if ($approval === 'APPROVED') {
                $loanApplication->status = 'SUBMITTED_FOR_DISBURSEMENT';
                $loanApplication->employer_approval_date = now();
                
                // Create a deduction record
                Deduction::create([
                    'employee_id' => $loanApplication->employee_id,
                    'loan_application_id' => $loanApplication->id,
                    'deduction_code' => $loanApplication->loanProduct->deduction_code,
                    'deduction_name' => $loanApplication->loanProduct->financialServiceProvider->fsp_name . ' Loan',
                    'deduction_amount' => $loanApplication->desired_deductible_amount,
                    'balance_amount' => $loanApplication->total_amount_to_pay,
                    'check_date' => now(),
                    'is_active' => true
                ]);
            } else {
                $loanApplication->status = 'EMPLOYER_REJECTED';
                $loanApplication->rejection_reason = $details['Reason'] ?? 'No reason provided';
            }
            
            $loanApplication->save();
            
            return $this->xmlResponse(8000, 'Loan final approval notification processed successfully', $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process loan disbursement notification
     *
     * @param Request $request
     * @return Response
     */
    public function processLoanDisbursement(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'LOAN_DISBURSEMENT_NOTIFICATION') {
                throw new Exception('Invalid message type. Expected LOAN_DISBURSEMENT_NOTIFICATION', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Process disbursement details
            $details = $data['MessageDetails'];
            $applicationNumber = $details['ApplicationNumber'];
            
            // Find the loan application
            $loanApplication = LoanApplication::where('application_number', $applicationNumber)->first();
            
            if (!$loanApplication) {
                throw new Exception('Loan application not found: ' . $applicationNumber, 404);
            }
            
            // Update loan application
            $loanApplication->status = 'COMPLETED';
            $loanApplication->disbursement_date = $details['DisbursementDate'];
            $loanApplication->save();
            
            return $this->xmlResponse(8000, 'Loan disbursement notification processed successfully', $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process loan disbursement failure
     *
     * @param Request $request
     * @return Response
     */
    public function processLoanDisbursementFailure(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'LOAN_DISBURSEMENT_FAILURE_NOTIFICATION') {
                throw new Exception('Invalid message type. Expected LOAN_DISBURSEMENT_FAILURE_NOTIFICATION', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Process failure details
            $details = $data['MessageDetails'];
            $applicationNumber = $details['ApplicationNumber'];
            
            // Find the loan application
            $loanApplication = LoanApplication::where('application_number', $applicationNumber)->first();
            
            if (!$loanApplication) {
                throw new Exception('Loan application not found: ' . $applicationNumber, 404);
            }
            
            // Update loan application
            $loanApplication->status = 'DISBURSEMENT_FAILURE';
            $loanApplication->rejection_reason = $details['Reason'] ?? 'No reason provided';
            $loanApplication->save();
            
            // Deactivate the deduction
            Deduction::where('loan_application_id', $loanApplication->id)
                ->update(['is_active' => false]);
            
            return $this->xmlResponse(8000, 'Loan disbursement failure notification processed successfully', $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process loan cancellation
     *
     * @param Request $request
     * @return Response
     */
    public function processLoanCancellation(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Check if this is coming from the employer (ESS)
            if (isset($data['Header']['Sender']) && $data['Header']['Sender'] !== 'ESS_UTUMISHI') {
                throw new Exception('Unauthorized sender', 401);
            }
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'LOAN_CANCELLATION_NOTIFICATION') {
                throw new Exception('Invalid message type. Expected LOAN_CANCELLATION_NOTIFICATION', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Process cancellation details
            $details = $data['MessageDetails'];
            $applicationNumber = $details['ApplicationNumber'];
            
            // Find the loan application
            $loanApplication = LoanApplication::where('application_number', $applicationNumber)->first();
            
            if (!$loanApplication) {
                throw new Exception('Loan application not found: ' . $applicationNumber, 404);
            }
            
            // Update loan application
            $loanApplication->status = 'EMPLOYEE_CANCELED';
            $loanApplication->rejection_reason = $details['Reason'] ?? 'No reason provided';
            $loanApplication->save();
            
            return $this->xmlResponse(8000, 'Loan cancellation notification processed successfully', $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process loan repayment
     *
     * @param Request $request
     * @return Response
     */
    public function processLoanRepayment(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Validate message type (handle both FULL and PARTIAL repayments)
            $validTypes = ['FULL_LOAN_REPAYMENT_NOTIFICATION', 'PARTIAL_LOAN_REPAYMENT_NOTIFICATION'];
            if (!isset($data['Header']['MessageType']) || !in_array($data['Header']['MessageType'], $validTypes)) {
                throw new Exception('Invalid message type. Expected repayment notification', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Process repayment details
            $details = $data['MessageDetails'];
            $loanNumber = $details['LoanNumber'];
            $checkNumber = $details['CheckNumber'];
            
            // Find the loan application
            $loanApplication = LoanApplication::where('loan_number', $loanNumber)->first();
            
            if (!$loanApplication) {
                throw new Exception('Loan application not found: ' . $loanNumber, 404);
            }
            
            // Determine payment type
            $paymentType = $data['Header']['MessageType'] === 'FULL_LOAN_REPAYMENT_NOTIFICATION' 
                ? 'FULL_PAYMENT' 
                : 'PARTIAL_PAYMENT';
            
            // Create repayment record
            $repayment = LoanRepayment::create([
                'loan_application_id' => $loanApplication->id,
                'payment_amount' => $details['PaymentAmount'],
                'payment_reference' => $details['PaymentReference'],
                'payment_description' => $details['PaymentDescription'] ?? null,
                'payment_date' => $details['PaymentDate'],
                'loan_balance' => $details['LoanBalance'],
                'payment_type' => $paymentType,
                'maturity_date' => $details['MaturityDate'] ?? null,
            ]);
            
            // Update loan status if fully paid
            if ($paymentType === 'FULL_PAYMENT' || (float)$details['LoanBalance'] <= 0) {
                $loanApplication->status = 'COMPLETED';
                $loanApplication->save();
                
                // Deactivate the deduction
                Deduction::where('loan_application_id', $loanApplication->id)
                    ->update(['is_active' => false]);
            }
            
            return $this->xmlResponse(8000, 'Loan repayment notification processed successfully', $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process loan liquidation
     *
     * @param Request $request
     * @return Response
     */
    public function processLoanLiquidation(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'LOAN_LIQUIDATION_NOTIFICATION') {
                throw new Exception('Invalid message type. Expected LOAN_LIQUIDATION_NOTIFICATION', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Process liquidation details
            $details = $data['MessageDetails'];
            $loanNumber = $details['LoanNumber'];
            
            // Find the loan application
            $loanApplication = LoanApplication::where('loan_number', $loanNumber)->first();
            
            if (!$loanApplication) {
                throw new Exception('Loan application not found: ' . $loanNumber, 404);
            }
            
            // Update loan status
            $loanApplication->status = 'COMPLETED';
            $loanApplication->save();
            
            // Deactivate the deduction
            Deduction::where('loan_application_id', $loanApplication->id)
                ->update(['is_active' => false]);
            
            return $this->xmlResponse(8000, 'Loan liquidation notification processed successfully', $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
    
    /**
     * Process loan status request
     *
     * @param Request $request
     * @return Response
     */
    public function processLoanStatusRequest(Request $request): Response
    {
        try {
            $data = $this->extractAndValidateXml($request);
            
            // Validate message type
            if (!isset($data['Header']['MessageType']) || $data['Header']['MessageType'] !== 'LOAN_STATUS_REQUEST') {
                throw new Exception('Invalid message type. Expected LOAN_STATUS_REQUEST', 400);
            }
            
            $fspCode = $data['Header']['FSPCode'];
            $messageId = $data['Header']['MsgId'];
            
            // Process status request details
            $details = $data['MessageDetails'];
            $applicationNumber = $details['ApplicationNumber'];
            
            // Find the loan application
            $loanApplication = LoanApplication::where('application_number', $applicationNumber)->first();
            
            if (!$loanApplication) {
                return $this->xmlResponse(8019, 'Loan application not found: ' . $applicationNumber, $fspCode, $messageId);
            }
            
            // Get the loan status and last updated date
            $status = $loanApplication->status;
            $lastUpdated = $loanApplication->updated_at->format('Y-m-d\TH:i:s');
            
            $description = "Loan with application {$applicationNumber} is at {$status}, last action done at {$lastUpdated}";
            
            return $this->xmlResponse(8000, $description, $fspCode, $messageId);
        } catch (Exception $e) {
            return $this->errorResponse($e);
        }
    }
} 