<?php

namespace App\Http\Controllers;

use App\Http\Responses\WhatsappRateLine;
use Illuminate\Http\Request;

class WhatsappRateLinesController extends BaseApiController
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $query = \App\Models\WhatsappRateLine::query()
            ->with('country') // eager load for filtering
            ->when($request->filled('pricing_model'), fn($q) => $q->where('pricing_model', $request->get('pricing_model'))
            )
            ->when($request->filled('category'), fn($q) => $q->where('category', $request->get('category'))
            )
            ->when($request->filled('effective_date'), fn($q) => $q->where('effective_date', (int)$request->get('effective_date'))
            )
            ->when($request->filled('country_name'), fn($q) => $q->whereHas('country', fn($subQ) => $subQ->where('id', '=', $request->get('country_name'))
            )
            )
            ->when($request->filled('country_market'), fn($q) => $q->whereHas('country.metaPricingMarket', fn($subQ) => $subQ->where('name', 'like', '%' . $request->get('country_market') . '%')
            )
            );

        $rateLines = $query->paginate($perPage, ['*'], 'page', $page);

        // Transform workspaces to a response format
        $response = $rateLines->getCollection()->map(function ($rateLine) {
            return new WhatsappRateLine($rateLine);
        });

        // Replace the collection with the transformed data
        $rateLines->setCollection($response);


        return $this->paginateResponse(
            message: 'WhatsApp Rate Lines retrieved successfully',
            items: $rateLines);
    }

    public function show(\App\Models\WhatsappRateLine $whatsappRateLine)
    {
        $whatsappRateLine->load('country.metaPricingMarket');

        return $this->response(
            message: 'WhatsApp Rate Line retrieved successfully.',
            data: new WhatsappRateLine($whatsappRateLine)
        );
    }

    public function update(Request $request, \App\Models\WhatsappRateLine $whatsappRateLine)
    {
        $validated = $request->validate([
            'price' => ['nullable', 'numeric', 'min:0'],
            'effective_date' => ['nullable', 'integer'],
            'expiry_date' => ['nullable', 'integer'],
        ]);

        $whatsappRateLine->update($validated);

        return $this->response(
            message: 'WhatsApp Rate Line updated successfully.',
            data: new WhatsappRateLine($whatsappRateLine->fresh('country.metaPricingMarket')),
        );
    }

}
