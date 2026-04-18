<?php

namespace App\Services\Sms;

use App\Models\AdContact;
use App\Http\Interfaces\Sms\AbstractNumberProcessor;

class AdsNumberProcessor extends AbstractNumberProcessor
{
    public function process($number, &$entries, $messageLong, &$numberArr,$message,$countries)
    {
        $tagId = substr($number, 1);
        $user = auth()->user();
        $adContacts = AdContact::whereHas('tags', function ($query) use ($tagId) {
            $query->where('id', $tagId);
        })->get()->toArray();
        foreach ($adContacts as $contact) {
            $country = $this->processCountry($contact['number'], $entries, $messageLong,$countries);
            if (!$country) {
                $this->addUndefinedCountry($entries);
            }else{
                $numberArr [] =[
                    'number'=> $contact['number'],
                    'country' => $country
                ];
            }
            
        }
    }
}
