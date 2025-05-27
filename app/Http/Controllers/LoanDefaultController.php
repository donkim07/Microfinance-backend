<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanDefault;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LoanDefaultController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin|super-admin|finance-officer|loan-officer']);
    }

    /**
     * Display a listing of the loan defaults.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $defaults = LoanDefault::with(['loan.user', 'loan.productCatalog', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('loan-defaults.index', compact('defaults'));
    }

    /**
     * Show the form for creating a new loan default.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\View\View
     */
    public function create(Loan $loan)
    {
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'Only active loans can be marked as defaulted.');
        }
        
        $loan->load(['user.employeeDetail', 'productCatalog.financialServiceProvider']);
        
        return view('loan-defaults.create', compact('loan'));
    }

    /**
     * Store a newly created loan default in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, Loan $loan)
    {
        if (!in_array($loan->status, ['ACTIVE', 'DISBURSED'])) {
            return back()->with('error', 'Only active loans can be marked as defaulted.');
        }
        
        $validator = Validator::make($request->all(), [
            'default_reason' => 'required|string',
            'default_date' => 'required|date',
            'default_amount' => 'required|numeric|min:0|max:' . $loan->outstanding_amount,
            'recovery_plan' => 'nullable|string',
            'comments' => 'nullable|string',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle file upload
        $documentPath = null;
        if ($request->hasFile('supporting_document')) {
            $documentPath = $request->file('supporting_document')->store('loan-defaults/' . $loan->id, 'public');
        }

        // Create loan default
        $default = LoanDefault::create([
            'loan_id' => $loan->id,
            'created_by' => Auth::id(),
            'default_reason' => $request->default_reason,
            'default_date' => $request->default_date,
            'default_amount' => $request->default_amount,
            'recovery_plan' => $request->recovery_plan,
            'comments' => $request->comments,
            'supporting_document' => $documentPath,
            'status' => 'ACTIVE',
        ]);
        
        // Update loan status
        $loan->update([
            'status' => 'DEFAULTED',
            'defaulted_at' => $request->default_date,
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $loan->user_id,
            'title' => 'Loan Marked as Defaulted',
            'message' => "Your loan ({$loan->reference_number}) has been marked as defaulted. Default amount: {$request->default_amount}. Reason: {$request->default_reason}",
            'type' => 'LOAN_DEFAULT',
            'reference_id' => $default->id,
            'is_read' => false,
        ]);
        
        // Create notifications for admins
        $adminNotification = [
            'title' => 'Loan Default Recorded',
            'message' => "A loan ({$loan->reference_number}) for user {$loan->user->name} has been marked as defaulted. Default amount: {$request->default_amount}",
            'type' => 'LOAN_DEFAULT',
            'reference_id' => $default->id,
            'is_read' => false,
        ];
        
        // Send to all admin/finance users except the current user
        $admins = User::whereHas('roles', function ($query) {
                $query->whereIn('slug', ['admin', 'super-admin', 'finance-officer']);
            })
            ->where('id', '!=', Auth::id())
            ->get();
        
        foreach ($admins as $admin) {
            $adminNotification['user_id'] = $admin->id;
            Notification::create($adminNotification);
        }
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'LoanDefault',
            'model_id' => $default->id,
            'description' => 'Loan marked as defaulted',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'loan_id' => $loan->id,
                'default_reason' => $request->default_reason,
                'default_date' => $request->default_date,
                'default_amount' => $request->default_amount,
                'recovery_plan' => $request->recovery_plan,
                'status' => 'ACTIVE',
            ]),
        ]);
        
        return redirect()->route('loan-defaults.index')
            ->with('success', 'Loan marked as defaulted successfully.');
    }
    
    /**
     * Display the specified loan default.
     *
     * @param  \App\Models\LoanDefault  $default
     * @return \Illuminate\View\View
     */
    public function show(LoanDefault $default)
    {
        $default->load(['loan.user.employeeDetail', 'loan.productCatalog', 'createdBy']);
        
        return view('loan-defaults.show', compact('default'));
    }
    
    /**
     * Show the form for editing the specified loan default.
     *
     * @param  \App\Models\LoanDefault  $default
     * @return \Illuminate\View\View
     */
    public function edit(LoanDefault $default)
    {
        $default->load(['loan.user.employeeDetail', 'loan.productCatalog', 'createdBy']);
        
        return view('loan-defaults.edit', compact('default'));
    }
    
    /**
     * Update the specified loan default in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\LoanDefault  $default
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, LoanDefault $default)
    {
        $validator = Validator::make($request->all(), [
            'recovery_plan' => 'nullable|string',
            'comments' => 'nullable|string',
            'supporting_document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status' => 'required|in:ACTIVE,RESOLVED,WRITTEN_OFF',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'recovery_plan' => $default->recovery_plan,
            'comments' => $default->comments,
            'status' => $default->status,
        ];

        // Handle file upload
        if ($request->hasFile('supporting_document')) {
            // Delete old document if exists
            if ($default->supporting_document) {
                Storage::disk('public')->delete($default->supporting_document);
            }
            
            $documentPath = $request->file('supporting_document')->store('loan-defaults/' . $default->loan_id, 'public');
            $default->supporting_document = $documentPath;
        }

        // Update loan default
        $default->update([
            'recovery_plan' => $request->recovery_plan,
            'comments' => $request->comments,
            'status' => $request->status,
            'resolved_at' => $request->status === 'RESOLVED' ? now() : $default->resolved_at,
            'written_off_at' => $request->status === 'WRITTEN_OFF' ? now() : $default->written_off_at,
        ]);
        
        // Update loan status if default is resolved or written off
        $loan = $default->loan;
        
        if ($request->status === 'RESOLVED') {
            $loan->update([
                'status' => 'ACTIVE',
                'defaulted_at' => null,
            ]);
            
            // Create notification for user
            Notification::create([
                'user_id' => $loan->user_id,
                'title' => 'Loan Default Resolved',
                'message' => "The default on your loan ({$loan->reference_number}) has been resolved. The loan is now marked as active again.",
                'type' => 'LOAN_DEFAULT',
                'reference_id' => $default->id,
                'is_read' => false,
            ]);
        } elseif ($request->status === 'WRITTEN_OFF') {
            $loan->update([
                'status' => 'WRITTEN_OFF',
                'written_off_at' => now(),
                'actual_end_date' => now(),
            ]);
            
            // Create notification for user
            Notification::create([
                'user_id' => $loan->user_id,
                'title' => 'Loan Written Off',
                'message' => "Your loan ({$loan->reference_number}) has been written off. This means the remaining balance will not be collected.",
                'type' => 'LOAN_DEFAULT',
                'reference_id' => $default->id,
                'is_read' => false,
            ]);
        }
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'LoanDefault',
            'model_id' => $default->id,
            'description' => 'Loan default updated',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'recovery_plan' => $request->recovery_plan,
                'comments' => $request->comments,
                'status' => $request->status,
            ]),
        ]);
        
        return redirect()->route('loan-defaults.index')
            ->with('success', 'Loan default updated successfully.');
    }
    
    /**
     * Download the supporting document.
     *
     * @param  \App\Models\LoanDefault  $default
     * @return \Illuminate\Http\Response
     */
    public function downloadDocument(LoanDefault $default)
    {
        if (!$default->supporting_document) {
            return back()->with('error', 'No supporting document available for this default record.');
        }
        
        return Storage::disk('public')->download(
            $default->supporting_document,
            'Default_Document_' . $default->id . '.' . pathinfo($default->supporting_document, PATHINFO_EXTENSION)
        );
    }
}
