<?php

namespace App\Http\Responses;

use App\Http\Interfaces\DataInterface;


/**
 * @OA\Schema(
 *     schema="WhatsappBusinessProfile",
 *     title="WhatsappBusinessProfile",
 *     description="The model for WhatsApp business profile.",
 *     @OA\Property(
 *         property="id",
 *         type="string",
 *         description="The ID of the business profile.",
 *         example="108427225641466"
 *     ),
 *     @OA\Property(
 *         property="whatsapp_business_account_id",
 *         type="string",
 *         description="The ID of the WhatsApp business account.",
 *         example="109346662215398"
 *     ),
 *     @OA\Property(
 *         property="whatsapp_phone_number_id",
 *         type="string",
 *         description="The ID of the phone number associated with the business profile.",
 *         example="108427225641466"
 *     ),
 *     @OA\Property(
 *         property="about",
 *         type="string",
 *         description="About information of the business profile.",
 *         example="Leading provider of innovative solutions."
 *     ),
 *     @OA\Property(
 *         property="address",
 *         type="string",
 *         description="Address of the business.",
 *         example="123 Business St, Business City, BC 12345"
 *     ),
 *     @OA\Property(
 *         property="description",
 *         type="string",
 *         description="Description of the business profile.",
 *         example="We provide top-quality products and services."
 *     ),
 *     @OA\Property(
 *         property="email",
 *         type="string",
 *         format="email",
 *         description="Email address associated with the business.",
 *         example="contact@business.com"
 *     ),
 *     @OA\Property(
 *         property="profile_picture_url",
 *         type="string",
 *         description="URL of the business profile picture.",
 *         example="https://example.com/profile-picture.jpg"
 *     ),
 *     @OA\Property(
 *         property="vertical",
 *         type="string",
 *         description="Vertical or category of the business.",
 *         example="Retail"
 *     )
 * )
 */
class WhatsappBusinessProfile extends DataInterface
{
    public string $id;
    public $whatsapp_business_account_id;
    public $whatsapp_phone_number_id;
    public $about;
    public $address;
    public $description;
    public $email;
    public $profile_picture_url;
    public $vertical;

    public function __construct(\App\Models\WhatsappBusinessProfile $businessProfile)
    {
        $this->id = $businessProfile->id;
        $this->whatsapp_business_account_id = $businessProfile->whatsapp_business_account_id;
        $this->whatsapp_phone_number_id = $businessProfile->whatsapp_phone_number_id;
        $this->about = $businessProfile->about;
        $this->address = $businessProfile->address;
        $this->description = $businessProfile->description;
        $this->email = $businessProfile->email;
        $this->profile_picture_url = $businessProfile->profile_picture_url;
        $this->vertical = $businessProfile->vertical;
    }
}
