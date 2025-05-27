<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bank;
use App\Models\Deduction;
use App\Models\FinancialServiceProvider;
use App\Models\Institution;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanRepayment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin|super-admin']);
    }

    /**
     * Show the admin dashboard.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Count summary statistics
        $stats = $this->getDashboardStats();
        
        // Get recent loan applications
        $recentLoanApplications = LoanApplication::with(['user', 'productCatalog'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Get recent loan repayments
        $recentLoanRepayments = LoanRepayment::with(['loan', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        // Loan status distribution
        $loanStatusData = Loan::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();
        
        // Monthly loan disbursements for the past 12 months
        $monthlyDisbursements = $this->getMonthlyDisbursements();
        
        // Monthly loan repayments for the past 12 months
        $monthlyRepayments = $this->getMonthlyRepayments();
        
        return view('admin.dashboard', compact(
            'stats',
            'recentLoanApplications',
            'recentLoanRepayments',
            'loanStatusData',
            'monthlyDisbursements',
            'monthlyRepayments'
        ));
    }
    
    /**
     * Get dashboard statistics.
     *
     * @return array
     */
    public function getDashboardStats()
    {
        $stats = [
            'total_users' => User::count(),
            'total_loans' => Loan::count(),
            'active_loans' => Loan::whereIn('status', ['ACTIVE', 'DISBURSED'])->count(),
            'total_fsp' => FinancialServiceProvider::count(),
            'total_institutions' => Institution::count(),
            'total_banks' => Bank::count(),
            'pending_applications' => LoanApplication::where('status', 'PENDING')->count(),
            'total_disbursed' => Loan::where('status', 'DISBURSED')->sum('principal_amount'),
            'total_repayments' => LoanRepayment::where('status', 'COMPLETED')->sum('amount'),
            'total_deductions' => Deduction::where('status', 'ACTIVE')->sum('amount'),
            'monthly_applications' => LoanApplication::whereMonth('created_at', Carbon::now()->month)
                                    ->whereYear('created_at', Carbon::now()->year)
                                    ->count(),
            'monthly_disbursements' => Loan::whereMonth('disbursement_date', Carbon::now()->month)
                                    ->whereYear('disbursement_date', Carbon::now()->year)
                                    ->sum('principal_amount'),
            'monthly_repayments' => LoanRepayment::whereMonth('created_at', Carbon::now()->month)
                                    ->whereYear('created_at', Carbon::now()->year)
                                    ->where('status', 'COMPLETED')
                                    ->sum('amount'),
        ];
        
        return $stats;
    }
    
    /**
     * Get monthly loan disbursements for the past 12 months.
     *
     * @return array
     */
    private function getMonthlyDisbursements()
    {
        $data = [];
        $months = [];
        $amounts = [];
        
        // Get data for the past 12 months
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthLabel = $month->format('M Y');
            $monthStart = $month->startOfMonth()->format('Y-m-d');
            $monthEnd = $month->endOfMonth()->format('Y-m-d');
            
            $amount = Loan::whereBetween('disbursement_date', [$monthStart, $monthEnd])
                ->sum('principal_amount');
            
            $months[] = $monthLabel;
            $amounts[] = $amount;
        }
        
        $data['labels'] = $months;
        $data['datasets'] = [
            [
                'label' => 'Loan Disbursements',
                'data' => $amounts,
                'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                'borderColor' => 'rgba(54, 162, 235, 1)',
                'borderWidth' => 1
            ]
        ];
        
        return $data;
    }
    
    /**
     * Get monthly loan repayments for the past 12 months.
     *
     * @return array
     */
    private function getMonthlyRepayments()
    {
        $data = [];
        $months = [];
        $amounts = [];
        
        // Get data for the past 12 months
        for ($i = 11; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $monthLabel = $month->format('M Y');
            $monthStart = $month->startOfMonth()->format('Y-m-d');
            $monthEnd = $month->endOfMonth()->format('Y-m-d');
            
            $amount = LoanRepayment::whereBetween('created_at', [$monthStart, $monthEnd])
                ->where('status', 'COMPLETED')
                ->sum('amount');
            
            $months[] = $monthLabel;
            $amounts[] = $amount;
        }
        
        $data['labels'] = $months;
        $data['datasets'] = [
            [
                'label' => 'Loan Repayments',
                'data' => $amounts,
                'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                'borderColor' => 'rgba(75, 192, 192, 1)',
                'borderWidth' => 1
            ]
        ];
        
        return $data;
    }
    
    /**
     * Get dashboard statistics as JSON.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getStats()
    {
        $stats = $this->getDashboardStats();
        
        return new JsonResponse($stats);
    }
    
    /**
     * Get loan disbursements chart data as JSON.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLoansChart()
    {
        $data = $this->getMonthlyDisbursements();
        
        return new JsonResponse($data);
    }
    
    /**
     * Get loan repayments chart data as JSON.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRepaymentsChart()
    {
        $data = $this->getMonthlyRepayments();
        
        return new JsonResponse($data);
    }
}
