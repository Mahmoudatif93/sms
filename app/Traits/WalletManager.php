<?php

namespace App\Traits;

use App\Models\Wallet;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Workspace;
use App\Models\WalletTransaction;
use App\Models\PreChatForm;
use App\Models\PreChatFormField;
use App\Models\PostChatForm;
use App\Models\PostChatFormField;
use App\Enums\Service as EnumService;
use App\Enums\WalletTransactionType;
use App\Enums\WalletTransactionStatus;
use App\Jobs\LogWorkspaceBalance;

trait WalletManager
{

    /**
     * Check if two wallets belong to the same organization
     *
     * @param Wallet $otherWallet
     * @return bool
     */
    public function belongsToSameOrganization(Wallet $fromWallet, Wallet $towallet): bool
    {
        $fromOrganizatioID = $fromWallet->wallettable instanceof Organization ? $fromWallet->wallettable->id : $fromWallet->wallettable->organization_id;
        $toOrganizatioID = $towallet->wallettable instanceof Workspace ? $towallet->wallettable->organization_id : $towallet->wallettable->id;
        return $fromOrganizatioID == $toOrganizatioID;
    }


    /**
     * Get the workspace wallet based on a given wallet and optional service ID
     *
     * This method retrieves a wallet associated with a workspace through wallet assignments.
     * It can optionally filter by a specific service ID.
     *
     * @param \App\Models\Wallet $wallet The source wallet to check assignments for
     * @param int|null $serviceId Optional service ID to filter the wallet by
     * @return \App\Models\Wallet|null Returns the associated workspace wallet or null if not found
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If the wallet assignments relation is not found
     */
    public function getWorkspaceWallet(Workspace $workspace, ?int $serviceId = null)
    {
        return $workspace->walletAssignments()
            ->whereHas('wallet', function ($query) use ($serviceId) {
                if ($serviceId) {
                    $query->where('service_id', $serviceId);
                }
            })
            ->with([
                'wallet' => function ($query) use ($serviceId) {
                    if ($serviceId) {
                        $query->where('service_id', $serviceId);
                    }
                }
            ])
            ->first()?->wallet;
    }

    public function getObjectWallet($object, ?int $serviceId = null, ?int $user_id = null)
    {
        $organization = null;
        $is_owner = false;

        if ($object instanceof Organization) {
            $is_owner = $object->owner_id === $user_id;
            $organization = $object;
        } elseif ($object instanceof Workspace) {
            $is_owner = $object->organization->owner_id === $user_id;
            $organization = $object->organization;
        }

        if ($is_owner && $organization) {
            return $organization->walletAssignments()
                ->whereHas('wallet', function ($query) use ($serviceId) {
                    if ($serviceId) {
                        $query->where('service_id', $serviceId);
                    }
                })
                ->with([
                    'wallet' => function ($query) use ($serviceId) {
                        if ($serviceId) {
                            $query->where('service_id', $serviceId);
                        }
                    }
                ])
                ->first()?->wallet;
        }
        // First check if user_id is provided and try to get user's wallet
        if ($user_id) {
            $userWallet = OrganizationUser::where('user_id', $user_id)
                ->where('organization_id', $this->getOrganizationId($object))
                // ->where('has_special_wallet', true)
                ->first()?->walletAssignments()
                ->whereHas('wallet', function ($query) use ($serviceId) {
                    if ($serviceId) {
                        $query->where('service_id', $serviceId);
                    }
                })
                ->with([
                    'wallet' => function ($query) use ($serviceId) {
                        if ($serviceId) {
                            $query->where('service_id', $serviceId);
                        }
                    }
                ])
                ->first()?->wallet;

            if ($userWallet) {
                return $userWallet;
            }
        }


        if (!($object instanceof Organization) && !($object instanceof Workspace) && !($object instanceof OrganizationUser)) {
            throw new \InvalidArgumentException('Object must be an Organization , Workspace or OrganizationUser instance');
        }
        return $object->walletAssignments()
            ->whereHas('wallet', function ($query) use ($serviceId) {
                if ($serviceId) {
                    $query->where('service_id', $serviceId);
                }
            })
            ->with([
                'wallet' => function ($query) use ($serviceId) {
                    if ($serviceId) {
                        $query->where('service_id', $serviceId);
                    }
                }
            ])
            ->first()?->wallet;
    }


    public function changeBalance(Wallet $wallet, float $amount, string $service, string $reason, float $sms_price = 0, ?string $balanceExpireDate = null): bool
    {
        return match ($service) {
            EnumService::SMS => $this->changeBalanceSms($wallet, $amount, $reason, $sms_price, $balanceExpireDate),
            EnumService::OTHER => $this->changeBalanceOther($wallet, $amount, $reason),
            default => response()->json(['error' => 'Unsupported Platform'], 400),
        };
    }

