<?php

namespace App\Http\Responses;

use App\Enums\Service as EnumService;
use App\Http\Interfaces\DataInterface;
use App\Models\Organization;
use App\Models\Workspace;

class Wallet extends DataInterface
{

    public const TYPE_MAIN = 'main';
    public const TYPE_SUB = 'sub';

    public string $id;
    public string $service;
    public string $service_decription;
    public string|null|float $amount;
    // public string $system;
    public ?string $status;
    public ?string $currency_code;
    public string $type;

    public string $display_name;

    public function __construct(\App\Models\Wallet $wallet)
    {
        $this->id = $wallet->id;
        $this->service = $wallet->service->name;
        $this->type = $wallet->type == "primary" ? self::TYPE_MAIN : self::TYPE_SUB;
        /*
         * @todo depends to wallet , later
         */
        $this->amount = $wallet->service->name === EnumService::SMS ? $wallet->sms_point : $wallet->amount;
        $this->currency_code = $wallet->service->name === EnumService::SMS ? 'POINTS' : $wallet->currency_code??'SAR';
        $this->status = $wallet->status;
        $this->service_decription = $wallet->service->decription;
        $this->display_name =  $wallet->name;

    }

    private function setDisplayName(\App\Models\Wallet $wallet): void
    {
        $service = $wallet->service->name == "other" ? "" : "(" . $wallet->service->name . ")";
     

        $this->display_name = $wallet->name . " " . $service;
    }


}
