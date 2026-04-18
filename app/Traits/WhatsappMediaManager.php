<?php

namespace App\Traits;

use App\Constants\Meta;
use App\Models\WhatsappPhoneNumber;
use App\Services\FileUploadService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Log;
use OSS\OssClient;

trait WhatsappMediaManager
{

    public function downloadMediaAndSave($mediaId, WhatsappPhoneNumber $whatsappPhoneNumber): JsonResponse
    {
        // Step 1: Get the media URL from WhatsApp
        $mediaUrl = $this->getMediaUrl($mediaId);

        if (empty($mediaUrl)) {
            return response()->json(['error' => 'Failed to retrieve media URL'], 500);
        }

        $accessToken = Meta::ACCESS_TOKEN;

        // Step 2: Download the media file using the media URL
        $response = Http::withToken($accessToken)->get($mediaUrl);


        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to download media'], 500);
        }

        // Step 3: Save the media file in OSS
        // $user = $whatsappPhoneNumber->whatsappBusinessAccount->businessManagerAccount->user;
        $fileName = basename($mediaUrl); // Extract filename from the URL
        $filePath = 'uploads/whatsapp/' . $whatsappPhoneNumber->id . '/' . $fileName;

        // Use the file content from the response
        $fileContent = $response->body();
        $mimeType = $response->header('Content-Type');

        // Create an instance of FileUploadService
        $fileUploadService = new FileUploadService(); // You can pass dependencies to the constructor if required

        // Upload the file content to OSS
        $ossFilePath = $fileUploadService->uploadFromContent($fileContent, 'oss', $filePath);

//        // Step 4: Ensure the file was uploaded correctly
//        if (!$this->fileUploadService->getFileOss($ossFilePath)) {
//            return response()->json(['error' => 'File does not exist after upload in OSS.'], 500);
//        }

        // Step 5: Get the temporary URL for the uploaded file in OSS
        $fileUrl = $fileUploadService->getFileUrl($ossFilePath);


        // Step 6: Attach the media to the WhatsApp phone number model using Spatie Media Library
        $media = $whatsappPhoneNumber->addMediaFromBase64(base64_encode($fileContent)) // Base64 encode the file content
        ->usingFileName($fileName)
            ->withCustomProperties(['mime_type' => $mimeType]) // Attach MIME type
            ->toMediaCollection('whatsapp_media');

        $whatsappPhoneNumber->save();