    protected function changeBalanceSms(Wallet $wallet, float $amount, string $reason, float $sms_price = 0, ?string $balanceExpireDate = null): bool
    {

        $lockedWallet = Wallet::where('id', $wallet->id)
            ->lockForUpdate()
            ->first();

        if (!$lockedWallet) {
            return false;
        }
        if ($lockedWallet->sms_point + $amount <= 0) {
            return false;
        }
        $lockedWallet->sms_point += $amount;
        $lockedWallet->amount += ($amount * $sms_price);
        $lockedWallet->save();

        dispatch(new LogWorkspaceBalance($wallet, $amount, $reason, $sms_price, $balanceExpireDate))->onQueue('sms-normal');
        $this->AddToWalletLog($amount, $lockedWallet->id, $amount >= 0 ? WalletTransactionType::CHARGE : WalletTransactionType::USAGE, WalletTransactionStatus::ACTIVE, $reason);
        return true;
    }

    protected function changeBalanceOther(Wallet $wallet, float $amount, ?string $reason, ?array $meta = null,?string $category = null): bool
    {

        $lockedWallet = Wallet::where('id', $wallet->id)
            ->lockForUpdate()
            ->first();
        if (!$lockedWallet) {
            return false;
        }
        if ($lockedWallet->amount + $amount < 0) {
            return false;
        }
        $lockedWallet->amount += $amount;
        $lockedWallet->save();
        $this->AddToWalletLog($amount, $lockedWallet->id, $amount >= 0 ? WalletTransactionType::CHARGE : WalletTransactionType::USAGE, WalletTransactionStatus::ACTIVE, $reason, $meta, $category);
        return true;
    }

    public function transferWallet(Wallet $from, wallet $to, float $amount, string $service, string $operation): bool
    {
        return match ($service) {
            EnumService::SMS => $this->transferBalanceSms($from, $to, $amount, $operation),
            EnumService::OTHER => $this->transferBalanceOther($from, $to, $amount, $operation),
            default => response()->json(['error' => 'Unsupported Platform'], 400),
        };
    }

    protected function transferBalanceSms(Wallet $fromWallet, Wallet $toWallet, int $points, $operation): bool
    {
        // Determine source and destination wallets based on operation
        $sourceWallet = $operation === 'ADD' ? $fromWallet : $toWallet;
        $destWallet = $operation === 'ADD' ? $toWallet : $fromWallet;

        if ($points <= 0 || $sourceWallet->sms_point < $points) {
            return false;
        }
        $stillPoint = $points;

        $quotas = $sourceWallet->smsquotas()->where(function ($q) {
            $q->where('status', 'active')
                ->where('available_points', '>', 0)
                ->orderBy('expire_date')
            ;
        })->get();
        if ($quotas->count() == 0) {
            return false;
        }
        foreach ($quotas as $quota) {
            if ($stillPoint == 0) {
                break;
            }
            if ($quota->available_points >= $stillPoint) {
                $didcate_point = $stillPoint;
                $stillPoint = 0;
            } else {
                $didcate_point = $quota->available_points;
                $stillPoint = $stillPoint - $quota->available_points;
            }
            $this->changeBalance($sourceWallet, -1 * $didcate_point, EnumService::SMS, "Transfer Balance to {$destWallet->id}", $quota->sms_price, null);
            $this->changeBalance($destWallet, $didcate_point, EnumService::SMS, "Transfer Balance from {$sourceWallet->id}", $quota->sms_price, $quota->expire_date);
        }

        return true;
    }

    protected function transferBalanceOther(Wallet $fromWallet, Wallet $toWallet, int $amount, string $operation)
    {
        $sourceWallet = $operation === 'ADD' ? $fromWallet : $toWallet;
        $destWallet = $operation === 'ADD' ? $toWallet : $fromWallet;
        $this->changeBalance($sourceWallet, -1 * $amount, EnumService::OTHER, "Transfer Balance to {$destWallet->id}", 0, null);
        $this->changeBalance($destWallet, $amount, EnumService::OTHER, "Transfer Balance from {$sourceWallet->id}", 0, null);
        return true;
    }

    protected function AddToWalletLog($amount, $wallet_id, $transaction_type, $status, $description = "", $meta = null,$category = null)
    {

        $walletTransaction = new WalletTransaction();
        $walletTransaction->amount = $amount;
        $walletTransaction->wallet_id = $wallet_id;
        $walletTransaction->transaction_type = $transaction_type;
        $walletTransaction->status = $status;
        $walletTransaction->description = $description;
        $walletTransaction->meta = $meta;
        $walletTransaction->category = $category;
        $walletTransaction->save();
        return $walletTransaction;
    }


    private function getOrganizationId($object): ?string
    {
        if ($object instanceof Organization) {
            return $object->id;
        } elseif ($object instanceof Workspace) {
            return $object->organization_id;
        } elseif ($object instanceof OrganizationUser) {
            return $object->organization_id;
        }

        return null;
    }

