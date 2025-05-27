<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeDetail;
use App\Models\Institution;
use App\Models\JobClass;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EmployeeController extends Controller
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
     * Display a listing of the employees.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $employees = User::with(['employeeDetail.institution', 'employeeDetail.department', 'employeeDetail.designation', 'roles'])
            ->whereHas('employeeDetail')
            ->orderBy('name')
            ->paginate(15);
        
        return view('employees.index', compact('employees'));
    }

    /**
     * Show the form for creating a new employee.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $institutions = Institution::where('status', 'ACTIVE')->orderBy('name')->get();
        $departments = Department::where('status', 'ACTIVE')->orderBy('name')->get();
        $designations = Designation::where('status', 'ACTIVE')->orderBy('name')->get();
        $jobClasses = JobClass::where('status', 'ACTIVE')->orderBy('name')->get();
        
        return view('employees.create', compact('institutions', 'departments', 'designations', 'jobClasses'));
    }

    /**
     * Store a newly created employee in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'national_id' => 'required|string|max:20|unique:employee_details',
            'payroll_number' => 'required|string|max:20|unique:employee_details',
            'institution_id' => 'required|exists:institutions,id',
            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'job_class_id' => 'nullable|exists:job_classes,id',
            'salary' => 'required|numeric|min:0',
            'date_of_birth' => 'required|date|before:today',
            'date_of_employment' => 'required|date',
            'employment_type' => 'required|in:PERMANENT,CONTRACT,TEMPORARY',
            'gender' => 'required|in:MALE,FEMALE,OTHER',
            'marital_status' => 'required|in:SINGLE,MARRIED,DIVORCED,WIDOWED',
            'address' => 'nullable|string|max:255',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Create user account
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make(Str::random(10)), // Random password, will be reset by employee
            'email_verified_at' => now(),
        ]);
        
        // Handle profile photo upload
        $profilePhotoPath = null;
        if ($request->hasFile('profile_photo')) {
            $profilePhotoPath = $request->file('profile_photo')->store('profile-photos/' . $user->id, 'public');
        }
        
        // Handle document upload
        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('employee-documents/' . $user->id, 'public');
        }

        // Create employee details
        $employeeDetail = EmployeeDetail::create([
            'user_id' => $user->id,
            'national_id' => $request->national_id,
            'payroll_number' => $request->payroll_number,
            'institution_id' => $request->institution_id,
            'department_id' => $request->department_id,
            'designation_id' => $request->designation_id,
            'job_class_id' => $request->job_class_id,
            'salary' => $request->salary,
            'date_of_birth' => $request->date_of_birth,
            'date_of_employment' => $request->date_of_employment,
            'employment_type' => $request->employment_type,
            'gender' => $request->gender,
            'marital_status' => $request->marital_status,
            'address' => $request->address,
            'profile_photo' => $profilePhotoPath,
            'document' => $documentPath,
            'status' => 'ACTIVE',
        ]);
        
        // Assign default employee role
        $user->assignRole('employee');
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'Employee',
            'model_id' => $user->id,
            'description' => 'Employee created: ' . $user->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'national_id' => $employeeDetail->national_id,
                'payroll_number' => $employeeDetail->payroll_number,
                'institution_id' => $employeeDetail->institution_id,
                'department_id' => $employeeDetail->department_id,
                'designation_id' => $employeeDetail->designation_id,
            ]),
        ]);
        
        return redirect()->route('employees.index')
            ->with('success', 'Employee created successfully. A password reset link has been sent to their email.');
    }

    /**
     * Display the specified employee.
     *
     * @param  \App\Models\User  $employee
     * @return \Illuminate\View\View
     */
    public function show(User $employee)
    {
        $employee->load(['employeeDetail.institution', 'employeeDetail.department', 'employeeDetail.designation', 'employeeDetail.jobClass', 'roles']);
        
        // Get employee loan history
        $loans = $employee->loans()
            ->with('productCatalog')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('employees.show', compact('employee', 'loans'));
    }

    /**
     * Show the form for editing the specified employee.
     *
     * @param  \App\Models\User  $employee
     * @return \Illuminate\View\View
     */
    public function edit(User $employee)
    {
        $employee->load(['employeeDetail']);
        
        $institutions = Institution::where('status', 'ACTIVE')->orderBy('name')->get();
        $departments = Department::where('status', 'ACTIVE')->orderBy('name')->get();
        $designations = Designation::where('status', 'ACTIVE')->orderBy('name')->get();
        $jobClasses = JobClass::where('status', 'ACTIVE')->orderBy('name')->get();
        
        return view('employees.edit', compact('employee', 'institutions', 'departments', 'designations', 'jobClasses'));
    }

    /**
     * Update the specified employee in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $employee
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, User $employee)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $employee->id,
            'phone' => 'required|string|max:20|unique:users,phone,' . $employee->id,
            'national_id' => 'required|string|max:20|unique:employee_details,national_id,' . $employee->employeeDetail->id,
            'payroll_number' => 'required|string|max:20|unique:employee_details,payroll_number,' . $employee->employeeDetail->id,
            'institution_id' => 'required|exists:institutions,id',
            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'job_class_id' => 'nullable|exists:job_classes,id',
            'salary' => 'required|numeric|min:0',
            'date_of_birth' => 'required|date|before:today',
            'date_of_employment' => 'required|date',
            'employment_type' => 'required|in:PERMANENT,CONTRACT,TEMPORARY',
            'gender' => 'required|in:MALE,FEMALE,OTHER',
            'marital_status' => 'required|in:SINGLE,MARRIED,DIVORCED,WIDOWED',
            'address' => 'nullable|string|max:255',
            'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status' => 'required|in:ACTIVE,INACTIVE,SUSPENDED,TERMINATED',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $employee->name,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'national_id' => $employee->employeeDetail->national_id,
            'payroll_number' => $employee->employeeDetail->payroll_number,
            'institution_id' => $employee->employeeDetail->institution_id,
            'department_id' => $employee->employeeDetail->department_id,
            'designation_id' => $employee->employeeDetail->designation_id,
            'salary' => $employee->employeeDetail->salary,
            'status' => $employee->employeeDetail->status,
        ];

        // Update user account
        $employee->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);
        
        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($employee->employeeDetail->profile_photo) {
                Storage::disk('public')->delete($employee->employeeDetail->profile_photo);
            }
            
            $profilePhotoPath = $request->file('profile_photo')->store('profile-photos/' . $employee->id, 'public');
            $employee->employeeDetail->profile_photo = $profilePhotoPath;
        }
        
        // Handle document upload
        if ($request->hasFile('document')) {
            // Delete old document if exists
            if ($employee->employeeDetail->document) {
                Storage::disk('public')->delete($employee->employeeDetail->document);
            }
            
            $documentPath = $request->file('document')->store('employee-documents/' . $employee->id, 'public');
            $employee->employeeDetail->document = $documentPath;
        }

        // Update employee details
        $employee->employeeDetail->update([
            'national_id' => $request->national_id,
            'payroll_number' => $request->payroll_number,
            'institution_id' => $request->institution_id,
            'department_id' => $request->department_id,
            'designation_id' => $request->designation_id,
            'job_class_id' => $request->job_class_id,
            'salary' => $request->salary,
            'date_of_birth' => $request->date_of_birth,
            'date_of_employment' => $request->date_of_employment,
            'employment_type' => $request->employment_type,
            'gender' => $request->gender,
            'marital_status' => $request->marital_status,
            'address' => $request->address,
            'status' => $request->status,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'Employee',
            'model_id' => $employee->id,
            'description' => 'Employee updated: ' . $employee->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'name' => $employee->name,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'national_id' => $employee->employeeDetail->national_id,
                'payroll_number' => $employee->employeeDetail->payroll_number,
                'institution_id' => $employee->employeeDetail->institution_id,
                'department_id' => $employee->employeeDetail->department_id,
                'designation_id' => $employee->employeeDetail->designation_id,
                'salary' => $employee->employeeDetail->salary,
                'status' => $employee->employeeDetail->status,
            ]),
        ]);
        
        return redirect()->route('employees.index')
            ->with('success', 'Employee updated successfully.');
    }

    /**
     * Remove the specified employee from storage (soft delete).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $employee
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, User $employee)
    {
        // Check if employee has active loans
        $hasActiveLoans = $employee->loans()
            ->whereIn('status', ['ACTIVE', 'DISBURSED', 'PENDING', 'APPROVED', 'DEFAULTED'])
            ->exists();
        
        if ($hasActiveLoans) {
            return back()->with('error', 'Cannot delete employee with active loans.');
        }
        
        // Store values for audit log
        $employeeInfo = [
            'id' => $employee->id,
            'name' => $employee->name,
            'email' => $employee->email,
            'national_id' => $employee->employeeDetail->national_id,
            'payroll_number' => $employee->employeeDetail->payroll_number,
        ];
        
        // Update employee status to 'TERMINATED'
        $employee->employeeDetail->update([
            'status' => 'TERMINATED',
            'terminated_at' => now(),
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE',
            'model_type' => 'Employee',
            'model_id' => $employee->id,
            'description' => 'Employee deleted (terminated): ' . $employee->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($employeeInfo),
        ]);
        
        return redirect()->route('employees.index')
            ->with('success', 'Employee terminated successfully.');
    }

    /**
     * Download the employee document.
     *
     * @param  \App\Models\User  $employee
     * @return \Illuminate\Http\Response
     */
    public function downloadDocument(User $employee)
    {
        if (!$employee->employeeDetail || !$employee->employeeDetail->document) {
            return back()->with('error', 'No document available for this employee.');
        }
        
        return Storage::disk('public')->download(
            $employee->employeeDetail->document,
            'Employee_Document_' . $employee->id . '.' . pathinfo($employee->employeeDetail->document, PATHINFO_EXTENSION)
        );
    }
}
