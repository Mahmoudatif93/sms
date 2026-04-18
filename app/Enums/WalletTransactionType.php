<?php

namespace App\Enums;
class WalletTransactionType {
    const CHARGE = 'charge';
    const USAGE = 'usage';

    public static function getDescription($value)
    {
        $descriptions = [
            self::CHARGE => 'Charge transaction',
            self::USAGE => 'Usage transaction',
        ];

        return $descriptions[$value] ?? 'Unknown transaction type';
    }
}
