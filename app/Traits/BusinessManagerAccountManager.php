<?php

namespace App\Traits;

use App\Constants\Meta;
use App\Models\BusinessManagerAccount;
use Http;

trait BusinessManagerAccountManager
{
    public function fetchAndStoreBusinessManagerDetails($businessManagerId, $accessToken): BusinessManagerAccount|array|null|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model
    {
        $endpoint = "https://graph.facebook.com/v23.0/{$businessManagerId}";
        $accessToken = Meta::ACCESS_TOKEN;
        $response = Http::withToken($accessToken)->get($endpoint, [
            'fields' => 'is_hidden,link,name,two_factor_type,verification_status,vertical,vertical_id,profile_picture_uri',
        ]);

        if ($response->successful()) {
            $data = json_decode($response->body());
            $account = BusinessManagerAccount::find($businessManagerId);

            if ($account) {
                // Update existing record
                $account->update([
                    'name' => $data->name,
                    'link' => $data->link,
                    'is_hidden' => $data->is_hidden ?? null,
                    'two_factor_type' => $data->two_factor_type?? 'none',
                    'vertical' => $data->vertical,
                    'vertical_id' => $data->vertical_id,
                    'verification_status' => $data->verification_status,
                    'profile_picture_uri' => $data->profile_picture_uri,
                ]);
            } else {
                // Create a new record
               $account = BusinessManagerAccount::create([
                    'id' => $businessManagerId,
                    'name' => $data->name,
                    'link' => $data->link,
                    'is_hidden' => $data->is_hidden ?? null,
                    'two_factor_type' => $data->two_factor_type?? 'none',
                    'vertical' => $data->vertical,
                    'vertical_id' => $data->vertical_id,
                    'verification_status' => $data->verification_status,
                    'profile_picture_uri' => $data->profile_picture_uri,
                ]);
            }
            return $account;
        }

        return null;
    }
}
