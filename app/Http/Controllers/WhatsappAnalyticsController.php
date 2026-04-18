<?php

namespace App\Http\Controllers;

use App\Constants\Meta;
use App\Http\Responses\ValidatorErrorResponse;
use App\Http\Responses\WhatsappConversationAnalytics;
use App\Models\Channel;
use App\Rules\GranularityTimeWindow;
use App\Rules\MidnightUtc;
use App\Traits\ChannelManager;
use Carbon\Carbon;
use Http;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WhatsappAnalyticsController extends BaseApiController
{
    use ChannelManager;
    /**
     * @OA\Get(
     *     path="/api/whatsapp/analytics/conversations/{whatsapp_business_account_id}",
     *     summary="Retrieve WhatsApp conversation analytics",
     *     description="Fetch conversation analytics for a specified WhatsApp business account",
     *     operationId="getConversationAnalytics",
     *     tags={"WhatsApp Analytics"},
     *     @OA\Parameter(
     *         name="whatsapp_business_account_id",
     *         in="path",
     *         required=true,
     *         description="ID of the WhatsApp business account",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="start",
     *         in="query",
     *         description="The start date for the date range you are retrieving analytics for.",
     *         @OA\Schema(type="integer", format="int64", example=1724101200)
     *     ),
     *     @OA\Parameter(
     *         name="end",
     *         in="query",
     *         description="The end date for the date range you are retrieving analytics for.",
     *         @OA\Schema(type="integer", format="int64", example=1724706000)
     *     ),
     *     @OA\Parameter(
     *         name="granularity",
     *         in="query",
     *         description="The granularity by which you would like to retrieve the analytics.",
     *         @OA\Schema(
     *             type="string",
     *             enum={"HALF_HOUR", "DAILY", "MONTHLY"},
     *             example="DAILY"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="phone_numbers",
     *         in="query",
     *         required=false,
     *         description="An array of phone numbers for which you would like to retrieve analytics.",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="string"),
     *             example={"966920019103"}
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="dimensions",
     *         in="query",
     *         required=false,
     *         description="List of breakdowns you would like to apply to your metrics.",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="string"),
     *             enum={"CONVERSATION_CATEGORY", "CONVERSATION_DIRECTION", "CONVERSATION_TYPE", "COUNTRY", "PHONE"},
     *             example={"CONVERSATION_CATEGORY", "CONVERSATION_TYPE", "COUNTRY"}
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analytics were fetched Successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="All Conversations", ref="#/components/schemas/ConversationAnalyticsAllConversations"),
     *             @OA\Property(property="Free Conversations", ref="#/components/schemas/ConversationAnalyticsFreeConversations"),
     *             @OA\Property(property="Paid Conversations", ref="#/components/schemas/ConversationAnalyticsPaidConversations"),
     *             @OA\Property(property="Approximate Charges", ref="#/components/schemas/ConversationAnalyticsApproximateCharges")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request parameters",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid query parameters")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Analytics Fetch Failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve data")
     *         )
     *     )
     * )
     */
    public function getConversationAnalytics(Request $request, Channel $channel): JsonResponse
    {
        $channelDetails = $this->getChannelDetails($channel);

        // Check if the response from getChannelDetails is an error response
        if ($channelDetails instanceof JsonResponse) {
            return $channelDetails; // Return the error response
        }

        $whatsappBusinessAccountID = $channelDetails['whatsapp_business_account_id'];
        $accessToken = $channelDetails['access_token'];

        $defaultEnd = strtotime('today', time());

        $defaultStart = strtotime('today', strtotime('-7 days'));

        $start = (int)$request->query('start', $defaultStart);
        $end = (int)$request->query('end', $defaultEnd);
        $granularity = $request->query('granularity', "DAILY");


        $validator = Validator::make(
            [
                'start' => $start,
                'end' => $end,
                'granularity' => $granularity
            ],
            [
                'start' => ['integer', new MidnightUtc()],
                'end' => ['integer', new MidnightUtc()],
                'granularity' => ['string', Rule::in(['DAILY', 'WEEKLY', 'MONTHLY']), new GranularityTimeWindow($start, $end, $granularity)],
            ]
        );

        if ($validator->fails()) {
            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }


        $fields = "conversation_analytics
            .start({$start})
            .end({$end})
            .granularity({$granularity})
            .phone_numbers([])
            .country_codes([])
            .metric_types([])
            .conversation_types([])
            .conversation_directions([])
            .conversation_categories([])
            .dimensions([\"CONVERSATION_CATEGORY\", \"CONVERSATION_DIRECTION\", \"CONVERSATION_TYPE\", \"COUNTRY\", \"PHONE\"])";


        $queryParams = [
            'access_token' => $accessToken,
            'fields' => $fields
        ];


        $url = "https://graph.facebook.com/v20.0/{$whatsappBusinessAccountID}";
        $response = Http::withToken($accessToken)->get($url, $queryParams);


        if ($response->successful()) {
            $responseData = json_decode($response->body());
            if (!isset($responseData->conversation_analytics)) {
                return $this->response(false, 'Insights data isn’t available. There may be a delay in data processing. If you had conversations during this time, please check back later.', null, 400);
            }
            $dataPoints = $responseData->conversation_analytics->data[0]->data_points;

            $analyticsResponse = new WhatsappConversationAnalytics($dataPoints);


            return $this->response(true, 'Analytics were fetched Successfully.', $analyticsResponse);


        }
        return $this->response(false, 'Analytics Fetch Failed', $response->body());

    }

    /**
     * @OA\Get(
     *     path="/api/whatsapp/analytics/conversations-data-points/{whatsapp_business_account_id}",
     *     summary="Retrieve WhatsApp conversation analytics",
     *     description="Fetch conversation analytics for a specified WhatsApp business account",
     *     operationId="getConversationAnalyticsDataPoints",
     *     tags={"WhatsApp Analytics"},
     *     @OA\Parameter(
     *         name="whatsapp_business_account_id",
     *         in="path",
     *         required=true,
     *         description="ID of the WhatsApp business account",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="start",
     *         in="query",
     *         description="The start date for the date range you are retrieving analytics for.",
     *         @OA\Schema(type="integer", format="int64", example=1724101200)
     *     ),
     *     @OA\Parameter(
     *         name="end",
     *         in="query",
     *         description="The end date for the date range you are retrieving analytics for.",
     *         @OA\Schema(type="integer", format="int64", example=1724706000)
     *     ),
     *     @OA\Parameter(
     *         name="granularity",
     *         in="query",
     *         description="The granularity by which you would like to retrieve the analytics.",
     *         @OA\Schema(
     *             type="string",
     *             enum={"HALF_HOUR", "DAILY", "MONTHLY"},
     *             example="DAILY"
     *         )
     *     ),
     *     @OA\Parameter(
     *         name="phone_numbers",
     *         in="query",
     *         required=false,
     *         description="An array of phone numbers for which you would like to retrieve analytics.",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="string"),
     *             example={"966920019103"}
     *         )
     *     ),
     *      @OA\Parameter(
     *         name="dimensions",
     *         in="query",
     *         required=false,
     *         description="List of breakdowns you would like to apply to your metrics.",
     *         @OA\Schema(
     *             type="string",
     *             enum={"CONVERSATION_CATEGORY, CONVERSATION_DIRECTION, CONVERSATION_TYPE, COUNTRY, PHONE"},
     *             example="CONVERSATION_CATEGORY, CONVERSATION_TYPE, COUNTRY"
     *         )
     *    ),
     *     @OA\Response(
     *         response=200,
     *         description="Analytics were fetched Successfully."
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request parameters",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid query parameters")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Analytics Fetch Failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to retrieve data")
     *         )
     *     )
     * )
     */
    public function getConversationAnalyticsDataPoints(Request $request, Channel $channel): JsonResponse
    {

        $channelDetails = $this->getChannelDetails($channel);

        // Check if the response from getChannelDetails is an error response
        if ($channelDetails instanceof JsonResponse) {
            return $channelDetails; // Return the error response
        }

        $whatsappBusinessAccountID = $channelDetails['whatsapp_business_account_id'];
        $accessToken = $channelDetails['access_token'];


        $defaultEnd = strtotime('today', time());

        $defaultStart = strtotime('today', strtotime('-7 days'));

        $start = $request->query('start', $defaultStart);
        $end = $request->query('end', $defaultEnd);

        $granularity = $request->query('granularity', 'DAILY'); // Default to DAILY
        $phoneNumbers = $request->query('phone_numbers', []);
        $dimensions = $request->query('dimensions', ['CONVERSATION_CATEGORY', 'CONVERSATION_DIRECTION', 'CONVERSATION_TYPE', 'COUNTRY', 'PHONE']);

        if (is_array($dimensions)) {
            $dimensions = implode(",", $dimensions);
        }

        if (is_array($phoneNumbers)) {
            $phoneNumbers = "[]";
        }

        $queryParams = [
            'access_token' => $accessToken,
            'fields' => "conversation_analytics.start($start).end($end).granularity($granularity).phone_numbers($phoneNumbers).country_codes([]).metric_types([]).conversation_types([]).conversation_directions([]).conversation_categories([]).dimensions($dimensions)"
        ];

        // Make the HTTP GET request
        $response = Http::get("https://graph.facebook.com/v20.0/{$whatsappBusinessAccountID}", $queryParams);


        if ($response->successful()) {
            $responseData = json_decode($response->body());
            if (!isset($responseData->conversation_analytics)) {
                return $this->response(false, 'Insights data isn’t available. There may be a delay in data processing. If you had conversations during this time, please check back later.', null, 400);
            }
            return $this->response(true, 'Analytics were fetched Successfully.', $responseData);


        }
        return $this->response(false, 'Analytics Fetch Failed', $response->body());

    }

    /**
     * Convert a Unix timestamp to a human-readable date and time string.
     *
     * @param int $timestamp The Unix timestamp to convert.
     * @return string  The formatted date and time string.
     */
    function formatTimestamp($timestamp)
    {
        // Create a DateTime object from the Unix timestamp
        $date = Carbon::createFromTimestamp($timestamp);

        $date->setTimezone('UTC');
        // Set the timezone to UTC
        $timeZone = $date->time;


        // Format the date and time
        $formattedDate = $date->format('l, F j, Y');
        $formattedTime = $date->format('H:i:s');

        return "Date: $formattedDate\nTime: $formattedTime $timeZone";
    }


    /**
     * @OA\Get(
     *     path="/api/whatsapp/analytics/messages-data-points/{whatsapp_business_account_id}",
     *     summary="Retrieve WhatsApp message analytics data points",
     *     description="Fetch message analytics data points for a specified WhatsApp business account",
     *     operationId="getMessagesAnalyticsDataPoints",
     *     tags={"WhatsApp Analytics"},
     *     @OA\Parameter(
     *         name="whatsapp_business_account_id",
     *         in="path",
     *         required=true,
     *         description="ID of the WhatsApp business account",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="start",
     *         in="query",
     *         description="The start date for the date range you are retrieving analytics for.",
     *         @OA\Schema(type="integer", format="int64", example=1724101200)
     *     ),
     *     @OA\Parameter(
     *         name="end",
     *         in="query",
     *         description="The end date for the date range you are retrieving analytics for.",
     *         @OA\Schema(type="integer", format="int64", example=1724706000)
     *     ),
     *     @OA\Parameter(
     *         name="granularity",
     *         in="query",
     *         description="The granularity by which you would like to retrieve the analytics.",
     *         @OA\Schema(
     *             type="string",
     *             enum={"DAY", "HALF_HOUR", "MONTH"},
     *             example="DAY"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Analytics were fetched Successfully.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="data_points_one",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             ),
     *             @OA\Property(
     *                 property="data_points_two",
     *                 type="array",
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Validation Error(s)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Analytics Fetch Failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Analytics Fetch Failed")
     *         )
     *     )
     * )
     */

    public function getMessagesAnalyticsDataPoints(Request $request, Channel $channel): JsonResponse
    {
        $channelDetails = $this->getChannelDetails($channel);

        // Check if the response from getChannelDetails is an error response
        if ($channelDetails instanceof JsonResponse) {
            return $channelDetails; // Return the error response
        }

        $whatsappBusinessAccountID = $channelDetails['whatsapp_business_account_id'];
        $accessToken = $channelDetails['access_token'];

        $defaultEnd = strtotime('today', time());

        $defaultStart = strtotime('today', strtotime('-7 days'));

        $start = (int)$request->query('start', $defaultStart);
        $end = (int)$request->query('end', $defaultEnd);
        $granularity = $request->query('granularity', "DAY");


        $validator = Validator::make(
            [
                'start' => $start,
                'end' => $end,
                'granularity' => $granularity
            ],
            [
                'start' => ['integer', new MidnightUtc()],
                'end' => ['integer', new MidnightUtc()],
                'granularity' => ['string', Rule::in(['DAY', 'HALF_HOUR', 'MONTH'])],
            ]
        );

        if ($validator->fails()) {
            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
            }
        }


        $fieldsOne = "analytics
            .start({$start})
            .end({$end})
            .granularity({$granularity})
            .phone_numbers([])
            .metric_types([\"COST\",\"DELIVERED\",\"RECEIVED\",\"SENT\"])
            .product_types([0,2])
            .message_media_types([\"AUDIO_VIDEO\",\"DOCUMENT\",\"IMAGE\",\"LIST\",\"LOCATION\",\"OTHER\",\"TEXT\"])
            .interaction_types([\"BUTTONS\",\"NO_BUTTONS\"])
            .country_codes([])";

        $fieldsTwo = "analytics
            .start({$start})
            .end({$end})
            .granularity({$granularity})
            .phone_numbers([])
            .metric_types([\"COST\",\"DELIVERED\",\"RECEIVED\",\"SENT\"])
            .product_types([100])
            .message_media_types([\"AUDIO_VIDEO\",\"DOCUMENT\",\"IMAGE\",\"LIST\",\"LOCATION\",\"OTHER\",\"TEXT\"])
            .interaction_types([\"BUTTONS\",\"NO_BUTTONS\"])
            .country_codes([])";

        $queryParamsOne = [
            'access_token' => $accessToken,
            'fields' => $fieldsOne,
        ];


        $queryParamsTwo = [
            'access_token' => $accessToken,
            'fields' => $fieldsTwo,
        ];

        $url = "https://graph.facebook.com/v20.0/{$whatsappBusinessAccountID}";


        $responseOne = Http::withToken($accessToken)->get($url, $queryParamsOne);


        $responseTwo = Http::withToken($accessToken)->get($url, $queryParamsTwo);

        if ($responseOne->successful() && $responseTwo->successful()) {
            $responseDataOne = json_decode($responseOne->body());
            $responseDataTwo = json_decode($responseTwo->body());

            if (!isset($responseDataOne->analytics) || !isset($responseDataTwo->analytics)) {
                return $this->response(false, 'Insights data isn’t available. There may be a delay in data processing. If you had conversations during this time, please check back later.', null, 400);
            }

            if (!isset($responseDataOne->analytics?->data_points) || !isset($responseDataTwo->analytics?->data_points)) {
                return $this->response(false, 'Insights data isn’t available. There may be a delay in data processing. If you had conversations during this time, please check back later.', null, 400);
            }

            $dataPointsOne = $responseDataOne->analytics->data_points ?? null;
            $dataPointsTwo = $responseDataTwo->analytics->data_points ?? null;

            $combinedDataPoints = [
                'data_points_one' => $dataPointsOne,
                'data_points_two' => $dataPointsTwo,
            ];

            return $this->response(true, 'Analytics were fetched Successfully.', $combinedDataPoints);


        }
        return $this->response(false, 'Analytics Fetch Failed', $responseOne->body().$responseTwo->body());

    }

    /**
     * @OA\Get(
     *     path="/api/whatsapp/{whatsapp_business_account_id}/template-analytics",
     *     summary="Retrieve template analytics",
     *     description="Fetches analytics data for specified templates within a date range.",
     *     operationId="getTemplateAnalytics",
     *     tags={"WhatsApp Analytics"},
     *     @OA\Parameter(
     *         name="whatsapp_business_account_id",
     *         in="path",
     *         required=true,
     *         description="WhatsApp Business Account ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="start",
     *         in="query",
     *         required=false,
     *         description="Start timestamp (UTC midnight)",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="end",
     *         in="query",
     *         required=false,
     *         description="End timestamp (UTC midnight)",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Parameter(
     *         name="granularity",
     *         in="query",
     *         required=false,
     *         description="Data granularity (e.g., DAILY)",
     *         @OA\Schema(type="string", enum={"DAILY"})
     *     ),
     *     @OA\Parameter(
     *         name="template_ids",
     *         in="query",
     *         required=true,
     *         description="Array of template IDs",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="integer")
     *         ),
     *         style="form",
     *         explode=true
     *     ),
     *     @OA\Parameter(
     *         name="metric_types",
     *         in="query",
     *         required=false,
     *         description="Array of metric types",
     *         @OA\Schema(
     *             type="array",
     *             @OA\Items(type="string", enum={"COST", "CLICKED", "DELIVERED", "READ", "SENT"})
     *         ),
     *         style="form",
     *         explode=true
     *     ),
     *     @OA\Parameter(
     *         name="product_type",
     *         in="query",
     *         required=false,
     *         description="Product type",
     *         @OA\Schema(type="string", enum={"CLOUD_API", "MARKETING_MESSAGES_LITE_API"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_sent", type="integer"),
     *                 @OA\Property(property="total_delivered", type="integer"),
     *                 @OA\Property(property="total_read", type="integer"),
     *                 @OA\Property(property="read_rate", type="number", format="float"),
     *                 @OA\Property(
     *                     property="data_points",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="template_id", type="string"),
     *                         @OA\Property(property="start", type="integer", format="int64"),
     *                         @OA\Property(property="end", type="integer", format="int64"),
     *                         @OA\Property(property="sent", type="integer"),
     *                         @OA\Property(property="delivered", type="integer"),
     *                         @OA\Property(property="read", type="integer"),
     *                         @OA\Property(
     *                             property="cost",
     *                             type="array",
     *                             @OA\Items(
     *                                 type="object",
     *                                 @OA\Property(property="type", type="string"),
     *                                 @OA\Property(property="value", type="number", format="float", nullable=true)
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation Error(s)",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Analytics fetch failed",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean"),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getTemplateAnalytics(Request $request, $whatsapp_business_account_id): JsonResponse
    {

        $defaultEnd = strtotime('today', time());

        $defaultStart = strtotime('today', strtotime('-7 days'));

        $start = (int)$request->query('start', $defaultStart);
        $end = (int)$request->query('end', $defaultEnd);

        $granularity = $request->query('granularity', 'DAILY');
        $templateIds = $request->query('template_ids', []);
        $metricTypes = $request->query('metric_types', []);
        $productType = $request->query('product_type', 'CLOUD_API');

      //  dd($templateIds, $metricTypes, $productType);

        $validator = Validator::make(
            [
                'start' => $start,
                'end' => $end,
                'granularity' => $granularity,
                'template_ids' => $templateIds,
                'metric_types' => $metricTypes,
                'product_type' => $productType,
            ],
            [
                'start' => ['integer', new MidnightUtc()],
                'end' => ['integer', new MidnightUtc()],
                'granularity' => ['required', 'string', Rule::in(['DAILY'])],
                'template_ids' => ['required', 'array', 'max:10'],
                'template_ids.*' => ['integer', 'exists:whatsapp_message_templates,id'],
                'metric_types' => ['array'],
                'metric_types.*' => ['string', Rule::in(['COST', 'CLICKED', 'DELIVERED', 'READ', 'SENT'])],
                'product_type' => ['string', Rule::in(['CLOUD_API', 'MARKETING_MESSAGES_LITE_API'])],
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $templateIdsString = '[' . implode(',', $templateIds) . ']';
        $metricTypesString = $metricTypes ? implode(',', $metricTypes) : '';

        $accessToken = Meta::ACCESS_TOKEN;

        $queryParams = [
            'access_token' => $accessToken,
            'start' => $start,
            'end' => $end,
            'granularity' => $granularity,
            'template_ids' => $templateIdsString,
            'metric_types' => $metricTypesString,
            'product_type' => $productType,
        ];

        $url = "https://graph.facebook.com/v21.0/{$whatsapp_business_account_id}/template_analytics";
        $allDataPoints = [];
        do {
            $response = Http::withToken($accessToken)->get($url, $queryParams);
            if ($response->successful()) {
                $responseData = $response->json();
                if (isset($responseData['data'])) {
                    $responseData = $responseData['data'];
                    $allDataPoints = array_merge($allDataPoints, $responseData[0]['data_points']);
                }
                $queryParams['after'] = $responseData['paging']['cursors']['after'] ?? null;
            } else {
                // Handle error
                return $this->response(false, 'Analytics fetch failed', $response->body());
            }
        } while (isset($queryParams['after']));


        // Initialize counters
        $totalSent = 0;
        $totalDelivered = 0;
        $totalRead = 0;

        // Aggregate data
        foreach ($allDataPoints as $dataPoint) {
            $totalSent += $dataPoint['sent'] ?? 0;
            $totalDelivered += $dataPoint['delivered'] ?? 0;
            $totalRead += $dataPoint['read'] ?? 0;
        }

        // Calculate percentages
        $readRate = $totalDelivered > 0 ? ($totalRead / $totalDelivered) * 100 : 0;

        // Prepare the response data
        $responseData = [
            'total_sent' => $totalSent,
            'total_delivered' => $totalDelivered,
            'total_read' => $totalRead,
            'read_rate' => $readRate,
            'data_points' => $allDataPoints,
        ];



        return $this->response(true, 'Analytics fetched Successfully', $responseData);
    }

}
