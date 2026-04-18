<?php

namespace App\Traits;

use App\Http\Responses\ValidatorErrorResponse;
use App\Models\Connector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;


trait ConnectorManager
{

    use ResponseManager;

    /**
     * Validate the connector request based on platform rules.
     *
     * @param Request $request
     * @return array
     * @throws ValidationException
     */
    public function validateConnectorRequest(Request $request): array
    {
        $rules = $this->getPlatformValidationRules($request);

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw new ValidationException(
                validator: $validator,
                response: $this->errorResponse(
                    message: 'Validation Error(s)',
                    errors: new ValidatorErrorResponse($validator->errors()->toArray()),
                    statusCode: 422
                )
            );
        }

        return $validator->validated();
    }

    /**
     * Get platform-specific validation rules.
     *
     * @param Request $request
     * @return array
     */
    private function getPlatformValidationRules(Request $request): array
    {
        return [
            'workspace_id' => 'required|uuid|exists:workspaces,id',
            'name' => 'nullable|string',
            'status' => 'nullable|string',
            'region' => 'nullable|string',
            'platform' => 'required|string|in:whatsapp,sms,livechat,messenger,ticketing',

            // Shared between WhatsApp and Messenger
            'code' => 'required_if:platform,whatsapp,messenger|string',

            // WhatsApp specific
            'whatsapp_business_account_id' => [
                'required_if:platform,whatsapp',
                'string',
                Rule::unique('whatsapp_configurations', 'whatsapp_business_account_id'),
            ],

            'whatsapp_phone_number_id' => [
                'required_if:platform,whatsapp',
                'string',
                Rule::unique('whatsapp_configurations', 'primary_whatsapp_phone_number_id'),
            ],

            // LiveChat specific
            'theme_color' => 'nullable|string',
            'logo_url' => 'nullable|url',
            'welcome_message' => 'nullable|string|max:255',
            'offline_message' => 'nullable|string|max:255',
            'position' => 'nullable|string|in:left,right,bottom',
            'language' => 'nullable|string|size:2',
            'allowed_domains' => 'nullable|array',
        ];
    }

    private function createBaseConnector(array $data): Connector
    {
        return Connector::create([
            'workspace_id' => $data['workspace_id'],
            'name' => $data['name'] ?? 'Connector_' . $data['platform'],
            'status' => $data['platform'] === 'ticketing' ? 'active' : ($data['status'] ?? 'pending'),
            'region' => $data['region'] ?? null,
        ]);
    }
}
