<?php

namespace App\Http\Controllers;


use App\Http\Responses\ValidatorErrorResponse;
use App\Http\Responses\WhatsappRatesByRegion;
use App\Models\MetaPricingMarket;
use App\Models\Organization;
use App\Models\OrganizationWhatsappRateLine;
use App\Models\WhatsappRateLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrganizationWhatsappRateLineController extends BaseApiController
{
    public function index(Request $request, Organization $organization)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $query = OrganizationWhatsappRateLine::query()
            ->with(['whatsappRateLine.country'])
            ->where('organization_id', $organization->id)
            ->orderBy('created_at', 'desc');

        // Filters...
        if ($request->filled('country_name')) {
            $query->whereHas('whatsappRateLine.country', function ($q) use ($request) {
                $q->where('name_en', 'like', '%' . $request->get('country_name') . '%');
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('whatsappRateLine', fn($q) => $q->where('category', $request->get('category')));
        }

        if ($request->filled('pricing_model')) {
            $query->whereHas('whatsappRateLine', fn($q) => $q->where('pricing_model', $request->get('pricing_model')));
        }

        $results = $query->paginate($perPage, ['*'], 'page', $page);

        $response = $results->getCollection()->map(fn($item) => new \App\Http\Responses\OrganizationWhatsappRateLine($item));
        $results->setCollection($response);

        return $this->paginateResponse(true, 'Custom WhatsApp Rate Lines retrieved successfully.', $results);
    }


    public function store(Request $request, Organization $organization)
    {
        $validator = Validator::make($request->all(), [
            'whatsapp_rate_line_id' => 'required|exists:whatsapp_rate_lines,id',
            'custom_price' => 'required|numeric|min:0',
            'currency' => 'required|string|max:3',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        // Prevent duplicate
        $exists = OrganizationWhatsappRateLine::where('organization_id', $organization->id)
            ->where('whatsapp_rate_line_id', $request->whatsapp_rate_line_id)
            ->exists();

        if ($exists) {
            return $this->response(false, 'A custom rate already exists for this organization and rate line.', null, 409);
        }

        $entry = OrganizationWhatsappRateLine::create([
            'organization_id' => $organization->id,
            'whatsapp_rate_line_id' => $request->whatsapp_rate_line_id,
            'custom_price' => $request->custom_price,
            'currency' => $request->currency,
        ]);

        return $this->response(true, 'Custom WhatsApp Rate Line created successfully.', new \App\Http\Responses\OrganizationWhatsappRateLine($entry));
    }


    public function update(Request $request, Organization $organization, OrganizationWhatsappRateLine $organizationWhatsappRateLine)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'custom_price' => 'required|numeric|min:0',
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $organizationWhatsappRateLine->update([
            'custom_price' => $request->custom_price,
        ]);

        return $this->response(true, 'Custom price updated successfully.', new \App\Http\Responses\OrganizationWhatsappRateLine($organizationWhatsappRateLine));
    }

    public function destroy(Organization $organization, OrganizationWhatsappRateLine $organizationWhatsappRateLine)
    {
        $organizationWhatsappRateLine->delete();

        return $this->response(true, 'Custom WhatsApp rate line deleted successfully.');
    }

    public function organizationIndex(Request $request, Organization $organization)
    {
        $markets = MetaPricingMarket::with('countries')->get();
        $response = collect(); // or new Collection()

        foreach ($markets as $market) {
            $countryIds = $market->countries->pluck('id');

            if ($organization->usesCustomWhatsappRates()) {
                // Get all customized lines by organization
                $customLines = OrganizationWhatsappRateLine::with('whatsappRateLine.country')
                    ->where('organization_id', $organization->id)
                    ->whereHas('whatsappRateLine', fn($q) => $q->whereIn('world_country_id', $countryIds))
                    ->get();

                // Exclude customized rate_line_ids from default
                $excludedIds = $customLines->pluck('whatsapp_rate_line_id');

                $defaultLines = WhatsappRateLine::with('country')
                    ->whereIn('world_country_id', $countryIds)
                    ->whereNotIn('id', $excludedIds)
                    ->get();

                // Merge custom and default lines
                $regionRates = $customLines->map(fn($line) => new \App\Http\Responses\OrganizationWhatsappRateLine($line))
                    ->merge(
                        $defaultLines->map(fn($line) => new \App\Http\Responses\OrganizationWhatsappRateLine(null, $line))
                    );
            } else {
                // Return only default rates
                $defaultLines = WhatsappRateLine::with('country')
                    ->whereIn('world_country_id', $countryIds)
                    ->get();

                $regionRates = $defaultLines->map(fn($line) => new \App\Http\Responses\OrganizationWhatsappRateLine(null, $line));
            }

            // Append each region response to the collection
            $response->push(new WhatsappRatesByRegion($market->name, $regionRates));
        }

        return $this->response(true, 'Rates grouped by region', $response);
    }


}
