<?php

namespace App\Exceptions;

use Exception;

class WalletNotFoundException extends Exception
{
    /**
     * Create a new wallet not found exception instance.
     */
    public function __construct(string $message = 'Wallet not found', int $code = 404, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        \Log::error('Wallet not found', [
            'message' => $this->getMessage(),
            'user_id' => auth()->id(),
            'timestamp' => now()
        ]);
    }

    /**
     * Render the exception as an HTTP response.
     */
    public function render($request)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $this->getMessage(),
                'error_code' => 'WALLET_NOT_FOUND'
            ], $this->getCode());
        }

        return redirect()->back()->withErrors(['wallet' => $this->getMessage()]);
    }
}
