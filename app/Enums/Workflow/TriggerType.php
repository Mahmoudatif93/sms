<?php

namespace App\Enums\Workflow;

/**
 * Enum representing the types of triggers that can initiate a workflow.
 */
enum TriggerType: string
{
    /**
     * Triggered when a template message reaches a specific status (sent, delivered, read).
     */
    case TEMPLATE_STATUS = 'template_status';

    /**
     * Triggered when a user clicks a button in an interactive message.
     */
    case BUTTON_REPLY = 'button_reply';

    /**
     * Triggered when a user selects an item from a list in an interactive message.
     */
    case LIST_REPLY = 'list_reply';

    case START_CONVERSATION = 'start_conversation';


    /**
     * Get a human-readable label for the trigger type.
     */
    public function label(): string
    {
        return match ($this) {
            self::TEMPLATE_STATUS => 'Template Status Trigger',
            self::BUTTON_REPLY => 'Button Reply Trigger',
            self::LIST_REPLY => 'List Reply Trigger',
            self::START_CONVERSATION => 'Conversation Start Trigger',
        };
    }

    /**
     * Get a description for the trigger type.
     */
    public function description(): string
    {
        return match ($this) {
            self::TEMPLATE_STATUS => 'Triggered when a template message is sent, delivered, or read',
            self::BUTTON_REPLY => 'Triggered when a user clicks a button in an interactive message',
            self::LIST_REPLY => 'Triggered when a user selects an item from a list message',
            self::START_CONVERSATION => 'Triggered when a conversation is started',
        };
    }

    /**
     * Get all trigger types as an array.
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all trigger types with labels.
     */
    public static function toSelectArray(): array
    {
        $result = [];
        foreach (self::cases() as $case) {
            $result[$case->value] = $case->label();
        }
        return $result;
    }

    /**
     * Check if this is an interactive trigger type.
     */
    public function isInteractive(): bool
    {
        return \in_array($this, [self::BUTTON_REPLY, self::LIST_REPLY], true);
    }

    /**
     * Check if this is a template trigger type.
     */
    public function isTemplate(): bool
    {
        return $this === self::TEMPLATE_STATUS;
    }

    /**
     * Get required config keys for this trigger type.
     */
    public function requiredConfigKeys(): array
    {
        return match ($this) {
            self::TEMPLATE_STATUS => ['template_id', 'status'],
            self::BUTTON_REPLY => ['interactive_draft_id', 'button_id'],
            self::LIST_REPLY => ['interactive_draft_id', 'row_id'],
            self::START_CONVERSATION => ['conversatin_id'],
        };
    }

    /**
     * Validate trigger config for this trigger type.
     */
    public function validateConfig(array $config): bool
    {
        $requiredKeys = $this->requiredConfigKeys();
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key]) || empty($config[$key])) {
                return false;
            }
        }

        // Additional validation for template_status
        if ($this === self::TEMPLATE_STATUS) {
            $validStatuses = ['sent', 'delivered', 'read'];
            if (!\in_array($config['status'], $validStatuses, true)) {
                return false;
            }
        }

        return true;
    }
}

