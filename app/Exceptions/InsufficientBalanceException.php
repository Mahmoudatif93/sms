<?php

namespace App\Exceptions;

use Exception;

class InsufficientBalanceException extends Exception
{
    /**
     * Create a new insufficient balance exception instance.
     */
    public function __construct(string $message = 'Insufficient balance', int $code = 403, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        \Log::warning('Insufficient balance attempt', [
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
                'error_code' => 'INSUFFICIENT_BALANCE'
            ], $this->getCode());
        }

        return redirect()->back()->withErrors(['balance' => $this->getMessage()]);
    }
}
