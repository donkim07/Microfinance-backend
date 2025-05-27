<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Bank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BankController extends Controller
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
     * Display a listing of the banks.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $banks = Bank::orderBy('name')
            ->paginate(15);
        
        return view('banks.index', compact('banks'));
    }

    /**
     * Show the form for creating a new bank.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('banks.create');
    }

    /**
     * Store a newly created bank in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:banks',
            'code' => 'required|string|max:50|unique:banks',
            'address' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'swift_code' => 'nullable|string|max:11',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle logo upload
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('bank-logos', 'public');
        }

        // Create bank
        $bank = Bank::create([
            'name' => $request->name,
            'code' => $request->code,
            'address' => $request->address,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'contact_person' => $request->contact_person,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'swift_code' => $request->swift_code,
            'description' => $request->description,
            'logo' => $logoPath,
            'status' => 'ACTIVE',
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'Bank',
            'model_id' => $bank->id,
            'description' => 'Bank created: ' . $bank->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $bank->name,
                'code' => $bank->code,
                'email' => $bank->email,
                'phone' => $bank->phone,
                'status' => $bank->status,
            ]),
        ]);
        
        return redirect()->route('banks.index')
            ->with('success', 'Bank created successfully.');
    }

    /**
     * Display the specified bank.
     *
     * @param  \App\Models\Bank  $bank
     * @return \Illuminate\View\View
     */
    public function show(Bank $bank)
    {
        // Get branches for this bank
        $branches = $bank->branches()
            ->orderBy('name')
            ->paginate(10);
        
        return view('banks.show', compact('bank', 'branches'));
    }

    /**
     * Show the form for editing the specified bank.
     *
     * @param  \App\Models\Bank  $bank
     * @return \Illuminate\View\View
     */
    public function edit(Bank $bank)
    {
        return view('banks.edit', compact('bank'));
    }

    /**
     * Update the specified bank in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Bank  $bank
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Bank $bank)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:banks,name,' . $bank->id,
            'code' => 'required|string|max:50|unique:banks,code,' . $bank->id,
            'address' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'swift_code' => 'nullable|string|max:11',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $bank->name,
            'code' => $bank->code,
            'email' => $bank->email,
            'phone' => $bank->phone,
            'status' => $bank->status,
        ];

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($bank->logo) {
                Storage::disk('public')->delete($bank->logo);
            }
            
            $logoPath = $request->file('logo')->store('bank-logos', 'public');
            $bank->logo = $logoPath;
        }

        // Update bank
        $bank->update([
            'name' => $request->name,
            'code' => $request->code,
            'address' => $request->address,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'contact_person' => $request->contact_person,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'swift_code' => $request->swift_code,
            'description' => $request->description,
            'status' => $request->status,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'Bank',
            'model_id' => $bank->id,
            'description' => 'Bank updated: ' . $bank->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'name' => $bank->name,
                'code' => $bank->code,
                'email' => $bank->email,
                'phone' => $bank->phone,
                'status' => $bank->status,
            ]),
        ]);
        
        return redirect()->route('banks.index')
            ->with('success', 'Bank updated successfully.');
    }

    /**
     * Remove the specified bank from storage (soft delete).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Bank  $bank
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Bank $bank)
    {
        // Check if bank has branches
        $hasBranches = $bank->branches()->exists();
        
        if ($hasBranches) {
            return back()->with('error', 'Cannot delete bank with branches. Please delete the branches first.');
        }
        
        // Store values for audit log
        $bankInfo = [
            'id' => $bank->id,
            'name' => $bank->name,
            'code' => $bank->code,
        ];
        
        // Update bank status
        $bank->update([
            'status' => 'INACTIVE',
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE',
            'model_type' => 'Bank',
            'model_id' => $bank->id,
            'description' => 'Bank deleted (set to inactive): ' . $bank->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($bankInfo),
        ]);
        
        return redirect()->route('banks.index')
            ->with('success', 'Bank deactivated successfully.');
    }
}
