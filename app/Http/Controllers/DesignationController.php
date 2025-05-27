<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\JobClass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DesignationController extends Controller
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
     * Display a listing of the designations.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $designations = Designation::with(['department.institution', 'jobClass'])
            ->orderBy('name')
            ->paginate(15);
        
        return view('designations.index', compact('designations'));
    }

    /**
     * Show the form for creating a new designation.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $departments = Department::where('status', 'ACTIVE')
            ->with('institution')
            ->orderBy('name')
            ->get();
        
        $jobClasses = JobClass::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        return view('designations.create', compact('departments', 'jobClasses'));
    }

    /**
     * Store a newly created designation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:designations',
            'department_id' => 'required|exists:departments,id',
            'job_class_id' => 'nullable|exists:job_classes,id',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Create designation
        $designation = Designation::create([
            'name' => $request->name,
            'code' => $request->code,
            'department_id' => $request->department_id,
            'job_class_id' => $request->job_class_id,
            'description' => $request->description,
            'status' => 'ACTIVE',
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'Designation',
            'model_id' => $designation->id,
            'description' => 'Designation created: ' . $designation->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $designation->name,
                'code' => $designation->code,
                'department_id' => $designation->department_id,
                'job_class_id' => $designation->job_class_id,
                'status' => $designation->status,
            ]),
        ]);
        
        return redirect()->route('designations.index')
            ->with('success', 'Designation created successfully.');
    }

    /**
     * Display the specified designation.
     *
     * @param  \App\Models\Designation  $designation
     * @return \Illuminate\View\View
     */
    public function show(Designation $designation)
    {
        $designation->load(['department.institution', 'jobClass']);
        
        // Get employee count for this designation
        $employeeCount = $designation->employees()->count();
        
        // Get loan statistics for this designation
        $loanStats = [
            'total_loans' => $designation->employees()
                ->withCount('loans')
                ->get()
                ->sum('loans_count'),
            'active_loans' => $designation->employees()
                ->whereHas('loans', function($query) {
                    $query->whereIn('status', ['ACTIVE', 'DISBURSED']);
                })
                ->count(),
            'total_amount' => $designation->employees()
                ->withSum('loans', 'principal_amount')
                ->get()
                ->sum('loans_sum_principal_amount'),
            'outstanding_amount' => $designation->employees()
                ->whereHas('loans', function($query) {
                    $query->whereIn('status', ['ACTIVE', 'DISBURSED']);
                })
                ->withSum('loans', 'outstanding_amount')
                ->get()
                ->sum('loans_sum_outstanding_amount'),
        ];
        
        return view('designations.show', compact('designation', 'employeeCount', 'loanStats'));
    }

    /**
     * Show the form for editing the specified designation.
     *
     * @param  \App\Models\Designation  $designation
     * @return \Illuminate\View\View
     */
    public function edit(Designation $designation)
    {
        $departments = Department::where('status', 'ACTIVE')
            ->with('institution')
            ->orderBy('name')
            ->get();
        
        $jobClasses = JobClass::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        return view('designations.edit', compact('designation', 'departments', 'jobClasses'));
    }

    /**
     * Update the specified designation in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Designation  $designation
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Designation $designation)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:designations,code,' . $designation->id,
            'department_id' => 'required|exists:departments,id',
            'job_class_id' => 'nullable|exists:job_classes,id',
            'description' => 'nullable|string',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $designation->name,
            'code' => $designation->code,
            'department_id' => $designation->department_id,
            'job_class_id' => $designation->job_class_id,
            'status' => $designation->status,
        ];

        // Update designation
        $designation->update([
            'name' => $request->name,
            'code' => $request->code,
            'department_id' => $request->department_id,
            'job_class_id' => $request->job_class_id,
            'description' => $request->description,
            'status' => $request->status,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'Designation',
            'model_id' => $designation->id,
            'description' => 'Designation updated: ' . $designation->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'name' => $designation->name,
                'code' => $designation->code,
                'department_id' => $designation->department_id,
                'job_class_id' => $designation->job_class_id,
                'status' => $designation->status,
            ]),
        ]);
        
        return redirect()->route('designations.index')
            ->with('success', 'Designation updated successfully.');
    }

    /**
     * Remove the specified designation from storage (soft delete).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Designation  $designation
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Designation $designation)
    {
        // Check if designation has employees
        $hasEmployees = $designation->employees()->exists();
        
        if ($hasEmployees) {
            return back()->with('error', 'Cannot delete designation with employees. Please reassign or delete the employees first.');
        }
        
        // Store values for audit log
        $designationInfo = [
            'id' => $designation->id,
            'name' => $designation->name,
            'code' => $designation->code,
            'department_id' => $designation->department_id,
        ];
        
        // Update designation status
        $designation->update([
            'status' => 'INACTIVE',
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE',
            'model_type' => 'Designation',
            'model_id' => $designation->id,
            'description' => 'Designation deleted (set to inactive): ' . $designation->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($designationInfo),
        ]);
        
        return redirect()->route('designations.index')
            ->with('success', 'Designation deactivated successfully.');
    }
}
