<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Institution;
use App\Models\Loan;
use App\Models\LoanDefault;
use App\Models\LoanRepayment;
use App\Models\ProductCatalog;
use App\Models\User;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Validator;

class ReportController extends Controller
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
     * Display a listing of the reports.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $loanProducts = ProductCatalog::orderBy('name')->get();
        $recentReports = Report::with('user')->where('user_id', auth()->id())->latest()->take(5)->get();
        
        return view('reports.index', compact('loanProducts', 'recentReports'));
    }

    /**
     * Display the loan summary report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function loanSummary(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get loan data
        $loans = Loan::whereBetween('created_at', [$startDate, $endDate])->get();
        
        // Calculate summary data
        $totalLoans = $loans->count();
        $totalAmount = $loans->sum('amount');
        $activeLoans = $loans->where('status', 'ACTIVE')->count();
        $activeAmount = $loans->where('status', 'ACTIVE')->sum('amount');
        $fullyPaidLoans = $loans->where('status', 'FULLY_PAID')->count();
        $fullyPaidAmount = $loans->where('status', 'FULLY_PAID')->sum('amount');
        $defaultedLoans = $loans->where('status', 'DEFAULTED')->count();
        $defaultedAmount = $loans->where('status', 'DEFAULTED')->sum('amount');
        
        // Group by status
        $loansByStatus = $loans->groupBy('status');
        
        return view('reports.loan-summary', compact(
            'startDate', 'endDate', 'totalLoans', 'totalAmount', 'activeLoans', 
            'activeAmount', 'fullyPaidLoans', 'fullyPaidAmount', 'defaultedLoans', 
            'defaultedAmount', 'loansByStatus', 'loans'
        ));
    }

    /**
     * Display the loan disbursements report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function loanDisbursements(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get disbursed loans
        $disbursedLoans = Loan::whereNotNull('disbursed_at')
            ->whereBetween('disbursed_at', [$startDate, $endDate])
            ->get();
        
        return view('reports.loan-disbursements', compact('startDate', 'endDate', 'disbursedLoans'));
    }

    /**
     * Display the loan repayments report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function loanRepayments(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get repayments
        $repayments = LoanRepayment::whereBetween('payment_date', [$startDate, $endDate])
            ->with('loan', 'loan.user')
            ->get();
        
        return view('reports.loan-repayments', compact('startDate', 'endDate', 'repayments'));
    }

    /**
     * Display the loan defaults report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function loanDefaults(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get defaulted loans
        $defaultedLoans = Loan::where('status', 'DEFAULTED')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->with('user')
            ->get();
        
        return view('reports.loan-defaults', compact('startDate', 'endDate', 'defaultedLoans'));
    }

    /**
     * Display the loan aging report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function loanAging(Request $request)
    {
        // Get active loans
        $activeLoans = Loan::where('status', 'ACTIVE')->with('user')->get();
        
        // Group by age
        $current = $activeLoans->filter(function($loan) {
            return $loan->days_overdue == 0;
        });
        
        $overdue30 = $activeLoans->filter(function($loan) {
            return $loan->days_overdue > 0 && $loan->days_overdue <= 30;
        });
        
        $overdue60 = $activeLoans->filter(function($loan) {
            return $loan->days_overdue > 30 && $loan->days_overdue <= 60;
        });
        
        $overdue90 = $activeLoans->filter(function($loan) {
            return $loan->days_overdue > 60 && $loan->days_overdue <= 90;
        });
        
        $overdue90Plus = $activeLoans->filter(function($loan) {
            return $loan->days_overdue > 90;
        });
        
        return view('reports.loan-aging', compact(
            'activeLoans', 'current', 'overdue30', 'overdue60', 'overdue90', 'overdue90Plus'
        ));
    }

    /**
     * Display the revenue report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function revenue(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get repayments
        $repayments = LoanRepayment::whereBetween('payment_date', [$startDate, $endDate])->get();
        
        // Calculate revenue
        $totalRevenue = $repayments->sum('amount');
        $principalRepayments = $repayments->sum('principal');
        $interestIncome = $repayments->sum('interest');
        $feesIncome = $repayments->sum('fees');
        $penaltiesIncome = $repayments->sum('penalties');
        
        return view('reports.revenue', compact(
            'startDate', 'endDate', 'repayments', 'totalRevenue', 'principalRepayments',
            'interestIncome', 'feesIncome', 'penaltiesIncome'
        ));
    }

    /**
     * Generate custom report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function custom(Request $request)
    {
        $reportType = $request->input('report_type');
        
        if (!$reportType) {
            return redirect()->route('reports.index');
        }
        
        switch ($reportType) {
            case 'loan_summary':
                return $this->loanSummary($request);
            case 'loan_repayments':
                return $this->loanRepayments($request);
            case 'loan_disbursements':
                return $this->loanDisbursements($request);
            case 'financial_summary':
                return $this->financialSummary($request);
            case 'employee_loans':
                return $this->employeeLoans($request);
            default:
                return redirect()->route('reports.index');
        }
    }

    /**
     * Display the specified report.
     *
     * @param  \App\Models\Report  $report
     * @return \Illuminate\Http\Response
     */
    public function show(Report $report)
    {
        return view('reports.show', compact('report'));
    }

    /**
     * Download the specified report.
     *
     * @param  \App\Models\Report  $report
     * @return \Illuminate\Http\Response
     */
    public function download(Report $report)
    {
        // Handle report download logic
        return response()->download(storage_path('app/reports/' . $report->file_path));
    }

    /**
     * Display the employee loans report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function employeeLoans(Request $request)
    {
        $employeeId = $request->input('employee_id');
        
        if ($employeeId) {
            $employee = User::findOrFail($employeeId);
            $loans = $employee->loans()->with('product')->get();
            
            return view('reports.employee-loans', compact('employee', 'loans'));
        }
        
        $employees = User::has('loans')->get();
        
        return view('reports.employee-loans', compact('employees'));
    }

    /**
     * Display the interest income report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function interestIncome(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get repayments
        $repayments = LoanRepayment::whereBetween('payment_date', [$startDate, $endDate])->get();
        
        // Calculate interest income
        $interestIncome = $repayments->sum('interest');
        
        return view('reports.interest-income', compact('startDate', 'endDate', 'repayments', 'interestIncome'));
    }

    /**
     * Display the fees income report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function feesIncome(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get repayments
        $repayments = LoanRepayment::whereBetween('payment_date', [$startDate, $endDate])->get();
        
        // Calculate fees income
        $feesIncome = $repayments->sum('fees');
        $penaltiesIncome = $repayments->sum('penalties');
        $totalFeesIncome = $feesIncome + $penaltiesIncome;
        
        return view('reports.fees-income', compact(
            'startDate', 'endDate', 'repayments', 'feesIncome', 'penaltiesIncome', 'totalFeesIncome'
        ));
    }

    /**
     * Display the cash flow report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function cashFlow(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get disbursements
        $disbursements = Loan::whereNotNull('disbursed_at')
            ->whereBetween('disbursed_at', [$startDate, $endDate])
            ->get();
        
        // Get repayments
        $repayments = LoanRepayment::whereBetween('payment_date', [$startDate, $endDate])->get();
        
        // Calculate cash flow
        $totalDisbursements = $disbursements->sum('amount');
        $totalRepayments = $repayments->sum('amount');
        $netCashFlow = $totalRepayments - $totalDisbursements;
        
        return view('reports.cash-flow', compact(
            'startDate', 'endDate', 'disbursements', 'repayments',
            'totalDisbursements', 'totalRepayments', 'netCashFlow'
        ));
    }

    /**
     * Display the financial summary report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function financialSummary(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get all loans
        $loans = Loan::all();
        
        // Get active loans
        $activeLoans = $loans->where('status', 'ACTIVE');
        
        // Get disbursements in period
        $disbursements = Loan::whereNotNull('disbursed_at')
            ->whereBetween('disbursed_at', [$startDate, $endDate])
            ->get();
        
        // Get repayments in period
        $repayments = LoanRepayment::whereBetween('payment_date', [$startDate, $endDate])->get();
        
        // Calculate summary data
        $totalLoanPortfolio = $activeLoans->sum('balance');
        $totalDisbursements = $disbursements->sum('amount');
        $totalRepayments = $repayments->sum('amount');
        $interestIncome = $repayments->sum('interest');
        $feesIncome = $repayments->sum('fees');
        $penaltiesIncome = $repayments->sum('penalties');
        $totalRevenue = $interestIncome + $feesIncome + $penaltiesIncome;
        
        return view('reports.financial-summary', compact(
            'startDate', 'endDate', 'totalLoanPortfolio', 'totalDisbursements',
            'totalRepayments', 'interestIncome', 'feesIncome', 'penaltiesIncome', 'totalRevenue'
        ));
    }

    /**
     * Display the department loans report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function departmentLoans(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get all loans in period
        $loans = Loan::whereBetween('created_at', [$startDate, $endDate])
            ->with('user')
            ->get();
        
        // Group by department
        $loansByDepartment = $loans->groupBy(function($loan) {
            return $loan->user->department ?? 'Unknown';
        });
        
        return view('reports.department-loans', compact('startDate', 'endDate', 'loansByDepartment'));
    }

    /**
     * Display the user activity report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function userActivity(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // This would depend on your activity logging system
        // For example purposes, we'll assume you have an Activity model
        $activities = []; // Activity::whereBetween('created_at', [$startDate, $endDate])->with('user')->get();
        
        return view('reports.user-activity', compact('startDate', 'endDate', 'activities'));
    }

    /**
     * Display the new registrations report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function newRegistrations(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // Get new users
        $newUsers = User::whereBetween('created_at', [$startDate, $endDate])->get();
        
        return view('reports.new-registrations', compact('startDate', 'endDate', 'newUsers'));
    }

    /**
     * Display the staff performance report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function staffPerformance(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonth()->format('Y-m-d'));
        $endDate = $request->input('end_date', Carbon::now()->format('Y-m-d'));
        
        // This would depend on your staff performance metrics
        // For example purposes, we'll just show an empty view
        
        return view('reports.staff-performance', compact('startDate', 'endDate'));
    }

    /**
     * Display the loan report page.
     *
     * @return \Illuminate\View\View
     */
    public function loanReport()
    {
        $institutions = Institution::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        $departments = Department::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        $productCatalogs = ProductCatalog::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        return view('reports.loan-report', compact('institutions', 'departments', 'productCatalogs'));
    }

    /**
     * Generate the loan report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generateLoanReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'institution_id' => 'nullable|exists:institutions,id',
            'department_id' => 'nullable|exists:departments,id',
            'product_catalog_id' => 'nullable|exists:product_catalogs,id',
            'status' => 'nullable|in:PENDING,APPROVED,REJECTED,DISBURSED,ACTIVE,COMPLETED,DEFAULTED,WRITTEN_OFF,TAKEN_OVER',
            'report_type' => 'required|in:detailed,summary',
            'format' => 'required|in:pdf,csv,excel',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        // Base query for loans
        $query = Loan::with(['user.employeeDetail', 'productCatalog'])
            ->whereBetween('created_at', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        
        // Apply filters
        if ($request->institution_id) {
            $query->whereHas('user.employeeDetail', function($q) use ($request) {
                $q->where('institution_id', $request->institution_id);
            });
        }
        
        if ($request->department_id) {
            $query->whereHas('user.employeeDetail', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }
        
        if ($request->product_catalog_id) {
            $query->where('product_catalog_id', $request->product_catalog_id);
        }
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        // Get loans
        $loans = $query->orderBy('created_at', 'desc')->get();
        
        // Generate report based on format
        switch ($request->format) {
            case 'pdf':
                return $this->generatePDFReport($loans, $request->report_type, 'loans');
            case 'csv':
                return $this->generateCSVReport($loans, $request->report_type, 'loans');
            case 'excel':
                return $this->generateExcelReport($loans, $request->report_type, 'loans');
            default:
                return back()->with('error', 'Invalid report format.');
        }
    }

    /**
     * Display the repayment report page.
     *
     * @return \Illuminate\View\View
     */
    public function repaymentReport()
    {
        $institutions = Institution::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        $departments = Department::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        $productCatalogs = ProductCatalog::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        return view('reports.repayment-report', compact('institutions', 'departments', 'productCatalogs'));
    }

    /**
     * Generate the repayment report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generateRepaymentReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'institution_id' => 'nullable|exists:institutions,id',
            'department_id' => 'nullable|exists:departments,id',
            'product_catalog_id' => 'nullable|exists:product_catalogs,id',
            'payment_method' => 'nullable|in:BANK_TRANSFER,MOBILE_MONEY,CHECK,CASH,SALARY_DEDUCTION',
            'status' => 'nullable|in:PENDING,COMPLETED,REJECTED',
            'report_type' => 'required|in:detailed,summary',
            'format' => 'required|in:pdf,csv,excel',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        // Base query for repayments
        $query = LoanRepayment::with(['loan.user.employeeDetail', 'loan.productCatalog', 'user'])
            ->whereBetween('payment_date', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        
        // Apply filters
        if ($request->institution_id) {
            $query->whereHas('loan.user.employeeDetail', function($q) use ($request) {
                $q->where('institution_id', $request->institution_id);
            });
        }
        
        if ($request->department_id) {
            $query->whereHas('loan.user.employeeDetail', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }
        
        if ($request->product_catalog_id) {
            $query->whereHas('loan', function($q) use ($request) {
                $q->where('product_catalog_id', $request->product_catalog_id);
            });
        }
        
        if ($request->payment_method) {
            $query->where('payment_method', $request->payment_method);
        }
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        // Get repayments
        $repayments = $query->orderBy('payment_date', 'desc')->get();
        
        // Generate report based on format
        switch ($request->format) {
            case 'pdf':
                return $this->generatePDFReport($repayments, $request->report_type, 'repayments');
            case 'csv':
                return $this->generateCSVReport($repayments, $request->report_type, 'repayments');
            case 'excel':
                return $this->generateExcelReport($repayments, $request->report_type, 'repayments');
            default:
                return back()->with('error', 'Invalid report format.');
        }
    }

    /**
     * Display the default report page.
     *
     * @return \Illuminate\View\View
     */
    public function defaultReport()
    {
        $institutions = Institution::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        $departments = Department::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        $productCatalogs = ProductCatalog::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        return view('reports.default-report', compact('institutions', 'departments', 'productCatalogs'));
    }

    /**
     * Generate the default report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generateDefaultReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'institution_id' => 'nullable|exists:institutions,id',
            'department_id' => 'nullable|exists:departments,id',
            'product_catalog_id' => 'nullable|exists:product_catalogs,id',
            'status' => 'nullable|in:ACTIVE,RESOLVED,WRITTEN_OFF',
            'report_type' => 'required|in:detailed,summary',
            'format' => 'required|in:pdf,csv,excel',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        // Base query for defaults
        $query = LoanDefault::with(['loan.user.employeeDetail', 'loan.productCatalog', 'createdBy'])
            ->whereBetween('default_date', [
                Carbon::parse($request->start_date)->startOfDay(),
                Carbon::parse($request->end_date)->endOfDay(),
            ]);
        
        // Apply filters
        if ($request->institution_id) {
            $query->whereHas('loan.user.employeeDetail', function($q) use ($request) {
                $q->where('institution_id', $request->institution_id);
            });
        }
        
        if ($request->department_id) {
            $query->whereHas('loan.user.employeeDetail', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }
        
        if ($request->product_catalog_id) {
            $query->whereHas('loan', function($q) use ($request) {
                $q->where('product_catalog_id', $request->product_catalog_id);
            });
        }
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        // Get defaults
        $defaults = $query->orderBy('default_date', 'desc')->get();
        
        // Generate report based on format
        switch ($request->format) {
            case 'pdf':
                return $this->generatePDFReport($defaults, $request->report_type, 'defaults');
            case 'csv':
                return $this->generateCSVReport($defaults, $request->report_type, 'defaults');
            case 'excel':
                return $this->generateExcelReport($defaults, $request->report_type, 'defaults');
            default:
                return back()->with('error', 'Invalid report format.');
        }
    }

    /**
     * Display the user report page.
     *
     * @return \Illuminate\View\View
     */
    public function userReport()
    {
        $institutions = Institution::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        $departments = Department::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        return view('reports.user-report', compact('institutions', 'departments'));
    }

    /**
     * Generate the user report.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function generateUserReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'institution_id' => 'nullable|exists:institutions,id',
            'department_id' => 'nullable|exists:departments,id',
            'employment_type' => 'nullable|in:PERMANENT,CONTRACT,TEMPORARY',
            'status' => 'nullable|in:ACTIVE,INACTIVE,SUSPENDED,TERMINATED',
            'report_type' => 'required|in:detailed,summary',
            'format' => 'required|in:pdf,csv,excel',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }
        
        // Base query for users
        $query = User::with(['employeeDetail.institution', 'employeeDetail.department', 'employeeDetail.designation', 'roles'])
            ->whereHas('employeeDetail');
        
        // Apply filters
        if ($request->institution_id) {
            $query->whereHas('employeeDetail', function($q) use ($request) {
                $q->where('institution_id', $request->institution_id);
            });
        }
        
        if ($request->department_id) {
            $query->whereHas('employeeDetail', function($q) use ($request) {
                $q->where('department_id', $request->department_id);
            });
        }
        
        if ($request->employment_type) {
            $query->whereHas('employeeDetail', function($q) use ($request) {
                $q->where('employment_type', $request->employment_type);
            });
        }
        
        if ($request->status) {
            $query->whereHas('employeeDetail', function($q) use ($request) {
                $q->where('status', $request->status);
            });
        }
        
        // Get users
        $users = $query->orderBy('name')->get();
        
        // Generate report based on format
        switch ($request->format) {
            case 'pdf':
                return $this->generatePDFReport($users, $request->report_type, 'users');
            case 'csv':
                return $this->generateCSVReport($users, $request->report_type, 'users');
            case 'excel':
                return $this->generateExcelReport($users, $request->report_type, 'users');
            default:
                return back()->with('error', 'Invalid report format.');
        }
    }

    /**
     * Generate a PDF report.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $data
     * @param  string  $reportType
     * @param  string  $reportName
     * @return \Illuminate\Http\Response
     */
    private function generatePDFReport($data, $reportType, $reportName)
    {
        // Generate report data based on report type
        $reportData = $this->prepareReportData($data, $reportType, $reportName);
        
        // Generate PDF using a PDF library (like DomPDF, TCPDF, etc.)
        $pdf = \PDF::loadView('reports.pdf.' . $reportName, $reportData);
        
        // Log report generation
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'GENERATE_REPORT',
            'model_type' => ucfirst($reportName) . 'Report',
            'description' => ucfirst($reportType) . ' ' . ucfirst($reportName) . ' Report generated in PDF format',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        
        // Return the PDF for download
        return $pdf->download(ucfirst($reportName) . '_Report_' . date('Y-m-d') . '.pdf');
    }

    /**
     * Generate a CSV report.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $data
     * @param  string  $reportType
     * @param  string  $reportName
     * @return \Illuminate\Http\Response
     */
    private function generateCSVReport($data, $reportType, $reportName)
    {
        // Generate report data based on report type
        $reportData = $this->prepareReportData($data, $reportType, $reportName);
        
        // Create CSV headers
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . ucfirst($reportName) . '_Report_' . date('Y-m-d') . '.csv"',
        ];
        
        // Create callback for CSV generation
        $callback = function() use ($reportData, $reportName, $reportType) {
            $file = fopen('php://output', 'w');
            
            // Add CSV headers
            fputcsv($file, array_keys($reportData['headers']));
            
            // Add CSV data rows
            foreach ($reportData['data'] as $row) {
                fputcsv($file, $row);
            }
            
            fclose($file);
        };
        
        // Log report generation
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'GENERATE_REPORT',
            'model_type' => ucfirst($reportName) . 'Report',
            'description' => ucfirst($reportType) . ' ' . ucfirst($reportName) . ' Report generated in CSV format',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        
        // Return the CSV for download
        return Response::stream($callback, 200, $headers);
    }

    /**
     * Generate an Excel report.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $data
     * @param  string  $reportType
     * @param  string  $reportName
     * @return \Illuminate\Http\Response
     */
    private function generateExcelReport($data, $reportType, $reportName)
    {
        // Generate report data based on report type
        $reportData = $this->prepareReportData($data, $reportType, $reportName);
        
        // Create Excel using a library like Maatwebsite/Laravel-Excel or PhpSpreadsheet
        // For example with Laravel-Excel:
        $export = new \App\Exports\GenericExport($reportData);
        
        // Log report generation
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'GENERATE_REPORT',
            'model_type' => ucfirst($reportName) . 'Report',
            'description' => ucfirst($reportType) . ' ' . ucfirst($reportName) . ' Report generated in Excel format',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
        
        // Return the Excel for download
        return \Maatwebsite\Excel\Facades\Excel::download($export, ucfirst($reportName) . '_Report_' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Prepare report data based on report type and name.
     *
     * @param  \Illuminate\Database\Eloquent\Collection  $data
     * @param  string  $reportType
     * @param  string  $reportName
     * @return array
     */
    private function prepareReportData($data, $reportType, $reportName)
    {
        $reportData = [
            'title' => ucfirst($reportType) . ' ' . ucfirst($reportName) . ' Report',
            'date' => date('Y-m-d'),
            'generated_by' => Auth::user()->name,
            'headers' => [],
            'data' => [],
            'summary' => [],
        ];
        
        // Based on report type and name, prepare the headers and data
        switch ($reportName) {
            case 'loans':
                if ($reportType === 'detailed') {
                    $reportData['headers'] = [
                        'reference_number' => 'Reference Number',
                        'employee_name' => 'Employee Name',
                        'institution' => 'Institution',
                        'department' => 'Department',
                        'product' => 'Product',
                        'principal_amount' => 'Principal Amount',
                        'interest_amount' => 'Interest Amount',
                        'total_amount' => 'Total Amount',
                        'outstanding_amount' => 'Outstanding Amount',
                        'status' => 'Status',
                        'start_date' => 'Start Date',
                        'expected_end_date' => 'Expected End Date',
                    ];
                    
                    foreach ($data as $loan) {
                        $reportData['data'][] = [
                            'reference_number' => $loan->reference_number,
                            'employee_name' => $loan->user->name,
                            'institution' => $loan->user->employeeDetail->institution->name,
                            'department' => $loan->user->employeeDetail->department->name,
                            'product' => $loan->productCatalog->name,
                            'principal_amount' => number_format($loan->principal_amount, 2),
                            'interest_amount' => number_format($loan->interest_amount, 2),
                            'total_amount' => number_format($loan->total_amount, 2),
                            'outstanding_amount' => number_format($loan->outstanding_amount, 2),
                            'status' => $loan->status,
                            'start_date' => $loan->start_date,
                            'expected_end_date' => $loan->expected_end_date,
                        ];
                    }
                } else { // summary
                    $reportData['summary'] = [
                        'total_loans' => $data->count(),
                        'total_principal' => number_format($data->sum('principal_amount'), 2),
                        'total_interest' => number_format($data->sum('interest_amount'), 2),
                        'total_amount' => number_format($data->sum('total_amount'), 2),
                        'total_outstanding' => number_format($data->sum('outstanding_amount'), 2),
                        'active_loans' => $data->where('status', 'ACTIVE')->count(),
                        'completed_loans' => $data->where('status', 'COMPLETED')->count(),
                        'defaulted_loans' => $data->where('status', 'DEFAULTED')->count(),
                    ];
                    
                    // Group by institution
                    $byInstitution = $data->groupBy(function ($loan) {
                        return $loan->user->employeeDetail->institution->name;
                    });
                    
                    foreach ($byInstitution as $institution => $loans) {
                        $reportData['by_institution'][] = [
                            'institution' => $institution,
                            'total_loans' => $loans->count(),
                            'total_principal' => number_format($loans->sum('principal_amount'), 2),
                            'total_outstanding' => number_format($loans->sum('outstanding_amount'), 2),
                        ];
                    }
                    
                    // Group by product
                    $byProduct = $data->groupBy(function ($loan) {
                        return $loan->productCatalog->name;
                    });
                    
                    foreach ($byProduct as $product => $loans) {
                        $reportData['by_product'][] = [
                            'product' => $product,
                            'total_loans' => $loans->count(),
                            'total_principal' => number_format($loans->sum('principal_amount'), 2),
                            'total_outstanding' => number_format($loans->sum('outstanding_amount'), 2),
                        ];
                    }
                    
                    // Group by status
                    $byStatus = $data->groupBy('status');
                    
                    foreach ($byStatus as $status => $loans) {
                        $reportData['by_status'][] = [
                            'status' => $status,
                            'total_loans' => $loans->count(),
                            'total_principal' => number_format($loans->sum('principal_amount'), 2),
                            'total_outstanding' => number_format($loans->sum('outstanding_amount'), 2),
                        ];
                    }
                }
                break;
                
            case 'repayments':
                // Similar implementations for repayment reports
                break;
                
            case 'defaults':
                // Similar implementations for default reports
                break;
                
            case 'users':
                // Similar implementations for user reports
                break;
        }
        
        return $reportData;
    }

    /**
     * Get loan statistics for the dashboard.
     *
     * @return array
     */
    private function getLoanStatistics()
    {
        $stats = [
            'total_loans' => Loan::count(),
            'active_loans' => Loan::whereIn('status', ['ACTIVE', 'DISBURSED'])->count(),
            'completed_loans' => Loan::where('status', 'COMPLETED')->count(),
            'defaulted_loans' => Loan::where('status', 'DEFAULTED')->count(),
            'total_principal' => Loan::sum('principal_amount'),
            'total_interest' => Loan::sum('interest_amount'),
            'total_outstanding' => Loan::sum('outstanding_amount'),
        ];
        
        // Get monthly loan disbursements for the last 12 months
        $monthlyDisbursements = Loan::select(
                DB::raw('YEAR(start_date) as year'),
                DB::raw('MONTH(start_date) as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(principal_amount) as amount')
            )
            ->whereNotNull('start_date')
            ->where('start_date', '>=', Carbon::now()->subMonths(12))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
        
        $stats['monthly_disbursements'] = $monthlyDisbursements;
        
        return $stats;
    }

    /**
     * Get repayment statistics for the dashboard.
     *
     * @return array
     */
    private function getRepaymentStatistics()
    {
        $stats = [
            'total_repayments' => LoanRepayment::count(),
            'total_amount' => LoanRepayment::sum('amount'),
            'pending_repayments' => LoanRepayment::where('status', 'PENDING')->count(),
            'completed_repayments' => LoanRepayment::where('status', 'COMPLETED')->count(),
        ];
        
        // Get monthly repayments for the last 12 months
        $monthlyRepayments = LoanRepayment::select(
                DB::raw('YEAR(payment_date) as year'),
                DB::raw('MONTH(payment_date) as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as amount')
            )
            ->whereNotNull('payment_date')
            ->where('payment_date', '>=', Carbon::now()->subMonths(12))
            ->where('status', 'COMPLETED')
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
        
        $stats['monthly_repayments'] = $monthlyRepayments;
        
        return $stats;
    }

    /**
     * Get default statistics for the dashboard.
     *
     * @return array
     */
    private function getDefaultStatistics()
    {
        $stats = [
            'total_defaults' => LoanDefault::count(),
            'active_defaults' => LoanDefault::where('status', 'ACTIVE')->count(),
            'resolved_defaults' => LoanDefault::where('status', 'RESOLVED')->count(),
            'written_off_defaults' => LoanDefault::where('status', 'WRITTEN_OFF')->count(),
            'total_default_amount' => LoanDefault::sum('default_amount'),
        ];
        
        // Get monthly defaults for the last 12 months
        $monthlyDefaults = LoanDefault::select(
                DB::raw('YEAR(default_date) as year'),
                DB::raw('MONTH(default_date) as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(default_amount) as amount')
            )
            ->whereNotNull('default_date')
            ->where('default_date', '>=', Carbon::now()->subMonths(12))
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();
        
        $stats['monthly_defaults'] = $monthlyDefaults;
        
        return $stats;
    }
}
