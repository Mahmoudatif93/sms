<?php

namespace App\Services\Sms;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Http\Interfaces\Sms\AbstractNumberProcessor;
use App\Models\IAMList;
use App\Models\ContactEntity;
class GroupNumberProcessor extends AbstractNumberProcessor
{
    public function process($number, &$entries, $messageLong, &$numberArr,$message,$countries)
    {
        $groupId = substr($number, 1);
        $groupContacts = $this->getGroupContactsV2($groupId);
        foreach ($groupContacts as $contact) {
            $number = $this->processNumber($contact->identifiers->toArray()[0]['number']);
            if (!isset($numberArr[$number])) {
                $country = $this->processCountry($number, $entries, $messageLong,$countries);
                if (!$country) {
                    $this->addUndefinedCountry($entries);
                }else{
                    $numberArr [] =[
                        'number'=> $contact['number'],
                        'country' => $country['id'],
                        'cost' => ($country['price']*$messageLong)
                    ];
                }
               
            }
        }
    }

    private function getGroupContacts($groupId, $userId)
    {
        $groupContacts = Contact::where(['group_id' => $groupId, 'user_id' => $userId])->get()->toArray();
        $subGroups = ContactGroup::where('group_id', $groupId)->pluck('id')->toArray();
        if (!empty($subGroups)) {
            $subGroupContacts = Contact::whereIn('group_id', $subGroups)->where('user_id', $userId)->get()->toArray();
            $groupContacts = array_merge($groupContacts, $subGroupContacts);
        }
        return $groupContacts;
    }

    private function getGroupContactsV2($listId)
    {
        $list = IAMList::where('id',$listId)->first();
        return $list->contacts()
        ->whereHas('identifiers', function ($query) {
            $query->where('key', ContactEntity::IDENTIFIER_TYPE_PHONE);
        })
        ->with(['identifiers' => function ($query) {
            $query->where('key', ContactEntity::IDENTIFIER_TYPE_PHONE);
        }])
        ->get();
    }
       
    
}
