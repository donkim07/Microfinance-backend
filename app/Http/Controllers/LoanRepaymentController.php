<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoanRepaymentController extends Controller
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
     * Display a listing of the repayments.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();
        
        // Check if admin/finance user
        if ($user->hasAnyRole(['admin', 'super-admin', 'finance-officer'])) {
            $repayments = LoanRepayment::with(['loan.user', 'user'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
                
            return view('loan-repayments.admin.index', compact('repayments'));
        }
        
        // Regular user
        $repayments = LoanRepayment::whereHas('loan', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['loan', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('loan-repayments.index', compact('repayments'));
    }

    /**
     * Show the form for creating a new repayment.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\View\View
     */
    public function create(Loan $loan)
    {
        $this->authorize('view', $loan);
        
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'Only active loans can receive repayments.');
        }
        
        return view('loan-repayments.create', compact('loan'));
    }

    /**
     * Store a newly created repayment in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, Loan $loan)
    {
        $this->authorize('view', $loan);
        
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'Only active loans can receive repayments.');
        }
        
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:BANK_TRANSFER,MOBILE_MONEY,CHECK,CASH,SALARY_DEDUCTION',
            'reference_number' => 'required|string|max:50',
            'notes' => 'nullable|string',
            'payment_proof' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Check if repayment amount is greater than outstanding amount
        if ($request->amount > $loan->outstanding_amount) {
            return back()->withErrors(['amount' => 'Repayment amount cannot be greater than the outstanding loan amount.'])->withInput();
        }

        // Handle file upload
        $proofPath = null;
        if ($request->hasFile('payment_proof')) {
            $proofPath = $request->file('payment_proof')->store('loan-repayments/' . $loan->id, 'public');
        }

        // Create repayment record
        $repayment = LoanRepayment::create([
            'loan_id' => $loan->id,
            'user_id' => Auth::id(),
            'amount' => $request->amount,
            'payment_date' => $request->payment_date,
            'payment_method' => $request->payment_method,
            'reference_number' => $request->reference_number,
            'notes' => $request->notes,
            'payment_proof' => $proofPath,
            'status' => Auth::user()->hasAnyRole(['admin', 'super-admin', 'finance-officer']) 
                ? 'COMPLETED' 
                : 'PENDING',
        ]);
        
        // If admin/finance user, update loan outstanding amount
        if (Auth::user()->hasAnyRole(['admin', 'super-admin', 'finance-officer'])) {
            $newOutstandingAmount = $loan->outstanding_amount - $request->amount;
            
            // Update loan status if fully paid
            if ($newOutstandingAmount <= 0) {
                $loan->update([
                    'outstanding_amount' => 0,
                    'status' => 'COMPLETED',
                    'actual_end_date' => now(),
                ]);
                
                // Create notification for loan completion
                Notification::create([
                    'user_id' => $loan->user_id,
                    'title' => 'Loan Completed',
                    'message' => "Your loan ({$loan->reference_number}) has been fully repaid and marked as completed.",
                    'type' => 'LOAN_COMPLETED',
                    'reference_id' => $loan->id,
                    'is_read' => false,
                ]);
            } else {
                $loan->update([
                    'outstanding_amount' => $newOutstandingAmount,
                    'status' => 'ACTIVE',
                ]);
            }
        }
        
        // Create notification
        if (Auth::id() !== $loan->user_id) {
            // Notification for loan owner if repayment added by admin
            Notification::create([
                'user_id' => $loan->user_id,
                'title' => 'Loan Repayment Recorded',
                'message' => "A repayment of {$request->amount} has been recorded for your loan ({$loan->reference_number}).",
                'type' => 'LOAN_REPAYMENT',
                'reference_id' => $repayment->id,
                'is_read' => false,
            ]);
        }
        
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'finance-officer'])) {
            // Notification for admin if repayment added by user
            $adminNotification = [
                'title' => 'New Loan Repayment',
                'message' => "A new loan repayment of {$request->amount} has been submitted for loan {$loan->reference_number} and requires verification.",
                'type' => 'LOAN_REPAYMENT',
                'reference_id' => $repayment->id,
                'is_read' => false,
            ];
            
            // Send to all admin/finance users
            $admins = User::whereHas('roles', function ($query) {
                $query->whereIn('slug', ['admin', 'super-admin', 'finance-officer']);
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
            'model_type' => 'LoanRepayment',
            'model_id' => $repayment->id,
            'description' => 'Loan repayment recorded',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'loan_id' => $loan->id,
                'amount' => $request->amount,
                'payment_date' => $request->payment_date,
                'payment_method' => $request->payment_method,
                'reference_number' => $request->reference_number,
                'status' => $repayment->status,
            ]),
        ]);
        
        return redirect()->route('loans.show', $loan)
            ->with('success', 'Loan repayment recorded successfully.');
    }
    
    /**
     * Display the specified repayment.
     *
     * @param  \App\Models\LoanRepayment  $repayment
     * @return \Illuminate\View\View
     */
    public function show(LoanRepayment $repayment)
    {
        $this->authorize('view', $repayment->loan);
        
        $repayment->load(['loan.user', 'user']);
        
        return view('loan-repayments.show', compact('repayment'));
    }
    
    /**
     * Approve a pending repayment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanRepayment  $repayment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function approve(Request $request, LoanRepayment $repayment)
    {
        // Only admin/finance users can approve
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'finance-officer'])) {
            return back()->with('error', 'You do not have permission to approve repayments.');
        }
        
        if ($repayment->status !== 'PENDING') {
            return back()->with('error', 'Only pending repayments can be approved.');
        }
        
        $loan = $repayment->loan;
        
        // Update repayment status
        $repayment->update([
            'status' => 'COMPLETED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        
        // Update loan outstanding amount
        $newOutstandingAmount = $loan->outstanding_amount - $repayment->amount;
        
        // Update loan status if fully paid
        if ($newOutstandingAmount <= 0) {
            $loan->update([
                'outstanding_amount' => 0,
                'status' => 'COMPLETED',
                'actual_end_date' => now(),
            ]);
            
            // Create notification for loan completion
            Notification::create([
                'user_id' => $loan->user_id,
                'title' => 'Loan Completed',
                'message' => "Your loan ({$loan->reference_number}) has been fully repaid and marked as completed.",
                'type' => 'LOAN_COMPLETED',
                'reference_id' => $loan->id,
                'is_read' => false,
            ]);
        } else {
            $loan->update([
                'outstanding_amount' => $newOutstandingAmount,
                'status' => 'ACTIVE',
            ]);
        }
        
        // Create notification for user
        Notification::create([
            'user_id' => $loan->user_id,
            'title' => 'Loan Repayment Approved',
            'message' => "Your loan repayment of {$repayment->amount} for loan ({$loan->reference_number}) has been approved.",
            'type' => 'LOAN_REPAYMENT',
            'reference_id' => $repayment->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'APPROVE',
            'model_type' => 'LoanRepayment',
            'model_id' => $repayment->id,
            'description' => 'Loan repayment approved',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode([
                'status' => 'PENDING',
            ]),
            'new_values' => json_encode([
                'status' => 'COMPLETED',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]),
        ]);
        
        return redirect()->route('loan-repayments.index')
            ->with('success', 'Loan repayment approved successfully.');
    }
    
    /**
     * Reject a pending repayment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanRepayment  $repayment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reject(Request $request, LoanRepayment $repayment)
    {
        // Only admin/finance users can reject
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'finance-officer'])) {
            return back()->with('error', 'You do not have permission to reject repayments.');
        }
        
        if ($repayment->status !== 'PENDING') {
            return back()->with('error', 'Only pending repayments can be rejected.');
        }
        
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        // Update repayment status
        $repayment->update([
            'status' => 'REJECTED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $repayment->loan->user_id,
            'title' => 'Loan Repayment Rejected',
            'message' => "Your loan repayment of {$repayment->amount} for loan ({$repayment->loan->reference_number}) has been rejected. Reason: {$request->rejection_reason}",
            'type' => 'LOAN_REPAYMENT',
            'reference_id' => $repayment->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'REJECT',
            'model_type' => 'LoanRepayment',
            'model_id' => $repayment->id,
            'description' => 'Loan repayment rejected',
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
        
        return redirect()->route('loan-repayments.index')
            ->with('success', 'Loan repayment rejected successfully.');
    }
    
    /**
     * Download the payment proof.
     *
     * @param  \App\Models\LoanRepayment  $repayment
     * @return \Illuminate\Http\Response
     */
    public function downloadProof(LoanRepayment $repayment)
    {
        $this->authorize('view', $repayment->loan);
        
        if (!$repayment->payment_proof) {
            return back()->with('error', 'No payment proof available for this repayment.');
        }
        
        return Storage::disk('public')->download(
            $repayment->payment_proof,
            'Payment_Proof_' . $repayment->reference_number . '.' . pathinfo($repayment->payment_proof, PATHINFO_EXTENSION)
        );
    }
}
