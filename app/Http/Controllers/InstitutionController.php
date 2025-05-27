<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Institution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InstitutionController extends Controller
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
     * Display a listing of the institutions.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $institutions = Institution::orderBy('name')
            ->paginate(15);
        
        return view('institutions.index', compact('institutions'));
    }

    /**
     * Show the form for creating a new institution.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('institutions.create');
    }

    /**
     * Store a newly created institution in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:institutions',
            'code' => 'required|string|max:50|unique:institutions',
            'address' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle logo upload
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('institution-logos', 'public');
        }
        
        // Handle document upload
        $documentPath = null;
        if ($request->hasFile('document')) {
            $documentPath = $request->file('document')->store('institution-documents', 'public');
        }

        // Create institution
        $institution = Institution::create([
            'name' => $request->name,
            'code' => $request->code,
            'address' => $request->address,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'contact_person' => $request->contact_person,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'description' => $request->description,
            'logo' => $logoPath,
            'document' => $documentPath,
            'status' => 'ACTIVE',
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'Institution',
            'model_id' => $institution->id,
            'description' => 'Institution created: ' . $institution->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $institution->name,
                'code' => $institution->code,
                'email' => $institution->email,
                'phone' => $institution->phone,
                'status' => $institution->status,
            ]),
        ]);
        
        return redirect()->route('institutions.index')
            ->with('success', 'Institution created successfully.');
    }

    /**
     * Display the specified institution.
     *
     * @param  \App\Models\Institution  $institution
     * @return \Illuminate\View\View
     */
    public function show(Institution $institution)
    {
        // Get departments for this institution
        $departments = $institution->departments()
            ->orderBy('name')
            ->get();
        
        // Get employee count for this institution
        $employeeCount = $institution->employees()->count();
        
        // Get loan statistics for this institution
        $loanStats = [
            'total_loans' => $institution->employees()
                ->withCount('loans')
                ->get()
                ->sum('loans_count'),
            'active_loans' => $institution->employees()
                ->whereHas('loans', function($query) {
                    $query->whereIn('status', ['ACTIVE', 'DISBURSED']);
                })
                ->count(),
            'total_amount' => $institution->employees()
                ->withSum('loans', 'principal_amount')
                ->get()
                ->sum('loans_sum_principal_amount'),
            'outstanding_amount' => $institution->employees()
                ->whereHas('loans', function($query) {
                    $query->whereIn('status', ['ACTIVE', 'DISBURSED']);
                })
                ->withSum('loans', 'outstanding_amount')
                ->get()
                ->sum('loans_sum_outstanding_amount'),
        ];
        
        return view('institutions.show', compact('institution', 'departments', 'employeeCount', 'loanStats'));
    }

    /**
     * Show the form for editing the specified institution.
     *
     * @param  \App\Models\Institution  $institution
     * @return \Illuminate\View\View
     */
    public function edit(Institution $institution)
    {
        return view('institutions.edit', compact('institution'));
    }

    /**
     * Update the specified institution in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Institution  $institution
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Institution $institution)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:institutions,name,' . $institution->id,
            'code' => 'required|string|max:50|unique:institutions,code,' . $institution->id,
            'address' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'website' => 'nullable|url|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:20',
            'contact_email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:2048',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $institution->name,
            'code' => $institution->code,
            'email' => $institution->email,
            'phone' => $institution->phone,
            'status' => $institution->status,
        ];

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($institution->logo) {
                Storage::disk('public')->delete($institution->logo);
            }
            
            $logoPath = $request->file('logo')->store('institution-logos', 'public');
            $institution->logo = $logoPath;
        }
        
        // Handle document upload
        if ($request->hasFile('document')) {
            // Delete old document if exists
            if ($institution->document) {
                Storage::disk('public')->delete($institution->document);
            }
            
            $documentPath = $request->file('document')->store('institution-documents', 'public');
            $institution->document = $documentPath;
        }

        // Update institution
        $institution->update([
            'name' => $request->name,
            'code' => $request->code,
            'address' => $request->address,
            'email' => $request->email,
            'phone' => $request->phone,
            'website' => $request->website,
            'contact_person' => $request->contact_person,
            'contact_phone' => $request->contact_phone,
            'contact_email' => $request->contact_email,
            'description' => $request->description,
            'status' => $request->status,
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'Institution',
            'model_id' => $institution->id,
            'description' => 'Institution updated: ' . $institution->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'name' => $institution->name,
                'code' => $institution->code,
                'email' => $institution->email,
                'phone' => $institution->phone,
                'status' => $institution->status,
            ]),
        ]);
        
        return redirect()->route('institutions.index')
            ->with('success', 'Institution updated successfully.');
    }

    /**
     * Remove the specified institution from storage (soft delete).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Institution  $institution
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, Institution $institution)
    {
        // Check if institution has departments
        $hasDepartments = $institution->departments()->exists();
        
        if ($hasDepartments) {
            return back()->with('error', 'Cannot delete institution with departments. Please delete the departments first.');
        }
        
        // Check if institution has employees
        $hasEmployees = $institution->employees()->exists();
        
        if ($hasEmployees) {
            return back()->with('error', 'Cannot delete institution with employees. Please reassign or delete the employees first.');
        }
        
        // Store values for audit log
        $institutionInfo = [
            'id' => $institution->id,
            'name' => $institution->name,
            'code' => $institution->code,
        ];
        
        // Update institution status
        $institution->update([
            'status' => 'INACTIVE',
        ]);
        
        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE',
            'model_type' => 'Institution',
            'model_id' => $institution->id,
            'description' => 'Institution deleted (set to inactive): ' . $institution->name,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($institutionInfo),
        ]);
        
        return redirect()->route('institutions.index')
            ->with('success', 'Institution deactivated successfully.');
    }

    /**
     * Download the institution document.
     *
     * @param  \App\Models\Institution  $institution
     * @return \Illuminate\Http\Response
     */
    public function downloadDocument(Institution $institution)
    {
        if (!$institution->document) {
            return back()->with('error', 'No document available for this institution.');
        }
        
        return Storage::disk('public')->download(
            $institution->document,
            'Institution_Document_' . $institution->code . '.' . pathinfo($institution->document, PATHINFO_EXTENSION)
        );
    }
}
