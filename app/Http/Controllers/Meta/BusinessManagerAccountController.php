<?php

namespace App\Http\Controllers\Meta;

use App\Constants\Meta;
use App\Http\Controllers\BaseApiController;
use App\Models\BusinessManagerAccount;
use App\Models\Channel;
use App\Models\WhatsappBusinessAccount;
use Http;
use Illuminate\Http\JsonResponse;
use Request;

class BusinessManagerAccountController extends BaseApiController
{

    /**
     * Display a listing of the business manager accounts.
     *
     * @return JsonResponse
     */

    /**
     * @OA\Get(
     *     path="/api/whatsapp/business-manager-accounts",
     *     summary="Get all Business Manager Accounts",
     *     tags={"WhatsApp"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful response",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Business accounts fetched successfully."),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/BusinessManagerAccount")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     )
     * )
     */
    public function index(Channel $channel): JsonResponse
    {

        $accessToken = Meta::ACCESS_TOKEN;
        $businessId =  $channel->connector->whatsappConfiguration->whatsappBusinessAccount->businessManagerAccount->id;
        $url = "https://graph.facebook.com/v20.0/{$businessId}";


        $response = Http::get($url, [
            'fields' => 'is_hidden,link,name,two_factor_type,verification_status,vertical,vertical_id,profile_picture_uri',
            'access_token' => $accessToken,
        ]);


        if ($response->successful()) {

            $data = json_decode($response->body());

            $businessManagerAccount = BusinessManagerAccount::updateOrCreate([
                'id' => $data->id
            ], [
                'name' => $data->name,
                'link' => $data->link,
                'is_hidden' => $data->is_hidden,
                'two_factor_type' => $data->two_factor_type,
                'vertical' => $data->vertical,
                'vertical_id' => $data->vertical_id,
                'verification_status' => $data->verification_status,
                'profile_picture_uri' => $data->profile_picture_uri,
            ]);

        }

        $whatsappUrl = "{$url}/owned_whatsapp_business_accounts";
        $whatsappResponse = Http::withToken($accessToken)->get($whatsappUrl);

        if ($whatsappResponse->successful()) {
            $whatsappData = json_decode($whatsappResponse->body())->data;

            foreach ($whatsappData as $account) {
                WhatsappBusinessAccount::updateOrCreate([
                    'id' => $account->id,
                    'business_manager_account_id' => $businessId
                ], [
                    'name' => $account->name,
                    'message_template_namespace' => $account->message_template_namespace,
                    'currency' => $account->currency ?? null,
                ]);
            }
        }

        $accounts = BusinessManagerAccount::all();
        $formattedAccounts = $accounts->map(fn(BusinessManagerAccount $account) => new \App\Http\Responses\BusinessManagerAccount($account));

        return $this->response(true, 'Business accounts fetched successfully.', $formattedAccounts);

    }

    /**
     * Display the specified business manager account.
     *
     * @param string $id
     * @return JsonResponse
     */

    /**
     * @OA\Get(
     *     path="/api/whatsapp/business-manager-accounts/{id}",
     *     summary="Display a specific Business Manager Account",
     *     description="Fetches a specific Business Manager Account by its ID.",
     *     operationId="getBusinessManagerAccount",
     *     tags={"WhatsApp"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="The ID of the Business Manager Account to retrieve.",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Business Manager Account fetched successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Business Manager account fetched successfully."
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 ref="#/components/schemas/BusinessManagerAccount"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Account not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="success",
     *                 type="boolean",
     *                 example=false
     *             ),
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Account not found"
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="null"
     *             )
     *         )
     *     )
     * )
     */
    public function show(Channel $channel): JsonResponse
    {
        $accessToken = Meta::ACCESS_TOKEN;
        $businessId =  $channel->connector->whatsappConfiguration->whatsappBusinessAccount->businessManagerAccount->id;
        $url = "https://graph.facebook.com/v20.0/{$businessId}";


        $response = Http::get($url, [
            'fields' => 'is_hidden,link,name,two_factor_type,verification_status,vertical,vertical_id,profile_picture_uri',
            'access_token' => $accessToken,
        ]);


        if ($response->successful()) {

            $data = json_decode($response->body());

            $businessManagerAccount = BusinessManagerAccount::updateOrCreate(
                ['id' => $data->id],
                [
                    'name' => $data->name ?? null,
                    'link' => $data->link ?? null,
                    'is_hidden' => $data->is_hidden ?? null,
                    'two_factor_type' => $data->two_factor_type ?? 'none', // Safely handle missing property
                    'vertical' => $data->vertical ?? null,
                    'vertical_id' => $data->vertical_id ?? null,
                    'verification_status' => $data->verification_status ?? 'not_set',
                    'profile_picture_uri' => $data->profile_picture_uri ?? null,
                ]
            );

        }

        $whatsappUrl = "{$url}/owned_whatsapp_business_accounts";
        $whatsappResponse = Http::withToken($accessToken)->get($whatsappUrl);

        if ($whatsappResponse->successful()) {
            $whatsappData = json_decode($whatsappResponse->body())->data;

            foreach ($whatsappData as $account) {
                WhatsappBusinessAccount::updateOrCreate([
                    'id' => $account->id,
                    'business_manager_account_id' => $businessId
                ], [
                    'name' => $account->name,
                    'message_template_namespace' => $account->message_template_namespace,
                    'currency' => $account->currency ?? null,
                ]);
            }
        }

        $formattedAccount =  new \App\Http\Responses\BusinessManagerAccount($businessManagerAccount);

        return $this->response(true, 'Business Manager account fetched successfully.', $formattedAccount);
    }

    /**
     * Store a newly created business manager account in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|unique:business_manager_accounts,id',
            'user_id' => 'required|exists:users,id',
            'name' => 'required|string',
            'link' => 'nullable|string',
            'profile_picture_uri' => 'nullable|string',
            'two_factor_type' => 'required|string',
            'verification_status' => 'required|string',
            'vertical' => 'nullable|string',
            'vertical_id' => 'nullable|integer',
        ]);

        $account = BusinessManagerAccount::create($validated);

        return response()->json($account, 201);
    }

    /**
     * Update the specified business manager account in storage.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $account = BusinessManagerAccount::find($id);

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $validated = $request->validate([
            'user_id' => 'sometimes|required|exists:users,id',
            'name' => 'sometimes|required|string',
            'link' => 'nullable|string',
            'profile_picture_uri' => 'nullable|string',
            'two_factor_type' => 'sometimes|required|string',
            'verification_status' => 'sometimes|required|string',
            'vertical' => 'nullable|string',
            'vertical_id' => 'nullable|integer',
        ]);

        $account->update($validated);

        return response()->json($account);
    }

    /**
     * Remove the specified business manager account from storage.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $account = BusinessManagerAccount::find($id);

        if (!$account) {
            return response()->json(['message' => 'Account not found'], 404);
        }

        $account->delete();

        return response()->json(['message' => 'Account deleted']);
    }

}
