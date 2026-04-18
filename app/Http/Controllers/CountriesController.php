<?php

namespace App\Http\Controllers;

use App\Http\Responses\Country;
use App\Models\WorldCountry;
use Illuminate\Http\Request;

class CountriesController extends BaseApiController
{
    public function index(Request $request)
    {

//        $countries = countries();
//        foreach ($countries as $rinvexCountry) {
//            $country = country($rinvexCountry['iso_3166_1_alpha2']);
//
//            $name_en = $country->getName();
//            $emoji = $country->getEmoji();
//            $iso2 = $country->getIsoAlpha2();
//            $continent = $country->getContinent();
//            dd($name_en, $emoji, $iso2, $continent);
//        }
        // Get pagination parameters
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        $countries = WorldCountry::paginate($perPage, ['*'], 'page', $page);

        $response = $countries->getCollection()->map(function ($country) {
            return new Country($country);
        });

        $countries->setCollection($response);

        return $this->paginateResponse(true, 'World Countries retrieved successfully', $countries);
    }
}
