<?php

namespace App\Http\Meta;

class Constants
{


    /**
     * Defines the possible types of two-factor authentication for the business.
     *
     * @var string[] List of two-factor authentication types.
     * Possible values:
     * - `none`: No two-factor authentication required.
     * - `admin_required`: Two-factor authentication required for admins.
     * - `all_required`: Two-factor authentication required for all users.
     */
    const TWO_FACTOR_TYPE = ['none', 'admin_required', 'all_required'];


    /**
     * Defines the possible statuses for business verification.
     *
     * @var string[] List of verification statuses.
     * Possible values:
     * - `not_set`: The verification status has not been set.
     * - `verified`: The business has been verified.
     * - `pending`: The verification process is pending.
     * - `rejected`: The verification request has been rejected.
     */
    const VERIFICATION_STATUS = ['not_set', 'verified', 'pending', 'rejected'];


    /**
     * Defines the possible vertical industry categories for the business.
     *
     * @var string[] List of vertical industry categories.
     * Possible values:
     * - `NOT_SET`: The vertical industry has not been set.
     * - `ADVERTISING`: Advertising industry.
     * - `AUTOMOTIVE`: Automotive industry.
     * - `CONSUMER_PACKAGED_GOODS`: Consumer packaged goods industry.
     * - `ECOMMERCE`: E-commerce industry.
     * - `EDUCATION`: Education industry.
     * - `ENERGY_AND_UTILITIES`: Energy and utilities industry.
     * - `ENTERTAINMENT_AND_MEDIA`: Entertainment and media industry.
     * - `FINANCIAL_SERVICES`: Financial services industry.
     * - `GAMING`: Gaming industry.
     * - `GOVERNMENT_AND_POLITICS`: Government and politics industry.
     * - `MARKETING`: Marketing industry.
     * - `ORGANIZATIONS_AND_ASSOCIATIONS`: Organizations and associations industry.
     * - `PROFESSIONAL_SERVICES`: Professional services industry.
     * - `RETAIL`: Retail industry.
     * - `TECHNOLOGY`: Technology industry.
     * - `TELECOM`: Telecommunications industry.
     * - `TRAVEL`: Travel industry.
     * - `NON_PROFIT`: Non-profit industry.
     * - `RESTAURANT`: Restaurant industry.
     * - `HEALTH`: Health industry.
     * - `LUXURY`: Luxury industry.
     * - `OTHER`: Other industry.
     */
    const VERTICAL = ['NOT_SET',
        'ADVERTISING',
        'AUTOMOTIVE',
        'CONSUMER_PACKAGED_GOODS',
        'ECOMMERCE',
        'EDUCATION',
        'ENERGY_AND_UTILITIES',
        'ENTERTAINMENT_AND_MEDIA',
        'FINANCIAL_SERVICES',
        'GAMING',
        'GOVERNMENT_AND_POLITICS',
        'MARKETING',
        'ORGANIZATIONS_AND_ASSOCIATIONS',
        'PROFESSIONAL_SERVICES',
        'RETAIL',
        'TECHNOLOGY',
        'TELECOM',
        'TRAVEL',
        'NON_PROFIT',
        'RESTAURANT',
        'HEALTH',
        'LUXURY',
        'OTHER'];





    /*
     * @todo Add Interactive Message Type
     */

    /**
     * The list of vertical industry categories that a business profile can associate with.
     *
     * @var array<string>
     *
     * @example
     * [
     *     'UNDEFINED',
     *     'OTHER',
     *     'AUTO',
     *     'BEAUTY',
     *     'APPAREL',
     *     'EDU',
     *     'ENTERTAIN',
     *     'EVENT_PLAN',
     *     'FINANCE',
     *     'GROCERY',
     *     'GOVT',
     *     'HOTEL',
     *     'HEALTH',
     *     'NONPROFIT',
     *     'PROF_SERVICES',
     *     'RETAIL',
     *     'TRAVEL',
     *     'RESTAURANT',
     *     'NOT_A_BIZ'
     * ]
     */
    const BUSINESS_PROFILE_VERTICAL = ["UNDEFINED",
        "OTHER",
        "AUTO",
        "BEAUTY",
        "APPAREL",
        "EDU",
        "ENTERTAIN",
        "EVENT_PLAN",
        "FINANCE",
        "GROCERY",
        "GOVT",
        "HOTEL",
        "HEALTH",
        "NONPROFIT",
        "PROF_SERVICES",
        "RETAIL",
        "TRAVEL",
        "RESTAURANT",
        "NOT_A_BIZ"];

