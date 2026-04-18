<?php

namespace App\Exceptions;

use Exception;

class UserInactiveException extends Exception
{
    /**
     * Create a new user inactive exception instance.
     */
    public function __construct(string $message = null, int $code = 403, Exception $previous = null)
    {
        $message = $message ?? trans('message.msg_error_user_inactive');
        parent::__construct($message, $code, $previous);
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        \Log::warning('Inactive user access attempt', [
            'message' => $this->getMessage(),
            'user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
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
                'error_code' => 'USER_INACTIVE',
                'action_required' => 'contact_admin'
            ], $this->getCode());
        }

        return redirect()->route('login')->withErrors([
            'account' => $this->getMessage()
        ]);
    }

    /**
     * Get user-friendly message for different languages
     */
    public function getUserMessage(): string
    {
        return trans('message.msg_error_user_inactive');
    }
}
