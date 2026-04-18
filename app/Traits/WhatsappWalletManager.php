<?php

namespace App\Traits;

use App\Helpers\CurrencyHelper;
use App\Models\OrganizationWhatsappRateLine;
use App\Models\OrganizationWhatsappSetting;
use App\Models\Service;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\WhatsappMessage;
use App\Models\WhatsappRateLine;
use App\Models\WorldCountry;
use App\Models\ContactEntity;
use App\Models\Identifier;
use Illuminate\Support\Collection;

trait WhatsappWalletManager
{

    use WalletManager, WhatsappTemplateManager,WhatsappPhoneNumberManager;

    /**
     * Reserve funds in the wallet for a WhatsApp message.
     */
    public function reserveFunds(Wallet $wallet, float $amount, array $meta = [], ?string $reason = null, ?string $category = null): ?WalletTransaction
    {
        $lockedWallet = Wallet::where('id', $wallet->id)->lockForUpdate()->first();

        if (!$lockedWallet || $lockedWallet->available_amount < $amount) {
            return null;
        }

        // Reserve funds
        $lockedWallet->pending_amount += $amount;
        $lockedWallet->save();

        return WalletTransaction::create([
            'wallet_id' => $lockedWallet->id,
            'amount' => -1 * $amount,
            'transaction_type' => WalletTransactionType::USAGE,
            'status' => WalletTransactionStatus::PENDING,
            'description' => $reason ?? 'Reserved for WhatsApp message',
            'category' => $category,
            'meta' => $meta,
        ]);
    }

    /**
     * Confirm the reserved funds after message delivery.
     */
    public function confirmFunds(WalletTransaction $transaction, string $reason = null): bool
    {
        if ($transaction->status !== WalletTransactionStatus::PENDING) {
            return false;
        }

        $wallet = $transaction->wallet()->lockForUpdate()->first();

        $wallet->pending_amount -= abs($transaction->amount);
        $wallet->amount -= abs($transaction->amount);
        // Optionally track used_amount later
        $wallet->save();

        if ($transaction->category == WalletTransaction::WALLET_TRANSACTION_CHATBOT) {
            $transaction->description = 'Confirmed for Chatbot WhatsApp message';
        } else {
            $transaction->description = $reason ?? 'Confirmed WhatsApp message delivery';
        }
        $transaction->status = WalletTransactionStatus::ACTIVE;
        return $transaction->save();
    }

    /**
     * Release the reserved funds if the message was not delivered.
     */
    public function releaseFunds(WalletTransaction $transaction): bool
    {
        if ($transaction->status !== WalletTransactionStatus::PENDING) {
            return false;
        }

        $wallet = $transaction->wallet()->lockForUpdate()->first();

        $wallet->pending_amount -= abs($transaction->amount);
        $wallet->save();

        $transaction->status = WalletTransactionStatus::CANCELED;
        $transaction->description = 'Released WhatsApp funds (undelivered message)';
        return $transaction->save();
    }

