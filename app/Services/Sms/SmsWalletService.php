<?php

namespace App\Services\Sms;

use App\Models\User;
use App\Models\Wallet;
use App\Traits\WalletManager;
use App\Enums\Service as EnumService;
use App\Models\Service as MService;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\WalletNotFoundException;

class SmsWalletService
{
    use WalletManager;

    /**
     * Validate user balance and deduct cost for SMS sending
     */
    public function validateAndDeductBalance(User $user, $workspace, float $cost, string $description = "SMS Campaign"): array
    {
        $wallet = $this->getUserWallet($user, $workspace);
        
        if (!$wallet) {
            throw new WalletNotFoundException('Wallet not found for user');
        }

        if (!$this->deductBalance($wallet, $cost, $description)) {
            throw new InsufficientBalanceException(trans('message.msg_error_insufficient_balance'));
        }

        return [
            'success' => true,
            'wallet' => $wallet,
            'remaining_balance' => $wallet->fresh()->sms_point
        ];
    }

    /**
     * Check if user has sufficient balance without deducting
     */
    public function checkBalance(User $user, $workspace, float $cost): bool
    {
        $wallet = $this->getUserWallet($user, $workspace);
        
        if (!$wallet) {
            return false;
        }

        return $wallet->sms_point >= $cost;
    }

    /**
     * Get user's SMS wallet
     */
    public function getUserWallet(User $user, $workspace): ?Wallet
    {
        $serviceId = MService::where('name', EnumService::SMS)->value('id');
        
        return $this->getObjectWallet($workspace, $serviceId, $user->id);
    }

    /**
     * Deduct balance from wallet
     */
    private function deductBalance(Wallet $wallet, float $cost, string $description): bool
    {
        return $this->changeBalance($wallet, -1 * $cost, "sms", $description);
    }
}
