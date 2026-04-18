<?php

namespace App\Http\Controllers\Whatsapp;

use AlphaSnow\LaravelFilesystem\Aliyun\OssClientAdapter;
use App\Constants\Meta;
use App\Http\Controllers\BaseApiController;
use App\Http\Responses\ValidatorErrorResponse;
use App\Models\Channel;
use App\Models\Organization;
use App\Models\WhatsappPhoneNumber;
use App\Models\AppMedia;
use App\Services\FileUploadService;
use App\Traits\BusinessTokenManager;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Imagick;
use OSS\OssClient;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileDoesNotExist;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Storage;

class WhatsappMediaController extends BaseApiController
{

    use BusinessTokenManager;

    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * @OA\Post(
     *     path="/api/whatsapp/media/resumable-upload",
     *     tags={"WhatsApp Media"},
     *     summary="Upload media for WhatsApp messaging",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"file"},
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *          description="Media uploaded successfully",
     *          @OA\JsonContent(
     *              @OA\Property(property="success", type="boolean", example=true),
     *              @OA\Property(property="message", type="string", example="Media Uploaded Successfully"),
     *              @OA\Property(
     *                  property="data",
     *                  @OA\Property(
     *                      property="h",
     *                      type="string",
     *                      example="4:YW5nZWxpYy0xMjk4MTYyXzEyODAucG5n:aW1hZ2UvcG5n:ARZbZTvkp84-ws-WbEQfTNXTKwrZqp6oIbUEhjN4VuT2H-Lmb9ekIb9pzafMYhECmQbZgLAnE7LgNyEx8uknY5vHHwpKevik4z1LWBXCfJlfjg:e:1727524514:807813227927119:61565494432283:ARZV8TFIukumH2EOTuA"
     *                  )
     *              )
     *          )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     )
     * )
     */


    public function uploadResumableMedia(Request $request)
    {
        $file = $request->file('file');



        // Step 1: Start the upload session
        $uploadSession = $this->startUploadSession($file);
        $uploadSessionId = $uploadSession['id'];

        // Step 2: Upload the file in chunks
        $uploadResult = $this->uploadChunks($uploadSessionId, $file);


        // Return the file handle to the client
        return json_decode($uploadResult->getContent());
    }


    public function startUploadSession($file)
    {
        $appId = env('WHATSAPP_APP_ID');
        $accessToken = Meta::ACCESS_TOKEN;
        $fileName = $file->getClientOriginalName();
        $fileLength = $file->getSize();
        $fileType = $file->getMimeType();

        $url = "https://graph.facebook.com/v20.0/$appId/uploads";

        // Start upload session
        $response = Http::post($url, [
            'file_name' => $fileName,
            'file_length' => $fileLength,
            'file_type' => $fileType,
            'access_token' => $accessToken,
        ]);

        return $response->json(); // This will return the upload session ID
    }

    public function uploadChunks($uploadSessionId, $file)
    {

        $fileOffset = 0; // Initial offset
        $accessToken = Meta::ACCESS_TOKEN;
        $chunkSize = 5 * 1024 * 1024; // 5MB per chunk (adjust as needed)
        $filePath = $file->getPathname();
        $fileHandle = fopen($filePath, 'rb'); // Open file for reading

        // Meta allows 10 requests per second for upload
        // To be safe, we'll do 5 requests per second (200ms delay between requests)
        $delayMicroseconds = 200000; // 200ms = 0.2 seconds (5 requests/second)
        $isFirstChunk = true;

        while (!feof($fileHandle)) {
            $chunkData = fread($fileHandle, $chunkSize); // Read a chunk

            // Add delay BEFORE sending request (except for first chunk)
            if (!$isFirstChunk) {
                usleep($delayMicroseconds);
            }
            $isFirstChunk = false;

            $maxRetries = 3;
            $retryCount = 0;
            $uploaded = false;

            while (!$uploaded && $retryCount < $maxRetries) {
                $response = Http::withHeaders([
                    'Authorization' => "OAuth $accessToken",
                    'file_offset' => $fileOffset,
                ])
                    ->withBody($chunkData, 'application/octet-stream') // Send raw binary data
                    ->post("https://graph.facebook.com/v20.0/$uploadSessionId");

                // Check if rate limited
                if ($response->failed()) {
                    $responseData = $response->json();

                    // Check if it's a rate limit error
                    if (
                        isset($responseData['debug_info']['type']) &&
                        $responseData['debug_info']['type'] === 'UploadRateLimitedError'
                    ) {

                        $retryCount++;

                        if ($retryCount < $maxRetries) {
                            // Wait for the backoff period (convert from ms to microseconds)
                            $backoffMs = $responseData['backoff'] ?? 60000;
                            $backoffMicroseconds = $backoffMs * 1000;

                            \Log::warning("Rate limit hit. Waiting {$backoffMs}ms before retry {$retryCount}/{$maxRetries}");
                            usleep($backoffMicroseconds);
                            continue;
                        }
                    }

                    // If not rate limit or max retries reached
                    fclose($fileHandle);
                    return $this->response(false, 'Upload failed', [
                        'upload_session_id' => $uploadSessionId
                    ], 400);
                }

                $uploaded = true;
            }

            $fileOffset += strlen($chunkData); // Update offset for next chunk
        }

        fclose($fileHandle);

        return $this->response(true, 'Media Uploaded Successfully', $response->json()); // Return the file handle after successful upload
    }

    public function resumeUpload($uploadSessionId)
    {
        $accessToken = Meta::ACCESS_TOKEN;

        // Get current file offset
        $response = Http::withHeaders([
            'Authorization' => "OAuth $accessToken",
        ])->get("https://graph.facebook.com/v20.0/upload:$uploadSessionId");

        // Get the offset

        return $response->json('file_offset');
    }


    public function validateImageProperties($file)
    {
        // Validate file format (MIME type)
        $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png'];
        $mime = $file->getMimeType();

        if (!in_array($mime, $allowedMimes)) {
            return false; // Invalid MIME type
        }

        // Validate color depth and mode using Imagick
        try {
            $imagick = new Imagick();
            $imagick->readImage($file->getPathname());

            $imageFormat = strtolower($imagick->getImageFormat());
            $colorSpace = $imagick->getImageColorspace();
            $bitDepth = $imagick->getImageDepth();

            // Check format and color space
            if (
                ($imageFormat !== 'jpeg' && $imageFormat !== 'png') ||
                ($colorSpace !== Imagick::COLORSPACE_RGB && $colorSpace !== Imagick::COLORSPACE_GRAY)
            ) {
                return false;
            }

            // Check if image has alpha channel (RGBA)
            if (
                $imagick->getImageAlphaChannel() !== Imagick::ALPHACHANNEL_UNDEFINED &&
                $imagick->getImageAlphaChannel() !== Imagick::ALPHACHANNEL_ACTIVATE
            ) {
                return false;
            }

            // Check bit depth (channels)
            if ($bitDepth > 8) {
                return false;
            }

            return true; // Image passes validation
        } catch (Exception $e) {
            return false; // Error occurred during validation
        }
    }


    /**
     * @throws ConnectionException
     * @throws FileDoesNotExist
     * @throws FileIsTooBig
     */

    /**
     * @OA\Post(
     *     path="/api/whatsapp/media/{phoneNumberId}/upload",
     *     summary="Upload Media to WhatsApp",
     *     tags={"WhatsApp Media"},
     *     description="Uploads media to WhatsApp and saves the file using Spatie Media Library. The response contains the media URL from the Spatie library.",
     *     security={{"apiAuth":{}}},
     *     @OA\Parameter(
     *         name="phoneNumberId",
     *         in="path",
     *         required=true,
     *         description="The ID of the WhatsApp phone number for which the media is being uploaded",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="file",
     *                     type="string",
     *                     format="binary",
     *                     description="The file to be uploaded"
     *                 ),
     *                 @OA\Property(
     *                     property="type",
     *                     type="string",
     *                     description="The media type (e.g., image, video, etc.)",
     *                     example="image"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Media uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="media_id", type="string", example="wamid.HBgMMjAxMTI2MjIwODA2FQIAERgSOTJDREYzMDZBODc1RkY0OTgxAA=="),
     *             @OA\Property(property="media_url", type="string", example="https://yourdomain.com/uploads/1/whatsapp/1234/sample.jpg"),
     *             @OA\Property(property="message", type="string", example="Media uploaded successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Failed to get a valid access token",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to get a valid access token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid media properties",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid media properties.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Upload failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Upload failed")
     *         )
     *     ),
     * )
     */
    public function uploadMedia(Request $request, Channel $channel)
    {
        try {
            $whatsappConfiguration = $channel->connector->whatsappConfiguration;
            $whatsappBusinessAccount = $whatsappConfiguration->whatsappBusinessAccount;
            $whatsappPhoneNumber = $whatsappConfiguration->whatsappPhoneNumber;

            $phoneNumberId = $whatsappPhoneNumber->id;

            $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS'
                ? Meta::ACCESS_TOKEN
                : $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

            if (!$accessToken) {
                return $this->response(false, 'Failed to get a valid access token', null, 401);
            }

            if (!$request->hasFile('file')) {
                return response()->json(['error' => 'Missing file'], 400);
            }

            $file = $request->file('file');
            $fileName = $file->getClientOriginalName();
            $filePath = $file->getPathname();
            $fileType = $request->input('type');

            // ---------------------------------------------------
            // TRY TO CALL THE META MEDIA UPLOAD API
            // ---------------------------------------------------
            try {
                $url = "https://graph.facebook.com/v22.0/$phoneNumberId/media";

                $response = Http::withToken($accessToken)
                    ->attach('file', file_get_contents($filePath), $fileName)
                    ->post($url, [
                        'type' => $fileType,
                        'messaging_product' => 'whatsapp',
                    ]);
            } catch (\Throwable $e) {
                return response()->json([
                    'error' => 'Failed to upload media to Meta API',
                    'details' => $e->getMessage()
                ], 500);
            }

            // If Meta returns an error
            if (!$response->successful()) {
                return response()->json([
                    'error' => 'Meta API rejected the media upload',
                    'meta_error' => $response->json(),   // REAL META ERROR MESSAGE
                    'status' => $response->status(),
                ], 400);
            }

            // ---------------------------------------------------
            // SUCCESS — NOW UPLOAD TO OSS
            // ---------------------------------------------------
            try {
                $ossPath = $this->fileUploadService->upload(
                    $file,
                    'oss',
                    'uploads/whatsapp/' . $phoneNumberId . '/' . $fileName
                );

                $fileUrl = $this->fileUploadService->getFileUrl($ossPath);
            } catch (\Throwable $e) {
                return response()->json([
                    'error' => 'OSS upload failed',
                    'details' => $e->getMessage(),
                ], 500);
            }

            // Add to Spatie Media Library
            try {
                $whatsappPhoneNumber->addMedia($file)
                    ->toMediaCollection('whatsapp_media');
            } catch (\Throwable $e) {
                return response()->json([
                    'error' => 'Failed to attach media to model',
                    'details' => $e->getMessage()
                ], 500);
            }

            return response()->json([
                'media_id' => $response->json('id'),
                'media_url' => $fileUrl,
                'message' => 'Media uploaded successfully'
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Unexpected server error',
                'details' => $e->getMessage(),
                'line' => $e->getLine()
            ], 500);
        }
    }


    public function deleteMedia($mediaId)
    {
        $accessToken = Meta::ACCESS_TOKEN;

        // Make DELETE request
        $url = "https://graph.facebook.com/v20.0/$mediaId";
        $response = Http::withToken($accessToken)
            ->delete($url);

        if ($response->successful()) {
            return response()->json(['success' => true], 200);
        } else {
            return response()->json(['error' => 'Failed to delete media'], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/whatsapp/media/download/{mediaId}",
     *     summary="Download Media from WhatsApp Cloud API",
     *     tags={"WhatsApp Media"},
     *     description="Retrieve and download media content from WhatsApp Cloud API using the provided media ID.",
     *     operationId="downloadMedia",
     *     @OA\Parameter(
     *         name="mediaId",
     *         in="path",
     *         required=true,
     *         description="The ID of the media to download",
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully downloaded the media file.",
     *         @OA\MediaType(
     *             mediaType="application/octet-stream",
     *             @OA\Schema(
     *                 type="string",
     *                 format="binary"
     *             ),
     *             example="[binary content of the media file]"
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to retrieve or download media."
     *     ),
     *     security={
     *         {"apiAuth": {}}
     *     }
     * )
     */
    public function downloadMedia($mediaId)
    {
        $mediaResponse = $this->getMediaUrl($mediaId);

        if ($mediaResponse->status() !== 200) {
            return response()->json(['error' => 'Failed to retrieve media URL'], 500);
        }

        $mediaData = $mediaResponse->getData();
        $mediaUrl = $mediaData->url;
        $accessToken = Meta::ACCESS_TOKEN;

        // Download media file using the media URL
        $response = Http::withToken($accessToken)
            ->get($mediaUrl);

        if ($response->successful()) {
            return response($response->body(), 200)
                ->header('Content-Type', $response->header('Content-Type'));
        } else {
            return response()->json(['error' => 'Failed to download media'], 500);
        }
    }

    public function getMediaUrl($mediaId)
    {
        $accessToken = Meta::ACCESS_TOKEN;

        // Make GET request to retrieve the media URL
        $url = "https://graph.facebook.com/v20.0/$mediaId";
        $response = Http::withToken($accessToken)
            ->get($url);

        if ($response->successful()) {
            return response()->json($response->json(), 200);
        } else {
            return response()->json(['error' => 'Failed to retrieve media URL'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/whatsapp/media/{whatsappPhoneNumber}/upload-cloud",
     *     summary="Upload media to WhatsApp Cloud API",
     *     tags={"WhatsApp Media"},
     *     description="Uploads media to WhatsApp Cloud API and returns a media ID.",
     *     security={{"apiAuth":{}}},
     *     @OA\Parameter(
     *         name="whatsappPhoneNumber",
     *         in="path",
     *         required=true,
     *         description="ID of the WhatsApp phone number",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="file", description="Media file to upload"),
     *                 @OA\Property(property="type", type="string", description="Media type (image, video, etc.)", example="image")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Media uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="media_id", type="string", description="WhatsApp media ID", example="1166846181421424"),
     *             @OA\Property(property="message", type="string", example="Media uploaded successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Failed to get a valid access token"))
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Upload failed",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Upload to Cloud API failed"))
     *     )
     * )
     */

    public function uploadToCloudApi(Request $request, WhatsappPhoneNumber $whatsappPhoneNumber)
    {
        $phoneNumberId = $whatsappPhoneNumber->id;

        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        $accessToken = $whatsappBusinessAccount->name == 'Dreams SMS' ?
            Meta::ACCESS_TOKEN :
            $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);

        if (!$accessToken) {
            return $this->response(false, 'Failed to get a valid access token', null, 401);
        }

        // File upload logic for WhatsApp Cloud API
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();
        $filePath = $file->getPathname();
        $fileType = $request->input('type');

        $url = "https://graph.facebook.com/v20.0/$phoneNumberId/media";
        $response = Http::withToken($accessToken)
            ->attach('file', file_get_contents($filePath), $fileName)
            ->post($url, [
                'type' => $fileType,
                'messaging_product' => 'whatsapp',
            ]);

        if ($response->successful()) {
            return response()->json([
                'media_id' => $response->json('id'),
                'message' => 'Media uploaded successfully'
            ], 200);
        } else {
            return response()->json(['error' => 'Upload to Cloud API failed'], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/whatsapp/media/{whatsappPhoneNumber}/upload-oss",
     *     summary="Upload media to OSS",
     *     tags={"WhatsApp Media"},
     *     description="Uploads media to OSS and returns the media link.",
     *     security={{"apiAuth":{}}},
     *     @OA\Parameter(
     *         name="whatsappPhoneNumber",
     *         in="path",
     *         required=true,
     *         description="ID of the WhatsApp phone number",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="file", description="Media file to upload"),
     *                 @OA\Property(property="type", type="string", description="Media type (image, video, etc.)", example="image")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Media uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="media_url", type="string", description="OSS media URL", example="https://oss.yourdomain.com/uploads/whatsapp/12345/image.jpg"),
     *             @OA\Property(property="message", type="string", example="Media uploaded successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Upload failed",
     *         @OA\JsonContent(@OA\Property(property="error", type="string", example="Upload to OSS failed"))
     *     )
     * )
     */

    public function uploadToOSS(Request $request, Channel $channel)
    {
        // Validate the incoming file request
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
            'type' => 'required|string'
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $type = $request->input('type') ?? "image";

        $whatsappMedia = AppMedia::create([
            'user_identifier' => $request->input('user_identifier', 'anonymous_' . time()),
        ]);

        // Step 1: Upload the file to Alibaba OSS using Spatie Media Library and link it to the WhatsApp phone number
        $file = request()->file('file');
        $media = $whatsappMedia
            ->addMediaFromRequest('file')
            ->usingFileName(
                'whatsapp_' . $type . '_' . time() . '.' . $file->getClientOriginalExtension()
            )
            ->toMediaCollection('whatsapp-' . $type . 's', 'oss');

        // Return the signed URL to the frontend
        return $this->response(true, "Media Uploaded Successfully", ['oss_media_link' => $whatsappMedia->id]);
    }

    public function getMediaLinkFromCloudApi($mediaId)
    {
        $accessToken = Meta::ACCESS_TOKEN;
        $url = "https://graph.facebook.com/v20.0/$mediaId";

        $response = Http::withToken($accessToken)->get($url);

        if ($response->successful()) {
            $mediaUrl = $response->json('url');
            return $mediaUrl;
        }

        return null;
    }

    public function getChannelMediaGallery(Channel $channel)
    {
        $whatsappConfiguration = $channel->connector->whatsappConfiguration;

        if (!$whatsappConfiguration || !$whatsappConfiguration->whatsappPhoneNumber) {
            return response()->json(['error' => 'No WhatsApp phone number found for this channel'], 404);
        }

        $whatsappPhoneNumber = $whatsappConfiguration->whatsappPhoneNumber;

        // Retrieve all media from Spatie Media Library
        $mediaItems = $whatsappPhoneNumber
            ->getMedia('whatsapp_media') // <-- gallery collection
            ->map(function ($media) {
                return [
                    'id' => $media->id,
                    'filename' => $media->file_name,
                    'mime_type' => $media->mime_type,
                    'size' => $media->size,
                    'uploaded_at' => $media->created_at,
                    'url' => $media->getFullUrl(),              // public signed URL (OSS adapter)
                    'original_url' => $media->getUrl(),         // internal path
                ];
            });

        return response()->json([
            'success' => true,
            'media' => $mediaItems
        ]);
    }

    public function getOrganizationMediaGallery(Request $request, Organization $organization)
    {
        // Optional filter
        $filterType = $request->query('type'); // image, video, audio, document

        // 1. Get all WhatsApp channels belonging to this organization
        $whatsappChannels = Channel::query()
            ->whereHas('workspaces', function ($q) use ($organization) {
                $q->where('organization_id', $organization->id);
            })
            ->where('platform', 'whatsapp')
            ->with(['connector.whatsappConfiguration.whatsappPhoneNumber'])
            ->get();

        // 2. Get phone numbers
        $phoneNumbers = $whatsappChannels
            ->pluck('connector.whatsappConfiguration.whatsappPhoneNumber')
            ->filter()
            ->values();

        if ($phoneNumbers->isEmpty()) {
            return response()->json([
                'success' => true,
                'media' => [],
                'message' => 'No WhatsApp phone numbers found for this organization.'
            ]);
        }

        // 3. Build media gallery
        $mediaItems = collect();

        foreach ($phoneNumbers as $phoneNumber) {
            $items = $phoneNumber
                ->getMedia('whatsapp_media')
                ->filter(function ($media) use ($filterType) {

                    if (!$filterType)
                        return true;

                    $mime = $media->mime_type;

                    return match ($filterType) {
                        'image' => str_starts_with($mime, 'image/'),
                        'video' => str_starts_with($mime, 'video/'),
                        'audio' => str_starts_with($mime, 'audio/'),
                        'document' => (
                            str_starts_with($mime, 'application/') ||
                            str_contains($mime, 'pdf') ||
                            str_contains($mime, 'msword') ||
                            str_contains($mime, 'officedocument')
                        ),
                        default => true
                    };
                })
                ->map(function ($media) use ($phoneNumber) {
                    return [
                        'id' => $media->id,
                        'filename' => $media->file_name,
                        'mime_type' => $media->mime_type,
                        'size' => $media->size,
                        'uploaded_at' => $media->created_at,
                        'url' => $media->getFullUrl(),
                        'phone_number_id' => $phoneNumber->id,
                        'channel_id' => $phoneNumber->whatsappConfiguration->connector->channel_id,
                    ];
                });

            $mediaItems = $mediaItems->merge($items);
        }

        return response()->json([
            'success' => true,
            'count' => $mediaItems->count(),
            'media' => $mediaItems->sortByDesc('uploaded_at')->values()
        ]);
    }





}