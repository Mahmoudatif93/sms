<?php

namespace App\Traits;

use App\Enums\Service as EnumService;
use App\Helpers\CurrencyHelper;
use App\Models\Conversation;
use App\Models\Country;
use App\Models\Service;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationBilling;
use App\Models\WhatsappRate;
use App\Models\WorldCountry;
use App\Traits\WhatsappPhoneNumberManager;
use Illuminate\Support\Facades\Auth;
trait WhatsappBillingTrait
{
    use WhatsappPhoneNumberManager;

    public function getCost($type, $country_id)
    {
        // Define an array to map the type to the corresponding column
        $columns = [
            'marketing' => 'marketing',
            'utility' => 'utility',
            'authentication' => 'authentication',
            'authentication_international' => 'authentication_international',
            'service' => 'service',
        ];
        // Check if the type is valid
        if (!array_key_exists($type, $columns)) {
            return null;
        }
        // Retrieve the cost and currency from the specified column for the given country
        $result = WhatsappRate::where('country_id', $country_id)
            ->select($columns[$type])
            ->value($columns[$type]);

        return $result;
    }

    public function WhatsappWallet($type, $whatsappConversationID, $phoneNumber, $conversationID)
    {




        // Format PhoneNumber First

        $symbol = $this->getCountryCodeFromPhoneNumber($phoneNumber);
        $worldCountry = WorldCountry::where('iso2', $symbol)->first();
        $whatsappConversation = WhatsappConversation::findOrFail($whatsappConversationID);
        $conversation = Conversation::findOrFail($conversationID);


        return $conversation;

//        $query = $organization->whatsappRateLines()
//            ->where('effective_date', '<=', $now)
//            ->where(function ($q) use ($now) {
//                $q->whereNull('expiry_date')->orWhere('expiry_date', '>', $now);
//            });
//
//        if ($categoryFilter) {
//            $query->where('category', $categoryFilter);
//        }
//
//        if ($countryNameFilter) {
//            $query->where('countries.id', '=', $countryNameFilter);
//        }
//        // get organization rates with world Country and type
//        //
//        return $worldCountry;
//        $worldCountry = WorldCountry::where()


//        $country_id = Country::where('symbol', $symbol)->first()->id;
//        if (!$this->getCost($type, $country_id)) {
//            return $this->response(false, 'msg error no cost found', [], 400);
//        }
//        $Dollarcost = $this->getCost($type, $country_id);
//        $serviceId = Service::where('name', EnumService::OTHER)->value('id');
//        $wallet = Wallet::where(['user_id' => Auth::id(), 'service_id' => $serviceId, 'status' => 'active'])->first();
//
//        if (!$wallet) {
//            return $this->response(false, trans('message.msg_error_no_wallet_found'), [], 400);
//        }
//        try {
//            $cost = CurrencyHelper::convertDollarToSAR($Dollarcost);
//        } catch (\Exception $e) {
//            return $this->response(false, $e->getMessage(), [], 400);
//        }
//        if ($wallet->amount >= $cost) {
//            $charge = auth()->user()->addBalanceCurrency(-$cost, "Whatsapp Billing ($user->username)");
//            if ($charge) {
//                $entity_type = 'Wallet';
//                $whatsbilling = new WhatsappConversationBilling([
//                    'conversation_id' => $conversationID,
//                    'type' => $type,
//                    'cost' => $cost,
//                    'original_cost' => $Dollarcost,
//                    'currency' => $symbol,
//                    'billable' => true,
//                ]);
//                // Associate the wallet with the paymentsender
//                $whatsbilling->walletable()->associate($wallet);
//                $whatsbilling->save();
//            }
//        }
    }
}
