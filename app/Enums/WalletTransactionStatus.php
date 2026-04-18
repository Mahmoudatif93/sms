<?php

namespace App\Enums;
class WalletTransactionStatus {
    const ACTIVE = 'active';
    const PENDING = 'pending';
    const CANCELED = 'canceled';

    public static function getDescription($value)
    {
        $descriptions = [
            self::ACTIVE => 'Active',
            self::PENDING => 'Pending',
            self::CANCELED => 'Canceled',
        ];

        return $descriptions[$value] ?? 'Unknown transaction type';
    }
}
