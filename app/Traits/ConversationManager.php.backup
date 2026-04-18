<?php

namespace App\Traits;

use App\Logging\MetaConversationTextLogs;
use App\Models\AttributeDefinition;
use App\Models\Channel;
use App\Models\ContactAttribute;
use App\Models\ContactEntity;
use App\Models\Conversation;
use App\Models\ConversationAgent;
use App\Models\Identifier;
use App\Models\MetaConversationLog;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Models\Workspace;
use DB;
use Illuminate\Support\Str;

trait ConversationManager
{
    use WhatsappPhoneNumberManager;
    /**
     * Start a new conversation.
     *
     * @param string $platform The messaging platform (e.g., WhatsApp, SMS).
     * @param Channel $channel The channel associated with the conversation.
     * @param ContactEntity $contact The contact that the conversation is with.
     * @param string|null $message The first message (optional).
     * @param User|null $inboxAgent The inbox agent to assign (optional).
     * @return Conversation
     * @throws \Throwable
     */
    public function startConversation(
        string $platform,
        Channel $channel,
        ContactEntity $contact,
        $message = null,
        ?User $inboxAgent = null,
        string $status = Conversation::STATUS_OPEN,
        ?string $workspaceId = null
    ): Conversation {
        return DB::transaction(function () use ($platform, $channel, $contact, $message, $inboxAgent, $status,$workspaceId) {
            // ✅ Check if an active conversation already exists
            $existingConversation = $this->getActiveConversation($contact, $platform, $channel);
            if ($existingConversation) {
                return $existingConversation;
            }
            // Create the conversation
            $conversation = Conversation::create([
                'platform' => $platform,
                'channel_id' => $channel->id,
                'contact_id' => $contact->id,
                'status' => $status,
                'workspace_id' => $workspaceId ?? $channel->default_workspace_id,
            ]);

            // If a message is provided, store it
            if ($message) {
                // @todo for LiveChat
                // switch on platforms by using the MessagesController
            }

            // If an agent is provided, assign them to the conversation
            if ($inboxAgent) {
                $this->assignInboxAgentToConversation($inboxAgent, $conversation);
            }
            return $conversation;
        });
    }

    /**
     * Get an active conversation for a specific contact and platform.
     *
     * @param ContactEntity $contact The contact to find the conversation for.
     * @param string $platform The messaging platform to search on.
     * @return Conversation|null Returns the active conversation or null if none found.
     */
    public function getActiveConversation(ContactEntity $contact, string $platform, Channel $channel): ?Conversation
    {
        // Find a conversation that is in an active state (open or active status)
        return Conversation::where(['contact_id' => $contact->id, 'channel_id' => $channel->id])
            ->where('platform', $platform)
            ->whereIn('status', [
                Conversation::STATUS_OPEN,
                Conversation::STATUS_ACTIVE,
                Conversation::STATUS_WAITING,
                Conversation::STATUS_PENDING
            ])
            ->latest() // Get the most recent one if multiple exist
            ->first();
    }

    /**
     * Assign an inbox agent to a conversation.
     *
     * @param User $inboxAgent The agent to assign.
     * @param Conversation $conversation The conversation to assign the agent to.
     * @return bool
     */
    public function assignInboxAgentToConversation(User $inboxAgent, Conversation $conversation): bool
    {
        // Check if the agent is already assigned
        $existingAssignment = ConversationAgent::where('conversation_id', $conversation->id)
            ->where('inbox_agent_id', $inboxAgent->id)
            ->whereNull('removed_at')
            ->exists();

        if ($existingAssignment) {
            return false; // Agent is already assigned
        }

        // Assign the agent
        ConversationAgent::create([
            'conversation_id' => $conversation->id,
            'inbox_agent_id' => $inboxAgent->id,
            'assigned_at' => now()
        ]);

        return true;
    }

    /**
     * Remove an inbox agent from a conversation.
     *
     * @param User $inboxAgent The agent to remove.
     * @param Conversation $conversation The conversation to remove the agent from.
     * @return bool
     */
    public function removeInboxAgentFromConversation(User $inboxAgent, Conversation $conversation): bool
    {
        // Check if the agent is assigned
        $existingAssignment = ConversationAgent::where('conversation_id', $conversation->id)
            ->where('inbox_agent_id', $inboxAgent->id)
            ->whereNull('removed_at')
            ->first();

        if (!$existingAssignment) {
            return false; // Agent is not currently assigned
        }

        // Mark the agent as removed (keeping history)
        $existingAssignment->update([
            'removed_at' => now()
        ]);

        return true;
    }


