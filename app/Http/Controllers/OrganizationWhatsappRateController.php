<?php

namespace App\Http\Controllers;

use App\Http\Responses\WhatsappRateLineResponse;
use App\Models\Organization;
use App\Models\OrganizationWhatsappRate;
use App\Models\WhatsappRateLine;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrganizationWhatsappRateController extends BaseApiController
{

    public function index(Request $request, Organization $organization)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $categoryFilter = $request->get('category');
        $countryNameFilter = $request->get('country_name');

        if ($organization->usesCustomWhatsappRates()) {
            $query = $organization->whatsappRateLines()
                ->where('pricing_model', '=', 'PMP');

            if ($categoryFilter) {
                $query->where('category', $categoryFilter);
            }

            if ($countryNameFilter) {
                $query->where('world_country_id', '=', $countryNameFilter);
            }

            $rates = $query->orderBy('country_name')->paginate($perPage, ['*'], 'page', $page);
        } else {
            $query = WhatsappRateLine::with('country')
                ->where('pricing_model', '=', 'PMP');

            if ($categoryFilter) {
                $query->where('category', $categoryFilter);
            }

            if ($countryNameFilter) {
                $query->where('world_country_id', '=' , $countryNameFilter);
            }

            $rates = $query->orderBy('world_country_id')->paginate($perPage, ['*'], 'page', $page);
        }

        $response = $rates->getCollection()->map(
            fn($rate) => new WhatsappRateLineResponse($rate)
        );

        $rates->setCollection($response);

        return $this->paginateResponse(
            true,
            'Organization WhatsApp rates retrieved successfully.',
            $rates
        );
    }

    /**
     * Get a specific WhatsApp rate for an organization.
     */
    public function show(Organization $organization, $id)
    {
        $rate = $organization->whatsappRates()->with(['country', 'baseRate'])->findOrFail($id);

        return $this->response(
            success: true,
            message: 'Organization WhatsApp rate retrieved successfully.',
            data: $rate
        );
    }

    /**
     * Create a new WhatsApp rate for an organization.
     */
    public function store(Request $request, Organization $organization)
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
            'frequency' => 'nullable|string|in:daily,weekly,monthly,yearly',
            'status' => 'required|in:active,inactive',
        ]);

        $validated['organization_id'] = $organization->id;

        $rate = OrganizationWhatsappRate::create($validated);

        return $this->response(
            success: true,
            message: 'Organization WhatsApp rate created successfully.',
            data: $rate,
            statusCode: 201
        );
    }


    /**
     * Update an existing WhatsApp rate for an organization.
     */
    public function update(Request $request, Organization $organization, $id)
    {
        $rate = $organization->whatsappRates()->findOrFail($id);

        $validated = $request->validate([
            'custom_marketing_rate' => 'nullable|numeric|min:0',
            'custom_utility_rate' => 'nullable|numeric|min:0',
            'custom_authentication_rate' => 'nullable|numeric|min:0',
            'custom_authentication_international_rate' => 'nullable|numeric|min:0',
            'custom_service_rate' => 'nullable|numeric|min:0',
            'effective_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:effective_date',
            'frequency' => 'nullable|string|in:daily,weekly,monthly,yearly',
            'status' => 'required|in:active,inactive',
        ]);

        $rate->update($validated);

        return $this->response(
            success: true,
            message: 'Organization WhatsApp rate updated successfully.',
            data: $rate
        );
    }

    /**
     * Delete a WhatsApp rate for an organization.
     */
    public function destroy(Organization $organization, $id)
    {
        $rate = $organization->whatsappRates()->findOrFail($id);
        $rate->delete();

        return $this->response(
            success: true,
            message: 'Organization WhatsApp rate deleted successfully.'
        );
    }

}
