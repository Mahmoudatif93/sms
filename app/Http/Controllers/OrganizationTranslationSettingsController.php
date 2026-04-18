<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrganizationTranslationSettingsController extends BaseApiController
{
    /**
     * Get organization translation settings.
     *
     * @param Organization $organization
     * @return JsonResponse
     */
    public function show(Organization $organization): JsonResponse
    {
        return $this->response(data: [
            'auto_translation_enabled' => $organization->isAutoTranslationEnabled(),
        ]);
    }

    /**
     * Update organization translation settings.
     *
     * @param Request $request
     * @param Organization $organization
     * @return JsonResponse
     */
    public function update(Request $request, Organization $organization): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'auto_translation_enabled' => ['required', 'boolean'],
        ]);

        if ($validator->fails()) {
            return $this->response(
                false,
                'Validation Error(s)',
                $validator->errors()->toArray(),
                400
            );
        }

        $organization->update([
            'auto_translation_enabled' => $request->boolean('auto_translation_enabled'),
        ]);

        return $this->response(
            message: 'Translation settings updated successfully.',
            data: [
                'auto_translation_enabled' => $organization->isAutoTranslationEnabled(),
            ]
        );
    }
}
