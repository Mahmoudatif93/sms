<?php

namespace App\Traits;

use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Carbon\Carbon;

trait WhatsappConversationManager
{

    use WhatsappPhoneNumberManager;
    /**
     * Check if a service conversation can be opened or continue an existing one.
     *
     * @param string $recipientId The WhatsApp recipient ID.
     * @return bool
     */
    public function handleServiceConversation(string $recipientId): bool
    {
        // Check if any conversation is already open
        $existingConversation = $this->getOpenConversation($recipientId, 'service');

        if (!$existingConversation) {
            // Open a new service conversation
            $this->openNewConversation($recipientId, 'service', 'non-template');
            return true;
        }

        return false;
    }

    /**
     * Determine if a conversation is already open for a specific category.
     *
     * @param string $recipientId The WhatsApp recipient ID.
     * @param string $category The conversation category (e.g., marketing, utility).
     * @return WhatsappConversation|null
     */
    public function getOpenConversation(string $recipientId, string $category): ?WhatsappConversation
    {
        return WhatsappConversation::where('recipient_id', $recipientId)
            ->where('category', $category)
            ->where('expiration_timestamp', '>', time())
            ->latest()
            ->first();
    }

    /**
     * Open a new conversation if no existing one is found.
     *
     * @param string $recipientId The WhatsApp recipient ID.
     * @param string $category The conversation category.
     * @param string $conversationType The type (e.g., marketing, utility, service).
     * @return WhatsappConversation
     */
    public function openNewConversation(string $recipientId, string $category, string $conversationType): WhatsappConversation
    {
        $expirationTimestamp = Carbon::now()->addHours(24)->timestamp; // 24-hour window for new conversations

        // If it's a free entry point conversation, it lasts 72 hours.
        if ($category === 'free_entry_point') {
            $expirationTimestamp = Carbon::now()->addHours(72)->timestamp;
        }

        return WhatsappConversation::create([
            'recipient_id' => $recipientId,
            'category' => $category,
            'type' => $conversationType,
            'expiration_timestamp' => $expirationTimestamp,
        ]);
    }

    /**
     * Handle template-based conversations: marketing, utility, and authentication.
     *
     * @param string $recipientId The WhatsApp recipient ID.
     * @param string $templateCategory The category of the template (e.g., marketing, utility).
     * @return bool
     */
    public function handleTemplateConversation(string $recipientId, string $templateCategory): bool
    {
        // Check if a conversation of this category is already open
        $existingConversation = $this->getOpenConversation($recipientId, $templateCategory);

        if (!$existingConversation) {
            // Open a new template-based conversation
            $this->openNewConversation($recipientId, $templateCategory, 'template');
            return true;
        }

        return false;
    }

    /**
     * Handle free entry point conversations (e.g., from Click-to-WhatsApp ads).
     *
     * @param string $recipientId The WhatsApp recipient ID.
     * @return bool
     */
    public function handleFreeEntryPointConversation(string $recipientId): bool
    {
        // Free-entry point conversations last 72 hours and close all other conversations.
        $existingConversation = $this->getOpenConversation($recipientId, 'free_entry_point');

        if (!$existingConversation) {
            // Open a new free-entry point conversation
            $this->openNewConversation($recipientId, 'free_entry_point', 'free');
            // Close any other conversations (this is WhatsApp's behavior)
            WhatsappConversation::where('recipient_id', $recipientId)
                ->where('category', '!=', 'free_entry_point')
                ->update(['expiration_timestamp' => Carbon::now()->timestamp]);

            return true;
        }

        return false;
    }

    /**
     * Track message and associate it with the current conversation.
     *
     * @param string $messageId The ID of the WhatsApp message.
     * @param string $recipientId The WhatsApp recipient ID.
     * @param string $category The conversation category.
     * @return void
     */
    public function trackMessageInConversation(string $messageId, string $recipientId, string $category): void
    {
        // Find the open conversation and associate the message with it.
        $conversation = $this->getOpenConversation($recipientId, $category);

        if ($conversation) {
            $conversation->messages()->create([
                'whatsapp_message_id' => $messageId,
            ]);
        }
    }


    private function trackConversation(array $status, WhatsappMessage $whatsappMessage, string $phoneNumberID): void
    {
        $conversationID = $status['conversation']['id'];
        $recipientID = $status['recipient_id'];
        $category = $status['conversation']['origin']['type'] ?? 'service'; // Default to service if not specified
        $countryCode = $this->getCountryCodeFromPhoneNumber($recipientID);  // Assume you have a method to extract the country code
        $timestamp = $status['timestamp'];

        // Store conversation data if it doesn't already exist
        $conversation = WhatsappConversation::firstOrCreate(
            [
                'id' => $conversationID,
            ],
            [
                'whatsapp_phone_number_id' => $phoneNumberID,
                'type' => $category,
                'expiration_timestamp' => Carbon::createFromTimestamp($timestamp)->addHours(24)->timestamp,
                'country_code' => $countryCode,
            ]
        );

        // Update the conversation with the latest status timestamp
        $conversation?->update(['updated_at' => $timestamp]);
    }


}
