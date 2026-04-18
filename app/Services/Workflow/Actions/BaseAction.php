<?php

namespace App\Services\Workflow\Actions;

use App\Models\WhatsappMessage;
use App\Models\WhatsappWorkflowAction;
use App\Services\Workflow\Contracts\WorkflowActionInterface;
use Illuminate\Support\Facades\Log;

/**
 * Base class for workflow actions.
 *
 * Provides common functionality for all workflow actions.
 */
abstract class BaseAction implements WorkflowActionInterface
{
    /**
     * Log the action execution.
     */
    protected function log(string $message, array $context = []): void
    {
        Log::info("[Workflow Action] {$message}", $context);
    }

    /**
     * Log an error during action execution.
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error("[Workflow Action Error] {$message}", $context);
    }

    /**
     * Get a configuration value from the action.
     */
    protected function getConfig(WhatsappWorkflowAction $action, string $key, $default = null): mixed
    {
        return $action->getConfigValue($key, $default);
    }

    /**
     * Build a success result.
     */
    protected function success(string $message, array $data = []): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Build a failure result.
     */
    protected function failure(string $message, array $data = []): array
    {
        return [
            'success' => false,
            'message' => $message,
            'data' => $data,
        ];
    }
}

