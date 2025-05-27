<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanApproval;
use App\Models\LoanDefault;
use App\Models\LoanDisbursement;
use App\Models\LoanRepayment;
use App\Models\LoanRestructure;
use App\Models\LoanTakeover;
use App\Models\Notification;
use App\Models\ProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LoanController extends Controller
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
     * Display a listing of the user's loans.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();
        
        $loans = Loan::with(['productCatalog.financialServiceProvider', 'user'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        $activeLoans = Loan::where('user_id', $user->id)
            ->whereIn('status', ['ACTIVE', 'DISBURSED'])
            ->count();
        
        $totalOutstanding = Loan::where('user_id', $user->id)
            ->whereIn('status', ['ACTIVE', 'DISBURSED'])
            ->sum('outstanding_amount');
        
        $totalRepaid = LoanRepayment::whereHas('loan', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'COMPLETED')
            ->sum('amount');
        
        return view('loans.index', compact('loans', 'activeLoans', 'totalOutstanding', 'totalRepaid'));
    }

    /**
     * Display the specified loan.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\View\View
     */
    public function show(Loan $loan)
    {
        $this->authorize('view', $loan);
        
        $loan->load([
            'productCatalog.financialServiceProvider', 
            'user',
            'loanApplication',
            'loanApproval',
            'loanDisbursement',
            'loanRepayments' => function ($query) {
                $query->orderBy('created_at', 'desc');
            },
        ]);
        
        // Get loan restructures if any
        $restructures = LoanRestructure::where('loan_id', $loan->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get loan takeovers if any
        $takeovers = LoanTakeover::where('loan_id', $loan->id)
            ->orWhere('new_loan_id', $loan->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get loan defaults if any
        $defaults = LoanDefault::where('loan_id', $loan->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get repayment schedule
        $schedule = $this->generateRepaymentSchedule($loan);
        
        return view('loans.show', compact('loan', 'restructures', 'takeovers', 'defaults', 'schedule'));
    }

    /**
     * Display the form to apply for a new loan.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $user = Auth::user();
        
        // Check if user has all required information
        if (!$user->employeeDetail || !$user->employeeDetail->institution || !$user->employeeDetail->department) {
            return redirect()->route('profile.edit')
                ->with('error', 'Please complete your profile information before applying for a loan.');
        }
        
        // Get active product catalogs
        $products = ProductCatalog::with('financialServiceProvider')
            ->where('status', 'ACTIVE')
            ->get();
        
        // Get user's active loans
        $activeLoans = Loan::where('user_id', $user->id)
            ->whereIn('status', ['ACTIVE', 'DISBURSED'])
            ->with('productCatalog')
            ->get();
        
        return view('loans.create', compact('products', 'activeLoans'));
    }

    /**
     * Generate repayment schedule for a loan.
     *
     * @param  \App\Models\Loan  $loan
     * @return array
     */
    private function generateRepaymentSchedule(Loan $loan)
    {
        $schedule = [];
        
        $principal = $loan->principal_amount;
        $totalAmount = $loan->total_amount;
        $term = $loan->term;
        $termPeriod = $loan->term_period;
        $startDate = $loan->start_date;
        $interestType = $loan->interest_type;
        $interestRate = $loan->interest_rate;
        
        // Calculate payment amount
        $paymentAmount = $totalAmount / $term;
        
        // For simplicity, we'll use a flat interest model here
        // In a real application, you'd need more complex calculations
        $outstandingPrincipal = $principal;
        $principalPerPeriod = $principal / $term;
        
        for ($i = 0; $i < $term; $i++) {
            // Calculate payment date based on term period
            $paymentDate = clone $startDate;
            if ($termPeriod === 'DAY') {
                $paymentDate->addDays($i + 1);
            } elseif ($termPeriod === 'WEEK') {
                $paymentDate->addWeeks($i + 1);
            } elseif ($termPeriod === 'MONTH') {
                $paymentDate->addMonths($i + 1);
            } elseif ($termPeriod === 'YEAR') {
                $paymentDate->addYears($i + 1);
            }
            
            // Calculate interest for this period
            $interest = 0;
            if ($interestType === 'FIXED') {
                $interest = ($principal * $interestRate / 100) / $term;
            } else { // REDUCING_BALANCE
                $interest = $outstandingPrincipal * ($interestRate / 100);
                if ($termPeriod === 'MONTH') {
                    $interest /= 12;
                } elseif ($termPeriod === 'WEEK') {
                    $interest /= 52;
                } elseif ($termPeriod === 'DAY') {
                    $interest /= 365;
                }
            }
            
            // Add to schedule
            $schedule[] = [
                'payment_number' => $i + 1,
                'payment_date' => $paymentDate->format('Y-m-d'),
                'principal_amount' => round($principalPerPeriod, 2),
                'interest_amount' => round($interest, 2),
                'total_amount' => round($principalPerPeriod + $interest, 2),
                'outstanding_principal' => round($outstandingPrincipal - $principalPerPeriod, 2),
            ];
            
            // Update outstanding principal for next iteration
            $outstandingPrincipal -= $principalPerPeriod;
        }
        
        return $schedule;
    }

    /**
     * Display loan statement.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\View\View
     */
    public function statement(Loan $loan)
    {
        $this->authorize('view', $loan);
        
        $loan->load([
            'productCatalog.financialServiceProvider', 
            'user',
            'loanRepayments' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
        ]);
        
        // Generate transaction history
        $transactions = [];
        
        // Add disbursement as first transaction
        $transactions[] = [
            'date' => $loan->start_date,
            'description' => 'Loan Disbursement',
            'debit' => $loan->principal_amount,
            'credit' => 0,
            'balance' => $loan->principal_amount,
        ];
        
        // Add interest as second transaction
        $transactions[] = [
            'date' => $loan->start_date,
            'description' => 'Loan Interest',
            'debit' => $loan->interest_amount,
            'credit' => 0,
            'balance' => $loan->principal_amount + $loan->interest_amount,
        ];
        
        // Add fees as third transaction if any
        if ($loan->fees_amount > 0) {
            $transactions[] = [
                'date' => $loan->start_date,
                'description' => 'Loan Fees',
                'debit' => $loan->fees_amount,
                'credit' => 0,
                'balance' => $loan->principal_amount + $loan->interest_amount + $loan->fees_amount,
            ];
        }
        
        // Add repayments
        $balance = $loan->total_amount;
        foreach ($loan->loanRepayments as $repayment) {
            if ($repayment->status === 'COMPLETED') {
                $balance -= $repayment->amount;
                
                $transactions[] = [
                    'date' => $repayment->created_at,
                    'description' => 'Loan Repayment - ' . $repayment->payment_method,
                    'debit' => 0,
                    'credit' => $repayment->amount,
                    'balance' => $balance,
                    'reference' => $repayment->reference_number,
                ];
            }
        }
        
        return view('loans.statement', compact('loan', 'transactions'));
    }

    /**
     * Generate and download loan statement as PDF.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\Response
     */
    public function downloadStatement(Loan $loan)
    {
        $this->authorize('view', $loan);
        
        $loan->load([
            'productCatalog.financialServiceProvider', 
            'user',
            'loanRepayments' => function ($query) {
                $query->orderBy('created_at', 'asc');
            },
        ]);
        
        // Generate transaction history (same as in statement method)
        $transactions = [];
        
        // Add disbursement as first transaction
        $transactions[] = [
            'date' => $loan->start_date,
            'description' => 'Loan Disbursement',
            'debit' => $loan->principal_amount,
            'credit' => 0,
            'balance' => $loan->principal_amount,
        ];
        
        // Add interest as second transaction
        $transactions[] = [
            'date' => $loan->start_date,
            'description' => 'Loan Interest',
            'debit' => $loan->interest_amount,
            'credit' => 0,
            'balance' => $loan->principal_amount + $loan->interest_amount,
        ];
        
        // Add fees as third transaction if any
        if ($loan->fees_amount > 0) {
            $transactions[] = [
                'date' => $loan->start_date,
                'description' => 'Loan Fees',
                'debit' => $loan->fees_amount,
                'credit' => 0,
                'balance' => $loan->principal_amount + $loan->interest_amount + $loan->fees_amount,
            ];
        }
        
        // Add repayments
        $balance = $loan->total_amount;
        foreach ($loan->loanRepayments as $repayment) {
            if ($repayment->status === 'COMPLETED') {
                $balance -= $repayment->amount;
                
                $transactions[] = [
                    'date' => $repayment->created_at,
                    'description' => 'Loan Repayment - ' . $repayment->payment_method,
                    'debit' => 0,
                    'credit' => $repayment->amount,
                    'balance' => $balance,
                    'reference' => $repayment->reference_number,
                ];
            }
        }
        
        // Generate PDF
        $pdf = app()->make('dompdf.wrapper');
        $pdf->loadView('loans.statement_pdf', compact('loan', 'transactions'));
        
        return $pdf->download('loan_statement_' . $loan->reference_number . '.pdf');
    }

    /**
     * Display loan agreement.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\View\View
     */
    public function agreement(Loan $loan)
    {
        $this->authorize('view', $loan);
        
        $loan->load([
            'productCatalog.financialServiceProvider',
            'productCatalog.termsConditions', 
            'user',
            'loanApplication',
            'loanDisbursement',
        ]);
        
        // Generate repayment schedule
        $schedule = $this->generateRepaymentSchedule($loan);
        
        return view('loans.agreement', compact('loan', 'schedule'));
    }

    /**
     * Generate and download loan agreement as PDF.
     *
     * @param  \App\Models\Loan  $loan
     * @return \Illuminate\Http\Response
     */
    public function downloadAgreement(Loan $loan)
    {
        $this->authorize('view', $loan);
        
        $loan->load([
            'productCatalog.financialServiceProvider',
            'productCatalog.termsConditions', 
            'user',
            'loanApplication',
            'loanDisbursement',
        ]);
        
        // Generate repayment schedule
        $schedule = $this->generateRepaymentSchedule($loan);
        
        // Generate PDF
        $pdf = app()->make('dompdf.wrapper');
        $pdf->loadView('loans.agreement_pdf', compact('loan', 'schedule'));
        
        return $pdf->download('loan_agreement_' . $loan->reference_number . '.pdf');
    }

    /**
     * Display dashboard for user's loans.
     *
     * @return \Illuminate\View\View
     */
    public function dashboard()
    {
        $user = Auth::user();
        
        $activeLoans = Loan::where('user_id', $user->id)
            ->whereIn('status', ['ACTIVE', 'DISBURSED'])
            ->with('productCatalog.financialServiceProvider')
            ->get();
        
        $totalOutstanding = $activeLoans->sum('outstanding_amount');
        
        $totalRepaid = LoanRepayment::whereHas('loan', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('status', 'COMPLETED')
            ->sum('amount');
        
        $pendingApplications = LoanApplication::where('user_id', $user->id)
            ->whereIn('status', ['PENDING', 'SUBMITTED', 'UNDER_REVIEW'])
            ->with('productCatalog.financialServiceProvider')
            ->get();
        
        $recentRepayments = LoanRepayment::whereHas('loan', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with('loan.productCatalog')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        $upcomingPayments = [];
        foreach ($activeLoans as $loan) {
            $schedule = $this->generateRepaymentSchedule($loan);
            
            foreach ($schedule as $payment) {
                if (strtotime($payment['payment_date']) > time()) {
                    $upcomingPayments[] = [
                        'loan' => $loan,
                        'payment_date' => $payment['payment_date'],
                        'amount' => $payment['total_amount'],
                    ];
                }
            }
        }
        
        // Sort upcoming payments by date
        usort($upcomingPayments, function ($a, $b) {
            return strtotime($a['payment_date']) - strtotime($b['payment_date']);
        });
        
        // Limit to next 5
        $upcomingPayments = array_slice($upcomingPayments, 0, 5);
        
        return view('loans.dashboard', compact('activeLoans', 'totalOutstanding', 'totalRepaid', 'pendingApplications', 'recentRepayments', 'upcomingPayments'));
    }
}
