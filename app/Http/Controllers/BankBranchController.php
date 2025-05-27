<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Bank;
use App\Models\BankBranch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class BankBranchController extends Controller
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
     * Display a listing of the bank branches.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $branches = BankBranch::with('bank')
            ->orderBy('name')
            ->paginate(15);
        
        return view('bank-branches.index', compact('branches'));
    }

    /**
     * Show the form for creating a new bank branch.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        $banks = Bank::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        return view('bank-branches.create', compact('banks'));
    }

    /**
     * Store a newly created bank branch in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:bank_branches',
            'bank_id' => 'required|exists:banks,id',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'manager_name' => 'nullable|string|max:255',
            'manager_phone' => 'nullable|string|max:20',
            'manager_email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Create bank branch
        $branch = BankBranch::create([
            'name' => $request->name,
            'code' => $request->code,
            'bank_id' => $request->bank_id,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'email' => $request->email,
            'phone' => $request->phone,
            'manager_name' => $request->manager_name,
            'manager_phone' => $request->manager_phone,
            'manager_email' => $request->manager_email,
            'description' => $request->description,
            'status' => 'ACTIVE',
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'BankBranch',
            'model_id' => $branch->id,
            'description' => 'Bank branch created: ' . $branch->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $branch->name,
                'code' => $branch->code,
                'bank_id' => $branch->bank_id,
                'city' => $branch->city,
                'status' => $branch->status,
            ]),
        ]);
        
        return redirect()->route('bank-branches.index')
            ->with('success', 'Bank branch created successfully.');
    }

    /**
     * Display the specified bank branch.
     *
     * @param  \App\Models\BankBranch  $branch
     * @return \Illuminate\View\View
     */
    public function show(BankBranch $branch)
    {
        $branch->load('bank');
        
        return view('bank-branches.show', compact('branch'));
    }

    /**
     * Show the form for editing the specified bank branch.
     *
     * @param  \App\Models\BankBranch  $branch
     * @return \Illuminate\View\View
     */
    public function edit(BankBranch $branch)
    {
        $banks = Bank::where('status', 'ACTIVE')
            ->orderBy('name')
            ->get();
        
        return view('bank-branches.edit', compact('branch', 'banks'));
    }

    /**
     * Update the specified bank branch in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\BankBranch  $branch
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, BankBranch $branch)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:bank_branches,code,' . $branch->id,
            'bank_id' => 'required|exists:banks,id',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'manager_name' => 'nullable|string|max:255',
            'manager_phone' => 'nullable|string|max:20',
            'manager_email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $branch->name,
            'code' => $branch->code,
            'bank_id' => $branch->bank_id,
            'city' => $branch->city,
            'status' => $branch->status,
        ];

        // Update bank branch
        $branch->update([
            'name' => $request->name,
            'code' => $request->code,
            'bank_id' => $request->bank_id,
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'postal_code' => $request->postal_code,
            'email' => $request->email,
            'phone' => $request->phone,
            'manager_name' => $request->manager_name,
            'manager_phone' => $request->manager_phone,
            'manager_email' => $request->manager_email,
            'description' => $request->description,
            'status' => $request->status,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'BankBranch',
            'model_id' => $branch->id,
            'description' => 'Bank branch updated: ' . $branch->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'name' => $branch->name,
                'code' => $branch->code,
                'bank_id' => $branch->bank_id,
                'city' => $branch->city,
                'status' => $branch->status,
            ]),
        ]);
        
        return redirect()->route('bank-branches.index')
            ->with('success', 'Bank branch updated successfully.');
    }

    /**
     * Remove the specified bank branch from storage (soft delete).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\BankBranch  $branch
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, BankBranch $branch)
    {
        // Store values for audit log
        $branchInfo = [
            'id' => $branch->id,
            'name' => $branch->name,
            'code' => $branch->code,
            'bank_id' => $branch->bank_id,
        ];
        
        // Update branch status
        $branch->update([
            'status' => 'INACTIVE',
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE',
            'model_type' => 'BankBranch',
            'model_id' => $branch->id,
            'description' => 'Bank branch deleted (set to inactive): ' . $branch->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($branchInfo),
        ]);
        
        return redirect()->route('bank-branches.index')
            ->with('success', 'Bank branch deactivated successfully.');
    }

    /**
     * Get branches for a specific bank (used for AJAX requests).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBranchesByBank(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bank_id' => 'required|exists:banks,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => 'Invalid bank ID.'], 400);
        }

        $branches = BankBranch::where('bank_id', $request->bank_id)
            ->where('status', 'ACTIVE')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'city']);
        
        return response()->json(['branches' => $branches]);
    }
}
