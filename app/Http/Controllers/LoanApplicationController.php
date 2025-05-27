<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\FinancialServiceProvider;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\Notification;
use App\Models\ProductCatalog;
use App\Models\TermsCondition;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoanApplicationController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the user's loan applications.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();
        
        $applications = LoanApplication::with(['productCatalog.financialServiceProvider', 'user'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('loan-applications.index', compact('applications'));
    }

    /**
     * Show the form for creating a new loan application.
     *
     * @param  \App\Models\ProductCatalog  $product
     * @return \Illuminate\View\View
     */
    public function create(ProductCatalog $product)
    {
        $user = Auth::user();
        
        // Check if user has all required information
        if (!$user->employeeDetail || !$user->employeeDetail->institution || !$user->employeeDetail->department) {
            return redirect()->route('profile.edit')
                ->with('error', 'Please complete your profile information before applying for a loan.');
        }
        
        // Get active product catalogs if no product is specified
        if (!$product->id) {
            return redirect()->route('products.index')
                ->with('error', 'Please select a loan product to apply for.');
        }
        
        // Load product details
        $product->load('financialServiceProvider', 'termsConditions');
        
        // Get banks for disbursement options
        $banks = Bank::orderBy('name')->get();
        
        return view('loan-applications.create', compact('product', 'banks'));
    }

    /**
     * Store a newly created loan application in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ProductCatalog  $product
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, ProductCatalog $product)
    {
        $user = Auth::user();
        
        // Load product details
        $product->load('financialServiceProvider');
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'loan_amount' => "required|numeric|min:{$product->min_amount}|max:{$product->max_amount}",
            'loan_term' => "required|integer|min:{$product->min_term}|max:{$product->max_term}",
            'purpose' => 'required|string|max:255',
            'bank_id' => 'required|exists:banks,id',
            'bank_branch_id' => 'required|exists:bank_branches,id',
            'account_number' => 'required|string|max:50',
            'accept_terms' => 'required|accepted',
            'additional_info' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Generate reference number
        $referenceNumber = 'LA-' . strtoupper(Str::random(8));
        
        // Calculate interest and fees
        $amount = $request->loan_amount;
        $term = $request->loan_term;
        
        // Calculate processing fee
        $processingFee = 0;
        if ($product->processing_fee) {
            if ($product->processing_fee_type === 'FIXED') {
                $processingFee = $product->processing_fee;
            } else {
                $processingFee = ($product->processing_fee / 100) * $amount;
            }
        }
        
        // Calculate insurance fee
        $insuranceFee = 0;
        if ($product->insurance_fee) {
            if ($product->insurance_fee_type === 'FIXED') {
                $insuranceFee = $product->insurance_fee;
            } else {
                $insuranceFee = ($product->insurance_fee / 100) * $amount;
            }
        }
        
        // Calculate total fees
        $totalFees = $processingFee + $insuranceFee;
        
        // Calculate interest
        $interest = 0;
        if ($product->interest_type === 'FIXED') {
            $interest = ($product->interest_rate / 100) * $amount * $term;
            if ($product->term_period === 'YEAR') {
                $interest *= 12; // Convert annual rate to monthly
            }
        } else { // REDUCING_BALANCE
            // This is a simple implementation - more complex calculations can be added
            $monthlyRate = $product->interest_rate / 100;
            if ($product->term_period === 'YEAR') {
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
        
        // Handle file attachments
        $attachments = [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('loan-applications/' . $user->id, 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        // Create loan application
        $application = LoanApplication::create([
            'user_id' => $user->id,
            'product_catalog_id' => $product->id,
            'reference_number' => $referenceNumber,
            'amount' => $amount,
            'term' => $term,
            'term_period' => $product->term_period,
            'interest_rate' => $product->interest_rate,
            'interest_type' => $product->interest_type,
            'interest_amount' => $interest,
            'processing_fee' => $processingFee,
            'insurance_fee' => $insuranceFee,
            'total_fees' => $totalFees,
            'total_amount' => $totalAmount,
            'purpose' => $request->purpose,
            'bank_id' => $request->bank_id,
            'bank_branch_id' => $request->bank_branch_id,
            'account_number' => $request->account_number,
            'additional_info' => $request->additional_info,
            'attachments' => json_encode($attachments),
            'status' => 'PENDING',
        ]);

        // Create notification
        Notification::create([
            'user_id' => $user->id,
            'title' => 'Loan Application Submitted',
            'message' => "Your loan application ({$referenceNumber}) has been submitted successfully and is pending review.",
            'type' => 'LOAN_APPLICATION',
            'reference_id' => $application->id,
            'is_read' => false,
        ]);

        // Log action
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'CREATE',
            'model_type' => 'LoanApplication',
            'model_id' => $application->id,
            'description' => 'User submitted loan application',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'reference_number' => $referenceNumber,
                'amount' => $amount,
                'term' => $term,
                'term_period' => $product->term_period,
                'interest_rate' => $product->interest_rate,
                'interest_type' => $product->interest_type,
                'total_amount' => $totalAmount,
                'purpose' => $request->purpose,
            ]),
        ]);

        return redirect()->route('loan-applications.show', $application)
            ->with('success', 'Loan application submitted successfully.');
    }

    /**
     * Display the specified loan application.
     *
     * @param  \App\Models\LoanApplication  $application
     * @return \Illuminate\View\View
     */
    public function show(LoanApplication $application)
    {
        $this->authorize('view', $application);
        
        $application->load(['productCatalog.financialServiceProvider', 'user', 'bank', 'bankBranch']);
        
        return view('loan-applications.show', compact('application'));
    }

    /**
     * Show the form for editing the specified loan application.
     *
     * @param  \App\Models\LoanApplication  $application
     * @return \Illuminate\View\View
     */
    public function edit(LoanApplication $application)
    {
        $this->authorize('update', $application);
        
        // Only pending applications can be edited
        if ($application->status !== 'PENDING') {
            return redirect()->route('loan-applications.show', $application)
                ->with('error', 'Only pending applications can be edited.');
        }
        
        $application->load(['productCatalog.financialServiceProvider', 'user', 'bank', 'bankBranch']);
        
        // Get banks for disbursement options
        $banks = Bank::orderBy('name')->get();
        
        // Get bank branches for the selected bank
        $branches = BankBranch::where('bank_id', $application->bank_id)
            ->orderBy('name')
            ->get();
        
        return view('loan-applications.edit', compact('application', 'banks', 'branches'));
    }

    /**
     * Update the specified loan application in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanApplication  $application
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, LoanApplication $application)
    {
        $this->authorize('update', $application);
        
        // Only pending applications can be updated
        if ($application->status !== 'PENDING') {
            return redirect()->route('loan-applications.show', $application)
                ->with('error', 'Only pending applications can be updated.');
        }
        
        $application->load('productCatalog');
        $product = $application->productCatalog;
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'loan_amount' => "required|numeric|min:{$product->min_amount}|max:{$product->max_amount}",
            'loan_term' => "required|integer|min:{$product->min_term}|max:{$product->max_term}",
            'purpose' => 'required|string|max:255',
            'bank_id' => 'required|exists:banks,id',
            'bank_branch_id' => 'required|exists:bank_branches,id',
            'account_number' => 'required|string|max:50',
            'additional_info' => 'nullable|string',
            'attachments.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'amount' => $application->amount,
            'term' => $application->term,
            'purpose' => $application->purpose,
            'bank_id' => $application->bank_id,
            'bank_branch_id' => $application->bank_branch_id,
            'account_number' => $application->account_number,
            'additional_info' => $application->additional_info,
        ];

        // Calculate interest and fees
        $amount = $request->loan_amount;
        $term = $request->loan_term;
        
        // Calculate processing fee
        $processingFee = 0;
        if ($product->processing_fee) {
            if ($product->processing_fee_type === 'FIXED') {
                $processingFee = $product->processing_fee;
            } else {
                $processingFee = ($product->processing_fee / 100) * $amount;
            }
        }
        
        // Calculate insurance fee
        $insuranceFee = 0;
        if ($product->insurance_fee) {
            if ($product->insurance_fee_type === 'FIXED') {
                $insuranceFee = $product->insurance_fee;
            } else {
                $insuranceFee = ($product->insurance_fee / 100) * $amount;
            }
        }
        
        // Calculate total fees
        $totalFees = $processingFee + $insuranceFee;
        
        // Calculate interest
        $interest = 0;
        if ($product->interest_type === 'FIXED') {
            $interest = ($product->interest_rate / 100) * $amount * $term;
            if ($product->term_period === 'YEAR') {
                $interest *= 12; // Convert annual rate to monthly
            }
        } else { // REDUCING_BALANCE
            // This is a simple implementation - more complex calculations can be added
            $monthlyRate = $product->interest_rate / 100;
            if ($product->term_period === 'YEAR') {
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
        
        // Handle file attachments
        $attachments = json_decode($application->attachments, true) ?: [];
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('loan-applications/' . Auth::id(), 'public');
                $attachments[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'type' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ];
            }
        }

        // Update loan application
        $application->update([
            'amount' => $amount,
            'term' => $term,
            'interest_amount' => $interest,
            'processing_fee' => $processingFee,
            'insurance_fee' => $insuranceFee,
            'total_fees' => $totalFees,
            'total_amount' => $totalAmount,
            'purpose' => $request->purpose,
            'bank_id' => $request->bank_id,
            'bank_branch_id' => $request->bank_branch_id,
            'account_number' => $request->account_number,
            'additional_info' => $request->additional_info,
            'attachments' => json_encode($attachments),
            'status' => 'PENDING',
        ]);

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'LoanApplication',
            'model_id' => $application->id,
            'description' => 'User updated loan application',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'amount' => $amount,
                'term' => $term,
                'purpose' => $request->purpose,
                'bank_id' => $request->bank_id,
                'bank_branch_id' => $request->bank_branch_id,
                'account_number' => $request->account_number,
                'additional_info' => $request->additional_info,
            ]),
        ]);

        return redirect()->route('loan-applications.show', $application)
            ->with('success', 'Loan application updated successfully.');
    }

    /**
     * Cancel the specified loan application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanApplication  $application
     * @return \Illuminate\Http\RedirectResponse
     */
    public function cancel(Request $request, LoanApplication $application)
    {
        $this->authorize('update', $application);
        
        // Only pending applications can be cancelled
        if ($application->status !== 'PENDING') {
            return back()->with('error', 'Only pending applications can be cancelled.');
        }
        
        // Store old values for audit log
        $oldValues = [
            'status' => $application->status,
        ];

        // Update loan application
        $application->update([
            'status' => 'CANCELLED',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->cancellation_reason ?? 'Cancelled by user',
        ]);

        // Create notification
        Notification::create([
            'user_id' => Auth::id(),
            'title' => 'Loan Application Cancelled',
            'message' => "Your loan application ({$application->reference_number}) has been cancelled.",
            'type' => 'LOAN_APPLICATION',
            'reference_id' => $application->id,
            'is_read' => false,
        ]);

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CANCEL',
            'model_type' => 'LoanApplication',
            'model_id' => $application->id,
            'description' => 'User cancelled loan application',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'status' => 'CANCELLED',
                'cancelled_at' => now(),
                'cancellation_reason' => $request->cancellation_reason ?? 'Cancelled by user',
            ]),
        ]);

        return redirect()->route('loan-applications.index')
            ->with('success', 'Loan application cancelled successfully.');
    }

    /**
     * Download an attachment for the specified loan application.
     *
     * @param  \App\Models\LoanApplication  $application
     * @param  int  $index
     * @return \Illuminate\Http\Response
     */
    public function downloadAttachment(LoanApplication $application, $index)
    {
        $this->authorize('view', $application);
        
        $attachments = json_decode($application->attachments, true) ?: [];
        
        if (!isset($attachments[$index])) {
            return back()->with('error', 'Attachment not found.');
        }
        
        $attachment = $attachments[$index];
        $path = Storage::disk('public')->path($attachment['path']);
        
        return response()->download($path, $attachment['name']);
    }

    /**
     * Delete an attachment for the specified loan application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanApplication  $application
     * @param  int  $index
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteAttachment(Request $request, LoanApplication $application, $index)
    {
        $this->authorize('update', $application);
        
        // Only pending applications can be updated
        if ($application->status !== 'PENDING') {
            return back()->with('error', 'Only pending applications can be updated.');
        }
        
        $attachments = json_decode($application->attachments, true) ?: [];
        
        if (!isset($attachments[$index])) {
            return back()->with('error', 'Attachment not found.');
        }
        
        $attachment = $attachments[$index];
        
        // Delete file from storage
        Storage::disk('public')->delete($attachment['path']);
        
        // Remove attachment from array
        unset($attachments[$index]);
        $attachments = array_values($attachments);
        
        // Update loan application
        $application->update([
            'attachments' => json_encode($attachments),
        ]);

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE_ATTACHMENT',
            'model_type' => 'LoanApplication',
            'model_id' => $application->id,
            'description' => 'User deleted attachment from loan application',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return back()->with('success', 'Attachment deleted successfully.');
    }

    /**
     * Submit the specified loan application for approval.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanApplication  $application
     * @return \Illuminate\Http\RedirectResponse
     */
    public function submit(Request $request, LoanApplication $application)
    {
        $this->authorize('update', $application);
        
        // Only pending applications can be submitted
        if ($application->status !== 'PENDING') {
            return back()->with('error', 'Only pending applications can be submitted.');
        }
        
        // Store old values for audit log
        $oldValues = [
            'status' => $application->status,
        ];

        // Update loan application
        $application->update([
            'status' => 'SUBMITTED',
            'submitted_at' => now(),
        ]);

        // Create notification for user
        Notification::create([
            'user_id' => Auth::id(),
            'title' => 'Loan Application Submitted',
            'message' => "Your loan application ({$application->reference_number}) has been submitted for approval.",
            'type' => 'LOAN_APPLICATION',
            'reference_id' => $application->id,
            'is_read' => false,
        ]);

        // Create notification for admin users
        $adminUsers = User::whereHas('roles', function ($query) {
            $query->whereIn('slug', ['admin', 'super-admin', 'loan-officer']);
        })->get();
        
        foreach ($adminUsers as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'title' => 'New Loan Application',
                'message' => "A new loan application ({$application->reference_number}) has been submitted and requires review.",
                'type' => 'LOAN_APPLICATION',
                'reference_id' => $application->id,
                'is_read' => false,
            ]);
        }

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'SUBMIT',
            'model_type' => 'LoanApplication',
            'model_id' => $application->id,
            'description' => 'User submitted loan application for approval',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'status' => 'SUBMITTED',
                'submitted_at' => now(),
            ]),
        ]);

        return redirect()->route('loan-applications.show', $application)
            ->with('success', 'Loan application submitted for approval successfully.');
    }

    /**
     * Get bank branches for the specified bank.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBankBranches(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_id' => 'required|exists:banks,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $branches = BankBranch::where('bank_id', $request->bank_id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'address']);

        return response()->json([
            'success' => true,
            'data' => $branches,
        ]);
    }
}
