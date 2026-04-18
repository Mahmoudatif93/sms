<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use App\Models\MessageBilling;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationBilling;

class WhatsappMessage extends DataInterface
{
    public string $id;
    public string $type;
    public string $status;
    public Channel|null $channel;
    public mixed $contact;
    public mixed $billing;
    public string $direction;
    public int $createdAt;
    public int $updatedAt;


    /**
     * Constructor to initialize the WhatsApp Message response.
     *
     * @param \App\Models\WhatsappMessage $message
     */
    public function __construct(\App\Models\WhatsappMessage $message, $translationQuota = null)
    {
         
        $this->id = (string) $message->id;
        $this->channel = $message->channel ? new Channel($message->channel) : null;
        $this->contact = $message->direction == \App\Models\WhatsappMessage::MESSAGE_DIRECTION_SENT ? $message->recipient->phone_number : $message->sender->phone_number;
        $this->type = $message->type;
        $this->status = $message->status;
        $this->direction = $message->direction;
        $this->billing = $this->generateBillingMessage($message);
        $this->createdAt = $message->created_at;
        $this->updatedAt = $message->updated_at;
    }

    /**
     * Generates the billing details string for the given conversation.
     *
     * @param \App\Models\WhatsappMessage $message
     * @return array The formatted billing details.
     */
    private function generateBillingMessage(\App\Models\WhatsappMessage $message): array
    {
        
        $translationCost = (float) $message->translationBilling()
            ->where('is_billed', true)
            ->sum('cost') ?? 0.0;
     
        $chatbootCost = (float) $message->chatbotBilling()
            ->where('is_billed', true)
            ->sum('cost') ?? 0.0;

            

        $billing = [
            'translation_cost' => $translationCost,
            'chatboot_cost' => $chatbootCost,
            'total_cost' => $translationCost + $chatbootCost,
            'currency' => 'SAR',
        ];
 
        if ($message->type === \App\Models\WhatsappMessage::MESSAGE_TYPE_TEMPLATE) {
            $transaction = $message->walletTransaction;
            if(isset($transaction->meta) && $transaction->meta != null){
                if($transaction->meta['type'] == "whatsapp_message_interactive"){
                      return $billing;
                }
            }
            $metaCost = abs($transaction?->amount ?? 0.0);
            $currency = $transaction?->wallet?->currency_code ?? 'SAR';

            $billing['wallet_transaction_id'] = $transaction?->id;
            $billing['meta_cost'] = $metaCost;
            $billing['total_cost'] += $metaCost;
            $billing['currency'] = $currency;
            $billing['status'] = $transaction?->status ?? 'unbilled';
        }
      
        return $billing;
    }

}
