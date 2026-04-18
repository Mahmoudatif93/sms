<?php

namespace App\Services\Workflow\Contracts;

use App\Enums\Workflow\ActionType;
use App\Models\WhatsappMessage;
use App\Models\WhatsappWorkflowAction;

/**
 * Interface for workflow actions.
 *
 * All workflow actions must implement this interface to ensure
 * consistent behavior and easy extensibility.
 */
interface WorkflowActionInterface
{
    /**
     * Execute the action.
     *
     * @param WhatsappWorkflowAction $action The action configuration
     * @param WhatsappMessage $triggerMessage The message that triggered the workflow
     * @return array The result of the action execution
     * @throws \Exception If the action fails
     */
    public function execute(WhatsappWorkflowAction $action, WhatsappMessage $triggerMessage): array;

    /**
     * Get the action type enum.
     */
    public static function getType(): ActionType;

    /**
     * Validate the action configuration.
     *
     * @param array $config The action configuration
     * @return bool
     */
    public function validateConfig(array $config): bool;
}

