<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Symfony\Component\HttpFoundation\Response;

class LanguageMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated and has a preferred language
        if (auth()->check() && auth()->user()->preferred_language) {
            App::setLocale(auth()->user()->preferred_language);
        } 
        // Check if language is set in session
        else if (Session::has('locale')) {
            App::setLocale(Session::get('locale'));
        }
        // Check if language is requested in URL
        else if ($request->has('lang')) {
            $locale = $request->get('lang');
            // Check if the language is supported
            if (in_array($locale, ['en', 'sw'])) {
                Session::put('locale', $locale);
                App::setLocale($locale);
                
                // If user is authenticated, update their preference
                if (auth()->check()) {
                    auth()->user()->update(['preferred_language' => $locale]);
                }
            }
        }
        // Default to English
        else {
            App::setLocale('en');
        }

        return $next($request);
    }
}
