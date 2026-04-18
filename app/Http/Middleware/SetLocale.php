<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App;

class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
       // Get the Accept-Language header
       $acceptLanguage = $request->header('Accept-Language');
       // Parse the first preferred language
       if ($acceptLanguage) {
           // Split by comma and get the first part (highest priority language)
           $languages = explode(',', $acceptLanguage);
           $primaryLang = $languages[0];
           
           // Clean up the language code (remove quality value if present)
           $primaryLang = trim(explode(';', $primaryLang)[0]);

           // Map language aliases (sa = Saudi Arabia, treat as Arabic)
           $languageMap = [
               'sa' => 'ar',
               'ar-SA' => 'ar',
               'ar-sa' => 'ar',
               'en' => 'en'
           ];

           // Apply mapping if exists
           if (isset($languageMap[$primaryLang])) {
               $primaryLang = $languageMap[$primaryLang];
           }

           // Check if it's one of our supported languages
           if (in_array($primaryLang, ['en', 'ar'])) {
               App::setLocale($primaryLang);
           } else {
               // Use default locale
               App::setLocale(config('app.locale'));
           }
       }
       
       return $next($request);
    }
}
