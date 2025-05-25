<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Add XML request methods
        Request::macro('isXml', function () {
            $contentType = $this->header('Content-Type');
            return strpos($contentType, 'application/xml') !== false || 
                   strpos($contentType, 'text/xml') !== false;
        });
        
        Request::macro('isJson', function () {
            $contentType = $this->header('Content-Type');
            return strpos($contentType, 'application/json') !== false;
        });
    }
}
