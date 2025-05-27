<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanDisbursement;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class LoanDisbursementController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin|super-admin|finance-officer']);
    }

    /**
     * Display a listing of the loan disbursements.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $disbursements = LoanDisbursement::with(['loan.user', 'loan.productCatalog', 'disbursedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('loan-disbursements.index', compact('disbursements'));
    }

    /**
     * Display a listing of pending loan disbursements.
     *
     * @return \Illuminate\View\View
     */
    public function pendingDisbursements()
    {
        $loans = Loan::with(['user', 'productCatalog.financialServiceProvider', 'loanApplication'])
            ->where('status', 'APPROVED')
            ->whereDoesntHave('loanDisbursement')
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('loan-disbursements.pending', compact('loans'));
    }

    /**
     * Show the form for disbursing a loan.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\View\View
     */
    public function disburse(Loan $loan)
    {
        if ($loan->status !== 'APPROVED') {
            return redirect()->route('loan-disbursements.pending')
                ->with('error', 'Only approved loans can be disbursed.');
        }
        
        $loan->load([
            'user.employeeDetail.institution', 
            'user.employeeDetail.department',
            'productCatalog.financialServiceProvider',
            'bank',
            'bankBranch',
            'loanApplication'
        ]);
        
        return view('loan-disbursements.disburse', compact('loan'));
    }

    /**
     * Process the loan disbursement.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\RedirectResponse
     */
    public function process(Request $request, Loan $loan)
    {
        if ($loan->status !== 'APPROVED') {
            return redirect()->route('loan-disbursements.pending')
                ->with('error', 'Only approved loans can be disbursed.');
        }
        
        $validator = Validator::make($request->all(), [
            'disbursement_date' => 'required|date',
            'payment_method' => 'required|in:BANK_TRANSFER,MOBILE_MONEY,CHECK,CASH',
            'transaction_reference' => 'required|string|max:50',
            'disbursement_amount' => 'required|numeric|min:1',
            'disbursement_notes' => 'nullable|string',
            'proof_of_disbursement' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle file upload
        $proofPath = null;
        if ($request->hasFile('proof_of_disbursement')) {
            $proofPath = $request->file('proof_of_disbursement')->store('loan-disbursements/' . $loan->id, 'public');
        }

        // Calculate expected end date based on term and term period
        $startDate = Carbon::parse($request->disbursement_date);
        $endDate = clone $startDate;
        
        if ($loan->term_period === 'DAY') {
            $endDate->addDays($loan->term);
        } elseif ($loan->term_period === 'WEEK') {
            $endDate->addWeeks($loan->term);
        } elseif ($loan->term_period === 'MONTH') {
            $endDate->addMonths($loan->term);
        } elseif ($loan->term_period === 'YEAR') {
            $endDate->addYears($loan->term);
        }

        // Create loan disbursement
        $disbursement = LoanDisbursement::create([
            'loan_id' => $loan->id,
            'disbursed_by' => Auth::id(),
            'disbursement_date' => $request->disbursement_date,
            'payment_method' => $request->payment_method,
            'transaction_reference' => $request->transaction_reference,
            'amount' => $request->disbursement_amount,
            'notes' => $request->disbursement_notes,
            'proof_of_disbursement' => $proofPath,
            'status' => 'COMPLETED',
        ]);
        
        // Update loan
        $loan->update([
            'status' => 'DISBURSED',
            'start_date' => $request->disbursement_date,
            'expected_end_date' => $endDate,
            'disbursement_date' => $request->disbursement_date,
        ]);
        
        // Create notification for user
        Notification::create([
            'user_id' => $loan->user_id,
            'title' => 'Loan Disbursed',
            'message' => "Your loan ({$loan->reference_number}) has been disbursed. Amount: {$request->disbursement_amount} via {$request->payment_method}. Transaction Reference: {$request->transaction_reference}",
            'type' => 'LOAN_DISBURSEMENT',
            'reference_id' => $disbursement->id,
            'is_read' => false,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DISBURSE',
            'model_type' => 'Loan',
            'model_id' => $loan->id,
            'description' => 'Loan disbursed',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'disbursement_date' => $request->disbursement_date,
                'payment_method' => $request->payment_method,
                'transaction_reference' => $request->transaction_reference,
                'amount' => $request->disbursement_amount,
                'status' => 'DISBURSED',
            ]),
        ]);
        
        return redirect()->route('loan-disbursements.index')
            ->with('success', 'Loan disbursed successfully.');
    }
    
    /**
     * Display the specified loan disbursement.
     *
     * @param  \App\Models\LoanDisbursement  $disbursement
     * @return \Illuminate\View\View
     */
    public function show(LoanDisbursement $disbursement)
    {
        $disbursement->load([
            'loan.user.employeeDetail.institution', 
            'loan.user.employeeDetail.department',
            'loan.productCatalog.financialServiceProvider',
            'loan.bank',
            'loan.bankBranch',
            'disbursedBy'
        ]);
        
        return view('loan-disbursements.show', compact('disbursement'));
    }
    
    /**
     * Download the proof of disbursement.
     *
     * @param  \App\Models\LoanDisbursement  $disbursement
     * @return \Illuminate\Http\Response
     */
    public function downloadProof(LoanDisbursement $disbursement)
    {
        if (!$disbursement->proof_of_disbursement) {
            return back()->with('error', 'No proof of disbursement available.');
        }
        
        return Storage::disk('public')->download(
            $disbursement->proof_of_disbursement,
            'Proof_of_Disbursement_' . $disbursement->loan->reference_number . '.' . pathinfo($disbursement->proof_of_disbursement, PATHINFO_EXTENSION)
        );
    }
}
