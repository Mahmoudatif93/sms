<?php

namespace App\Traits;

use Exception;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\File;

trait RSAKeyGenerator
{
    /**
     * @throws FileNotFoundException
     */
    public function generateRsaKeyPair($phoneNumberId): array
    {


        // Set storage paths based on phone number ID
        $privateKeyPath = storage_path("app/keys/{$phoneNumberId}_private.pem");
        $publicKeyPath = storage_path("app/keys/{$phoneNumberId}_public.pem");

        // Check if the key files already exist
        if (File::exists($privateKeyPath) && File::exists($publicKeyPath)) {
            // Return paths if keys already exist
            return [
                'private_key' => File::get($privateKeyPath),
                'public_key' => File::get($publicKeyPath),
            ];
        }


        // Get the app key from the environment configuration
        $appKey = env('APP_KEY');

        // Configuration for the private key
        $config = [
            "private_key_bits" => 2048,
            "private_key_type" => OPENSSL_KEYTYPE_RSA,
        ];

        // Generate a new private key
        $privateKey = openssl_pkey_new($config);
        if (!$privateKey) {
            throw new Exception('Unable to generate private key. ' . openssl_error_string());
        }

        // Extract the private key to a variable, encrypting it with the app key
        openssl_pkey_export($privateKey, $privateKeyOut, $appKey);

        // Extract the public key from the private key
        $publicKey = openssl_pkey_get_details($privateKey)['key'];

        // Set storage paths based on phone number ID
        $privateKeyPath = storage_path("app/keys/{$phoneNumberId}_private.pem");
        $publicKeyPath = storage_path("app/keys/{$phoneNumberId}_public.pem");

        // Ensure the directory exists
        File::ensureDirectoryExists(storage_path('app/keys'));

        // Save the private key to a file
        File::put($privateKeyPath, $privateKeyOut);

        // Save the public key to a file
        File::put($publicKeyPath, $publicKey);

        // Note: No need to call openssl_free_key as OpenSSL functions manage memory automatically

        return [
            'private_key' => $privateKeyOut,
            'public_key_path' => $publicKey,
        ];
    }
}
