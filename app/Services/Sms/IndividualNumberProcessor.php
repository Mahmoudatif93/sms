<?php

namespace App\Services\Sms;

use App\Helpers\Sms\NumberFormatter;
use App\Http\Interfaces\Sms\AbstractNumberProcessor;

class IndividualNumberProcessor extends AbstractNumberProcessor
{
    public function process($number, &$entries, $messageLong, &$numberArr,$message,$countries)
    {
        $number = NumberFormatter::formatNumber($number);
        if (!isset($numberArr[$number])) {
            $country = $this->processCountry($number, $entries, $messageLong,$countries);
           
            if (!$country) {
                $this->addUndefinedCountry($entries);
            }else{
                $numberArr [] =[
                    'number'=> $number,
                    'country' => $country['id'],
                    'cost' => ($country['price']*$messageLong)
                ];
            }
            
            
        }
    }
}
