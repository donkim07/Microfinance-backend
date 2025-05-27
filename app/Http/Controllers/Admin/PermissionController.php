<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PermissionController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(['auth', 'role:super-admin']);
    }

    /**
     * Display a listing of the permissions.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $permissions = Permission::all()->groupBy(function($permission) {
            return explode('.', $permission->name)[0];
        });
        
        return view('admin.permissions.index', compact('permissions'));
    }

    /**
     * Show the form for creating a new permission.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        // Get all unique permission groups
        $groups = Permission::all()->map(function($permission) {
            return explode('.', $permission->name)[0];
        })->unique()->values()->toArray();
        
        return view('admin.permissions.create', compact('groups'));
    }

    /**
     * Store a newly created permission in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Format the permission name
        $permissionName = Str::slug($request->group) . '.' . Str::slug($request->name);
        
        // Check if permission already exists
        if (Permission::where('name', $permissionName)->exists()) {
            return back()->withErrors(['name' => 'This permission already exists.'])->withInput();
        }

        // Create permission
        $permission = Permission::create([
            'name' => $permissionName,
            'display_name' => $request->name,
            'description' => $request->description,
        ]);

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'Permission',
            'model_id' => $permission->id,
            'description' => 'Permission created by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'description' => $permission->description,
            ]),
        ]);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission created successfully.');
    }

    /**
     * Display the specified permission.
     *
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\View\View
     */
    public function show(Permission $permission)
    {
        // Get roles with this permission
        $roles = $permission->roles()->get();
        
        return view('admin.permissions.show', compact('permission', 'roles'));
    }

    /**
     * Show the form for editing the specified permission.
     *
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\View\View
     */
    public function edit(Permission $permission)
    {
        // Get all unique permission groups
        $groups = Permission::all()->map(function($p) {
            return explode('.', $p->name)[0];
        })->unique()->values()->toArray();
        
        // Get current group and name
        $parts = explode('.', $permission->name);
        $currentGroup = $parts[0];
        $currentName = $permission->display_name;
        
        return view('admin.permissions.edit', compact('permission', 'groups', 'currentGroup', 'currentName'));
    }

    /**
     * Update the specified permission in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Permission $permission)
    {
        $validator = Validator::make($request->all(), [
            'group' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Format the permission name
        $permissionName = Str::slug($request->group) . '.' . Str::slug($request->name);
        
        // Check if permission already exists
        if (Permission::where('name', $permissionName)->where('id', '!=', $permission->id)->exists()) {
            return back()->withErrors(['name' => 'This permission already exists.'])->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $permission->name,
            'display_name' => $permission->display_name,
            'description' => $permission->description,
        ];

        // Update permission
        $permission->update([
            'name' => $permissionName,
            'display_name' => $request->name,
            'description' => $request->description,
        ]);

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'Permission',
            'model_id' => $permission->id,
            'description' => 'Permission updated by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'name' => $permission->name,
                'display_name' => $permission->display_name,
                'description' => $permission->description,
            ]),
        ]);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission updated successfully.');
    }

    /**
     * Remove the specified permission from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Permission  $permission
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Permission $permission)
    {
        // Prevent deletion of core permissions
        if (Str::startsWith($permission->name, ['users.', 'roles.', 'permissions.'])) {
            return back()->with('error', 'Core permissions cannot be deleted.');
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $permission->name,
            'display_name' => $permission->display_name,
            'description' => $permission->description,
        ];

        // Detach from roles
        $permission->roles()->detach();

        // Delete permission
        $permission->delete();

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE',
            'model_type' => 'Permission',
            'model_id' => $permission->id,
            'description' => 'Permission deleted by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
        ]);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission deleted successfully.');
    }
}
