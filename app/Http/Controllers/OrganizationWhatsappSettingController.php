<?php

namespace App\Http\Controllers;

use App\Http\Responses\ValidatorErrorResponse;
use App\Models\Organization;
use App\Models\OrganizationWhatsappSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrganizationWhatsappSettingController extends BaseApiController
{

    public function show(Organization $organization)
    {
        // Ensure default setting exists
        $organization->createDefaultWhatsappSetting();

        // Now safely retrieve the setting
        $setting = $organization->whatsappSetting;

        if (empty($setting->markup_percentage))
        {
            $setting->markup_percentage = 0.0;
            $setting->save();
        }

        return $this->response(data: $setting);
    }

    public function update(Request $request, Organization $organization)
    {
        $validator = Validator::make($request->all(), [
            'use_custom_rates' => ['required', 'boolean'],
            'who_pays_meta' => ['required', 'in:client,provider'],
            'wallet_charge_mode' => ['required', 'in:none,markup_only,meta_only,full'],
            'markup_percentage' => ['required', 'numeric', 'min:0', 'max:100'], // <-- NEW
        ]);

        if ($validator->fails()) {
            return $this->response(
                false,
                'Validation Error(s)',
                new ValidatorErrorResponse($validator->errors()->toArray()),
                400
            );
        }

        $useCustom = $request->boolean('use_custom_rates');
        $whoPays = $request->input('who_pays_meta');
        $walletMode = $request->input('wallet_charge_mode');
        $markupPercentage = $request->input('markup_percentage'); // <-- NEW

        $errorMessage = match (true) {
            // ❌ client + markup_only + use_custom_rates = false
            $whoPays === 'client' && $walletMode === 'markup_only' && !$useCustom =>
            'Invalid configuration: You’ve selected to charge the client a platform fee (markup_only), but custom rates are disabled. Please enable custom rates.',

            // ❌ client cannot use meta_only or full
            $whoPays === 'client' && in_array($walletMode, ['meta_only', 'full']) =>
            'Invalid configuration: When the client pays Meta directly, wallet charge mode must be either "none" or "markup_only".',

            // ❌ provider cannot use 'none' or 'markup_only'
            $whoPays === 'provider' && in_array($walletMode, ['none', 'markup_only']) =>
            'Invalid configuration: When the platform (provider) pays Meta, wallet charge mode must be "meta_only" or "full".',

            // ❌ provider + meta_only + use_custom_rates = true
            $whoPays === 'provider' && $walletMode === 'meta_only' && $useCustom =>
            'Invalid configuration: When charging only the Meta cost (meta_only), custom rates must be disabled.',

            // ❌ provider + full + use_custom_rates = false
            $whoPays === 'provider' && $walletMode === 'full' && !$useCustom =>
            'Invalid configuration: When charging the full cost (Meta + markup), custom rates must be enabled.',

            default => null,
        };

        if ($errorMessage) {
            return $this->response(
                false,
                $errorMessage,
                422
            );
        }

        $setting = OrganizationWhatsappSetting::updateOrCreate(
            ['organization_id' => $organization->id],
            [
                'use_custom_rates' => $useCustom,
                'who_pays_meta' => $whoPays,
                'wallet_charge_mode' => $walletMode,
                'markup_percentage' => $markupPercentage,
            ]
        );


        return $this->response(
            message: 'WhatsApp billing settings updated successfully.',
            data: $setting
        );
    }


}
