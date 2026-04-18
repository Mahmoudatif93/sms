<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use Illuminate\Support\Collection;

/**
 * @OA\Schema(
 *     schema="WhatsAppBusinessAccount",
 *     type="object",
 *     @OA\Property(property="id", type="string", description="The ID of the WhatsApp Business Account."),
 *     @OA\Property(property="name", type="string", description="The name of the WhatsApp Business Account."),
 *     @OA\Property(property="currency", type="string", nullable=true, description="The currency used by the WhatsApp Business Account."),
 *     @OA\Property(property="message_template_namespace", type="string", nullable=true, description="The namespace for the message templates.")
 * )
 */
class WhatsAppBusinessAccount extends DataInterface
{

    public string $id;
    public string $name;
    public ?string $currency;
    public ?string $message_template_namespace;
    public Collection $whatsapp_phone_numbers;

    public function __construct(\App\Models\WhatsappBusinessAccount $waAccount)
    {
        $this->id = $waAccount->id;
        $this->name = $waAccount->name;
        $this->currency = $waAccount->currency;
        $this->message_template_namespace = $waAccount->message_template_namespace;
        $this->whatsapp_phone_numbers = $waAccount->whatsappPhoneNumbers->map(fn(\App\Models\WhatsappPhoneNumber $waPhoneNumber) => new WhatsappPhoneNumber($waPhoneNumber));
    }
}
