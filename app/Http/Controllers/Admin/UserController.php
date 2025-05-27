<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Designation;
use App\Models\EmployeeDetail;
use App\Models\Institution;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
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
     * Display a listing of the users.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $users = User::with(['roles', 'employeeDetail.institution', 'employeeDetail.department', 'employeeDetail.designation'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);
        
        return view('admin.users.index', compact('users'));
    }

    /**
     * Show the form for creating a new user.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $roles = Role::all();
        $institutions = Institution::all();
        $departments = Department::all();
        $designations = Designation::all();
        
        return view('admin.users.create', compact('roles', 'institutions', 'departments', 'designations'));
    }

    /**
     * Store a newly created user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone_number' => 'required|string|max:15|unique:users',
            'employee_number' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'institution_id' => 'required|exists:institutions,id',
            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Create user
        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'employee_number' => $request->employee_number,
            'password' => Hash::make($request->password),
        ]);

        // Assign roles
        $user->roles()->attach($request->roles);

        // Create employee details
        EmployeeDetail::create([
            'user_id' => $user->id,
            'institution_id' => $request->institution_id,
            'department_id' => $request->department_id,
            'designation_id' => $request->designation_id,
            'employment_date' => $request->employment_date ?? now(),
            'status' => 'ACTIVE',
        ]);

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => 'User created by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $user->name,
                'email' => $user->email,
                'employee_number' => $user->employee_number,
                'phone_number' => $user->phone_number,
                'roles' => $request->roles,
                'institution_id' => $request->institution_id,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
            ]),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified user.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\View\View
     */
    public function show(User $user)
    {
        $user->load(['roles', 'employeeDetail.institution', 'employeeDetail.department', 'employeeDetail.designation']);
        
        // Get user's loan applications
        $loanApplications = $user->loanApplications()
            ->with('productCatalog')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get user's loans
        $loans = $user->loans()
            ->with('productCatalog')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Get user's loan repayments
        $loanRepayments = $user->loanRepayments()
            ->with('loan')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return view('admin.users.show', compact('user', 'loanApplications', 'loans', 'loanRepayments'));
    }

    /**
     * Show the form for editing the specified user.
     *
     * @param  \App\Models\User  $user
     * @return \Illuminate\View\View
     */
    public function edit(User $user)
    {
        $user->load(['roles', 'employeeDetail']);
        
        $roles = Role::all();
        $institutions = Institution::all();
        $departments = Department::all();
        $designations = Designation::all();
        
        return view('admin.users.edit', compact('user', 'roles', 'institutions', 'departments', 'designations'));
    }

    /**
     * Update the specified user in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, User $user)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone_number' => ['required', 'string', 'max:15', Rule::unique('users')->ignore($user->id)],
            'employee_number' => ['required', 'string', 'max:20', Rule::unique('users')->ignore($user->id)],
            'institution_id' => 'required|exists:institutions,id',
            'department_id' => 'required|exists:departments,id',
            'designation_id' => 'required|exists:designations,id',
            'roles' => 'required|array',
            'roles.*' => 'exists:roles,id',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'employee_number' => $user->employee_number,
            'phone_number' => $user->phone_number,
            'roles' => $user->roles->pluck('id')->toArray(),
            'institution_id' => $user->employeeDetail ? $user->employeeDetail->institution_id : null,
            'department_id' => $user->employeeDetail ? $user->employeeDetail->department_id : null,
            'designation_id' => $user->employeeDetail ? $user->employeeDetail->designation_id : null,
        ];

        // Update user
        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'employee_number' => $request->employee_number,
        ]);

        // Update password if provided
        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        // Update roles
        $user->roles()->sync($request->roles);

        // Update employee details
        if ($user->employeeDetail) {
            $user->employeeDetail->update([
                'institution_id' => $request->institution_id,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
                'employment_date' => $request->employment_date ?? $user->employeeDetail->employment_date,
                'status' => $request->status ?? $user->employeeDetail->status,
            ]);
        } else {
            EmployeeDetail::create([
                'user_id' => $user->id,
                'institution_id' => $request->institution_id,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
                'employment_date' => $request->employment_date ?? now(),
                'status' => 'ACTIVE',
            ]);
        }

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => 'User updated by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'name' => $user->name,
                'email' => $user->email,
                'employee_number' => $user->employee_number,
                'phone_number' => $user->phone_number,
                'roles' => $request->roles,
                'institution_id' => $request->institution_id,
                'department_id' => $request->department_id,
                'designation_id' => $request->designation_id,
            ]),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified user from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, User $user)
    {
        // Check if user has loans
        if ($user->loans()->count() > 0) {
            return back()->with('error', 'User cannot be deleted because they have loans associated with their account.');
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'employee_number' => $user->employee_number,
            'phone_number' => $user->phone_number,
            'roles' => $user->roles->pluck('id')->toArray(),
        ];

        // Delete employee details
        if ($user->employeeDetail) {
            $user->employeeDetail->delete();
        }

        // Detach roles
        $user->roles()->detach();

        // Delete user
        $user->delete();

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => 'User deleted by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Show user profile for editing.
     *
     * @return \Illuminate\View\View
     */
    public function profile()
    {
        $user = Auth::user();
        $user->load(['roles', 'employeeDetail.institution', 'employeeDetail.department', 'employeeDetail.designation']);
        
        return view('profile', compact('user'));
    }

    /**
     * Update user profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'phone_number' => ['required', 'string', 'max:15', Rule::unique('users')->ignore($user->id)],
            'current_password' => 'nullable|required_with:password|string',
            'password' => 'nullable|string|min:8|confirmed',
            'preferred_language' => 'nullable|string|in:en,sw',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Verify current password if changing password
        if ($request->filled('current_password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return back()->withErrors(['current_password' => 'The current password is incorrect.'])->withInput();
            }
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $user->name,
            'email' => $user->email,
            'phone_number' => $user->phone_number,
            'preferred_language' => $user->preferred_language,
        ];

        // Update user profile
        $user->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'phone_number' => $request->phone_number,
            'preferred_language' => $request->preferred_language,
        ]);

        // Update password if provided
        if ($request->filled('password')) {
            $user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        // Log action
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'UPDATE_PROFILE',
            'model_type' => 'User',
            'model_id' => $user->id,
            'description' => 'User updated their profile',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'name' => $user->name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'preferred_language' => $user->preferred_language,
            ]),
        ]);

        return redirect()->route('profile')
            ->with('success', 'Profile updated successfully.');
    }
}