        // Step 7: Return response with the media URL
        return response()->json([
            'media_id' => $mediaId,
            'media_url' => $fileUrl,
            'message' => 'Media uploaded and saved successfully'
        ], 200);
    }

    public function getMediaUrl($mediaId)
    {
        $accessToken = Meta::ACCESS_TOKEN;

        // Make GET request to retrieve the media URL
        $url = "https://graph.facebook.com/v20.0/$mediaId";
        $response = Http::withToken($accessToken)
            ->get($url);


        if ($response->successful()) {
            return $response->json()['url'] ?? null; // Extract the URL from the response
        } else {
            return null; // Handle the case where fetching the URL fails
        }
    }

    public function getMediaLinkFromCloudApi($mediaId): string|null
    {
        $accessToken = Meta::ACCESS_TOKEN;
        $url = "https://graph.facebook.com/v20.0/$mediaId";

        $response = Http::withToken($accessToken)->get($url);

        if ($response->successful()) {
            return $response->json('url');
        }

        return null;
    }

    public function getOssMediaLink($mediaId, $currentLink): ?string
    {
        // If media already has a valid link (external URL), return it
        if (!empty($currentLink)) {
            return $currentLink;
        }

        // Step 1: Get the media URL from WhatsApp
        $mediaUrl = $this->getMediaUrl($mediaId);

        if (empty($mediaUrl)) {
            return response()->json(['error' => 'Failed to retrieve media URL'], 500);
        }

        $accessToken = Meta::ACCESS_TOKEN;

        // Step 2: Download the media file using the media URL
        $response = Http::withToken($accessToken)->get($mediaUrl);


        if (!$response->successful()) {
            return response()->json(['error' => 'Failed to download media'], 500);
        }

        // Step 3: Save the media file in OSS
        // $user = $whatsappPhoneNumber->whatsappBusinessAccount->businessManagerAccount->user;
        $fileName = basename($mediaUrl); // Extract filename from the URL
        $filePath = 'uploads/whatsapp/' . $fileName;

        // Use the file content from the response
        $fileContent = $response->body();

        // Create an instance of FileUploadService
        $fileUploadService = new FileUploadService(); // You can pass dependencies to the constructor if required

        // Upload the file content to OSS
        $ossFilePath = $fileUploadService->uploadFromContent($fileContent, 'oss', $filePath);


        // Get the temporary URL for the uploaded file in OSS
        return $fileUploadService->getFileUrl($ossFilePath);
    }

    public function downloadMedia($mediaId)
    {
        $accessToken = Meta::ACCESS_TOKEN;
        $url = "https://graph.facebook.com/v20.0/$mediaId";

        // Make GET request to download the media
        return Http::withToken($accessToken)->get($url);
    }

    protected function getFileExtensionFromMimeType($mimeType): string
    {
        $mimeMap = [
            // Audio types
            'audio/aac' => 'aac',
            'audio/amr' => 'amr',
            'audio/mpeg' => 'mp3',
            'audio/mp4' => 'm4a',
            'audio/ogg' => 'ogg',

            // Document types
            'text/plain' => 'txt',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/pdf' => 'pdf',

            // Image types
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',

            // Sticker types (WebP)
            'image/webp' => 'webp',

            // Video types
            'video/3gp' => '3gp',
            'video/mp4' => 'mp4',
        ];

        return $mimeMap[$mimeType] ?? 'bin';  // Default to 'bin' if unknown type
    }

    /**
     * Helper function to download the image from WhatsApp Cloud API
     *
     * @param string $mediaID
     * @return string|null
     * @throws ConnectionException
     */
    public function downloadMediaFromWhatsAppCloudAPI(string $mediaID): ?string
    {
        // WhatsApp API URL to get media
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $accessToken = Meta::ACCESS_TOKEN; // Get a valid access token

        $url = "$baseUrl/$version/$mediaID";

        // Make the request to download media
        $response = Http::withToken($accessToken)->get($url);

        if ($response->successful()) {
            // Return the direct URL of the image to download
            $mediaURL = $response->json('url'); // Assuming the API returns the media URL
            return $mediaURL;
        } else {
            Log::error('Failed to download image from WhatsApp API: ' . $response->body());
            return null;
        }
    }

    public function downloadMediaFromWhatsAppCloudAPIV2(string $mediaID): ?array
    {
        // WhatsApp API URL to get media
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $accessToken = Meta::ACCESS_TOKEN; // Get a valid access token

        $url = "$baseUrl/$version/$mediaID";

        // Make the request to download media
        $response = Http::withToken($accessToken)->get($url);

        if ($response->successful()) {
            // Return the direct URL of the image to download
            return[
                'url' => $response->json('url'),
                'mime_type' => $response->json('mime_type'),
                'id' => $response->json('mime_type'),
                'sha256' => $response->json('sha256'),
                'messaging_product' => $response->json('messaging_product'),
                'file_size' =>  $response->json('file_size')
            ];
        } else {
            Log::error('Failed to download image from WhatsApp API: ' . $response->body());
            return null;
        }
    }

    public function getMediaUrlFromOssForPreview(int $expirationInSeconds = 864000): ?string
    {
        // Retrieve the media using Spatie's getFirstMedia method
        $media = $this->getFirstMedia('whatsapp-images');  // Replace 'whatsapp-images' with your media collection name

        if (!$media) {
            return null;  // Return null if no media found
        }

        // Get the path of the media object in OSS
        $objectPath = $media->getPath(); // This retrieves the path in OSS

        // Initialize OSS Client
        $ossClient = new OssClient(
            env('OSS_ACCESS_KEY_ID'),
            env('OSS_ACCESS_KEY_SECRET'),
            env('OSS_ENDPOINT')
        );

        $bucket = env('OSS_BUCKET');

        // Generate the signed URL with 'Content-Disposition: inline' for preview
        return $ossClient->signUrl($bucket, $objectPath, $expirationInSeconds, 'GET', [
            OssClient::OSS_HEADERS => [
                'Content-Disposition' => 'inline',  // Makes the file previewable instead of downloadable
            ],
        ]);
    }

    public function regenerateSignedPreviewUrlFromLink(string $originalUrl, int $expirationInSeconds = 864000): ?string
    {
        try {
            // Parse the path from the full signed URL
            $parsedUrl = parse_url($originalUrl);

            if (!isset($parsedUrl['path'])) {
                return null;
            }
            // FIX: Decode path to avoid double-encoding
            $objectPath = urldecode(ltrim($parsedUrl['path'], '/'));

            // Initialize OSS client
            $ossClient = new OssClient(
                env('OSS_ACCESS_KEY_ID'),
                env('OSS_ACCESS_KEY_SECRET'),
                env('OSS_ENDPOINT')
            );

            $bucket = env('OSS_BUCKET');

            // Generate the signed previewable URL
            return $ossClient->signUrl($bucket, $objectPath, $expirationInSeconds, 'GET', [
                OssClient::OSS_HEADERS => [
                    'Content-Disposition' => 'inline',
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);
            return null;
        }
    }

}
