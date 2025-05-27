<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanRepayment;
use App\Models\LoanDefault;
use App\Models\User;
use App\Models\Institution;
use App\Models\Department;
use App\Models\FinancialServiceProvider;
use App\Models\ProductCatalog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class ReportService
{
    /**
     * Generate a loan report.
     *
     * @param array $filters
     * @param string $format
     * @return mixed
     */
    public function generateLoanReport($filters, $format = 'pdf')
    {
        try {
            $query = Loan::with(['user.employeeDetail.institution', 'user.employeeDetail.department', 'productCatalog.financialServiceProvider']);
            
            // Apply filters
            if (isset($filters['start_date']) && $filters['start_date']) {
                $query->where('created_at', '>=', $filters['start_date']);
            }
            
            if (isset($filters['end_date']) && $filters['end_date']) {
                $query->where('created_at', '<=', $filters['end_date']);
            }
            
            if (isset($filters['status']) && $filters['status']) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['institution_id']) && $filters['institution_id']) {
                $query->whereHas('user.employeeDetail', function ($q) use ($filters) {
                    $q->where('institution_id', $filters['institution_id']);
                });
            }
            
            if (isset($filters['department_id']) && $filters['department_id']) {
                $query->whereHas('user.employeeDetail', function ($q) use ($filters) {
                    $q->where('department_id', $filters['department_id']);
                });
            }
            
            if (isset($filters['fsp_id']) && $filters['fsp_id']) {
                $query->whereHas('productCatalog', function ($q) use ($filters) {
                    $q->where('financial_service_provider_id', $filters['fsp_id']);
                });
            }
            
            if (isset($filters['product_id']) && $filters['product_id']) {
                $query->where('product_catalog_id', $filters['product_id']);
            }
            
            // Get results
            $loans = $query->get();
            
            // Calculate totals
            $totalPrincipal = $loans->sum('principal_amount');
            $totalInterest = $loans->sum('interest_amount');
            $totalFees = $loans->sum('fees_amount');
            $totalAmount = $loans->sum('total_amount');
            $totalOutstanding = $loans->sum('outstanding_amount');
            $totalPaid = $totalAmount - $totalOutstanding;
            
            // Prepare data for report
            $data = [
                'title' => 'Loan Report',
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'filters' => $filters,
                'loans' => $loans,
                'totals' => [
                    'count' => $loans->count(),
                    'principal' => $totalPrincipal,
                    'interest' => $totalInterest,
                    'fees' => $totalFees,
                    'total' => $totalAmount,
                    'outstanding' => $totalOutstanding,
                    'paid' => $totalPaid,
                ],
            ];
            
            // Generate report in requested format
            return $this->generateReport($data, 'loan', $format);
        } catch (\Exception $e) {
            Log::error("Error generating loan report: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Generate a repayment report.
     *
     * @param array $filters
     * @param string $format
     * @return mixed
     */
    public function generateRepaymentReport($filters, $format = 'pdf')
    {
        try {
            $query = LoanRepayment::with(['loan.user.employeeDetail.institution', 'loan.productCatalog', 'user']);
            
            // Apply filters
            if (isset($filters['start_date']) && $filters['start_date']) {
                $query->where('payment_date', '>=', $filters['start_date']);
            }
            
            if (isset($filters['end_date']) && $filters['end_date']) {
                $query->where('payment_date', '<=', $filters['end_date']);
            }
            
            if (isset($filters['status']) && $filters['status']) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['payment_method']) && $filters['payment_method']) {
                $query->where('payment_method', $filters['payment_method']);
            }
            
            if (isset($filters['institution_id']) && $filters['institution_id']) {
                $query->whereHas('loan.user.employeeDetail', function ($q) use ($filters) {
                    $q->where('institution_id', $filters['institution_id']);
                });
            }
            
            if (isset($filters['department_id']) && $filters['department_id']) {
                $query->whereHas('loan.user.employeeDetail', function ($q) use ($filters) {
                    $q->where('department_id', $filters['department_id']);
                });
            }
            
            // Get results
            $repayments = $query->get();
            
            // Calculate totals
            $totalAmount = $repayments->sum('amount');
            
            // Group by payment method
            $paymentMethodTotals = $repayments->groupBy('payment_method')
                ->map(function ($items) {
                    return [
                        'count' => $items->count(),
                        'amount' => $items->sum('amount'),
                    ];
                });
            
            // Prepare data for report
            $data = [
                'title' => 'Repayment Report',
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'filters' => $filters,
                'repayments' => $repayments,
                'totals' => [
                    'count' => $repayments->count(),
                    'amount' => $totalAmount,
                ],
                'payment_methods' => $paymentMethodTotals,
            ];
            
            // Generate report in requested format
            return $this->generateReport($data, 'repayment', $format);
        } catch (\Exception $e) {
            Log::error("Error generating repayment report: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Generate a default report.
     *
     * @param array $filters
     * @param string $format
     * @return mixed
     */
    public function generateDefaultReport($filters, $format = 'pdf')
    {
        try {
            $query = LoanDefault::with(['loan.user.employeeDetail.institution', 'loan.productCatalog', 'createdBy']);
            
            // Apply filters
            if (isset($filters['start_date']) && $filters['start_date']) {
                $query->where('default_date', '>=', $filters['start_date']);
            }
            
            if (isset($filters['end_date']) && $filters['end_date']) {
                $query->where('default_date', '<=', $filters['end_date']);
            }
            
            if (isset($filters['status']) && $filters['status']) {
                $query->where('status', $filters['status']);
            }
            
            if (isset($filters['institution_id']) && $filters['institution_id']) {
                $query->whereHas('loan.user.employeeDetail', function ($q) use ($filters) {
                    $q->where('institution_id', $filters['institution_id']);
                });
            }
            
            if (isset($filters['department_id']) && $filters['department_id']) {
                $query->whereHas('loan.user.employeeDetail', function ($q) use ($filters) {
                    $q->where('department_id', $filters['department_id']);
                });
            }
            
            // Get results
            $defaults = $query->get();
            
            // Calculate totals
            $totalAmount = $defaults->sum('default_amount');
            
            // Group by status
            $statusTotals = $defaults->groupBy('status')
                ->map(function ($items) {
                    return [
                        'count' => $items->count(),
                        'amount' => $items->sum('default_amount'),
                    ];
                });
            
            // Prepare data for report
            $data = [
                'title' => 'Loan Default Report',
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'filters' => $filters,
                'defaults' => $defaults,
                'totals' => [
                    'count' => $defaults->count(),
                    'amount' => $totalAmount,
                ],
                'status_totals' => $statusTotals,
            ];
            
            // Generate report in requested format
            return $this->generateReport($data, 'default', $format);
        } catch (\Exception $e) {
            Log::error("Error generating default report: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Generate a user report.
     *
     * @param array $filters
     * @param string $format
     * @return mixed
     */
    public function generateUserReport($filters, $format = 'pdf')
    {
        try {
            $query = User::with(['employeeDetail.institution', 'employeeDetail.department', 'roles', 'loans']);
            
            // Apply filters
            if (isset($filters['institution_id']) && $filters['institution_id']) {
                $query->whereHas('employeeDetail', function ($q) use ($filters) {
                    $q->where('institution_id', $filters['institution_id']);
                });
            }
            
            if (isset($filters['department_id']) && $filters['department_id']) {
                $query->whereHas('employeeDetail', function ($q) use ($filters) {
                    $q->where('department_id', $filters['department_id']);
                });
            }
            
            if (isset($filters['role']) && $filters['role']) {
                $query->whereHas('roles', function ($q) use ($filters) {
                    $q->where('slug', $filters['role']);
                });
            }
            
            if (isset($filters['has_active_loans']) && $filters['has_active_loans']) {
                $query->whereHas('loans', function ($q) {
                    $q->whereIn('status', ['ACTIVE', 'DISBURSED']);
                });
            }
            
            if (isset($filters['has_defaulted_loans']) && $filters['has_defaulted_loans']) {
                $query->whereHas('loans', function ($q) {
                    $q->where('status', 'DEFAULTED');
                });
            }
            
            // Get results
            $users = $query->get();
            
            // Process user loan data
            $usersWithLoanData = $users->map(function ($user) {
                $totalLoans = $user->loans->count();
                $activeLoans = $user->loans->whereIn('status', ['ACTIVE', 'DISBURSED'])->count();
                $totalAmount = $user->loans->sum('principal_amount');
                $outstandingAmount = $user->loans->sum('outstanding_amount');
                
                return [
                    'user' => $user,
                    'loan_data' => [
                        'total_loans' => $totalLoans,
                        'active_loans' => $activeLoans,
                        'total_amount' => $totalAmount,
                        'outstanding_amount' => $outstandingAmount,
                    ],
                ];
            });
            
            // Prepare data for report
            $data = [
                'title' => 'User Report',
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'filters' => $filters,
                'users' => $usersWithLoanData,
                'totals' => [
                    'count' => $users->count(),
                    'with_loans' => $users->filter(function ($user) {
                        return $user->loans->count() > 0;
                    })->count(),
                    'with_active_loans' => $users->filter(function ($user) {
                        return $user->loans->whereIn('status', ['ACTIVE', 'DISBURSED'])->count() > 0;
                    })->count(),
                ],
            ];
            
            // Generate report in requested format
            return $this->generateReport($data, 'user', $format);
        } catch (\Exception $e) {
            Log::error("Error generating user report: {$e->getMessage()}");
            return false;
        }
    }
    
    /**
     * Generate a report in the specified format.
     *
     * @param array $data
     * @param string $type
     * @param string $format
     * @return mixed
     */
    protected function generateReport($data, $type, $format)
    {
        $filename = strtolower($data['title']) . '_' . now()->format('Y-m-d_H-i-s');
        
        switch ($format) {
            case 'pdf':
                return $this->generatePDFReport($data, $type, $filename);
            case 'csv':
                return $this->generateCSVReport($data, $type, $filename);
            case 'excel':
                return $this->generateExcelReport($data, $type, $filename);
            default:
                return $data; // Return raw data if format not supported
        }
    }
    
    /**
     * Generate a PDF report.
     *
     * @param array $data
     * @param string $type
     * @param string $filename
     * @return string
     */
    protected function generatePDFReport($data, $type, $filename)
    {
        $view = "reports.{$type}.pdf";
        $pdf = PDF::loadView($view, $data);
        
        $path = "reports/{$filename}.pdf";
        Storage::disk('public')->put($path, $pdf->output());
        
        return $path;
    }
    
    /**
     * Generate a CSV report.
     *
     * @param array $data
     * @param string $type
     * @param string $filename
     * @return string
     */
    protected function generateCSVReport($data, $type, $filename)
    {
        $path = "reports/{$filename}.csv";
        $handle = fopen(storage_path("app/public/{$path}"), 'w');
        
        // Write headers
        $headers = $this->getReportHeaders($type);
        fputcsv($handle, $headers);
        
        // Write data rows
        $reportData = $this->getReportData($data, $type);
        foreach ($reportData as $row) {
            fputcsv($handle, $row);
        }
        
        fclose($handle);
        
        return $path;
    }
    
    /**
     * Generate an Excel report.
     *
     * @param array $data
     * @param string $type
     * @param string $filename
     * @return string
     */
    protected function generateExcelReport($data, $type, $filename)
    {
        $path = "reports/{$filename}.xlsx";
        
        // Create Excel export using collection
        Excel::store(
            new \App\Exports\ReportExport($data, $type),
            "public/{$path}"
        );
        
        return $path;
    }
    
    /**
     * Get report headers based on report type.
     *
     * @param string $type
     * @return array
     */
    protected function getReportHeaders($type)
    {
        switch ($type) {
            case 'loan':
                return [
                    'Loan Reference', 'User', 'Institution', 'Department', 
                    'Principal Amount', 'Interest Amount', 'Fees', 'Total Amount',
                    'Outstanding Amount', 'Status', 'FSP', 'Product', 'Start Date', 'End Date'
                ];
            case 'repayment':
                return [
                    'Loan Reference', 'User', 'Payment Date', 'Amount', 
                    'Payment Method', 'Reference Number', 'Status'
                ];
            case 'default':
                return [
                    'Loan Reference', 'User', 'Default Date', 'Default Amount', 
                    'Default Reason', 'Status', 'Created By'
                ];
            case 'user':
                return [
                    'Name', 'Email', 'Phone', 'Institution', 'Department',
                    'Designation', 'Salary', 'Total Loans', 'Active Loans',
                    'Total Loan Amount', 'Outstanding Amount', 'Roles'
                ];
            default:
                return [];
        }
    }
    
    /**
     * Get report data based on report type.
     *
     * @param array $data
     * @param string $type
     * @return array
     */
    protected function getReportData($data, $type)
    {
        $rows = [];
        
        switch ($type) {
            case 'loan':
                foreach ($data['loans'] as $loan) {
                    $rows[] = [
                        $loan->reference_number,
                        $loan->user->name,
                        $loan->user->employeeDetail->institution->name ?? '',
                        $loan->user->employeeDetail->department->name ?? '',
                        $loan->principal_amount,
                        $loan->interest_amount,
                        $loan->fees_amount,
                        $loan->total_amount,
                        $loan->outstanding_amount,
                        $loan->status,
                        $loan->productCatalog->financialServiceProvider->name ?? '',
                        $loan->productCatalog->name ?? '',
                        $loan->start_date,
                        $loan->expected_end_date,
                    ];
                }
                break;
            case 'repayment':
                foreach ($data['repayments'] as $repayment) {
                    $rows[] = [
                        $repayment->loan->reference_number ?? '',
                        $repayment->loan->user->name ?? '',
                        $repayment->payment_date,
                        $repayment->amount,
                        $repayment->payment_method,
                        $repayment->reference_number,
                        $repayment->status,
                    ];
                }
                break;
            case 'default':
                foreach ($data['defaults'] as $default) {
                    $rows[] = [
                        $default->loan->reference_number ?? '',
                        $default->loan->user->name ?? '',
                        $default->default_date,
                        $default->default_amount,
                        $default->default_reason,
                        $default->status,
                        $default->createdBy->name ?? '',
                    ];
                }
                break;
            case 'user':
                foreach ($data['users'] as $userData) {
                    $user = $userData['user'];
                    $loanData = $userData['loan_data'];
                    
                    $rows[] = [
                        $user->name,
                        $user->email,
                        $user->phone,
                        $user->employeeDetail->institution->name ?? '',
                        $user->employeeDetail->department->name ?? '',
                        $user->employeeDetail->designation->name ?? '',
                        $user->employeeDetail->salary ?? 0,
                        $loanData['total_loans'],
                        $loanData['active_loans'],
                        $loanData['total_amount'],
                        $loanData['outstanding_amount'],
                        $user->roles->pluck('name')->implode(', '),
                    ];
                }
                break;
        }
        
        return $rows;
    }
    
    /**
     * Get loan statistics for dashboard.
     *
     * @param array $filters
     * @return array
     */
    public function getLoanStatistics($filters = [])
    {
        try {
            $query = Loan::query();
            
            // Apply filters
            if (isset($filters['start_date']) && $filters['start_date']) {
                $query->where('created_at', '>=', $filters['start_date']);
            }
            
            if (isset($filters['end_date']) && $filters['end_date']) {
                $query->where('created_at', '<=', $filters['end_date']);
            }
            
            if (isset($filters['institution_id']) && $filters['institution_id']) {
                $query->whereHas('user.employeeDetail', function ($q) use ($filters) {
                    $q->where('institution_id', $filters['institution_id']);
                });
            }
            
            // Total counts
            $totalLoans = $query->count();
            $totalAmount = $query->sum('principal_amount');
            $outstandingAmount = $query->sum('outstanding_amount');
            
            // Status breakdown
            $statusCounts = $query->select('status', DB::raw('count(*) as count'), DB::raw('sum(principal_amount) as amount'))
                ->groupBy('status')
                ->get()
                ->pluck('amount', 'status')
                ->toArray();
            
            // Monthly trends (for the last 12 months)
            $monthlyTrends = [];
            for ($i = 11; $i >= 0; $i--) {
                $month = Carbon::now()->subMonths($i);
                $startDate = $month->startOfMonth()->format('Y-m-d');
                $endDate = $month->endOfMonth()->format('Y-m-d');
                
                $monthlyLoanCount = Loan::whereBetween('created_at', [$startDate, $endDate])->count();
                $monthlyLoanAmount = Loan::whereBetween('created_at', [$startDate, $endDate])->sum('principal_amount');
                
                $monthlyTrends[$month->format('M Y')] = [
                    'count' => $monthlyLoanCount,
                    'amount' => $monthlyLoanAmount,
                ];
            }
            
            return [
                'total_loans' => $totalLoans,
                'total_amount' => $totalAmount,
                'outstanding_amount' => $outstandingAmount,
                'status_counts' => $statusCounts,
                'monthly_trends' => $monthlyTrends,
            ];
        } catch (\Exception $e) {
            Log::error("Error getting loan statistics: {$e->getMessage()}");
            return [];
        }
    }
    
    /**
     * Get institution-based loan statistics.
     *
     * @param array $filters
     * @return array
     */
    public function getInstitutionStatistics($filters = [])
    {
        try {
            $institutions = Institution::where('status', 'ACTIVE')->get();
            
            $stats = [];
            foreach ($institutions as $institution) {
                $loanCount = Loan::whereHas('user.employeeDetail', function ($query) use ($institution) {
                    $query->where('institution_id', $institution->id);
                })->count();
                
                $loanAmount = Loan::whereHas('user.employeeDetail', function ($query) use ($institution) {
                    $query->where('institution_id', $institution->id);
                })->sum('principal_amount');
                
                $outstandingAmount = Loan::whereHas('user.employeeDetail', function ($query) use ($institution) {
                    $query->where('institution_id', $institution->id);
                })->sum('outstanding_amount');
                
                $defaultedAmount = LoanDefault::whereHas('loan.user.employeeDetail', function ($query) use ($institution) {
                    $query->where('institution_id', $institution->id);
                })->sum('default_amount');
                
                $stats[$institution->name] = [
                    'loan_count' => $loanCount,
                    'loan_amount' => $loanAmount,
                    'outstanding_amount' => $outstandingAmount,
                    'defaulted_amount' => $defaultedAmount,
                ];
            }
            
            return $stats;
        } catch (\Exception $e) {
            Log::error("Error getting institution statistics: {$e->getMessage()}");
            return [];
        }
    }
} 