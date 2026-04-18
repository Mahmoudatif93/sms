<?php

namespace App\Enums\Workflow;

/**
 * Enum representing the types of actions that can be executed in a workflow.
 */
enum ActionType: string
{
    case SEND_TEMPLATE = 'send_template';
    case SEND_INTERACTIVE = 'send_interactive';
    case SEND_TEXT = 'send_text';
    case ADD_TO_LIST = 'add_to_list';
    case REMOVE_FROM_LIST = 'remove_from_list';
    case UPDATE_CONTACT = 'update_contact';
    case WEBHOOK = 'webhook';

    /**
     * Get a human-readable label for the action type.
     */
    public function label(): string
    {
        return match ($this) {
            self::SEND_TEMPLATE => 'Send Template Message',
            self::SEND_INTERACTIVE => 'Send Interactive Message',
            self::SEND_TEXT => 'Send Text Message',
            self::ADD_TO_LIST => 'Add to List',
            self::REMOVE_FROM_LIST => 'Remove from List',
            self::UPDATE_CONTACT => 'Update Contact',
            self::WEBHOOK => 'Call Webhook',
        };
    }

    /**
     * Get a description for the action type.
     */
    public function description(): string
    {
        return match ($this) {
            self::SEND_TEMPLATE => 'Send a WhatsApp template message to the user',
            self::SEND_INTERACTIVE => 'Send an interactive message (buttons or list) to the user',
            self::SEND_TEXT => 'Send a plain text message to the user',
            self::ADD_TO_LIST => 'Add the contact to a specified list',
            self::REMOVE_FROM_LIST => 'Remove the contact from a specified list',
            self::UPDATE_CONTACT => 'Update contact properties',
            self::WEBHOOK => 'Send a webhook request to an external URL',
        };
    }

    /**
     * Get all action types as an array.
     */
    public static function toArray(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get all action types with labels.
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
     * Get required config keys for this action type.
     */
    public function requiredConfigKeys(): array
    {
        return match ($this) {
            self::SEND_TEMPLATE => ['template_id'],
            self::SEND_INTERACTIVE => [], // Either interactive_draft_id OR inline config
            self::SEND_TEXT => ['text'],
            self::ADD_TO_LIST => ['list_id'],
            self::REMOVE_FROM_LIST => ['list_id'],
            self::UPDATE_CONTACT => ['updates'],
            self::WEBHOOK => ['url'],
        };
    }

    /**
     * Check if this action type sends a message.
     */
    public function sendsMessage(): bool
    {
        return \in_array($this, [
            self::SEND_TEMPLATE,
            self::SEND_INTERACTIVE,
            self::SEND_TEXT,
        ], true);
    }

    /**
     * Check if this action type modifies contact data.
     */
    public function modifiesContact(): bool
    {
        return \in_array($this, [
            self::ADD_TO_LIST,
            self::REMOVE_FROM_LIST,
            self::UPDATE_CONTACT,
        ], true);
    }

    /**
     * Check if this action type makes external calls.
     */
    public function isExternal(): bool
    {
        return $this === self::WEBHOOK;
    }
}

