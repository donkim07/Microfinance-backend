<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Deduction;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Notification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DeductionController extends Controller
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
     * Display a listing of the deductions.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();
        
        // Check if admin/finance user
        if ($user->hasAnyRole(['admin', 'super-admin', 'finance-officer'])) {
            $deductions = Deduction::with(['loan.user', 'createdBy'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);
                
            return view('deductions.admin.index', compact('deductions'));
        }
        
        // Regular user
        $deductions = Deduction::whereHas('loan', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with(['loan', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('deductions.index', compact('deductions'));
    }

    /**
     * Show the form for creating a new deduction.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\View\View
     */
    public function create(Loan $loan)
    {
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'Only active loans can have deductions set up.');
        }
        
        if ($loan->outstanding_amount <= 0) {
            return back()->with('error', 'This loan has been fully repaid and does not require deductions.');
        }
        
        $loan->load(['user.employeeDetail', 'productCatalog']);
        
        return view('deductions.create', compact('loan'));
    }

    /**
     * Store a newly created deduction in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, Loan $loan)
    {
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'Only active loans can have deductions set up.');
        }
        
        if ($loan->outstanding_amount <= 0) {
            return back()->with('error', 'This loan has been fully repaid and does not require deductions.');
        }
        
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1|max:' . $loan->outstanding_amount,
            'frequency' => 'required|in:MONTHLY,WEEKLY,QUARTERLY',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string|max:50',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle file upload
        $documentPath = null;
        if ($request->hasFile('supporting_document')) {
            $documentPath = $request->file('supporting_document')->store('deductions/' . $loan->id, 'public');
        }

        // Create deduction
        $deduction = Deduction::create([
            'loan_id' => $loan->id,
            'created_by' => Auth::id(),
            'amount' => $request->amount,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
            'reference_number' => $request->reference_number ?? 'DED-' . strtoupper(Str::random(8)),
            'supporting_document' => $documentPath,
            'status' => 'ACTIVE',
        ]);
        
        // Create notification for user if created by admin
        if (Auth::id() !== $loan->user_id) {
            Notification::create([
                'user_id' => $loan->user_id,
                'title' => 'Salary Deduction Set Up',
                'message' => "A salary deduction of {$request->amount} ({$request->frequency}) has been set up for your loan ({$loan->reference_number}). Starting from: {$request->start_date}",
                'type' => 'DEDUCTION',
                'reference_id' => $deduction->id,
                'is_read' => false,
            ]);
        }
        
        // Create notifications for admins if created by user
        if (Auth::id() === $loan->user_id) {
            $adminNotification = [
                'title' => 'New Salary Deduction Request',
                'message' => "A new salary deduction request has been submitted for loan {$loan->reference_number} and may require verification.",
                'type' => 'DEDUCTION',
                'reference_id' => $deduction->id,
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
            'model_type' => 'Deduction',
            'model_id' => $deduction->id,
            'description' => 'Salary deduction set up',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'loan_id' => $loan->id,
                'amount' => $request->amount,
                'frequency' => $request->frequency,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'reference_number' => $deduction->reference_number,
                'status' => 'ACTIVE',
            ]),
        ]);
        
        return redirect()->route('deductions.index')
            ->with('success', 'Salary deduction set up successfully.');
    }

    /**
     * Display the specified deduction.
     *
     * @param  \App\Models\Deduction  $deduction
     * @return \Illuminate\View\View
     */
    public function show(Deduction $deduction)
    {
        $deduction->load(['loan.user', 'loan.productCatalog', 'createdBy']);
        
        // Check if user has permission to view this deduction
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'finance-officer']) && 
            $deduction->loan->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        return view('deductions.show', compact('deduction'));
    }

    /**
     * Show the form for editing the specified deduction.
     *
     * @param  \App\Models\Deduction  $deduction
     * @return \Illuminate\View\View
     */
    public function edit(Deduction $deduction)
    {
        // Only admin/finance users can edit deductions
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'finance-officer'])) {
            return back()->with('error', 'You do not have permission to edit deductions.');
        }
        
        $deduction->load(['loan.user', 'loan.productCatalog', 'createdBy']);
        
        return view('deductions.edit', compact('deduction'));
    }

    /**
     * Update the specified deduction in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Deduction  $deduction
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Deduction $deduction)
    {
        // Only admin/finance users can update deductions
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'finance-officer'])) {
            return back()->with('error', 'You do not have permission to update deductions.');
        }
        
        $loan = $deduction->loan;
        
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1|max:' . $loan->outstanding_amount,
            'frequency' => 'required|in:MONTHLY,WEEKLY,QUARTERLY',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'description' => 'nullable|string',
            'reference_number' => 'nullable|string|max:50',
            'status' => 'required|in:ACTIVE,INACTIVE,COMPLETED',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'amount' => $deduction->amount,
            'frequency' => $deduction->frequency,
            'start_date' => $deduction->start_date,
            'end_date' => $deduction->end_date,
            'description' => $deduction->description,
            'reference_number' => $deduction->reference_number,
            'status' => $deduction->status,
        ];

        // Handle file upload
        if ($request->hasFile('supporting_document')) {
            // Delete old document if exists
            if ($deduction->supporting_document) {
                Storage::disk('public')->delete($deduction->supporting_document);
            }
            
            $documentPath = $request->file('supporting_document')->store('deductions/' . $loan->id, 'public');
            $deduction->supporting_document = $documentPath;
        }

        // Update deduction
        $deduction->update([
            'amount' => $request->amount,
            'frequency' => $request->frequency,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'description' => $request->description,
            'reference_number' => $request->reference_number ?? $deduction->reference_number,
            'status' => $request->status,
            'completed_at' => $request->status === 'COMPLETED' ? now() : $deduction->completed_at,
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $loan->user_id,
            'title' => 'Salary Deduction Updated',
            'message' => "The salary deduction for your loan ({$loan->reference_number}) has been updated. Amount: {$request->amount}, Status: {$request->status}",
            'type' => 'DEDUCTION',
            'reference_id' => $deduction->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'Deduction',
            'model_id' => $deduction->id,
            'description' => 'Salary deduction updated',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'amount' => $request->amount,
                'frequency' => $request->frequency,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'description' => $request->description,
                'reference_number' => $deduction->reference_number,
                'status' => $request->status,
            ]),
        ]);
        
        return redirect()->route('deductions.index')
            ->with('success', 'Salary deduction updated successfully.');
    }

    /**
     * Process deduction payment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Deduction  $deduction
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processPayment(Request $request, Deduction $deduction)
    {
        // Only admin/finance users can process payments
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'finance-officer'])) {
            return back()->with('error', 'You do not have permission to process deduction payments.');
        }
        
        if ($deduction->status !== 'ACTIVE') {
            return back()->with('error', 'Only active deductions can be processed for payment.');
        }
        
        $validator = Validator::make($request->all(), [
            'payment_date' => 'required|date',
            'payment_amount' => 'required|numeric|min:1|max:' . $deduction->amount,
            'payment_reference' => 'required|string|max:50',
            'payment_note' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $loan = $deduction->loan;
        
        // Check if loan is still active
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'The associated loan is no longer active.');
        }
        
        // Create loan repayment record
        $repayment = LoanRepayment::create([
            'loan_id' => $loan->id,
            'user_id' => Auth::id(),
            'amount' => $request->payment_amount,
            'payment_date' => $request->payment_date,
            'payment_method' => 'SALARY_DEDUCTION',
            'reference_number' => $request->payment_reference,
            'notes' => $request->payment_note ?? 'Processed from salary deduction',
            'status' => 'COMPLETED',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        
        // Update loan outstanding amount
        $newOutstandingAmount = $loan->outstanding_amount - $request->payment_amount;
        
        // Update loan status if fully paid
        if ($newOutstandingAmount <= 0) {
            $loan->update([
                'outstanding_amount' => 0,
                'status' => 'COMPLETED',
                'actual_end_date' => now(),
            ]);
            
            // Update deduction status
            $deduction->update([
                'status' => 'COMPLETED',
                'completed_at' => now(),
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
            'title' => 'Salary Deduction Payment Processed',
            'message' => "A salary deduction payment of {$request->payment_amount} has been processed for your loan ({$loan->reference_number}).",
            'type' => 'DEDUCTION',
            'reference_id' => $deduction->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'PROCESS_PAYMENT',
            'model_type' => 'Deduction',
            'model_id' => $deduction->id,
            'description' => 'Salary deduction payment processed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'payment_date' => $request->payment_date,
                'payment_amount' => $request->payment_amount,
                'payment_reference' => $request->payment_reference,
                'loan_outstanding' => $newOutstandingAmount,
            ]),
        ]);
        
        return redirect()->route('deductions.show', $deduction)
            ->with('success', 'Salary deduction payment processed successfully.');
    }

    /**
     * Download the supporting document.
     *
     * @param  \App\Models\Deduction  $deduction
     * @return \Illuminate\Http\Response
     */
    public function downloadDocument(Deduction $deduction)
    {
        // Check if user has permission to view this deduction
        if (!Auth::user()->hasAnyRole(['admin', 'super-admin', 'finance-officer']) && 
            $deduction->loan->user_id !== Auth::id()) {
            abort(403, 'Unauthorized action.');
        }
        
        if (!$deduction->supporting_document) {
            return back()->with('error', 'No supporting document available for this deduction.');
        }
        
        return Storage::disk('public')->download(
            $deduction->supporting_document,
            'Deduction_Document_' . $deduction->reference_number . '.' . pathinfo($deduction->supporting_document, PATHINFO_EXTENSION)
        );
    }
}
