<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FinancialServiceProvider;
use App\Models\ProductCatalog;
use App\Models\TermsCondition;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ProductCatalogController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the product catalogs.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $products = ProductCatalog::with('financialServiceProvider')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return view('products.index', compact('products'));
    }

    /**
     * Display the specified product catalog.
     *
     * @param  \App\Models\ProductCatalog  $product
     * @return \Illuminate\View\View
     */
    public function show(ProductCatalog $product)
    {
        $product->load('financialServiceProvider', 'termsConditions');
        
        return view('products.show', compact('product'));
    }

    /**
     * Compare multiple product catalogs.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function compare(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:2|max:4',
            'products.*' => 'exists:product_catalogs,id',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $products = ProductCatalog::with('financialServiceProvider', 'termsConditions')
            ->whereIn('id', $request->products)
            ->get();
        
        return view('products.compare', compact('products'));
    }

    /**
     * Calculate loan details for a specific product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\ProductCatalog  $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateLoan(Request $request, ProductCatalog $product)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:' . $product->min_amount . '|max:' . $product->max_amount,
            'term' => 'required|integer|min:' . $product->min_term . '|max:' . $product->max_term,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $amount = $request->amount;
        $term = $request->term;
        $interestRate = $product->interest_rate;
        $interestType = $product->interest_type;
        
        // Calculate processing fee
        $processingFee = 0;
        if ($product->processing_fee) {
            if ($product->processing_fee_type === 'FIXED') {
                $processingFee = $product->processing_fee;
            } else {
                $processingFee = ($product->processing_fee / 100) * $amount;
            }
        }
        
        // Calculate insurance fee
        $insuranceFee = 0;
        if ($product->insurance_fee) {
            if ($product->insurance_fee_type === 'FIXED') {
                $insuranceFee = $product->insurance_fee;
            } else {
                $insuranceFee = ($product->insurance_fee / 100) * $amount;
            }
        }
        
        // Calculate total fees
        $totalFees = $processingFee + $insuranceFee;
        
        // Calculate interest
        $interest = 0;
        if ($interestType === 'FIXED') {
            $interest = ($interestRate / 100) * $amount * $term;
            if ($product->term_period === 'YEAR') {
                $interest *= 12; // Convert annual rate to monthly
            }
        } else { // REDUCING_BALANCE
            // This is a simple implementation - more complex calculations can be added
            $monthlyRate = $interestRate / 100;
            if ($product->term_period === 'YEAR') {
                $monthlyRate /= 12;
                $term *= 12;
            }
            
            $principal = $amount;
            $interest = 0;
            
            for ($i = 0; $i < $term; $i++) {
                $monthlyInterest = $principal * $monthlyRate;
                $interest += $monthlyInterest;
                
                // Calculate principal payment for this period
                $monthlyPrincipal = $amount / $term;
                $principal -= $monthlyPrincipal;
            }
        }
        
        // Calculate total amount
        $totalAmount = $amount + $interest + $totalFees;
        
        // Calculate monthly payment
        $monthlyPayment = $totalAmount / $term;
        if ($product->term_period === 'YEAR') {
            $monthlyPayment = $totalAmount / ($term * 12);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'principal_amount' => $amount,
                'term' => $term,
                'term_period' => $product->term_period,
                'interest_rate' => $interestRate,
                'interest_type' => $interestType,
                'interest_amount' => round($interest, 2),
                'processing_fee' => round($processingFee, 2),
                'insurance_fee' => round($insuranceFee, 2),
                'total_fees' => round($totalFees, 2),
                'total_amount' => round($totalAmount, 2),
                'monthly_payment' => round($monthlyPayment, 2),
                'repayment_frequency' => $product->repayment_frequency,
            ],
        ]);
    }

    /**
     * Display the terms and conditions for a specific product.
     *
     * @param  \App\Models\ProductCatalog  $product
     * @return \Illuminate\View\View
     */
    public function terms(ProductCatalog $product)
    {
        $product->load('termsConditions');
        
        return view('products.terms', compact('product'));
    }

    /**
     * Search for products based on criteria.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function search(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'nullable|numeric|min:0',
            'term' => 'nullable|integer|min:1',
            'term_period' => 'nullable|in:DAY,WEEK,MONTH,YEAR',
            'fsp_id' => 'nullable|exists:financial_service_providers,id',
            'interest_type' => 'nullable|in:FIXED,REDUCING_BALANCE',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $query = ProductCatalog::with('financialServiceProvider')
            ->where('status', 'ACTIVE');
        
        if ($request->filled('amount')) {
            $query->where('min_amount', '<=', $request->amount)
                ->where('max_amount', '>=', $request->amount);
        }
        
        if ($request->filled('term')) {
            $query->where('min_term', '<=', $request->term)
                ->where('max_term', '>=', $request->term);
            
            if ($request->filled('term_period')) {
                $query->where('term_period', $request->term_period);
            }
        }
        
        if ($request->filled('fsp_id')) {
            $query->where('financial_service_provider_id', $request->fsp_id);
        }
        
        if ($request->filled('interest_type')) {
            $query->where('interest_type', $request->interest_type);
        }
        
        $products = $query->orderBy('interest_rate', 'asc')
            ->paginate(10)
            ->appends($request->all());
        
        $fsps = FinancialServiceProvider::where('status', 'ACTIVE')->get();
        
        return view('products.search', compact('products', 'fsps'));
    }
}
