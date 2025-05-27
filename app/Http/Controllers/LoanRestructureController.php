<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanRestructure;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoanRestructureController extends Controller
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
     * Display a listing of the loan restructures.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();
        
        // Check if admin user
        if ($user->hasAnyRole(['admin', 'super-admin', 'loan-officer'])) {
            $restructures = LoanRestructure::with(['loan.user', 'user', 'approvedBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
                
            return view('loan-restructures.admin.index', compact('restructures'));
        }
        
        // Regular user
        $restructures = LoanRestructure::whereHas('loan', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['loan', 'user', 'approvedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('loan-restructures.index', compact('restructures'));
    }

    /**
     * Show the form for creating a new loan restructure.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\View\View
     */
    public function create(Loan $loan)
    {
        $this->authorize('view', $loan);
        
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'Only active loans can be restructured.');
        }
        
        $loan->load('productCatalog');
        
        return view('loan-restructures.create', compact('loan'));
    }

    /**
     * Store a newly created loan restructure in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, Loan $loan)
    {
        $this->authorize('view', $loan);
        
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'Only active loans can be restructured.');
        }
        
        $validator = Validator::make($request->all(), [
            'new_term' => 'required|integer|min:1',
            'new_interest_rate' => 'required|numeric|min:0|max:100',
            'new_interest_type' => 'required|in:FIXED,REDUCING_BALANCE',
            'restructure_reason' => 'required|string',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle file upload
        $documentPath = null;
        if ($request->hasFile('supporting_document')) {
            $documentPath = $request->file('supporting_document')->store('loan-restructures/' . $loan->id, 'public');
        }

        // Calculate new interest and total amount
        $outstandingPrincipal = $loan->outstanding_amount - ($loan->interest_amount - $loan->interest_paid);
        $newInterestAmount = 0;
        
        if ($request->new_interest_type === 'FIXED') {
            $newInterestAmount = ($request->new_interest_rate / 100) * $outstandingPrincipal * $request->new_term;
            if ($loan->term_period === 'YEAR') {
                $newInterestAmount *= 12; // Convert annual rate to monthly
            }
        } else { // REDUCING_BALANCE
            $monthlyRate = $request->new_interest_rate / 100;
            if ($loan->term_period === 'YEAR') {
                $monthlyRate /= 12;
                $term = $request->new_term * 12;
            } else {
                $term = $request->new_term;
            }
            
            $principal = $outstandingPrincipal;
            $newInterestAmount = 0;
            
            for ($i = 0; $i < $term; $i++) {
                $monthlyInterest = $principal * $monthlyRate;
                $newInterestAmount += $monthlyInterest;
                
                // Calculate principal payment for this period
                $monthlyPrincipal = $outstandingPrincipal / $term;
                $principal -= $monthlyPrincipal;
            }
        }
        
        $newTotalAmount = $outstandingPrincipal + $newInterestAmount;
        
        // Create restructure request
        $restructure = LoanRestructure::create([
            'loan_id' => $loan->id,
            'user_id' => Auth::id(),
            'outstanding_principal' => $outstandingPrincipal,
            'current_term_remaining' => $loan->term_remaining,
            'current_interest_rate' => $loan->interest_rate,
            'current_interest_type' => $loan->interest_type,
            'new_term' => $request->new_term,
            'new_interest_rate' => $request->new_interest_rate,
            'new_interest_type' => $request->new_interest_type,
            'new_interest_amount' => $newInterestAmount,
            'new_total_amount' => $newTotalAmount,
            'restructure_reason' => $request->restructure_reason,
            'supporting_document' => $documentPath,
            'status' => Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer']) 
                ? 'APPROVED' 
                : 'PENDING',
        ]);
        
        // If admin/loan officer, apply restructure immediately
        if (Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer'])) {
            // Update loan
            $endDate = Carbon::now()->addMonths($request->new_term);
            if ($loan->term_period === 'DAY') {
                $endDate = Carbon::now()->addDays($request->new_term);
            } elseif ($loan->term_period === 'WEEK') {
                $endDate = Carbon::now()->addWeeks($request->new_term);
            } elseif ($loan->term_period === 'YEAR') {
                $endDate = Carbon::now()->addYears($request->new_term);
            }
            
            $loan->update([
                'term' => $loan->term + $request->new_term,
                'term_remaining' => $request->new_term,
                'interest_rate' => $request->new_interest_rate,
                'interest_type' => $request->new_interest_type,
                'interest_amount' => $loan->interest_amount + $newInterestAmount,
                'total_amount' => $loan->total_amount - $loan->outstanding_amount + $newTotalAmount,
                'outstanding_amount' => $newTotalAmount,
                'expected_end_date' => $endDate,
                'restructured_at' => now(),
            ]);
            
            // Update restructure record
            $restructure->update([
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'applied_at' => now(),
            ]);
            
            // Create notification for user
            if (Auth::id() !== $loan->user_id) {
                Notification::create([
                    'user_id' => $loan->user_id,
                    'title' => 'Loan Restructured',
                    'message' => "Your loan ({$loan->reference_number}) has been restructured. New term: {$request->new_term} {$loan->term_period}s, New interest rate: {$request->new_interest_rate}%",
                    'type' => 'LOAN_RESTRUCTURE',
                    'reference_id' => $restructure->id,
                    'is_read' => false,
                ]);
            }
        } else {
            // Create notifications for admins
            $adminNotification = [
                'title' => 'New Loan Restructure Request',
                'message' => "A new loan restructure request has been submitted for loan {$loan->reference_number} and requires approval.",
                'type' => 'LOAN_RESTRUCTURE',
                'reference_id' => $restructure->id,
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
            'model_type' => 'LoanRestructure',
            'model_id' => $restructure->id,
            'description' => 'Loan restructure request created',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'loan_id' => $loan->id,
                'outstanding_principal' => $outstandingPrincipal,
                'new_term' => $request->new_term,
                'new_interest_rate' => $request->new_interest_rate,
                'new_interest_type' => $request->new_interest_type,
                'new_total_amount' => $newTotalAmount,
                'restructure_reason' => $request->restructure_reason,
                'status' => $restructure->status,
            ]),
        ]);
        
        if (Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer'])) {
            return redirect()->route('loans.show', $loan)
                ->with('success', 'Loan restructured successfully.');
        } else {
            return redirect()->route('loan-restructures.index')
                ->with('success', 'Loan restructure request submitted successfully and is pending approval.');
        }
    }
    
    /**
     * Display the specified loan restructure.
     *
     * @param  \App\Models\LoanRestructure  $restructure
     * @return \Illuminate\View\View
     */
    public function show(LoanRestructure $restructure)
    {
        $restructure->load(['loan.user', 'user', 'approvedBy']);
        
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer']) && 
            $restructure->loan->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        return view('loan-restructures.show', compact('restructure'));
    }
    
    /**
     * Approve a pending loan restructure.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanRestructure  $restructure
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(Request $request, LoanRestructure $restructure)
    {
        // Only admin/loan officer users can approve
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer'])) {
            return back()->with('error', 'You do not have permission to approve loan restructures.');
        }
        
        if ($restructure->status !== 'PENDING') {
            return back()->with('error', 'Only pending loan restructures can be approved.');
        }
        
        $loan = $restructure->loan;
        
        // Update loan
        $endDate = Carbon::now()->addMonths($restructure->new_term);
        if ($loan->term_period === 'DAY') {
            $endDate = Carbon::now()->addDays($restructure->new_term);
        } elseif ($loan->term_period === 'WEEK') {
            $endDate = Carbon::now()->addWeeks($restructure->new_term);
        } elseif ($loan->term_period === 'YEAR') {
            $endDate = Carbon::now()->addYears($restructure->new_term);
        }
        
        $loan->update([
            'term' => $loan->term + $restructure->new_term,
            'term_remaining' => $restructure->new_term,
            'interest_rate' => $restructure->new_interest_rate,
            'interest_type' => $restructure->new_interest_type,
            'interest_amount' => $loan->interest_amount + $restructure->new_interest_amount,
            'total_amount' => $loan->total_amount - $loan->outstanding_amount + $restructure->new_total_amount,
            'outstanding_amount' => $restructure->new_total_amount,
            'expected_end_date' => $endDate,
            'restructured_at' => now(),
        ]);
        
        // Update restructure record
        $restructure->update([
            'status' => 'APPROVED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'applied_at' => now(),
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $loan->user_id,
            'title' => 'Loan Restructure Approved',
            'message' => "Your loan restructure request for loan ({$loan->reference_number}) has been approved. New term: {$restructure->new_term} {$loan->term_period}s, New interest rate: {$restructure->new_interest_rate}%",
            'type' => 'LOAN_RESTRUCTURE',
            'reference_id' => $restructure->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'APPROVE',
            'model_type' => 'LoanRestructure',
            'model_id' => $restructure->id,
            'description' => 'Loan restructure request approved',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode([
                'status' => 'PENDING',
            ]),
            'new_values' => json_encode([
                'status' => 'APPROVED',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'applied_at' => now(),
            ]),
        ]);
        
        return redirect()->route('loan-restructures.index')
            ->with('success', 'Loan restructure approved successfully.');
    }
    
    /**
     * Reject a pending loan restructure.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanRestructure  $restructure
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Request $request, LoanRestructure $restructure)
    {
        // Only admin/loan officer users can reject
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer'])) {
            return back()->with('error', 'You do not have permission to reject loan restructures.');
        }
        
        if ($restructure->status !== 'PENDING') {
            return back()->with('error', 'Only pending loan restructures can be rejected.');
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        // Update restructure record
        $restructure->update([
            'status' => 'REJECTED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $restructure->loan->user_id,
            'title' => 'Loan Restructure Rejected',
            'message' => "Your loan restructure request for loan ({$restructure->loan->reference_number}) has been rejected. Reason: {$request->rejection_reason}",
            'type' => 'LOAN_RESTRUCTURE',
            'reference_id' => $restructure->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'REJECT',
            'model_type' => 'LoanRestructure',
            'model_id' => $restructure->id,
            'description' => 'Loan restructure request rejected',
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
        
        return redirect()->route('loan-restructures.index')
            ->with('success', 'Loan restructure rejected successfully.');
    }
    
    /**
     * Download the supporting document.
     *
     * @param  \App\Models\LoanRestructure  $restructure
     * @return \Illuminate\Http\Response
     */
    public function downloadDocument(LoanRestructure $restructure)
    {
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'loan-officer']) && 
            $restructure->loan->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        if (!$restructure->supporting_document) {
            return back()->with('error', 'No supporting document available for this restructure request.');
        }
        
        return Storage::disk('public')->download(
            $restructure->supporting_document,
            'Supporting_Document_' . $restructure->id . '.' . pathinfo($restructure->supporting_document, PATHINFO_EXTENSION)
        );
    }
}
