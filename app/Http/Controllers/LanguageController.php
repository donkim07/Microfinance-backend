<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Config;

class LanguageController extends Controller
{
    /**
     * Switch the application language
     *
     * @param Request $request
     * @param string $locale
     * @return \Illuminate\Http\RedirectResponse
     */
    public function switchLanguage(Request $request, $locale)
    {
        // Check if the locale is valid
        if (!in_array($locale, Config::get('app.available_locales'))) {
            return redirect()->back();
        }
        
        // Set the locale
        App::setLocale($locale);
        Session::put('locale', $locale);
        
        return redirect()->back();
    }
} 