<?php

namespace App\Http\Controllers\SmsUsers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseApiController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use App\Http\Responses\ValidatorErrorResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use App\Models\Message;
use App\Models\Outbox;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\NumbersImport;
use Carbon\Carbon;
use App\Models\HlrLookupHis;

class NumberLookupController extends   BaseApiController implements HasMiddleware
{

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    /**
     * @OA\Get(
     *     path="/api/SmsUsers/lookuphis",
     *     summary="Get HLR lookup history",
     *     description="Retrieve the HLR lookup history for the authenticated user",
     *     operationId="getNumberLookupHistory",
     *     tags={"Number Lookup"},
     *     @OA\Parameter(
     *         name="all",
     *         in="query",
     *         description="Get all items without pagination",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for filtering items",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page (default: 15)",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="items"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="user_id", type="integer"),
     *                     @OA\Property(property="number", type="string"),
     *                     @OA\Property(property="live_status", type="string"),
     *                     @OA\Property(property="country", type="string"),
     *                     @OA\Property(property="telephone_number_type", type="string"),
     *                     @OA\Property(property="network", type="string"),
     *                     @OA\Property(property="roaming", type="string"),
     *                     @OA\Property(property="currentDate", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="per_page", type="integer"),
     *                 @OA\Property(property="current_page", type="integer"),
     *                 @OA\Property(property="last_page", type="integer"),
     *                 @OA\Property(property="from", type="integer"),
     *                 @OA\Property(property="to", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function index(Request $request)
    {

            $search = $request->search ?? null;
            $perPage = $request->get('per_page', 15); // Default to 15 if not provided
            $page = $request->get('page', 1);
            // Set the current page for the paginator
            Paginator::currentPageResolver(function () use ($page) {
                return $page;
            });

            $items = HlrLookupHis::where('user_id', Auth::id())
            ->when(!empty($search), function ($query) use ($search) {
                 $query->where(function ($subQuery) use ($search) {
                     $subQuery->where('number', 'like', '%' . $search . '%')
                         ->orWhere('live_status', 'like', '%' . $search . '%')
                         ->orWhere('country', 'like', '%' . $search . '%')
                         ->orWhere('telephone_number_type', 'like', '%' . $search . '%')
                         ->orWhere('network', 'like', '%' . $search . '%')
                         ->orWhere('roaming', 'like', '%' . $search . '%')
                         ->orWhere('currentDate', 'like', '%' . $search . '%');
                 });
             })
             ->orderBy('currentDate', 'desc')->paginate($perPage);

            // Customize the response
            return response()->json([
                'data' => $items->items(),
                'pagination' => [
                    'total' => $items->total(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'last_page' => $items->lastPage(),
                    'from' => $items->firstItem(),
                    'to' => $items->lastItem(),
                ],
            ]);

    }

    /**
     * @OA\Get(
     *     path="/api/SmsUsers/lookup",
     *     summary="Lookup phone numbers",
     *     description="Lookup details for a list of phone numbers",
     *     operationId="lookupNumbers",
     *     tags={"Number Lookup"},
     *     @OA\Parameter(
     *         name="numbers",
     *         in="query",
     *         description="Comma-separated list of phone numbers to lookup (max 250)",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="response"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="number", type="string"),
     *                     @OA\Property(property="live_status", type="string"),
     *                     @OA\Property(property="country", type="string"),
     *                     @OA\Property(property="telephone_number_type", type="string"),
     *                     @OA\Property(property="network", type="string"),
     *                     @OA\Property(property="roaming", type="string"),
     *                     @OA\Property(property="currentDate", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */
    public function lookup(Request $request)
    {
        // Validate that the numbers parameter is present and is a list of at most 250 numbers
        $request->validate([
            'numbers' => 'required|string|max:250' //
        ]);

        $numbers = explode(',', $request->input('numbers'));

        // Ensure the numbers list does not exceed 250 entries
        if (count($numbers) > 250) {
            throw ValidationException::withMessages([
                'numbers' => 'The numbers list must not exceed 250 entries.',
            ]);
        }
        // Prepare the API URL with the provided numbers
        $url = 'https://wfilter.dreams.sa/api/lookup';
        $queryString = http_build_query(['numbers' => implode(',', $numbers)]);

        // Make the API request using Laravel's HTTP client
        $response = Http::get("{$url}?{$queryString}");
        $data = $response->json();
        // Add the current date to each item
        $currentDateTime = Carbon::now()->format('Y-m-d H:i:s');
        foreach ($data as &$item) {
            $item['currentDate'] = $currentDateTime;
            $item['user_id'] = Auth::id();
            $item['updated_at'] = Carbon::now(); // Set the current timestamp for update
            // Insert or update by mobile number
            HlrLookupHis::updateOrInsert(
                ['number' => $item['number']], // Condition to check (if mobile exists)
                array_merge($item, ['created_at' => Carbon::now()]) // Add created_at for new record

            );
        }

        // Return the response from the external API
        return $this->response(true, 'response', $data);
    }