    /**
     * Defines the possible types of WhatsApp messages.
     *
     * @var string[] List of WhatsApp message types.
     * Possible values:
     * - `text`
     * - `template`
     * - `reaction`
     * - `location`
     * - `contacts`
     * - `audio`
     * - `document`
     * - `image`
     * - `sticker`
     * - `video`
     */
    const MESSAGE_TYPE = ['text', 'template', 'reaction', 'location', 'contacts', 'audio', 'document', 'image', 'sticker', 'video'];


    /**
     * Defines the possible directions for WhatsApp messages.
     *
     * @var string[] List of message directions.
     * Possible values:
     * - `SENT`: The message was sent.
     * - `RECEIVED`: The message was received.
     */
    const MESSAGE_DIRECTION = ['SENT', 'RECEIVED'];


    /**
     * Defines the possible statuses for a WhatsApp message.
     *
     * @var string[] List of message statuses.
     * Possible values:
     * - `initiated`: The message has been initiated and is being prepared for sending.
     * - `sent`: The message is in transit within the system.
     * - `delivered`: The message was delivered to the customer's device.
     * - `read`: The message was read by the customer.
     * - `failed`: The message failed to send.
     * - `deleted`: The message was deleted by the customer.
     * - `warning`: The message contains an item in a catalog that is not available or does not exist.
     */
    const MESSAGE_STATUS = [
        'initiated',
        'sent',
        'delivered',
        'read',
        'failed',
        'deleted',
        'warning'
    ];


    /**
     * Defines the possible roles of a sender in WhatsApp messages.
     *
     * @var string[] List of sender roles.
     * Possible values:
     * - `BUSINESS`: The sender is a business-controlled number.
     * - `CONSUMER`: The sender is a consumer's phone number.
     */
    const SENDER_ROLE = ['BUSINESS', 'CONSUMER'];


    /**
     * Defines the possible categories of conversations in WhatsApp.
     *
     * @var string[] List of conversation categories.
     * Possible values:
     * - `authentication`: Indicates the conversation was opened by a business sending template categorized as AUTHENTICATION to the customer.
     * - `marketing`: Indicates the conversation was opened by a business sending template categorized as MARKETING to the customer.
     * - `utility`: Indicates the conversation was opened by a business sending template categorized as UTILITY to the customer.
     * - `service`: Indicates that the conversation opened by a business replying to a customer within a customer service window.
     * - `referral_conversion`: Indicates a free entry point conversation.
     */
    const CONVERSATION_CATEGORIES = [
        'authentication',
        'marketing',
        'utility',
        'service',
        'referral_conversion'
    ];

    /**
     * Defines the possible categories for message templates.
     *
     * @var string[] List of message template categories.
     *
     * Possible values:
     * - `AUTHENTICATION`: Used for authentication-related templates (e.g., one-time password).
     * - `MARKETING`: Used for marketing-related templates (e.g., promotional messages).
     * - `UTILITY`: Used for utility-related templates (e.g., transaction updates, delivery notifications).
     */
    const MESSAGE_TEMPLATE_CATEGORY = ['AUTHENTICATION', 'MARKETING', 'UTILITY'];

    /**
     * Defines the possible statuses for message templates.
     *
     * @var string[] List of template statuses.
     *
     * Possible values:
     * - `PENDING`: The template passed category validation and is undergoing template review.
     * - `APPROVED`: The template has passed the review and is approved for use.
     * - `REJECTED`: The template failed category validation or review.
     * - `PAUSED`: The template has been paused.
     */
    const MESSAGE_TEMPLATE_STATUS = ['PENDING', 'APPROVED', 'REJECTED', 'PAUSED'];


}
