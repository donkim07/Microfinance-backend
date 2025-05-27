<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanTakeover;
use App\Models\Notification;
use App\Models\ProductCatalog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoanTakeoverController extends Controller
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
     * Display a listing of the loan takeovers.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();
        
        // Check if admin user
        if ($user->hasAnyRole(['admin', 'super-admin', 'loan-officer'])) {
            $takeovers = LoanTakeover::with(['loan.user', 'newLoan.productCatalog', 'user', 'approvedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
                
            return view('loan-takeovers.admin.index', compact('takeovers'));
        }
        
        // Regular user
        $takeovers = LoanTakeover::where(function($query) use ($user) {
                $query->whereHas('loan', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })->orWhereHas('newLoan', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            })
            ->with(['loan', 'newLoan.productCatalog', 'user', 'approvedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('loan-takeovers.index', compact('takeovers'));
    }

    /**
     * Show the form for creating a new loan takeover.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\View\View
     */
    public function create(Loan $loan)
    {
        $this->authorize('view', $loan);
        
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'Only active loans can be taken over.');
        }
        
        $loan->load('productCatalog.financialServiceProvider');
        
        // Get available product catalogs for takeover
        $products = ProductCatalog::with('financialServiceProvider')
            ->where('status', 'ACTIVE')
            ->where('id', '!=', $loan->product_catalog_id)
            ->get();
        
        return view('loan-takeovers.create', compact('loan', 'products'));
    }

    /**
     * Store a newly created loan takeover in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, Loan $loan)
    {
        $this->authorize('view', $loan);
        
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'Only active loans can be taken over.');
        }
        
        $validator = Validator::make($request->all(), [
            'product_catalog_id' => 'required|exists:product_catalogs,id',
            'new_term' => 'required|integer|min:1',
            'takeover_reason' => 'required|string',
            'additional_amount' => 'nullable|numeric|min:0',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $product = ProductCatalog::findOrFail($request->product_catalog_id);
        
        // Handle file upload
        $documentPath = null;
        if ($request->hasFile('supporting_document')) {
            $documentPath = $request->file('supporting_document')->store('loan-takeovers/' . $loan->id, 'public');
        }

        // Calculate new loan amount (outstanding amount + additional amount)
        $outstandingPrincipal = $loan->outstanding_amount - ($loan->interest_amount - $loan->interest_paid);
        $totalPrincipal = $outstandingPrincipal + ($request->additional_amount ?? 0);
        
        // Validate against product limits
        if ($totalPrincipal < $product->min_amount || $totalPrincipal > $product->max_amount) {
            return back()->withErrors([
                'product_catalog_id' => "The total principal amount ({$totalPrincipal}) is outside the selected product's limits ({$product->min_amount} - {$product->max_amount})."
            ])->withInput();
        }
        
        if ($request->new_term < $product->min_term || $request->new_term > $product->max_term) {
            return back()->withErrors([
                'new_term' => "The term ({$request->new_term}) is outside the selected product's limits ({$product->min_term} - {$product->max_term})."
            ])->withInput();
        }
        
        // Calculate interest for new loan
        $newInterestAmount = 0;
        if ($product->interest_type === 'FIXED') {
            $newInterestAmount = ($product->interest_rate / 100) * $totalPrincipal * $request->new_term;
            if ($product->term_period === 'YEAR') {
                $newInterestAmount *= 12; // Convert annual rate to monthly
            }
        } else { // REDUCING_BALANCE
            $monthlyRate = $product->interest_rate / 100;
            if ($product->term_period === 'YEAR') {
                $monthlyRate /= 12;
                $term = $request->new_term * 12;
            } else {
                $term = $request->new_term;
            }
            
            $principal = $totalPrincipal;
            $newInterestAmount = 0;
            
            for ($i = 0; $i < $term; $i++) {
                $monthlyInterest = $principal * $monthlyRate;
                $newInterestAmount += $monthlyInterest;
                
                // Calculate principal payment for this period
                $monthlyPrincipal = $totalPrincipal / $term;
                $principal -= $monthlyPrincipal;
            }
        }
        
        // Calculate fees
        $processingFee = 0;
        if ($product->processing_fee) {
            if ($product->processing_fee_type === 'FIXED') {
                $processingFee = $product->processing_fee;
            } else {
                $processingFee = ($product->processing_fee / 100) * $totalPrincipal;
            }
        }
        
        $insuranceFee = 0;
        if ($product->insurance_fee) {
            if ($product->insurance_fee_type === 'FIXED') {
                $insuranceFee = $product->insurance_fee;
            } else {
                $insuranceFee = ($product->insurance_fee / 100) * $totalPrincipal;
            }
        }
        
        $totalFees = $processingFee + $insuranceFee;
        $newTotalAmount = $totalPrincipal + $newInterestAmount + $totalFees;
        
        // Create takeover request
        $takeover = LoanTakeover::create([
            'loan_id' => $loan->id,
            'user_id' => Auth::id(),
            'product_catalog_id' => $request->product_catalog_id,
            'outstanding_principal' => $outstandingPrincipal,
            'additional_amount' => $request->additional_amount ?? 0,
            'total_principal' => $totalPrincipal,
            'new_term' => $request->new_term,
            'new_interest_rate' => $product->interest_rate,
            'new_interest_type' => $product->interest_type,
            'new_interest_amount' => $newInterestAmount,
            'fees_amount' => $totalFees,
            'new_total_amount' => $newTotalAmount,
            'takeover_reason' => $request->takeover_reason,
            'supporting_document' => $documentPath,
            'status' => Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer']) 
                ? 'APPROVED' 
                : 'PENDING',
        ]);
        
        // If admin/loan officer, process takeover immediately
        if (Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer'])) {
            // Generate reference number for new loan
            $referenceNumber = 'LN-' . strtoupper(Str::random(8));
            
            // Calculate expected end date
            $startDate = Carbon::now();
            $endDate = clone $startDate;
            
            if ($product->term_period === 'DAY') {
                $endDate->addDays($request->new_term);
            } elseif ($product->term_period === 'WEEK') {
                $endDate->addWeeks($request->new_term);
            } elseif ($product->term_period === 'MONTH') {
                $endDate->addMonths($request->new_term);
            } elseif ($product->term_period === 'YEAR') {
                $endDate->addYears($request->new_term);
            }
            
            // Create new loan
            $newLoan = Loan::create([
                'user_id' => $loan->user_id,
                'product_catalog_id' => $request->product_catalog_id,
                'reference_number' => $referenceNumber,
                'principal_amount' => $totalPrincipal,
                'term' => $request->new_term,
                'term_period' => $product->term_period,
                'interest_rate' => $product->interest_rate,
                'interest_type' => $product->interest_type,
                'interest_amount' => $newInterestAmount,
                'fees_amount' => $totalFees,
                'total_amount' => $newTotalAmount,
                'outstanding_amount' => $newTotalAmount,
                'status' => 'ACTIVE',
                'purpose' => 'Takeover of loan ' . $loan->reference_number . ($request->additional_amount > 0 ? ' with additional amount' : ''),
                'bank_id' => $loan->bank_id,
                'bank_branch_id' => $loan->bank_branch_id,
                'account_number' => $loan->account_number,
                'start_date' => $startDate,
                'expected_end_date' => $endDate,
                'takeover_from_loan_id' => $loan->id,
            ]);
            
            // Update old loan
            $loan->update([
                'status' => 'TAKEN_OVER',
                'taken_over_at' => now(),
                'taken_over_by_loan_id' => $newLoan->id,
                'actual_end_date' => now(),
            ]);
            
            // Update takeover record
            $takeover->update([
                'new_loan_id' => $newLoan->id,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'processed_at' => now(),
            ]);
            
            // Create notification for user
            if (Auth::id() !== $loan->user_id) {
                Notification::create([
                    'user_id' => $loan->user_id,
                    'title' => 'Loan Takeover Processed',
                    'message' => "Your loan ({$loan->reference_number}) has been taken over. A new loan ({$newLoan->reference_number}) has been created with a principal amount of {$totalPrincipal}.",
                    'type' => 'LOAN_TAKEOVER',
                    'reference_id' => $takeover->id,
                    'is_read' => false,
                ]);
            }
        } else {
            // Create notifications for admins
            $adminNotification = [
                'title' => 'New Loan Takeover Request',
                'message' => "A new loan takeover request has been submitted for loan {$loan->reference_number} and requires approval.",
                'type' => 'LOAN_TAKEOVER',
                'reference_id' => $takeover->id,
                'is_read' => false,
            ];
            
            // Send to all admin/loan officer users
            $admins = User::whereHas('roles', function ($query) {
                $query->whereIn('slug', ['admin', 'super-admin', 'loan-officer']);
            })->get();
            
            foreach ($admins as $admin) {
                $adminNotification['user_id'] = $admin->id;
                Notification::create($adminNotification);
            }
        }
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'LoanTakeover',
            'model_id' => $takeover->id,
            'description' => 'Loan takeover request created',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'loan_id' => $loan->id,
                'product_catalog_id' => $request->product_catalog_id,
                'outstanding_principal' => $outstandingPrincipal,
                'additional_amount' => $request->additional_amount ?? 0,
                'total_principal' => $totalPrincipal,
                'new_term' => $request->new_term,
                'new_interest_rate' => $product->interest_rate,
                'new_interest_type' => $product->interest_type,
                'new_total_amount' => $newTotalAmount,
                'takeover_reason' => $request->takeover_reason,
                'status' => $takeover->status,
            ]),
        ]);
        
        if (Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer'])) {
            return redirect()->route('loans.show', $newLoan)
                ->with('success', 'Loan takeover processed successfully. A new loan has been created.');
        } else {
            return redirect()->route('loan-takeovers.index')
                ->with('success', 'Loan takeover request submitted successfully and is pending approval.');
        }
    }
    
    /**
     * Display the specified loan takeover.
     *
     * @param  \App\Models\LoanTakeover  $takeover
     * @return \Illuminate\View\View
     */
    public function show(LoanTakeover $takeover)
    {
        $takeover->load(['loan.user', 'newLoan.productCatalog', 'user', 'approvedBy', 'productCatalog']);
        
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer']) && 
            $takeover->loan->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        return view('loan-takeovers.show', compact('takeover'));
    }
    
    /**
     * Approve a pending loan takeover.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanTakeover  $takeover
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(Request $request, LoanTakeover $takeover)
    {
        // Only admin/loan officer users can approve
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer'])) {
            return back()->with('error', 'You do not have permission to approve loan takeovers.');
        }
        
        if ($takeover->status !== 'PENDING') {
            return back()->with('error', 'Only pending loan takeovers can be approved.');
        }
        
        $loan = $takeover->loan;
        $product = $takeover->productCatalog;
        
        // Generate reference number for new loan
        $referenceNumber = 'LN-' . strtoupper(Str::random(8));
        
        // Calculate expected end date
        $startDate = Carbon::now();
        $endDate = clone $startDate;
        
        if ($product->term_period === 'DAY') {
            $endDate->addDays($takeover->new_term);
        } elseif ($product->term_period === 'WEEK') {
            $endDate->addWeeks($takeover->new_term);
        } elseif ($product->term_period === 'MONTH') {
            $endDate->addMonths($takeover->new_term);
        } elseif ($product->term_period === 'YEAR') {
            $endDate->addYears($takeover->new_term);
        }
        
        // Create new loan
        $newLoan = Loan::create([
            'user_id' => $loan->user_id,
            'product_catalog_id' => $takeover->product_catalog_id,
            'reference_number' => $referenceNumber,
            'principal_amount' => $takeover->total_principal,
            'term' => $takeover->new_term,
            'term_period' => $product->term_period,
            'interest_rate' => $takeover->new_interest_rate,
            'interest_type' => $takeover->new_interest_type,
            'interest_amount' => $takeover->new_interest_amount,
            'fees_amount' => $takeover->fees_amount,
            'total_amount' => $takeover->new_total_amount,
            'outstanding_amount' => $takeover->new_total_amount,
            'status' => 'ACTIVE',
            'purpose' => 'Takeover of loan ' . $loan->reference_number . ($takeover->additional_amount > 0 ? ' with additional amount' : ''),
            'bank_id' => $loan->bank_id,
            'bank_branch_id' => $loan->bank_branch_id,
            'account_number' => $loan->account_number,
            'start_date' => $startDate,
            'expected_end_date' => $endDate,
            'takeover_from_loan_id' => $loan->id,
        ]);
        
        // Update old loan
        $loan->update([
            'status' => 'TAKEN_OVER',
            'taken_over_at' => now(),
            'taken_over_by_loan_id' => $newLoan->id,
            'actual_end_date' => now(),
        ]);
        
        // Update takeover record
        $takeover->update([
            'status' => 'APPROVED',
            'new_loan_id' => $newLoan->id,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'processed_at' => now(),
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $loan->user_id,
            'title' => 'Loan Takeover Approved',
            'message' => "Your loan takeover request for loan ({$loan->reference_number}) has been approved. A new loan ({$newLoan->reference_number}) has been created with a principal amount of {$takeover->total_principal}.",
            'type' => 'LOAN_TAKEOVER',
            'reference_id' => $takeover->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'APPROVE',
            'model_type' => 'LoanTakeover',
            'model_id' => $takeover->id,
            'description' => 'Loan takeover request approved',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode([
                'status' => 'PENDING',
            ]),
            'new_values' => json_encode([
                'status' => 'APPROVED',
                'new_loan_id' => $newLoan->id,
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'processed_at' => now(),
            ]),
        ]);
        
        return redirect()->route('loans.show', $newLoan)
            ->with('success', 'Loan takeover approved successfully. A new loan has been created.');
    }
    
    /**
     * Reject a pending loan takeover.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanTakeover  $takeover
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Request $request, LoanTakeover $takeover)
    {
        // Only admin/loan officer users can reject
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer'])) {
            return back()->with('error', 'You do not have permission to reject loan takeovers.');
        }
        
        if ($takeover->status !== 'PENDING') {
            return back()->with('error', 'Only pending loan takeovers can be rejected.');
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        // Update takeover record
        $takeover->update([
            'status' => 'REJECTED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $takeover->loan->user_id,
            'title' => 'Loan Takeover Rejected',
            'message' => "Your loan takeover request for loan ({$takeover->loan->reference_number}) has been rejected. Reason: {$request->rejection_reason}",
            'type' => 'LOAN_TAKEOVER',
            'reference_id' => $takeover->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'REJECT',
            'model_type' => 'LoanTakeover',
            'model_id' => $takeover->id,
            'description' => 'Loan takeover request rejected',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode([
                'status' => 'PENDING',
            ]),
            'new_values' => json_encode([
                'status' => 'REJECTED',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'rejection_reason' => $request->rejection_reason,
            ]),
        ]);
        
        return redirect()->route('loan-takeovers.index')
            ->with('success', 'Loan takeover rejected successfully.');
    }
    
    /**
     * Download the supporting document.
     *
     * @param  \App\Models\LoanTakeover  $takeover
     * @return \Illuminate\Http\Response
     */
    public function downloadDocument(LoanTakeover $takeover)
    {
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer']) && 
            $takeover->loan->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        if (!$takeover->supporting_document) {
            return back()->with('error', 'No supporting document available for this takeover request.');
        }
        
        return Storage::disk('public')->download(
            $takeover->supporting_document,
            'Supporting_Document_' . $takeover->id . '.' . pathinfo($takeover->supporting_document, PATHINFO_EXTENSION)
        );
    }
}