    public function prepareWalletTransactionForTemplate($channel, $conversation, $workspace, $contact, $senderPhone, $templateId): ?WalletTransaction
    {
        $category = $this->getTemplateCategoryById($templateId, $channel);

        if (!$conversation->shouldChargeForTemplate($category)) {
            return null;
        }

        $organizationId = $workspace->organization_id;

        $settings = OrganizationWhatsappSetting::firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'use_custom_rates' => false,
                'who_pays_meta' => 'client',
                'wallet_charge_mode' => 'none',
                'markup_percentage' => 0.0
            ]
        );

        $chargeType = $this->resolveWhatsappChargeType($settings);
        if (!$chargeType) {
            return null;
        }

        $iso2 = $this->getCountryCodeFromPhoneNumber($senderPhone);
        if (!$iso2) {
            throw new \Exception('Could not determine country from phone number.');
        }

        $country = WorldCountry::where('iso2', strtoupper($iso2))->firstOrFail();

        $rate = WhatsappRateLine::where([
            'world_country_id' => $country->id,
            'category' => $category,
            'pricing_model' => 'PMP',
        ])->firstOrFail();


        $metaPriceSAR = $rate->currency === 'USD'
            ? CurrencyHelper::convertDollarToSAR($rate->price)
            : $rate->price;


        $customRate = null;

        if (in_array($chargeType, ['markup_only', 'full'])) {
            $customRate = OrganizationWhatsappRateLine::where('organization_id', $organizationId)
                ->where('whatsapp_rate_line_id', $rate->id)
                ->first();

            if (!$customRate && (empty($settings->markup_percentage) || $settings->markup_percentage < 0.01)) {
                throw new \Exception("No custom rate or markup percentage configured for {$category} in {$country->name}");
            }
        }

        $cost = match ($chargeType) {
            'meta_only' => $metaPriceSAR,

            'markup_only' => $customRate
            ? max($customRate->custom_price - $metaPriceSAR, 0)
            : ceil($metaPriceSAR * ($settings->markup_percentage / 100) * 10000) / 10000,

            'full' => $customRate
            ? $customRate->custom_price
            : ceil($metaPriceSAR * (1 + ($settings->markup_percentage / 100)) * 10000) / 10000,

            default => throw new \Exception("Unexpected charge type: {$chargeType}"),
        };


        if ($chargeType === 'markup_only' && $cost <= 0) {
            return null;
        }

        $wallet = $this->getWorkspaceWallet(
            $workspace,
            Service::firstOrCreate(
                ['name' => \App\Enums\Service::OTHER],
                ['description' => 'whatsapp,hlr']
            )->id
        );


        if (!$wallet->hasSufficientFunds($cost)) {
            throw new \Exception("Insufficient wallet balance. Required: {$cost} {$wallet->currency_code}, Available: {$wallet->available_amount} {$wallet->currency_code}");
        }

        $meta = [
            'type' => 'whatsapp_message',
            'whatsapp_message_id' => null,
            'category' => $category,
            'country_iso2' => $iso2,
            'pricing_mode' => match ($chargeType) {
                'meta_only' => 'meta',
                'markup_only' => 'markup',
                'full' => 'full',
                default => null,
            },
            'template_id' => $templateId,
            'organization_id' => $organizationId,
            'channel_id' => $channel->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'whatsapp_rate_line_id' => $rate->id,
            'organization_rate_line_id' => $customRate?->id,
        ];

        return $this->reserveFunds($wallet, $cost, $meta, 'Reserved for WhatsApp message');
    }

    public function prepareWalletTransactionForChatboot($workspace)
    {
        $organization = $workspace->organization;
        $organizationId = $workspace->organization_id;
        $extra = \App\Models\OrganizationWhatsappExtra::where('organization_id', $organizationId)->first();
        if (!$extra || empty($extra->chatbot_quota) || $extra->chatbot_quota <= 0) {
            return null;
        }
        $wallet = $this->getObjectWallet(
            $organization,
            Service::where('name', \App\Enums\Service::OTHER)->value('id')
        );

        if (!$wallet->hasSufficientFunds($extra->chatbot_quota)) {
            return null;
            // throw new \Exception("Insufficient wallet balance. Required: {$extra->chatbot_quota} {$wallet->currency_code}, Available: {$wallet->available_amount} {$wallet->currency_code}");
        }

        $meta = [
            'type' => 'whatsapp_message',
            'whatsapp_message_id' => null
        ];

        return $this->reserveFunds($wallet, $extra->chatbot_quota, $meta, 'Reserved for Chatbot WhatsApp message', WalletTransaction::WALLET_TRANSACTION_CHATBOT);
    }

    /**
     * Prepare wallet transaction for translation.
     *
     * @param \App\Models\Workspace $workspace
     * @return WalletTransaction|null
     */
    public function prepareWalletTransactionForTranslation($workspace): ?WalletTransaction
    {
        $organization = $workspace->organization;
        $organizationId = $workspace->organization_id;
        $extra = \App\Models\OrganizationWhatsappExtra::where('organization_id', $organizationId)->first();
        if (!$extra || empty($extra->translation_quota) || $extra->translation_quota <= 0) {
            return null;
        }

        $wallet = $this->getObjectWallet(
            $organization,
            Service::where('name', \App\Enums\Service::OTHER)->value('id')
        );
        if (!$wallet->hasSufficientFunds($extra->translation_quota)) {
            return null;
        }

        $meta = [
            'type' => 'translation',
            'whatsapp_message_id' => null
        ];

        return $this->reserveFunds($wallet, $extra->translation_quota, $meta, 'Reserved for Translation', WalletTransaction::WALLET_TRANSACTION_AI);
    }

    /**
     * Finalize wallet transaction for translation based on message status.
     *
     * @param WhatsappMessage|null $message
     * @param string $status
     * @param WalletTransaction|null $transaction
     * @return void
     */
    public function finalizeTranslationWalletTransaction(?WhatsappMessage $message, string $status, ?WalletTransaction $transaction = null): void
    {
        if (!$message) {
            return;
        }

        // Use provided transaction or get from message
        $transaction = $transaction ?? $message->walletTransaction;

        if (!$transaction || $transaction->status !== WalletTransactionStatus::PENDING) {
            return;
        }

        if (
            in_array($status, [
                WhatsappMessage::MESSAGE_STATUS_SENT,
                WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                WhatsappMessage::MESSAGE_STATUS_READ
            ])
        ) {
            $this->confirmFunds($transaction);

            // Create billing record for translation
            \App\Models\MessageBilling::create([
                'messageable_id' => $message->id,
                'messageable_type' => WhatsappMessage::class,
                'type' => \App\Models\MessageBilling::TYPE_TRANSLATION,
                'cost' => $transaction->amount * -1,
                'is_billed' => true,
            ]);
        }

        if ($status === WhatsappMessage::MESSAGE_STATUS_FAILED) {
            $this->releaseFunds($transaction);
        }
    }


    /**
     * Determine what type of wallet charge to apply based on WhatsApp settings.
     *
     * @param OrganizationWhatsappSetting $settings
     * @return string|null 'meta_only', 'markup_only', 'full', or null (no charge)
     */
    public function resolveWhatsappChargeType(OrganizationWhatsappSetting $settings): ?string
    {
        $whoPays = $settings->who_pays_meta;
        $walletMode = $settings->wallet_charge_mode;

        return match (true) {
            $whoPays === 'client' && $walletMode === 'markup_only' => 'markup_only',
            $whoPays === 'provider' && $walletMode === 'meta_only' => 'meta_only',
            $whoPays === 'provider' && $walletMode === 'full' => 'full',
            default => null, // should never happen if settings are valid
        };
    }

    /**
     * Finalize the wallet transaction based on WhatsApp message status.
     */
    public function finalizeWhatsappWalletTransaction(?WhatsappMessage $message, string $status): void
    {
        if (!$message) {
            return;
        }

        $transaction = $message->walletTransaction;


        if (!$transaction || $transaction->status !== WalletTransactionStatus::PENDING) {
            return;
        }


        if (
            in_array($status, [
                WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                WhatsappMessage::MESSAGE_STATUS_READ
            ])
        ) {
            $this->confirmFunds($transaction);
        }

        if ($status === WhatsappMessage::MESSAGE_STATUS_FAILED) {
            $this->releaseFunds($transaction);
        }
    }

    public function finalizeWhatsappWalletChatBoot(?WhatsappMessage $message, string $status): void
    {
        if (!$message) {
            return;
        }

        $transaction = $message->walletTransaction;
        if (!$transaction || $transaction->status !== WalletTransactionStatus::PENDING) {
            return;
        }

        if (
            in_array($status, [
                WhatsappMessage::MESSAGE_STATUS_INITIATED,
                WhatsappMessage::MESSAGE_STATUS_SENT,
                WhatsappMessage::MESSAGE_STATUS_DELIVERED,
                WhatsappMessage::MESSAGE_STATUS_READ
            ])
        ) {

            $this->confirmFunds($transaction);
            // Create billing record for chatbot quota
            \App\Models\MessageBilling::create([
                'messageable_id' => $message->id,
                'messageable_type' => WhatsappMessage::class,
                'type' => \App\Models\MessageBilling::TYPE_CHATBOT,
                'cost' => $transaction->amount * -1,
                'is_billed' => true,
            ]);
        }

        if ($status === WhatsappMessage::MESSAGE_STATUS_FAILED) {
            $this->releaseFunds($transaction);
        }
    }

    public function calculateCostForLists(
        $workspace,
        array|Collection $contacts
    ): array {
        $organizationId = $workspace->organization_id;

        $settings = OrganizationWhatsappSetting::firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'use_custom_rates' => false,
                'who_pays_meta' => 'client',
                'wallet_charge_mode' => 'none',
            ]
        );

        $chargeType = $this->resolveWhatsappChargeType($settings);
        if (!$chargeType) {
            return ['success' => false, 'error' => 'Invalid WhatsApp billing settings'];
        }

        $category = 'marketing';

        $total = 0;
        $details = [];
        $hasFailure = false;

        foreach ($contacts as $contact) {
            $phone = $contact->getPhoneNumberIdentifier();
            if (!$phone) {
                $details[] = [
                    'contact_id' => $contact->id,
                    'success' => false,
                    'error' => 'Missing phone number'
                ];
                $hasFailure = true;
                continue;
            }

            $iso2 = $this->getCountryCodeFromPhoneNumber($phone);
            if (!$iso2) {
                $details[] = [
                    'contact_id' => $contact->id,
                    'phone' => $phone,
                    'success' => false,
                    'error' => 'Cannot resolve country'
                ];
                $hasFailure = true;
                continue;
            }

            $country = WorldCountry::where('iso2', strtoupper($iso2))->first();
            if (!$country) {
                $details[] = [
                    'contact_id' => $contact->id,
                    'phone' => $phone,
                    'success' => false,
                    'error' => 'Unsupported country'
                ];
                $hasFailure = true;
                continue;
            }

            $rate = WhatsappRateLine::where([
                'world_country_id' => $country->id,
                'category' => $category,
                'pricing_model' => 'PMP',
            ])->first();

            if (!$rate) {
                $details[] = [
                    'contact_id' => $contact->id,
                    'phone' => $phone,
                    'success' => false,
                    'error' => 'Rate not found'
                ];
                $hasFailure = true;
                continue;
            }

            $metaPriceSAR = $rate->currency === 'USD'
                ? CurrencyHelper::convertDollarToSAR($rate->price)
                : $rate->price;

            $customRate = null;

            if (in_array($chargeType, ['markup_only', 'full'])) {
                $customRate = OrganizationWhatsappRateLine::where('organization_id', $organizationId)
                    ->where('whatsapp_rate_line_id', $rate->id)
                    ->first();

                if (!$customRate && (empty($settings->markup_percentage) || $settings->markup_percentage < 0.01)) {
                    $details[] = [
                        'contact_id' => $contact->id,
                        'phone' => $phone,
                        'success' => false,
                        'error' => "Missing custom rate or markup percentage for {$country->name}"
                    ];
                    $hasFailure = true;
                    continue;
                }
            }

            $cost = match ($chargeType) {
                'meta_only' => $metaPriceSAR,

                'markup_only' => $customRate
                ? max($customRate->custom_price - $metaPriceSAR, 0)
                : ceil($metaPriceSAR * ($settings->markup_percentage / 100) * 10000) / 10000,

                'full' => $customRate
                ? $customRate->custom_price
                : ceil($metaPriceSAR * (1 + ($settings->markup_percentage / 100)) * 10000) / 10000,

                default => null,
            };

            $total += $cost;

            $details[] = [
                'contact_id' => $contact->id,
                'phone' => $phone,
                'success' => true,
                'cost' => $cost,
                'country' => $country->name,
                'category' => $category,
                'pricing_mode' => $chargeType,
            ];
        }

        return [
            'success' => !$hasFailure,
            'total_cost' => $total,
            'currency' => 'SAR',
            'contacts_count' => $contacts->count(),
            'pricing_mode' => $chargeType,
            'details' => $details
        ];
    }

    public function getPhoneNumberIdentifier($contact): ?string
    {
        return Identifier::where('contact_id', $contact->id)
            ->where('key', ContactEntity::IDENTIFIER_TYPE_PHONE)
            ->first()?->value ?? null;
    }
    public function calculateCostForListsV2($workspace, $contacts): array
    {
        $organizationId = $workspace->organization_id;

        // Load settings
        $settings = OrganizationWhatsappSetting::firstOrCreate(
            ['organization_id' => $organizationId],
            [
                'use_custom_rates' => false,
                'who_pays_meta' => 'client',
                'wallet_charge_mode' => 'none',
            ]
        );

        $chargeType = $this->resolveWhatsappChargeType($settings);
        if (!$chargeType) {
            return ['success' => false, 'error' => 'Invalid WhatsApp billing settings'];
        }

        $category = 'marketing';

        // --------------------------------------
        // 1) Preload all required data
        // --------------------------------------

        // Countries indexed by iso2
        $countries = WorldCountry::select('id', 'iso2', 'name')
            ->get()
            ->keyBy(fn($c) => strtoupper($c->iso2));

        // Rates indexed by country_id
        $rates = WhatsappRateLine::where('category', $category)
            ->where('pricing_model', 'PMP')
            ->get()
            ->keyBy('world_country_id');

        // Custom rates indexed by whatsapp_rate_line_id
        $customRates = OrganizationWhatsappRateLine::where('organization_id', $organizationId)
            ->get()
            ->keyBy('whatsapp_rate_line_id');

        // --------------------------------------
        // 2) Begin calculation
        // --------------------------------------
        $total = 0;
        $details = [];
        $hasFailure = false;

        foreach ($contacts as $contact) {

            // $contactEntity = ContactEntity::find($contact->id);
            $phone = $contact[0]->identifier_value;
            \Log::info('phone', ['phone' => $phone]);
            if (!$phone) {
                $details[] = [
                    'contact_id' => $contact->id,
                    'success' => false,
                    'error' => 'Missing phone number'
                ];
                $hasFailure = true;
                continue;
            }

            $iso2 = strtoupper($this->getCountryCodeFromPhoneNumber($phone));
            if (!$iso2 || !isset($countries[$iso2])) {
                $details[] = [
                    'contact_id' => $contact->id,
                    'phone' => $phone,
                    'success' => false,
                    'error' => 'Cannot resolve country'
                ];
                $hasFailure = true;
                continue;
            }

            $country = $countries[$iso2];
            $rate = $rates[$country->id] ?? null;

            if (!$rate) {
                $details[] = [
                    'contact_id' => $contact->id,
                    'phone' => $phone,
                    'success' => false,
                    'error' => 'Rate not found'
                ];
                $hasFailure = true;
                continue;
            }

            // Convert price
            $metaPriceSAR = $rate->currency === 'USD'
                ? CurrencyHelper::convertDollarToSAR($rate->price)
                : $rate->price;

            // Custom rate if needed
            $customRate = $customRates[$rate->id] ?? null;

            $cost = match ($chargeType) {
                'meta_only' => $metaPriceSAR,

                'markup_only' => $customRate
                ? max($customRate->custom_price - $metaPriceSAR, 0)
                : ceil($metaPriceSAR * ($settings->markup_percentage / 100) * 10000) / 10000,

                'full' => $customRate
                ? $customRate->custom_price
                : ceil($metaPriceSAR * (1 + ($settings->markup_percentage / 100)) * 10000) / 10000,

                default => null,
            };

            $total += $cost;

            $details[] = [
                'contact_id' => $contact[0]->id,
                'phone' => $phone,
                'success' => true,
                'cost' => $cost,
                'country' => $country->name,
                'category' => $category,
                'pricing_mode' => $chargeType,
            ];
        }

        return [
            'success' => !$hasFailure,
            'total_cost' => $total,
            'currency' => 'SAR',
            'contacts_count' => count($contacts),
            'pricing_mode' => $chargeType,
            'details' => $details,
        ];
    }


    /**
     * Deduct AI usage cost from organization wallet using translation quota.
     *
     * @param \App\Models\Workspace $workspace
     * @param array $result Response from parseChatgptResponse
     * @param string $feature Feature name (e.g., "suggest_reply", "summarize", "TRANSLATE_TEXT")
     * @return WalletTransaction|null
     */
    protected function chargeAiUsage($workspace, array $result, string $feature = 'ai_usage', $message = null): ?WalletTransaction
    {
        $organization = $workspace->organization;
        $organizationId = $workspace->organization_id;

        // Get translation quota for billing (same as translation billing)
        $extra = \App\Models\OrganizationWhatsappExtra::where('organization_id', $organizationId)->first();
        $cost = $extra->translation_quota ?? 0;

        if ($cost <= 0) {
            return null;
        }

        $wallet = $this->getObjectWallet(
            $organization,
            Service::where('name', \App\Enums\Service::OTHER)->value('id')
        );

        if (!$wallet || !$wallet->hasSufficientFunds($cost)) {
            return null;
        }

        // Reserve and confirm funds (same as translation billing)
        $transaction = $this->reserveFunds($wallet, $cost, [
            'type' => 'ai_usage',
            'workspace_id' => $workspace->id,
            'feature' => $feature,
            'model' => $result['model'] ?? null,
            'input_tokens' => $result['usage']['input_tokens'] ?? 0,
            'output_tokens' => $result['usage']['output_tokens'] ?? 0,
        ], "AI charge for {$feature}");

        if ($transaction) {
            $this->confirmFunds($transaction, "AI charge for {$feature}");

            // Create billing record under translation type
            \App\Models\MessageBilling::create([
                'messageable_id' => isset($message) ? $message->id : 0,
                'messageable_type' => isset($message) ? get_class($message) : 'text',
                'type' => \App\Models\MessageBilling::TYPE_TRANSLATION,
                'cost' => $cost,
                'is_billed' => true,
            ]);
        }

        return $transaction;
    }

}