    // Existing method for handling query string numbers...

    /* public function lookupFromExcel(Request $request)
    {
        // Validate that an Excel file is uploaded
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);

        // Load numbers from the uploaded Excel file
        $import = new NumbersImport();
        Excel::import($import, $request->file('file'));

        $numbers = $import->getNumbers();




        // Ensure the numbers list does not exceed 250 entries
        if (count($numbers) > 250) {
            throw ValidationException::withMessages([
                'file' => 'The Excel file must not contain more than 250 numbers.',
            ]);
        }

        // Prepare the API URL with the numbers
        $url = 'https://wfilter.dreams.sa/api/lookup';
        $queryString = http_build_query(['numbers' => implode(',', $numbers)]);

        // Make the API request using Laravel's HTTP client
        $response = Http::get("{$url}?{$queryString}");

        $data = $response->json();
        // Add the current date to each item
        $currentDateTime = Carbon::now()->format('Y-m-d H:i:s');
        foreach ($data as &$item) {
            $item['currentDate'] = $currentDateTime;
        }

        // Return the response from the external API
        return $this->response(true, 'response', $data);
    }*/

    /**
     * @OA\Post(
     *     path="/api/SmsUsers/lookup-from-excel",
     *     summary="Lookup numbers from Excel file",
     *     description="Upload an Excel file with numbers and perform HLR lookup",
     *     operationId="lookupFromExcel",
     *     tags={"Number Lookup"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="file",
     *                     format="binary",
     *                     description="Excel file containing numbers (xlsx or xls)"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="response"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="number", type="string"),
     *                     @OA\Property(property="live_status", type="string"),
     *                     @OA\Property(property="country", type="string"),
     *                     @OA\Property(property="telephone_number_type", type="string"),
     *                     @OA\Property(property="currentDate", type="string", format="date-time"),
     *                     @OA\Property(property="user_id", type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="file",
     *                     type="array",
     *                     @OA\Items(type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     security={{"bearerAuth": {}}}
     * )
     */

    public function lookupFromExcel(Request $request)
    {
        // Validate that an Excel file is uploaded
        $request->validate([
            'file' => 'required|mimes:xlsx,xls'
        ]);
        // Load numbers from the uploaded Excel file
        $import = new NumbersImport();
        Excel::import($import, $request->file('file'));
        // Retrieve the numbers from the import
        $numbers = $import->getNumbers();
        // Split the numbers into chunks of 100
        $chunks = array_chunk($numbers, 100);

        // Prepare the API URL
        $url = 'https://wfilter.dreams.sa/api/lookup';

        $allResults = [];
        $currentDateTime = Carbon::now()->format('Y-m-d H:i:s');

        // Loop through each chunk and send a request

        foreach ($chunks as $chunk) {
            // Make the API request using Laravel's HTTP client
            $response = Http::get($url, [
                'numbers' => implode(',', $chunk),
            ]);
            // Decode the response
            $data = $response->json();
            //dd( $data);
            // Add the current date to each item
            if (!empty($data)) {
                foreach ($data as &$item) {
                    $item['currentDate'] = $currentDateTime;
                    $item['user_id'] = Auth::id();
                    $item['updated_at'] = Carbon::now(); // Set the current timestamp for update
                    // Insert or update by mobile number
                    HlrLookupHis::updateOrInsert(
                        ['number' => $item['number']], // Condition to check (if mobile exists)
                        array_merge($item, ['created_at' => Carbon::now()]) // Add created_at for new record

                    );
                }
                // Merge the current chunk results with all results
                $allResults = array_merge($allResults, $data);
            }
        }

        // Return the merged response from the external API
        return $this->response(true, 'response', $allResults);
    }
}
