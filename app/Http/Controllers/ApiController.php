<?php

namespace App\Http\Controllers;

use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\Deduction;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanDefault;
use App\Models\LoanDisbursement;
use App\Models\LoanRepayment;
use App\Models\LoanRestructure;
use App\Models\LoanTakeover;
use App\Models\ProductCatalog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApiController extends Controller
{
    /**
     * Get all product catalogs.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductCatalogs()
    {
        $productCatalogs = ProductCatalog::where('is_active', true)->get();
        
        return response()->json([
            'success' => true,
            'data' => $productCatalogs
        ]);
    }
    
    /**
     * Get a specific product catalog.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductCatalog($id)
    {
        $productCatalog = ProductCatalog::findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $productCatalog
        ]);
    }
    
    /**
     * Create a new loan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createLoan(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_number' => 'required|string',
            'product_catalog_id' => 'required|exists:product_catalogs,id',
            'amount' => 'required|numeric|min:1000',
            'term_months' => 'required|integer|min:1',
            'purpose' => 'required|string',
            'bank_id' => 'required|exists:banks,id',
            'bank_branch_id' => 'required|exists:bank_branches,id',
            'account_number' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if user exists with employee number
        $user = User::where('employee_number', $request->employee_number)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Employee not found',
                'error_code' => 'EMPLOYEE_NOT_FOUND'
            ], 404);
        }
        
        // Check if user already has an active loan
        $hasActiveLoan = Loan::where('user_id', $user->id)
            ->whereNotIn('status', ['COMPLETED', 'DECLINED', 'CANCELLED'])
            ->exists();
            
        if ($hasActiveLoan) {
            return response()->json([
                'success' => false,
                'error' => 'Employee already has an active loan',
                'error_code' => 'ACTIVE_LOAN_EXISTS'
            ], 400);
        }
        
        // Create loan application
        $loanApplication = LoanApplication::create([
            'user_id' => $user->id,
            'product_catalog_id' => $request->product_catalog_id,
            'amount' => $request->amount,
            'term_months' => $request->term_months,
            'purpose' => $request->purpose,
            'bank_id' => $request->bank_id,
            'bank_branch_id' => $request->bank_branch_id,
            'account_number' => $request->account_number,
            'status' => 'PENDING',
            'source' => 'API',
        ]);
        
        // Create loan record
        $loan = Loan::create([
            'user_id' => $user->id,
            'loan_application_id' => $loanApplication->id,
            'product_catalog_id' => $request->product_catalog_id,
            'principal_amount' => $request->amount,
            'term_months' => $request->term_months,
            'status' => 'PENDING_APPROVAL',
            'source' => 'API',
        ]);
        
        return response()->json([
            'success' => true,
            'data' => [
                'loan_id' => $loan->id,
                'application_id' => $loanApplication->id,
                'status' => $loan->status
            ],
            'message' => 'Loan application submitted successfully'
        ]);
    }
    
    /**
     * Top up an existing loan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function topUpLoan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'additional_amount' => 'required|numeric|min:1000',
            'new_term_months' => 'required|integer|min:1',
            'purpose' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the loan
        $loan = Loan::findOrFail($id);
        
        // Check if loan is active
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return response()->json([
                'success' => false,
                'error' => 'Loan is not active and cannot be topped up',
                'error_code' => 'LOAN_NOT_ACTIVE'
            ], 400);
        }
        
        // Calculate outstanding balance
        $outstandingBalance = $loan->calculateOutstandingBalance();
        
        // Create a new loan application for the top-up
        $loanApplication = LoanApplication::create([
            'user_id' => $loan->user_id,
            'product_catalog_id' => $loan->product_catalog_id,
            'amount' => $request->additional_amount + $outstandingBalance,
            'term_months' => $request->new_term_months,
            'purpose' => $request->purpose,
            'bank_id' => $loan->loanApplication->bank_id,
            'bank_branch_id' => $loan->loanApplication->bank_branch_id,
            'account_number' => $loan->loanApplication->account_number,
            'status' => 'PENDING',
            'source' => 'API',
            'is_top_up' => true,
            'parent_loan_id' => $loan->id,
        ]);
        
        // Update the existing loan status
        $loan->update([
            'status' => 'PENDING_TOP_UP',
        ]);
        
        return response()->json([
            'success' => true,
            'data' => [
                'application_id' => $loanApplication->id,
                'original_loan_id' => $loan->id,
                'status' => 'PENDING_APPROVAL',
                'outstanding_balance' => $outstandingBalance,
                'new_total_amount' => $request->additional_amount + $outstandingBalance
            ],
            'message' => 'Loan top-up application submitted successfully'
        ]);
    }
    
    /**
     * Restructure an existing loan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function restructureLoan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'new_term_months' => 'required|integer|min:1',
            'reason' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the loan
        $loan = Loan::findOrFail($id);
        
        // Check if loan is active
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return response()->json([
                'success' => false,
                'error' => 'Loan is not active and cannot be restructured',
                'error_code' => 'LOAN_NOT_ACTIVE'
            ], 400);
        }
        
        // Calculate outstanding balance
        $outstandingBalance = $loan->calculateOutstandingBalance();
        
        // Create a loan restructure record
        $restructure = LoanRestructure::create([
            'loan_id' => $loan->id,
            'user_id' => $loan->user_id,
            'previous_term_months' => $loan->term_months,
            'new_term_months' => $request->new_term_months,
            'outstanding_balance' => $outstandingBalance,
            'reason' => $request->reason,
            'status' => 'PENDING',
            'source' => 'API',
        ]);
        
        // Update the loan status
        $loan->update([
            'status' => 'PENDING_RESTRUCTURE',
        ]);
        
        return response()->json([
            'success' => true,
            'data' => [
                'restructure_id' => $restructure->id,
                'loan_id' => $loan->id,
                'status' => 'PENDING_APPROVAL',
                'outstanding_balance' => $outstandingBalance,
            ],
            'message' => 'Loan restructure application submitted successfully'
        ]);
    }
    
    /**
     * Takeover an existing loan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function takeoverLoan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'new_fsp_id' => 'required|exists:financial_service_providers,id',
            'new_product_catalog_id' => 'required|exists:product_catalogs,id',
            'reason' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the loan
        $loan = Loan::findOrFail($id);
        
        // Check if loan is active
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return response()->json([
                'success' => false,
                'error' => 'Loan is not active and cannot be taken over',
                'error_code' => 'LOAN_NOT_ACTIVE'
            ], 400);
        }
        
        // Calculate outstanding balance
        $outstandingBalance = $loan->calculateOutstandingBalance();
        
        // Create a loan takeover record
        $takeover = LoanTakeover::create([
            'loan_id' => $loan->id,
            'user_id' => $loan->user_id,
            'old_fsp_id' => $loan->productCatalog->financial_service_provider_id,
            'new_fsp_id' => $request->new_fsp_id,
            'old_product_catalog_id' => $loan->product_catalog_id,
            'new_product_catalog_id' => $request->new_product_catalog_id,
            'outstanding_balance' => $outstandingBalance,
            'reason' => $request->reason,
            'status' => 'PENDING',
            'source' => 'API',
        ]);
        
        // Update the loan status
        $loan->update([
            'status' => 'PENDING_TAKEOVER',
        ]);
        
        return response()->json([
            'success' => true,
            'data' => [
                'takeover_id' => $takeover->id,
                'loan_id' => $loan->id,
                'status' => 'PENDING_APPROVAL',
                'outstanding_balance' => $outstandingBalance,
            ],
            'message' => 'Loan takeover application submitted successfully'
        ]);
    }
    
    /**
     * Record a loan repayment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function repayLoan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'reference_number' => 'required|string',
            'payment_method' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the loan
        $loan = Loan::findOrFail($id);
        
        // Check if loan is active
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return response()->json([
                'success' => false,
                'error' => 'Loan is not active and cannot receive payments',
                'error_code' => 'LOAN_NOT_ACTIVE'
            ], 400);
        }
        
        // Create a loan repayment record
        $repayment = LoanRepayment::create([
            'loan_id' => $loan->id,
            'user_id' => $loan->user_id,
            'amount' => $request->amount,
            'reference_number' => $request->reference_number,
            'payment_method' => $request->payment_method,
            'status' => 'COMPLETED',
            'source' => 'API',
        ]);
        
        // Check if loan is fully paid
        $outstandingBalance = $loan->calculateOutstandingBalance();
        
        if ($outstandingBalance <= 0) {
            $loan->update([
                'status' => 'COMPLETED',
                'completion_date' => now(),
            ]);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'repayment_id' => $repayment->id,
                'loan_id' => $loan->id,
                'amount' => $request->amount,
                'outstanding_balance' => $outstandingBalance,
                'is_completed' => $outstandingBalance <= 0,
            ],
            'message' => 'Loan repayment recorded successfully'
        ]);
    }
    
    /**
     * Mark a loan as defaulted.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function defaultLoan(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the loan
        $loan = Loan::findOrFail($id);
        
        // Check if loan is active
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return response()->json([
                'success' => false,
                'error' => 'Loan is not active and cannot be marked as defaulted',
                'error_code' => 'LOAN_NOT_ACTIVE'
            ], 400);
        }
        
        // Calculate outstanding balance
        $outstandingBalance = $loan->calculateOutstandingBalance();
        
        // Create a loan default record
        $default = LoanDefault::create([
            'loan_id' => $loan->id,
            'user_id' => $loan->user_id,
            'outstanding_balance' => $outstandingBalance,
            'reason' => $request->reason,
            'status' => 'PENDING',
            'source' => 'API',
        ]);
        
        // Update the loan status
        $loan->update([
            'status' => 'PENDING_DEFAULT',
        ]);
        
        return response()->json([
            'success' => true,
            'data' => [
                'default_id' => $default->id,
                'loan_id' => $loan->id,
                'status' => 'PENDING_DEFAULT',
                'outstanding_balance' => $outstandingBalance,
            ],
            'message' => 'Loan default request submitted successfully'
        ]);
    }
    
    /**
     * Get loan status.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLoanStatus($id)
    {
        $loan = Loan::findOrFail($id);
        
        // Calculate outstanding balance
        $outstandingBalance = $loan->calculateOutstandingBalance();
        
        // Get payment history
        $repayments = LoanRepayment::where('loan_id', $loan->id)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => [
                'loan_id' => $loan->id,
                'status' => $loan->status,
                'principal_amount' => $loan->principal_amount,
                'term_months' => $loan->term_months,
                'start_date' => $loan->start_date,
                'end_date' => $loan->end_date,
                'outstanding_balance' => $outstandingBalance,
                'total_paid' => $repayments->sum('amount'),
                'repayment_history' => $repayments,
            ],
        ]);
    }
    
    /**
     * Validate an employee account.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_number' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if user exists with employee number
        $user = User::where('employee_number', $request->employee_number)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Employee not found',
                'error_code' => 'EMPLOYEE_NOT_FOUND'
            ], 404);
        }
        
        // Check if user already has an active loan
        $hasActiveLoan = Loan::where('user_id', $user->id)
            ->whereNotIn('status', ['COMPLETED', 'DECLINED', 'CANCELLED'])
            ->exists();
            
        // Get employee details
        $employeeDetails = $user->employeeDetail;
        
        return response()->json([
            'success' => true,
            'data' => [
                'employee_id' => $user->id,
                'employee_number' => $user->employee_number,
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'institution' => $employeeDetails ? $employeeDetails->institution->name : null,
                'department' => $employeeDetails ? $employeeDetails->department->name : null,
                'designation' => $employeeDetails ? $employeeDetails->designation->name : null,
                'has_active_loan' => $hasActiveLoan,
            ],
        ]);
    }
    
    /**
     * Get all banks.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBanks()
    {
        $banks = Bank::where('is_active', true)->get();
        
        return response()->json([
            'success' => true,
            'data' => $banks
        ]);
    }
    
    /**
     * Get branches for a specific bank.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBankBranches($id)
    {
        $bankBranches = BankBranch::where('bank_id', $id)
            ->where('is_active', true)
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $bankBranches
        ]);
    }
    
    /**
     * Get all deductions.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDeductions()
    {
        $deductions = Deduction::where('status', 'ACTIVE')->get();
        
        return response()->json([
            'success' => true,
            'data' => $deductions
        ]);
    }
    
    /**
     * Create a new deduction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createDeduction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_number' => 'required|string',
            'loan_id' => 'required|exists:loans,id',
            'amount' => 'required|numeric|min:1',
            'deduction_date' => 'required|date',
            'reference_number' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if user exists with employee number
        $user = User::where('employee_number', $request->employee_number)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Employee not found',
                'error_code' => 'EMPLOYEE_NOT_FOUND'
            ], 404);
        }
        
        // Create deduction
        $deduction = Deduction::create([
            'user_id' => $user->id,
            'loan_id' => $request->loan_id,
            'amount' => $request->amount,
            'deduction_date' => $request->deduction_date,
            'reference_number' => $request->reference_number,
            'status' => 'ACTIVE',
            'source' => 'API',
        ]);
        
        // Record loan repayment
        $repayment = LoanRepayment::create([
            'loan_id' => $request->loan_id,
            'user_id' => $user->id,
            'amount' => $request->amount,
            'reference_number' => $request->reference_number,
            'payment_method' => 'DEDUCTION',
            'status' => 'COMPLETED',
            'source' => 'API',
            'deduction_id' => $deduction->id,
        ]);
        
        return response()->json([
            'success' => true,
            'data' => [
                'deduction_id' => $deduction->id,
                'repayment_id' => $repayment->id,
                'loan_id' => $request->loan_id,
                'amount' => $request->amount,
            ],
            'message' => 'Deduction created successfully'
        ]);
    }
    
    /**
     * Update an existing deduction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateDeduction(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|required|numeric|min:1',
            'deduction_date' => 'sometimes|required|date',
            'status' => 'sometimes|required|in:ACTIVE,CANCELLED',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Find the deduction
        $deduction = Deduction::findOrFail($id);
        
        // Update deduction
        $deduction->update($request->only(['amount', 'deduction_date', 'status']));
        
        // If amount is updated, update the associated repayment
        if ($request->has('amount')) {
            $repayment = LoanRepayment::where('deduction_id', $deduction->id)->first();
            
            if ($repayment) {
                $repayment->update([
                    'amount' => $request->amount,
                ]);
            }
        }
        
        // If status is changed to CANCELLED, update the associated repayment
        if ($request->has('status') && $request->status === 'CANCELLED') {
            $repayment = LoanRepayment::where('deduction_id', $deduction->id)->first();
            
            if ($repayment) {
                $repayment->update([
                    'status' => 'CANCELLED',
                ]);
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => $deduction,
            'message' => 'Deduction updated successfully'
        ]);
    }
    
    /**
     * Sign a document digitally.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|string',
            'document_id' => 'required',
            'employee_number' => 'required|string',
            'signature_data' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Check if user exists with employee number
        $user = User::where('employee_number', $request->employee_number)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Employee not found',
                'error_code' => 'EMPLOYEE_NOT_FOUND'
            ], 404);
        }
        
        // Digital signature implementation would go here
        // For now, we'll just log the request
        Log::info('Digital signature request', $request->all());
        
        return response()->json([
            'success' => true,
            'data' => [
                'signature_id' => 'SIG-' . uniqid(),
                'document_type' => $request->document_type,
                'document_id' => $request->document_id,
                'employee_id' => $user->id,
                'signed_at' => now(),
            ],
            'message' => 'Document signed successfully'
        ]);
    }
    
    /**
     * Verify a digital signature.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifySignature($id)
    {
        // Digital signature verification implementation would go here
        // For now, we'll just return a mock response
        return response()->json([
            'success' => true,
            'data' => [
                'signature_id' => $id,
                'is_valid' => true,
                'signed_by' => 'John Doe',
                'signed_at' => now()->subHours(2),
                'document_type' => 'LOAN_AGREEMENT',
                'document_id' => 'LOAN-123',
            ],
            'message' => 'Signature is valid'
        ]);
    }
}
