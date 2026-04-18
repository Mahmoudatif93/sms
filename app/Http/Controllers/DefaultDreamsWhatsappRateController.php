<?php

namespace App\Http\Controllers;

use App\Models\DefaultDreamsWhatsappRate;
use Illuminate\Http\Request;

class DefaultDreamsWhatsappRateController extends BaseApiController
{
    /**
     * Get all default WhatsApp rates.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $rates = DefaultDreamsWhatsappRate::with(['country', 'baseRate'])
            ->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(
            success: true,
            message: 'Default WhatsApp rates retrieved successfully.',
            items: $rates
        );
    }

    /**
     * Get a specific default WhatsApp rate.
     */
    public function show($id)
    {
        $rate = DefaultDreamsWhatsappRate::with(['country', 'baseRate'])->findOrFail($id);

        return $this->response(
            success: true,
            message: 'Default WhatsApp rate retrieved successfully.',
            data: $rate
        );
    }

    /**
     * Create a new default WhatsApp rate.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:country,id',
            'base_whatsapp_rate_id' => 'required|exists:whatsapp_rates,id',
            'custom_marketing_rate' => 'nullable|numeric|min:0',
            'custom_utility_rate' => 'nullable|numeric|min:0',
            'custom_authentication_rate' => 'nullable|numeric|min:0',
            'custom_authentication_international_rate' => 'nullable|numeric|min:0',
            'custom_service_rate' => 'nullable|numeric|min:0',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:effective_date',
            'frequency' => 'nullable|string',
            'status' => 'required|string|in:active,inactive',
        ]);

        $rate = DefaultDreamsWhatsappRate::create($validated);

        return $this->response(
            success: true,
            message: 'Default WhatsApp rate created successfully.',
            data: $rate,
            statusCode: 201
        );
    }

    /**
     * Update an existing default WhatsApp rate.
     */
    public function update(Request $request, $id)
    {
        $rate = DefaultDreamsWhatsappRate::findOrFail($id);

        $validated = $request->validate([
            'custom_marketing_rate' => 'nullable|numeric|min:0',
            'custom_utility_rate' => 'nullable|numeric|min:0',
            'custom_authentication_rate' => 'nullable|numeric|min:0',
            'custom_authentication_international_rate' => 'nullable|numeric|min:0',
            'custom_service_rate' => 'nullable|numeric|min:0',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:effective_date',
            'frequency' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $rate->update($validated);

        return $this->response(
            success: true,
            message: 'Default WhatsApp rate updated successfully.',
            data: $rate
        );
    }

    /**
     * Delete a default WhatsApp rate.
     */
    public function destroy($id)
    {
        $rate = DefaultDreamsWhatsappRate::findOrFail($id);
        $rate->delete();

        return $this->response(
            success: true,
            message: 'Default WhatsApp rate deleted successfully.'
        );
    }
}
