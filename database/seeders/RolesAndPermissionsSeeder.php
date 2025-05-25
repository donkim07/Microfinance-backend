<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create permissions
        $permissions = [
            // User management
            ['name' => 'view-users', 'display_name' => 'View Users', 'description' => 'Can view users'],
            ['name' => 'create-users', 'display_name' => 'Create Users', 'description' => 'Can create users'],
            ['name' => 'edit-users', 'display_name' => 'Edit Users', 'description' => 'Can edit users'],
            ['name' => 'delete-users', 'display_name' => 'Delete Users', 'description' => 'Can delete users'],
            
            // Role management
            ['name' => 'view-roles', 'display_name' => 'View Roles', 'description' => 'Can view roles'],
            ['name' => 'create-roles', 'display_name' => 'Create Roles', 'description' => 'Can create roles'],
            ['name' => 'edit-roles', 'display_name' => 'Edit Roles', 'description' => 'Can edit roles'],
            ['name' => 'delete-roles', 'display_name' => 'Delete Roles', 'description' => 'Can delete roles'],
            
            // FSP management
            ['name' => 'view-fsps', 'display_name' => 'View FSPs', 'description' => 'Can view financial service providers'],
            ['name' => 'create-fsps', 'display_name' => 'Create FSPs', 'description' => 'Can create financial service providers'],
            ['name' => 'edit-fsps', 'display_name' => 'Edit FSPs', 'description' => 'Can edit financial service providers'],
            ['name' => 'delete-fsps', 'display_name' => 'Delete FSPs', 'description' => 'Can delete financial service providers'],
            
            // Loan product management
            ['name' => 'view-loan-products', 'display_name' => 'View Loan Products', 'description' => 'Can view loan products'],
            ['name' => 'create-loan-products', 'display_name' => 'Create Loan Products', 'description' => 'Can create loan products'],
            ['name' => 'edit-loan-products', 'display_name' => 'Edit Loan Products', 'description' => 'Can edit loan products'],
            ['name' => 'delete-loan-products', 'display_name' => 'Delete Loan Products', 'description' => 'Can delete loan products'],
            
            // Loan application management
            ['name' => 'view-loan-applications', 'display_name' => 'View Loan Applications', 'description' => 'Can view loan applications'],
            ['name' => 'create-loan-applications', 'display_name' => 'Create Loan Applications', 'description' => 'Can create loan applications'],
            ['name' => 'edit-loan-applications', 'display_name' => 'Edit Loan Applications', 'description' => 'Can edit loan applications'],
            ['name' => 'approve-loan-applications', 'display_name' => 'Approve Loan Applications', 'description' => 'Can approve loan applications'],
            ['name' => 'reject-loan-applications', 'display_name' => 'Reject Loan Applications', 'description' => 'Can reject loan applications'],
            
            // Employee management
            ['name' => 'view-employees', 'display_name' => 'View Employees', 'description' => 'Can view employees'],
            ['name' => 'create-employees', 'display_name' => 'Create Employees', 'description' => 'Can create employees'],
            ['name' => 'edit-employees', 'display_name' => 'Edit Employees', 'description' => 'Can edit employees'],
            ['name' => 'delete-employees', 'display_name' => 'Delete Employees', 'description' => 'Can delete employees'],
            
            // Settings
            ['name' => 'manage-settings', 'display_name' => 'Manage Settings', 'description' => 'Can manage system settings'],
            
            // Reports
            ['name' => 'view-reports', 'display_name' => 'View Reports', 'description' => 'Can view reports'],
            ['name' => 'export-reports', 'display_name' => 'Export Reports', 'description' => 'Can export reports'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission['name']
            ], [
                'display_name' => $permission['display_name'],
                'description' => $permission['description']
            ]);
        }

        // Create roles
        $roles = [
            [
                'name' => 'super-admin',
                'display_name' => 'Super Administrator',
                'description' => 'Has all permissions'
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrator',
                'description' => 'Has most permissions except for critical ones'
            ],
            [
                'name' => 'employer',
                'display_name' => 'Employer',
                'description' => 'Can manage employees and approve loan applications'
            ],
            [
                'name' => 'fsp-admin',
                'display_name' => 'FSP Administrator',
                'description' => 'Can manage loan products and process loan applications'
            ],
            [
                'name' => 'employee',
                'display_name' => 'Employee',
                'description' => 'Can apply for loans and view own applications'
            ]
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role['name']
            ], [
                'display_name' => $role['display_name'],
                'description' => $role['description']
            ]);
        }

        // Assign permissions to roles
        $superAdmin = Role::where('name', 'super-admin')->first();
        $admin = Role::where('name', 'admin')->first();
        $employer = Role::where('name', 'employer')->first();
        $fspAdmin = Role::where('name', 'fsp-admin')->first();
        $employee = Role::where('name', 'employee')->first();

        // Super admin gets all permissions
        $superAdmin->permissions()->sync(Permission::all());

        // Admin gets most permissions
        $adminPermissions = Permission::whereNotIn('name', [
            'delete-users', 'delete-roles', 'manage-settings'
        ])->get();
        $admin->permissions()->sync($adminPermissions);

        // Employer gets employee management and loan approval permissions
        $employerPermissions = Permission::whereIn('name', [
            'view-employees', 'create-employees', 'edit-employees',
            'view-loan-applications', 'approve-loan-applications', 'reject-loan-applications',
            'view-reports'
        ])->get();
        $employer->permissions()->sync($employerPermissions);

        // FSP admin gets loan product and loan application processing permissions
        $fspAdminPermissions = Permission::whereIn('name', [
            'view-loan-products', 'create-loan-products', 'edit-loan-products',
            'view-loan-applications', 'edit-loan-applications',
            'view-reports'
        ])->get();
        $fspAdmin->permissions()->sync($fspAdminPermissions);

        // Employee gets basic permissions for loan applications
        $employeePermissions = Permission::whereIn('name', [
            'view-loan-applications', 'create-loan-applications'
        ])->get();
        $employee->permissions()->sync($employeePermissions);

        // Create a default super admin user if it doesn't exist
        $superAdminUser = User::where('email', 'admin@emkopo.com')->first();
        if (!$superAdminUser) {
            $superAdminUser = User::create([
                'name' => 'Super Admin',
                'email' => 'admin@emkopo.com',
                'password' => bcrypt('password'),
            ]);
            
            // Assign super-admin role
            $superAdminUser->roles()->attach($superAdmin);
        }
    }
} 