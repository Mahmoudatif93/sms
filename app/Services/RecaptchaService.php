<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use App\Models\Setting;

class RecaptchaService
{
    private $siteKey;
    private $secretKey;
    private $language;

    const SIGN_UP_URL = 'https://www.google.com/recaptcha/admin';
    const SITE_VERIFY_URL = 'https://www.google.com/recaptcha/api/siteverify';
    const API_URL = 'https://www.google.com/recaptcha/api.js';
    public function __construct(array $params)
    {
        $this->siteKey = env('RECAPTCHA_SITE_KEY');  //'6Lc-nVUqAAAAAA5XFZaEQq6-_LRBlagQRfUvlKUa'Setting::get_by_name('recaptcha_site_key') ;
        $this->secretKey =  env('RECAPTCHA_SECRET_KEY'); // '6Lc-nVUqAAAAAJ5FuFTk1XT1rxg5YNVuvJwZbo93'; //Setting::get_by_name('recaptcha_secret_key')
        $this->language =  Setting::get_by_name('default_lang') ?? 'en';
//dd(env('RECAPTCHA_SECRET_KEY'));
        if (empty($this->siteKey) || empty($this->secretKey)) {
            throw new \Exception("To use reCAPTCHA, you must get an API key from <a href='" . self::SIGN_UP_URL . "'>" . self::SIGN_UP_URL . "</a>");
        }
    }

    public function verifyResponse($recaptcha_token, $remoteIp = null)
    {
        $remoteIp = $remoteIp ?: request()->ip();
        if (empty($recaptcha_token)) {
            return [
                'success' => false,
                'error-codes' => 'missing-input',
            ];
        }
        // Google's reCAPTCHA API URL
        $recaptchaApiUrl = 'https://www.google.com/recaptcha/api/siteverify';
        // Secret key (server-side key from Google reCAPTCHA)
        $secretKey =  $this->secretKey; // Set this in .env file
        // Make a POST request to Google's reCAPTCHA API to verify the token
        $responses = Http::asForm()->post($recaptchaApiUrl, [
            'secret' => $secretKey,
            'response' => $recaptcha_token,
            'remoteip' => $remoteIp, // Optional: include the user's IP
        ]);

        $result = $responses->json();
        if (isset($result['success']) && $result['success'] == true) {
            return   $result;
        }
        return [
            'success' => false,
            'error-codes' => $result['error-codes'] ?? 'invalid-input-response',
        ];
    }

    private function submitHttpGet(array $data)
    {
        $response = Http::get(self::SITE_VERIFY_URL, $data);

        return $response->body();
    }

    public function getScriptTag(array $parameters = [])
    {
        $default = [
            'render' => 'onload',
            'hl' => $this->language,
        ];

        $params = array_merge($default, $parameters);

        return sprintf('<script src="%s?%s" async defer></script>', self::API_URL, http_build_query($params));
    }

    public function getWidget(array $parameters = [])
    {
        $default = [
            'data-sitekey' => $this->siteKey,
            'data-theme' => 'light',
            'data-type' => 'image',
            'data-size' => 'normal',
        ];

        $params = array_merge($default, $parameters);

        $attributes = '';
        foreach ($params as $key => $value) {
            $attributes .= sprintf('%s="%s" ', $key, $value);
        }
        //6Lc1bFUqAAAAAJtD0ZtD9aS6mPRsHgUd6gq_zRFl
        return    $attributes;
        return '<div class="g-recaptcha" ' . $attributes . '></div>';
    }
}
