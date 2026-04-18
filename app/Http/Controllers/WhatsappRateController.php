<?php

namespace App\Http\Controllers;

use App\Models\Country;
use App\Models\WhatsappRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\SimpleExcel\SimpleExcelReader;
use Validator;

class WhatsappRateController extends BaseApiController
{
    /**
     * Display a listing of WhatsApp rates grouped by country.
     *
     * @return JsonResponse
     */
    public function index()
    {
        // Fetch all rates grouped by country
        $ratesByCountry = WhatsappRate::with('country')
            ->get()
            ->groupBy(function ($rate) {
                return $rate->country->name_en; // Group by country name
            });

        // Transform the grouped data into a structured response
        $formattedResponse = $ratesByCountry->map(function ($rates, $country) {
            return [
                'country' => $country,
                'rates' => $rates->map(function ($rate) {
                    return [
                        'id' => $rate->id,
                        'currency' => $rate->currency,
                        'marketing' => $rate->marketing,
                        'utility' => $rate->utility,
                        'authentication' => $rate->authentication,
                        'authentication_international' => $rate->authentication_international,
                        'service' => $rate->service,
                        'effective_date' => $rate->effective_date,
                        'expiry_date' => $rate->expiry_date,
                    ];
                }),
            ];
        })->values(); // Re-index the collection

        return $this->response(
            success: true,
            message: 'WhatsApp rates grouped by country retrieved successfully.',
            data: $formattedResponse
        );
    }

    /**
     * Show a specific WhatsApp rate.
     *
     * @param WhatsappRate $rate
     * @return JsonResponse
     */
    public function show(WhatsappRate $rate)
    {
        $rate->load('country');

        return $this->response(
            success: true,
            message: 'WhatsApp rate retrieved successfully.',
            data: [
                'id' => $rate->id,
                'country' => $rate->country->name_en,
                'currency' => $rate->currency,
                'marketing' => $rate->marketing,
                'utility' => $rate->utility,
                'authentication' => $rate->authentication,
                'authentication_international' => $rate->authentication_international,
                'service' => $rate->service,
                'effective_date' => $rate->effective_date,
                'expiry_date' => $rate->expiry_date,
            ]
        );
    }

    /**
     * Store a new WhatsApp rate.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'country_id' => 'required|exists:country,id',
            'currency' => 'required|string|max:3',
            'marketing' => 'nullable|numeric|min:0',
            'utility' => 'nullable|numeric|min:0',
            'authentication' => 'nullable|numeric|min:0',
            'authentication_international' => 'nullable|numeric|min:0',
            'service' => 'nullable|numeric|min:0',
            'effective_date' => 'required|integer',
            'expiry_date' => 'nullable|integer|gte:effective_date',
        ]);

        $rate = WhatsappRate::create($validated);

        return $this->response(
            success: true,
            message: 'WhatsApp rate created successfully.',
            data: $rate,
            statusCode: 201
        );
    }

    /**
     * Update an existing WhatsApp rate.
     *
     * @param Request $request
     * @param WhatsappRate $rate
     * @return JsonResponse
     */
    public function update(Request $request, WhatsappRate $rate)
    {
        $validated = $request->validate([
            'currency' => 'nullable|string|max:3',
            'marketing' => 'nullable|numeric|min:0',
            'utility' => 'nullable|numeric|min:0',
            'authentication' => 'nullable|numeric|min:0',
            'authentication_international' => 'nullable|numeric|min:0',
            'service' => 'nullable|numeric|min:0',
            'effective_date' => 'nullable|integer',
            'expiry_date' => 'nullable|integer|gte:effective_date',
        ]);

        $rate->update($validated);

        return $this->response(
            success: true,
            message: 'WhatsApp rate updated successfully.',
            data: $rate
        );
    }

    /**
     * Delete a WhatsApp rate.
     *
     * @param WhatsappRate $rate
     * @return JsonResponse
     */
    public function destroy(WhatsappRate $rate)
    {
        $rate->delete();

        return $this->response(
            success: true,
            message: 'WhatsApp rate deleted successfully.'
        );
    }

    public function upload(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xlsx',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', $validator->errors(), 422);
        }

        $file = $request->file('file');
        $fileType = $file->getClientOriginalExtension();

        $filePath = $request->file('file')->getRealPath();
        $simpleExcelReader = SimpleExcelReader::create($filePath, $fileType);
        $simpleExcelReader->headerOnRow(6);
        $rows = $simpleExcelReader->getRows();

        $errors = [];
        $successfulInserts = 0;



        $rows->each(function ($row) use ( &$errors, &$successfulInserts) {


            // Skip rows without valid data
            $countryName = $row['Market'] ?? null;
            if (empty($countryName) || !is_string($countryName)) {
                return; // Skip rows with no 'Market' value or invalid data
            }

            $currency = $row['Currency'] ?? null;
            $marketing = isset($row['Marketing']) && is_numeric($row['Marketing']) ? $row['Marketing'] : null;
            $utility = isset($row['Utility']) && is_numeric($row['Utility']) ? $row['Utility'] : null;
            $authentication = isset($row['Authentication']) && is_numeric($row['Authentication']) ? $row['Authentication'] : null;
            $authenticationInternational = isset($row['Authentication-International']) && is_numeric($row['Authentication-International']) ? $row['Authentication-International'] : null;
            $service = isset($row['Service']) && is_numeric($row['Service']) ? $row['Service'] : null;

            // Convert currency values (remove '$' or other symbols if necessary)
            $currency = str_replace(['$', 'US'], '', $currency);

            // Validate country
            $country = Country::whereLike('name_en', $countryName)->first();
            if (!$country) {
                $errors[] = "Country '{$countryName}' not found in the database.";
                return; // Skip processing this row
            }

            // Prepare data for insertion
            $rateData = [
                'country_id' => $country->id,
                'currency' => $currency,
                'marketing' => $marketing,
                'utility' => $utility,
                'authentication' => $authentication,
                'authentication_international' => $authenticationInternational,
                'service' => $service,
                'effective_date' => strtotime('2023-06-01'), // Default effective date from your context
                'expiry_date' => null, // Default expiry date if not provided
            ];

            // Check for existing rate
            $existingRate = WhatsappRate::where([
                'country_id' => $rateData['country_id'],
                'currency' => $rateData['currency'],
                'marketing' => $rateData['marketing'],
                'utility' => $rateData['utility'],
                'authentication' => $rateData['authentication'],
                'authentication_international' => $rateData['authentication_international'],
                'service' => $rateData['service'],
            ])->first();

            if (!$existingRate) {
                WhatsappRate::create($rateData);
                $successfulInserts++;
            }
        });

        if (!empty($errors)) {
            return $this->response(
                false,
                'Some rows could not be processed.',
                ['errors' => $errors, 'successful_inserts' => $successfulInserts],
                200
            );
        }

        return $this->response(
            true,
            'File processed successfully.',
            ['successful_inserts' => $successfulInserts],
            200
        );
    }

    /**
     * Get WhatsApp rates by country_id.
     *
     * @param int $country_id
     * @return JsonResponse
     */
    public function getByCountry($country_id)
    {
        // Fetch rates for the specified country
        $rates = WhatsappRate::where('country_id', $country_id)
            ->with('country')
            ->get();

        // Check if rates exist for the given country
        if ($rates->isEmpty()) {
            return $this->response(
                success: false,
                message: 'No WhatsApp rates found for the specified country.',
                data: null,
                statusCode: 404
            );
        }


        return $this->response(
            success: true,
            message: 'WhatsApp rates for the specified country retrieved successfully.',
            data: $rates
        );
    }


}
