<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApproval;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoanApprovalController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin|super-admin|loan-officer']);
    }

    /**
     * Display a listing of the loan approvals.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $approvals = LoanApproval::with(['loanApplication.user', 'loanApplication.productCatalog', 'approvedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('loan-approvals.index', compact('approvals'));
    }

    /**
     * Display a listing of pending loan applications.
     *
     * @return \Illuminate\View\View
     */
    public function pendingApplications()
    {
        $applications = LoanApplication::with(['user', 'productCatalog.financialServiceProvider'])
            ->where('status', 'SUBMITTED')
            ->orderBy('submitted_at', 'desc')
            ->paginate(15);
        
        return view('loan-approvals.pending', compact('applications'));
    }

    /**
     * Show the form for reviewing a loan application.
     *
     * @param  \App\Models\LoanApplication  $application
     * @return \Illuminate\View\View
     */
    public function review(LoanApplication $application)
    {
        if (!in_array($application->status, ['SUBMITTED', 'UNDER_REVIEW'])) {
            return redirect()->route('loan-approvals.pending')
                ->with('error', 'Only submitted applications can be reviewed.');
        }
        
        $application->load([
            'user.employeeDetail.institution', 
            'user.employeeDetail.department',
            'productCatalog.financialServiceProvider',
            'bank',
            'bankBranch'
        ]);
        
        // Get user's active loans
        $activeLoans = Loan::where('user_id', $application->user_id)
            ->whereIn('status', ['ACTIVE', 'DISBURSED'])
            ->with('productCatalog')
            ->get();
        
        // Get user's loan history
        $loanHistory = Loan::where('user_id', $application->user_id)
            ->with('productCatalog')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Mark application as under review
        if ($application->status === 'SUBMITTED') {
            $application->update([
                'status' => 'UNDER_REVIEW',
                'reviewed_by' => Auth::id(),
                'reviewed_at' => now(),
            ]);
            
            // Log action
            AuditLog::create([
                'user_id' => Auth::id(),
                'action' => 'REVIEW',
                'model_type' => 'LoanApplication',
                'model_id' => $application->id,
                'description' => 'Loan application marked as under review',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
        
        return view('loan-approvals.review', compact('application', 'activeLoans', 'loanHistory'));
    }

    /**
     * Approve a loan application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanApplication  $application
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(Request $request, LoanApplication $application)
    {
        if (!in_array($application->status, ['SUBMITTED', 'UNDER_REVIEW'])) {
            return redirect()->route('loan-approvals.pending')
                ->with('error', 'Only submitted or under review applications can be approved.');
        }
        
        $validator = Validator::make($request->all(), [
            'approval_comments' => 'nullable|string',
            'approved_amount' => 'required|numeric|min:1',
            'approved_term' => 'required|integer|min:1',
            'interest_rate' => 'required|numeric|min:0',
            'interest_type' => 'required|in:FIXED,REDUCING_BALANCE',
            'processing_fee' => 'nullable|numeric|min:0',
            'insurance_fee' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Generate reference number
        $referenceNumber = 'LA-' . strtoupper(Str::random(8));
        
        // Calculate fees and interest
        $amount = $request->approved_amount;
        $term = $request->approved_term;
        $interestRate = $request->interest_rate;
        $interestType = $request->interest_type;
        
        // Calculate processing fee
        $processingFee = $request->processing_fee ?: 0;
        
        // Calculate insurance fee
        $insuranceFee = $request->insurance_fee ?: 0;
        
        // Calculate total fees
        $totalFees = $processingFee + $insuranceFee;
        
        // Calculate interest
        $interest = 0;
        if ($interestType === 'FIXED') {
            $interest = ($interestRate / 100) * $amount * $term;
            if ($application->term_period === 'YEAR') {
                $interest *= 12; // Convert annual rate to monthly
            }
        } else { // REDUCING_BALANCE
            $monthlyRate = $interestRate / 100;
            if ($application->term_period === 'YEAR') {
                $monthlyRate /= 12;
                $term *= 12;
            }
            
            $principal = $amount;
            $interest = 0;
            
            for ($i = 0; $i < $term; $i++) {
                $monthlyInterest = $principal * $monthlyRate;
                $interest += $monthlyInterest;
                
                // Calculate principal payment for this period
                $monthlyPrincipal = $amount / $term;
                $principal -= $monthlyPrincipal;
            }
        }
        
        // Calculate total amount
        $totalAmount = $amount + $interest + $totalFees;
        
        // Create loan approval
        $approval = LoanApproval::create([
            'loan_application_id' => $application->id,
            'approved_by' => Auth::id(),
            'approved_amount' => $amount,
            'approved_term' => $term,
            'interest_rate' => $interestRate,
            'interest_type' => $interestType,
            'interest_amount' => $interest,
            'processing_fee' => $processingFee,
            'insurance_fee' => $insuranceFee,
            'total_fees' => $totalFees,
            'total_amount' => $totalAmount,
            'comments' => $request->approval_comments,
            'status' => 'APPROVED',
        ]);
        
        // Update loan application
        $application->update([
            'status' => 'APPROVED',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ]);
        
        // Create loan record
        $loan = Loan::create([
            'user_id' => $application->user_id,
            'loan_application_id' => $application->id,
            'product_catalog_id' => $application->product_catalog_id,
            'reference_number' => $referenceNumber,
            'principal_amount' => $amount,
            'term' => $term,
            'term_period' => $application->term_period,
            'interest_rate' => $interestRate,
            'interest_type' => $interestType,
            'interest_amount' => $interest,
            'fees_amount' => $totalFees,
            'total_amount' => $totalAmount,
            'outstanding_amount' => $totalAmount,
            'status' => 'APPROVED',
            'purpose' => $application->purpose,
            'bank_id' => $application->bank_id,
            'bank_branch_id' => $application->bank_branch_id,
            'account_number' => $application->account_number,
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $application->user_id,
            'title' => 'Loan Application Approved',
            'message' => "Your loan application ({$application->reference_number}) has been approved. The loan amount of {$amount} has been approved and is pending disbursement.",
            'type' => 'LOAN_APPROVAL',
            'reference_id' => $approval->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'APPROVE',
            'model_type' => 'LoanApplication',
            'model_id' => $application->id,
            'description' => 'Loan application approved',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'approved_amount' => $amount,
                'approved_term' => $term,
                'interest_rate' => $interestRate,
                'interest_type' => $interestType,
                'total_amount' => $totalAmount,
                'status' => 'APPROVED',
            ]),
        ]);
        
        return redirect()->route('loan-approvals.index')
            ->with('success', 'Loan application approved successfully.');
    }

    /**
     * Reject a loan application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanApplication  $application
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Request $request, LoanApplication $application)
    {
        if (!in_array($application->status, ['SUBMITTED', 'UNDER_REVIEW'])) {
            return redirect()->route('loan-approvals.pending')
                ->with('error', 'Only submitted or under review applications can be rejected.');
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Create loan approval with rejection status
        $approval = LoanApproval::create([
            'loan_application_id' => $application->id,
            'approved_by' => Auth::id(),
            'comments' => $request->rejection_reason,
            'status' => 'REJECTED',
        ]);
        
        // Update loan application
        $application->update([
            'status' => 'REJECTED',
            'approved_at' => now(),
            'approved_by' => Auth::id(),
            'rejection_reason' => $request->rejection_reason,
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $application->user_id,
            'title' => 'Loan Application Rejected',
            'message' => "Your loan application ({$application->reference_number}) has been rejected. Reason: {$request->rejection_reason}",
            'type' => 'LOAN_APPROVAL',
            'reference_id' => $approval->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'REJECT',
            'model_type' => 'LoanApplication',
            'model_id' => $application->id,
            'description' => 'Loan application rejected',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'status' => 'REJECTED',
                'rejection_reason' => $request->rejection_reason,
            ]),
        ]);
        
        return redirect()->route('loan-approvals.index')
            ->with('success', 'Loan application rejected successfully.');
    }

    /**
     * Request additional information for a loan application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanApplication  $application
     * @return \Illuminate\Http\RedirectResponse
     */
    public function requestInfo(Request $request, LoanApplication $application)
    {
        if (!in_array($application->status, ['SUBMITTED', 'UNDER_REVIEW'])) {
            return redirect()->route('loan-approvals.pending')
                ->with('error', 'Only submitted or under review applications can have additional information requested.');
        }
        
        $validator = Validator::make($request->all(), [
            'additional_info_request' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Update loan application
        $application->update([
            'status' => 'INFO_REQUESTED',
            'info_requested_at' => now(),
            'info_requested_by' => Auth::id(),
            'additional_info_request' => $request->additional_info_request,
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $application->user_id,
            'title' => 'Additional Information Requested',
            'message' => "Additional information has been requested for your loan application ({$application->reference_number}). Please provide: {$request->additional_info_request}",
            'type' => 'LOAN_APPLICATION',
            'reference_id' => $application->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'REQUEST_INFO',
            'model_type' => 'LoanApplication',
            'model_id' => $application->id,
            'description' => 'Additional information requested for loan application',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'status' => 'INFO_REQUESTED',
                'additional_info_request' => $request->additional_info_request,
            ]),
        ]);
        
        return redirect()->route('loan-approvals.pending')
            ->with('success', 'Additional information requested successfully.');
    }
    
    /**
     * Display the specified loan approval.
     *
     * @param  \App\Models\LoanApproval  $approval
     * @return \Illuminate\View\View
     */
    public function show(LoanApproval $approval)
    {
        $approval->load([
            'loanApplication.user.employeeDetail.institution', 
            'loanApplication.user.employeeDetail.department',
            'loanApplication.productCatalog.financialServiceProvider',
            'loanApplication.bank',
            'loanApplication.bankBranch',
            'approvedBy'
        ]);
        
        return view('loan-approvals.show', compact('approval'));
    }
}
