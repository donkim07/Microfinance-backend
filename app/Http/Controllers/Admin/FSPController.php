<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FinancialServiceProvider;
use App\Models\ProductCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class FSPController extends Controller
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
     * Display a listing of the financial service providers.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $fsps = FinancialServiceProvider::withCount('productCatalogs')->get();
        
        return view('admin.fsps.index', compact('fsps'));
    }

    /**
     * Show the form for creating a new financial service provider.
     *
     * @return \Illuminate\View\View
     */
    public function create()
    {
        return view('admin.fsps.create');
    }

    /**
     * Store a newly created financial service provider in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:financial_service_providers',
            'short_name' => 'required|string|max:50|unique:financial_service_providers',
            'contact_person' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'physical_address' => 'required|string|max:255',
            'postal_address' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'api_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:255',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Handle logo upload
        $logoPath = null;
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('fsps', 'public');
        }

        // Create financial service provider
        $fsp = FinancialServiceProvider::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'short_name' => $request->short_name,
            'contact_person' => $request->contact_person,
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,
            'physical_address' => $request->physical_address,
            'postal_address' => $request->postal_address,
            'website' => $request->website,
            'description' => $request->description,
            'logo' => $logoPath,
            'api_url' => $request->api_url,
            'api_key' => $request->api_key,
            'status' => $request->status,
        ]);

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'FinancialServiceProvider',
            'model_id' => $fsp->id,
            'description' => 'Financial Service Provider created by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $fsp->name,
                'short_name' => $fsp->short_name,
                'contact_person' => $fsp->contact_person,
                'contact_email' => $fsp->contact_email,
                'contact_phone' => $fsp->contact_phone,
                'status' => $fsp->status,
            ]),
        ]);

        return redirect()->route('admin.fsps.index')
            ->with('success', 'Financial Service Provider created successfully.');
    }

    /**
     * Display the specified financial service provider.
     *
     * @param  \App\Models\FinancialServiceProvider  $fsp
     * @return \Illuminate\View\View
     */
    public function show(FinancialServiceProvider $fsp)
    {
        $products = $fsp->productCatalogs()->paginate(10);
        
        return view('admin.fsps.show', compact('fsp', 'products'));
    }

    /**
     * Show the form for editing the specified financial service provider.
     *
     * @param  \App\Models\FinancialServiceProvider  $fsp
     * @return \Illuminate\View\View
     */
    public function edit(FinancialServiceProvider $fsp)
    {
        return view('admin.fsps.edit', compact('fsp'));
    }

    /**
     * Update the specified financial service provider in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\FinancialServiceProvider  $fsp
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, FinancialServiceProvider $fsp)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', Rule::unique('financial_service_providers')->ignore($fsp->id)],
            'short_name' => ['required', 'string', 'max:50', Rule::unique('financial_service_providers')->ignore($fsp->id)],
            'contact_person' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'contact_phone' => 'required|string|max:20',
            'physical_address' => 'required|string|max:255',
            'postal_address' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'api_url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:255',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $fsp->name,
            'short_name' => $fsp->short_name,
            'contact_person' => $fsp->contact_person,
            'contact_email' => $fsp->contact_email,
            'contact_phone' => $fsp->contact_phone,
            'status' => $fsp->status,
        ];

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($fsp->logo) {
                Storage::disk('public')->delete($fsp->logo);
            }
            
            $logoPath = $request->file('logo')->store('fsps', 'public');
            $fsp->logo = $logoPath;
        }

        // Update financial service provider
        $fsp->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'short_name' => $request->short_name,
            'contact_person' => $request->contact_person,
            'contact_email' => $request->contact_email,
            'contact_phone' => $request->contact_phone,
            'physical_address' => $request->physical_address,
            'postal_address' => $request->postal_address,
            'website' => $request->website,
            'description' => $request->description,
            'api_url' => $request->api_url,
            'api_key' => $request->api_key,
            'status' => $request->status,
        ]);

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'UPDATE',
            'model_type' => 'FinancialServiceProvider',
            'model_id' => $fsp->id,
            'description' => 'Financial Service Provider updated by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
            'new_values' => json_encode([
                'name' => $fsp->name,
                'short_name' => $fsp->short_name,
                'contact_person' => $fsp->contact_person,
                'contact_email' => $fsp->contact_email,
                'contact_phone' => $fsp->contact_phone,
                'status' => $fsp->status,
            ]),
        ]);

        return redirect()->route('admin.fsps.index')
            ->with('success', 'Financial Service Provider updated successfully.');
    }

    /**
     * Remove the specified financial service provider from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\FinancialServiceProvider  $fsp
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, FinancialServiceProvider $fsp)
    {
        // Check if FSP has products
        if ($fsp->productCatalogs()->count() > 0) {
            return back()->with('error', 'Financial Service Provider cannot be deleted because it has products associated with it.');
        }

        // Store old values for audit log
        $oldValues = [
            'name' => $fsp->name,
            'short_name' => $fsp->short_name,
            'contact_person' => $fsp->contact_person,
            'contact_email' => $fsp->contact_email,
            'contact_phone' => $fsp->contact_phone,
            'status' => $fsp->status,
        ];

        // Delete logo if exists
        if ($fsp->logo) {
            Storage::disk('public')->delete($fsp->logo);
        }

        // Delete FSP
        $fsp->delete();

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'DELETE',
            'model_type' => 'FinancialServiceProvider',
            'model_id' => $fsp->id,
            'description' => 'Financial Service Provider deleted by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => json_encode($oldValues),
        ]);

        return redirect()->route('admin.fsps.index')
            ->with('success', 'Financial Service Provider deleted successfully.');
    }

    /**
     * Display the products for the specified financial service provider.
     *
     * @param  \App\Models\FinancialServiceProvider  $fsp
     * @return \Illuminate\View\View
     */
    public function products(FinancialServiceProvider $fsp)
    {
        $products = $fsp->productCatalogs()->paginate(10);
        
        return view('admin.fsps.products', compact('fsp', 'products'));
    }

    /**
     * Show the form for creating a new product for the specified financial service provider.
     *
     * @param  \App\Models\FinancialServiceProvider  $fsp
     * @return \Illuminate\View\View
     */
    public function createProduct(FinancialServiceProvider $fsp)
    {
        return view('admin.fsps.create-product', compact('fsp'));
    }

    /**
     * Store a newly created product for the specified financial service provider in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\FinancialServiceProvider  $fsp
     * @return \Illuminate\Http\RedirectResponse
     */
    public function storeProduct(Request $request, FinancialServiceProvider $fsp)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'description' => 'nullable|string',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'interest_type' => 'required|in:FIXED,REDUCING_BALANCE',
            'min_amount' => 'required|numeric|min:0',
            'max_amount' => 'required|numeric|gt:min_amount',
            'min_term' => 'required|integer|min:1',
            'max_term' => 'required|integer|gt:min_term',
            'term_period' => 'required|in:DAY,WEEK,MONTH,YEAR',
            'repayment_frequency' => 'required|in:DAILY,WEEKLY,MONTHLY,QUARTERLY,ANNUALLY',
            'processing_fee' => 'nullable|numeric|min:0',
            'processing_fee_type' => 'required_with:processing_fee|in:FIXED,PERCENTAGE',
            'insurance_fee' => 'nullable|numeric|min:0',
            'insurance_fee_type' => 'required_with:insurance_fee|in:FIXED,PERCENTAGE',
            'grace_period' => 'nullable|integer|min:0',
            'grace_period_type' => 'required_with:grace_period|in:DAY,WEEK,MONTH',
            'early_repayment_fee' => 'nullable|numeric|min:0',
            'early_repayment_fee_type' => 'required_with:early_repayment_fee|in:FIXED,PERCENTAGE',
            'late_payment_fee' => 'nullable|numeric|min:0',
            'late_payment_fee_type' => 'required_with:late_payment_fee|in:FIXED,PERCENTAGE',
            'status' => 'required|in:ACTIVE,INACTIVE',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Create product
        $product = $fsp->productCatalogs()->create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'interest_rate' => $request->interest_rate,
            'interest_type' => $request->interest_type,
            'min_amount' => $request->min_amount,
            'max_amount' => $request->max_amount,
            'min_term' => $request->min_term,
            'max_term' => $request->max_term,
            'term_period' => $request->term_period,
            'repayment_frequency' => $request->repayment_frequency,
            'processing_fee' => $request->processing_fee,
            'processing_fee_type' => $request->processing_fee_type,
            'insurance_fee' => $request->insurance_fee,
            'insurance_fee_type' => $request->insurance_fee_type,
            'grace_period' => $request->grace_period,
            'grace_period_type' => $request->grace_period_type,
            'early_repayment_fee' => $request->early_repayment_fee,
            'early_repayment_fee_type' => $request->early_repayment_fee_type,
            'late_payment_fee' => $request->late_payment_fee,
            'late_payment_fee_type' => $request->late_payment_fee_type,
            'status' => $request->status,
        ]);

        // Log action
        AuditLog::create([
            'user_id' => Auth::id(),
            'action' => 'CREATE',
            'model_type' => 'ProductCatalog',
            'model_id' => $product->id,
            'description' => 'Product created by admin',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'new_values' => json_encode([
                'name' => $product->name,
                'code' => $product->code,
                'interest_rate' => $product->interest_rate,
                'interest_type' => $product->interest_type,
                'min_amount' => $product->min_amount,
                'max_amount' => $product->max_amount,
                'status' => $product->status,
            ]),
        ]);

        return redirect()->route('admin.fsps.products', $fsp)
            ->with('success', 'Product created successfully.');
    }
}
