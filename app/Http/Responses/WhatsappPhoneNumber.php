<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;

/**
 * @OA\Schema(
 *     schema="WhatsappPhoneNumber",
 *     title="WhatsappPhoneNumber",
 *     description="Response format for WhatsApp Phone Number",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         example=1906385232743451,
 *         description="The unique ID of the phone number."
 *     ),
 *     @OA\Property(
 *         property="verified_name",
 *         type="string",
 *         example="Jasper's Market",
 *         description="Verified name of the business associated with the phone number."
 *     ),
 *     @OA\Property(
 *         property="code_verification_status",
 *         type="string",
 *         example="EXPIRED",
 *         description="Status of the code verification."
 *     ),
 *     @OA\Property(
 *         property="display_phone_number",
 *         type="string",
 *         example="+1 631-555-5555",
 *         description="The phone number displayed."
 *     ),
 *     @OA\Property(
 *         property="quality_rating",
 *         type="string",
 *         example="GREEN",
 *         description="Quality rating of the phone number."
 *     ),
 *     @OA\Property(
 *         property="platform_type",
 *         type="string",
 *         example="CLOUD_API",
 *         description="Platform type of the phone number."
 *     )
 * )
 */
class WhatsappPhoneNumber extends DataInterface
{
    public int $id;
    public ?string $verified_name;
    public ?string $code_verification_status;
    public ?string $display_phone_number;
    public ?string $quality_rating;
    public ?string $platform_type;
    public WhatsappBusinessProfile $whatsapp_business_profile;

    public function __construct(\App\Models\WhatsappPhoneNumber $phoneNumber)
    {
        $this->id = $phoneNumber->id;
        $this->verified_name = $phoneNumber->verified_name;
        $this->code_verification_status = $phoneNumber->code_verification_status;
        $this->display_phone_number = $phoneNumber->display_phone_number;
        $this->quality_rating = $phoneNumber->quality_rating;
        $this->platform_type = $phoneNumber->platform_type;
        $this->whatsapp_business_profile = new WhatsappBusinessProfile($phoneNumber->whatsappBusinessProfile);
    }
}
