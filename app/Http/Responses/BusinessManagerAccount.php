<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;
use Illuminate\Support\Collection;

/**
 * @OA\Schema(
 *     schema="BusinessManagerAccount",
 *     type="object",
 *     @OA\Property(property="id", type="string", description="The ID of the Business Manager Account."),
 *     @OA\Property(property="name", type="string", description="The name of the Business Manager Account."),
 *     @OA\Property(property="link", type="string", nullable=true, description="The link to the Business Manager Account."),
 *     @OA\Property(property="profile_picture_uri", type="string", nullable=true, description="The URI of the profile picture."),
 *     @OA\Property(property="two_factor_type", type="string", nullable=true, description="The two-factor authentication type."),
 *     @OA\Property(property="verification_status", type="string", nullable=true, description="The verification status of the Business Manager Account."),
 *     @OA\Property(property="vertical", type="string", nullable=true, description="The vertical type of the Business Manager Account."),
 *     @OA\Property(
 *         property="whatsapp_business_accounts",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/WhatsAppBusinessAccount"),
 *         description="List of associated WhatsApp Business Accounts."
 *     )
 * )
 */
class BusinessManagerAccount extends DataInterface
{
    public string $id;
    public string $name;
    public ?string $link;
    public ?string $profile_picture_uri;
    public ?string $two_factor_type;
    public ?string $verification_status;
    public ?string $vertical;


    public Collection $whatsapp_business_accounts;

    public function __construct(\App\Models\BusinessManagerAccount $account)
    {
        $this->id = $account->id;
        $this->name = $account->name;
        $this->link = $account->link;
        $this->profile_picture_uri = $account->profile_picture_uri;
        $this->two_factor_type = $account->two_factor_type;
        $this->verification_status = $account->verification_status;
        $this->vertical = $account->vertical;
        $this->whatsapp_business_accounts = $account->whatsappBusinessAccounts->map(fn(\App\Models\WhatsappBusinessAccount $waAccount) => new WhatsAppBusinessAccount($waAccount));
    }
}
