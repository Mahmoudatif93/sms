<?php

namespace App\Services\Workflow;

use App\Enums\Workflow\ActionType;
use App\Services\Workflow\Actions\SendInteractiveAction;
use App\Services\Workflow\Actions\SendTemplateAction;
use App\Services\Workflow\Actions\SendTextAction;
use App\Services\Workflow\Contracts\WorkflowActionInterface;
use InvalidArgumentException;

/**
 * Factory for creating workflow action instances.
 *
 * This factory allows for easy extensibility by registering new action types.
 */
class WorkflowActionFactory
{
    /**
     * Map of action types to their handler classes.
     *
     * @var array<string, class-string<WorkflowActionInterface>>
     */
    protected static array $actionHandlers = [
        'send_template' => SendTemplateAction::class,
        'send_interactive' => SendInteractiveAction::class,
        // Add more action handlers here as they are implemented
        'send_text' => SendTextAction::class,
        // 'add_to_list' => AddToListAction::class,
    ];

    /**
     * Create an action handler for the given action type.
     */
    public static function create(ActionType|string $actionType): WorkflowActionInterface
    {
        $typeValue = $actionType instanceof ActionType ? $actionType->value : $actionType;

        if (!isset(static::$actionHandlers[$typeValue])) {
            throw new InvalidArgumentException("Unknown action type: {$typeValue}");
        }

        $handlerClass = static::$actionHandlers[$typeValue];
        return new $handlerClass();
    }

    /**
     * Check if an action type is supported.
     */
    public static function supports(ActionType|string $actionType): bool
    {
        $typeValue = $actionType instanceof ActionType ? $actionType->value : $actionType;
        return isset(static::$actionHandlers[$typeValue]);
    }

    /**
     * Register a new action handler.
     *
     * @param class-string<WorkflowActionInterface> $handlerClass
     */
    public static function register(ActionType|string $actionType, string $handlerClass): void
    {
        $typeValue = $actionType instanceof ActionType ? $actionType->value : $actionType;
        static::$actionHandlers[$typeValue] = $handlerClass;
    }

    /**
     * Get all registered action types.
     *
     * @return array<string>
     */
    public static function getRegisteredTypes(): array
    {
        return array_keys(static::$actionHandlers);
    }
}

