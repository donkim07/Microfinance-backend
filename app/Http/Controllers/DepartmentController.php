<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DepartmentController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'role:admin|super-admin|hr-manager']);
    }

    /**
     * Display a listing of the departments.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $departments = Department::with('institution')
            ->orderBy('name')
            ->paginate(15);
        
        return view('departments.index', compact('departments'));
    }

    /**
     * Show the form for creating a new department.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $institutions = Institution::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        return view('departments.create', compact('institutions'));
    }

    /**
     * Store a newly created department in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments',
            'institution_id' => 'required|exists:institutions,id',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Create department
        $department = Department::create([
            'name' => $request->name,
            'code' => $request->code,
            'institution_id' => $request->institution_id,
            'description' => $request->description,
            'status' => 'ACTIVE',
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'Department',
            'model_id' => $department->id,
            'description' => 'Department created: ' . $department->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $department->name,
                'code' => $department->code,
                'institution_id' => $department->institution_id,
                'status' => $department->status,
            ]),
        ]);
        
        return redirect()->route('departments.index')
            ->with('success', 'Department created successfully.');
    }

    /**
     * Display the specified department.
     *
     * @param  \App\Models\Department  $department
     * @return \Illuminate\View\View
     */
    public function show(Department $department)
    {
        $department->load('institution');
        
        // Get designations for this department
        $designations = $department->designations()
            ->orderBy('name')
            ->get();
        
        // Get employee count for this department
        $employeeCount = $department->employees()->count();
        
        // Get loan statistics for this department
        $loanStats = [
            'total_loans' => $department->employees()
                ->withCount('loans')
                ->get()
                ->sum('loans_count'),
            'active_loans' => $department->employees()
                ->whereHas('loans', function($query) {
                    $query->whereIn('status', ['ACTIVE', 'DISBURSED']);
                })
                ->count(),
            'total_amount' => $department->employees()
                ->withSum('loans', 'principal_amount')
                ->get()
                ->sum('loans_sum_principal_amount'),
            'outstanding_amount' => $department->employees()
                ->whereHas('loans', function($query) {
                    $query->whereIn('status', ['ACTIVE', 'DISBURSED']);
                })
                ->withSum('loans', 'outstanding_amount')
                ->get()
                ->sum('loans_sum_outstanding_amount'),
        ];
        
        return view('departments.show', compact('department', 'designations', 'employeeCount', 'loanStats'));
    }

    /**
     * Show the form for editing the specified department.
     *
     * @param  \App\Models\Department  $department
     * @return \Illuminate\View\View
     */
    public function edit(Department $department)
    {
        $institutions = Institution::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        return view('departments.edit', compact('department', 'institutions'));
    }

    /**
     * Update the specified department in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Department  $department
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Department $department)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code,' . $department->id,
            'institution_id' => 'required|exists:institutions,id',
            'description' => 'nullable|string',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $department->name,
            'code' => $department->code,
            'institution_id' => $department->institution_id,
            'status' => $department->status,
        ];

        // Update department
        $department->update([
            'name' => $request->name,
            'code' => $request->code,
            'institution_id' => $request->institution_id,
            'description' => $request->description,
            'status' => $request->status,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'Department',
            'model_id' => $department->id,
            'description' => 'Department updated: ' . $department->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'name' => $department->name,
                'code' => $department->code,
                'institution_id' => $department->institution_id,
                'status' => $department->status,
            ]),
        ]);
        
        return redirect()->route('departments.index')
            ->with('success', 'Department updated successfully.');
    }

    /**
     * Remove the specified department from storage (soft delete).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Department  $department
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Department $department)
    {
        // Check if department has designations
        $hasDesignations = $department->designations()->exists();
        
        if ($hasDesignations) {
            return back()->with('error', 'Cannot delete department with designations. Please delete the designations first.');
        }
        
        // Check if department has employees
        $hasEmployees = $department->employees()->exists();
        
        if ($hasEmployees) {
            return back()->with('error', 'Cannot delete department with employees. Please reassign or delete the employees first.');
        }
        
        // Store values for audit log
        $departmentInfo = [
            'id' => $department->id,
            'name' => $department->name,
            'code' => $department->code,
            'institution_id' => $department->institution_id,
        ];
        
        // Update department status
        $department->update([
            'status' => 'INACTIVE',
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE',
            'model_type' => 'Department',
            'model_id' => $department->id,
            'description' => 'Department deleted (set to inactive): ' . $department->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($departmentInfo),
        ]);
        
        return redirect()->route('departments.index')
            ->with('success', 'Department deactivated successfully.');
    }
}
