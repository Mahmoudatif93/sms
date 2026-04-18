<?php
namespace App\Class\payment;

use App\Http\Interfaces\Payment\Service;

class ServiceFactory 
{
    public static function getService(string $serviceType): Service
    {
        return match($serviceType) {
            'sms' => new SmsService(),
            'points_to_currency' => new SmsService(),
            default => new OtherService(),
        };
    }


}