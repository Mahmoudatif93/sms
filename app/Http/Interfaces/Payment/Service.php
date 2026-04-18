<?php
namespace App\Http\Interfaces\Payment;
use App\Models\Organization;
use App\Models\WalletTransaction;
use App\Models\User;
use App\Enums\WalletTransactionStatus;
use App\Models\Workspace;

abstract class Service
{
    public function getTotalAmount($amount)
    {
        return ($amount + ($amount * 15 / 100));
    }

    abstract public function getName(): string;

    abstract public function addToWallet($amount, $points,Organization $organization);
    abstract public function ChangeWallet(Workspace $workspace,$amount,$points,$sms_price);
    abstract public function ChangeWalletV2($object,$amount,$points,$sms_price);
    abstract public function setQuota($object, $amount, $sms_price);

    abstract public function requestConversion(User $user,$amount);
    abstract public function getQuota(User $user, $amount, $price);
    public function AddToWalletLog2($amount, $wallet_id, $transaction_type, $status, $quota, $description = "")
    {

        $walletTransaction = new WalletTransaction();
        $walletTransaction->amount = $amount;
        $walletTransaction->wallet_id = $wallet_id;
        $walletTransaction->transaction_type = $transaction_type;
        $walletTransaction->status = $status;
        $walletTransaction->description = $description;
        $walletTransaction->save();

        $quota->walletTransactions()->save($walletTransaction);
        return $walletTransaction;
    }

    public function getNetAmount($amountWithTax)
    {
        return $amountWithTax / 1.15;
    }
}