    /**
     * Check if contact has any ended conversations
     */
    public function hasEndedactiveConversations(ContactEntity $contact, string $platform, Channel $channel): bool
    {
        return Conversation::where(['contact_id' => $contact->id, 'channel_id' => $channel->id, 'platform' => $platform])
            ->whereIn('status', [Conversation::STATUS_ENDED,Conversation::STATUS_ARCHIVED,Conversation::STATUS_CLOSED])
            ->exists();
    }

    public function startConversationFromWhatsappMessage(
        WhatsappMessage $whatsappMessage,
        $whatsappBusinessAccountID,
        $phoneNumber,
        ?string $whatsappName = null
    ): ?Conversation {
        return DB::transaction(function () use ($whatsappMessage, $whatsappBusinessAccountID, $phoneNumber, $whatsappName) {
            // 1. Normalize the phone number from Meta
            $senderPhone = $this->normalizePhoneNumber($phoneNumber);
            if (!$senderPhone) {
                return null;
            }

            // 2. Find the channel tied to this WABA
            $channel = Channel::with('workspaces')
                ->whereHas(
                    'whatsappConfiguration',
                    fn($q) =>
                    $q->where('whatsapp_business_account_id', $whatsappBusinessAccountID)
                )
                ->first();

            if (!$channel) {
                return null;
            }

            // 3. Get default workspace (needed for new contact / conversation fallback)
            $workspace = $channel->workspaces()
                ->where('workspaces.id', $channel->default_workspace_id)
                ->first();

            if (!$workspace) {
                return null;
            }

            $organizationId = $workspace->organization_id;

            // 4. Look up contact by phone anywhere in the org
            $contact = ContactEntity::whereHas('identifiers', function ($query) use ($senderPhone) {
                $query->where('key', ContactEntity::IDENTIFIER_TYPE_PHONE)
                    ->where('value', $senderPhone);
            })
                ->where('organization_id', $organizationId)
                ->first();

            if (!$contact) {

                $contact = ContactEntity::create([
                    'id' => Str::uuid(),
                    'organization_id' => $organizationId,
                ]);


                $contact->setPhoneNumberIdentifier($senderPhone);
                $contact->markAsWhatsappSubscribed();
                // $contact->setDisplayName($contactsMap[$senderPhone]);
            } else {
                $contact->markAsWhatsappSubscribed();
            }

            // 5. Try to find an active conversation for this contact + channel (any workspace)
            $existingConversation = $this->getActiveConversation(
                $contact,
                Channel::WHATSAPP_PLATFORM,
                $channel
            );
            $contact->setWhatsAppName($whatsappName??"");

            $isCustomerServiceWindowActive = false;
            if ($existingConversation) {
                $conversation = $existingConversation;
                $isCustomerServiceWindowActive = $conversation->isCustomerServiceWindowActive();
            } else {
                // 6. Otherwise, start a new conversation in the default workspace
                $conversation = $this->startConversation(
                    Channel::WHATSAPP_PLATFORM,
                    $channel,
                    $contact,
                    workspaceId: $workspace->id
                );
                $isCustomerServiceWindowActive = false;
            }

            // 7 Dispatch event if customer service window is not active
            if (!$isCustomerServiceWindowActive) {
                $this->dispatchStartConversationEvent($whatsappMessage, $conversation, $isCustomerServiceWindowActive);
            }

            // 8. Link the incoming message to the conversation
            $whatsappMessage->conversation_id = $conversation->id;
            $whatsappMessage->save();

            return $conversation;
        });
    }



    public function dispatchStartConversationEvent(WhatsappMessage $whatsappMessage, Conversation $conversation, bool $isCustomerServiceWindowActive)
    {
        if ($conversation && !$isCustomerServiceWindowActive) {
            event(new \App\Events\WhatsappStartConversation($whatsappMessage, $conversation->id, $isCustomerServiceWindowActive));
        }
        return;
    }



}
