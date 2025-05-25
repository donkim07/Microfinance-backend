<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LoanApplication;
use App\Models\Employee;
use App\Models\FinancialServiceProvider;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the admin dashboard.
     */
    public function index()
    {
        // Get counts for dashboard widgets
        $totalLoans = LoanApplication::count();
        $totalEmployees = Employee::count();
        $totalFsps = FinancialServiceProvider::count();
        $totalProducts = LoanProduct::count();
        
        // Get recent loan applications
        $recentLoans = LoanApplication::with(['employee', 'loanProduct'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        // Get loan status distribution
        $loanStatusDistribution = LoanApplication::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->pluck('total', 'status')
            ->toArray();
        
        // Get monthly loan statistics for the current year
        $year = date('Y');
        $monthlyLoanStats = LoanApplication::select(
                DB::raw('MONTH(created_at) as month'),
                DB::raw('count(*) as total_applications'),
                DB::raw('sum(case when status = "COMPLETED" then 1 else 0 end) as completed'),
                DB::raw('sum(requested_amount) as requested_amount')
            )
            ->whereYear('created_at', $year)
            ->groupBy(DB::raw('MONTH(created_at)'))
            ->get();
        
        return view('admin.dashboard', compact(
            'totalLoans',
            'totalEmployees',
            'totalFsps',
            'totalProducts',
            'recentLoans',
            'loanStatusDistribution',
            'monthlyLoanStats'
        ));
    }
} 