    /**
     * Create default pre-chat form for a channel
     *
     * @param string $channelId
     * @param array $validatedData
     * @return PreChatForm
     */
    private function createDefaultPreChatForm(string $channelId, string $widgetId, array $validatedData): PreChatForm
    {
        // Create the pre-chat form
        $preChatForm = PreChatForm::create([
            'channel_id' => $channelId,
            'widget_id' => $widgetId,
            'enabled' => $validatedData['pre_chat_form_enabled'] ?? true,
            'title' => $validatedData['pre_chat_form_title'] ?? __('message.Start Chat'),
            'description' => $validatedData['pre_chat_form_description'] ?? __('message.Please fill out the form below to start chatting with our team.'),
            'submit_button_text' => $validatedData['pre_chat_form_submit_button'] ?? __('message.Start Chat'),
            // 'require_fields' => $validatedData['pre_chat_form_require_fields'] ?? false,
        ]);

        // Add default fields
        $defaultFields = [
            [
                'type' => 'text',
                'name' => 'name',
                'label' => __('message.Name'),
                'placeholder' => __('message.Your name'),
                'required' => true,
                'enabled' => true,
                'order' => 0
            ],
            [
                'type' => 'email',
                'name' => 'email',
                'label' => __('message.Email'),
                'placeholder' => __('message.Your email address'),
                'required' => true,
                'enabled' => true,
                'order' => 1
            ],
            [
                'type' => 'textarea',
                'name' => 'message',
                'label' => __('message.How can we help you?'),
                'placeholder' => __('message.Please describe your question or issue'),
                'required' => false,
                'enabled' => true,
                'order' => 3
            ],
            [
                'type' => 'phone',
                'name' => 'phone',
                'label' => __('message.Phone'),
                'placeholder' => __('message.Your phone'),
                'required' => true,
                'enabled' => true,
                'order' => 2
            ]
        ];

        foreach ($defaultFields as $field) {
            $field['pre_chat_form_id'] = $preChatForm->id;
            PreChatFormField::create($field);
        }

        return $preChatForm;
    }

    /**
     * Create default post-chat form for a channel
     *
     * @param string $channelId
     * @param array $validatedData
     * @return PostChatForm
     */
    private function createDefaultPostChatForm(string $channelId, string $widgetId, array $validatedData): PostChatForm
    {
        // Create the post-chat form
        $postChatForm = PostChatForm::create([
            'channel_id' => $channelId,
            'widget_id' => $widgetId,
            'enabled' => $validatedData['post_chat_form_enabled'] ?? true,
            'title' => $validatedData['post_chat_form_title'] ?? __('message.Chat Feedback'),
            'description' => $validatedData['post_chat_form_description'] ?? __('Please rate your chat experience'),
            'submit_button_text' => $validatedData['post_chat_form_submit_button'] ?? __('message.Submit Feedback'),
            'require_fields' => $validatedData['post_chat_form_require_fields'] ?? false,
            'delay_seconds' => $validatedData['post_chat_form_delay'] ?? 0
        ]);

        // Add default fields
        $defaultFields = [
            [
                'type' => 'rating',
                'name' => 'satisfaction',
                'label' => __('message.How would you rate your chat experience?'),
                'placeholder' => '',
                'required' => true,
                'enabled' => true,
                'options' => json_encode([

                    ['value' => 1, 'label_ar' => 'ممتاز', 'label_en' => 'Excellent'],
                    ['value' => 2, 'label_ar' => 'جيد', 'label_en' => 'Good'],
                    ['value' => 3, 'label_ar' => 'متوسط', 'label_en' => 'Average'],
                    ['value' => 4, 'label_ar' => 'سيئة', 'label_en' => 'Poor'],

                ]),
                'order' => 0
            ],
            [
                'type' => 'textarea',
                'name' => 'feedback',
                'label' => __('message.Additional comments'),
                'placeholder' => __('message.Please share any additional feedback'),
                'required' => false,
                'enabled' => true,
                'order' => 1
            ],
            [
                'type' => 'checkbox',
                'name' => 'resolved',
                'label' => __('message.Was your issue resolved?'),
                'placeholder' => '',
                'required' => false,
                'enabled' => true,
                'order' => 2
            ]
        ];

        foreach ($defaultFields as $field) {
            $field['post_chat_form_id'] = $postChatForm->id;
            PostChatFormField::create($field);
        }

        return $postChatForm;
    }

    public function getOrganizationWallet(Organization $organization, ?int $serviceId = null): ?Wallet
    {
        return $organization->walletAssignments()
            ->whereHas('wallet', function ($query) use ($serviceId) {
                if ($serviceId) {
                    $query->where('service_id', $serviceId);
                }
            })
            ->with([
                'wallet' => function ($query) use ($serviceId) {
                    if ($serviceId) {
                        $query->where('service_id', $serviceId);
                    }
                }
            ])
            ->first()?->wallet;
    }
}
