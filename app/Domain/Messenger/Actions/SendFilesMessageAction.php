<?php

namespace App\Domain\Messenger\Actions;

use App\Domain\Messenger\Repositories\MessengerMessageRepositoryInterface;
use App\Models\AppMedia;
use App\Models\MessengerAttachmentMessage;
use App\Models\MessengerConsumer;
use App\Models\MessengerMessage;
use App\Models\MetaPage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SendFilesMessageAction
{
    public function __construct(
        private MessengerMessageRepositoryInterface $repository
    ) {
    }

    /**
     * Execute sending multiple file messages.
     * Returns success if at least one file was sent successfully.
     */
    public function execute(Request $request, string $pageId, string $recipientPsid, ?string $conversationId, ?string $replyToMessageId = null): array
    {
        try {
            $validation = $this->validateRequest($request);
            if ($validation->fails()) {
                return [
                    'success' => false,
                    'error' => 'Validation Error(s)',
                    'errors' => $validation->errors()->toArray(),
                    'messages' => [],
                ];
            }

            $metaPage = $this->repository->findMetaPage($pageId);
            if (!$metaPage) {
                return [
                    'success' => false,
                    'error' => 'Meta Page not found',
                    'messages' => [],
                ];
            }

            $accessToken = $this->repository->getPageAccessToken($metaPage);
            if (!$accessToken) {
                return [
                    'success' => false,
                    'error' => 'Failed to get a valid access token',
                    'messages' => [],
                ];
            }

            $consumer = $this->repository->findConsumerByPsid($recipientPsid, $pageId);
            if (!$consumer) {
                return [
                    'success' => false,
                    'error' => 'Messenger consumer not found',
                    'messages' => [],
                ];
            }

            $files = $request->input('files');
            $sentMessages = [];
            $errors = [];

            foreach ($files as $index => $fileData) {
                $result = $this->sendSingleFile(
                    $request,
                    $index,
                    $fileData,
                    $metaPage,
                    $consumer,
                    $recipientPsid,
                    $conversationId,
                    $accessToken,
                    $replyToMessageId
                );

                if ($result['success']) {
                    $sentMessages[] = $result['message'];
                } else {
                    $errors[] = $result['error'];
                }
            }

            if (empty($sentMessages)) {
                return [
                    'success' => false,
                    'error' => 'All files failed to send: ' . implode('; ', $errors),
                    'messages' => [],
                    'errors' => $errors,
                ];
            }

            return [
                'success' => true,
                'error' => null,
                'messages' => $sentMessages,
                'errors' => $errors,
            ];

        } catch (\Exception $e) {
            Log::error('SendFilesMessageAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'An error occurred: ' . $e->getMessage(),
                'messages' => [],
            ];
        }
    }

    private function validateRequest(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'files' => 'required|array|min:1',
            'files.*.type' => 'required|string|in:image,video,audio,file',
            'files.*.file' => 'required|file|max:25600', // 25MB in KB (Facebook Messenger limit)
            'files.*.caption' => 'nullable|string|max:1000',
        ], [
            'files.*.file.max' => 'The file at position :position must not exceed 25MB (Facebook Messenger limit).',
        ]);
    }

    private function sendSingleFile(
        Request $request,
        int $index,
        array $fileData,
        MetaPage $metaPage,
        MessengerConsumer $consumer,
        string $recipientPsid,
        ?string $conversationId,
        string $accessToken,
        ?string $replyToMessageId = null
    ): array {
        try {
            $fileType = $fileData['type']; // Used for Facebook API (e.g., 'file')
            $storageType = $fileData['storage_type'] ?? $fileType; // Used for DB storage (e.g., 'document')
            $caption = $fileData['caption'] ?? null;
            $uploadedFile = $request->file("files.{$index}.file");
            if (!$uploadedFile) {
                return ['success' => false, 'error' => "File at index {$index} is missing"];
            }

            // Create AppMedia and upload to OSS
            $appMedia = AppMedia::create([
                'user_identifier' => auth('api')->user()->id ?? 'anonymous_' . time(),
            ]);

            $collectionName = "messenger-{$storageType}s";
            $media = $appMedia
                ->addMedia($uploadedFile)
                ->usingFileName(
                    "messenger_{$storageType}_" . time() . '_' . $index . '.' . $uploadedFile->getClientOriginalExtension()
                )
                ->toMediaCollection($collectionName, 'oss');

            $mediaUrl = $media->getTemporaryUrl(Carbon::now()->addMinutes(5));
            $mediaUrlSave = $media->getUrl();

            // Send attachment to Facebook API (uses API type like 'file')
            $apiResponse = $this->sendToFacebookApi(
                $metaPage,
                $recipientPsid,
                $fileType,
                $mediaUrl,
                $accessToken,
                $replyToMessageId
            );

            if (!$apiResponse['success']) {
                $appMedia->delete();
                return [
                    'success' => false,
                    'error' => "Failed to send {$storageType} at index {$index}: " . $apiResponse['error'],
                ];
            }

            // Save message to database (uses storage type like 'document')
            $savedMessage = $this->saveMessage(
                $metaPage,
                $consumer,
                $conversationId,
                $storageType,
                $caption,
                $apiResponse['message_id'],
                $apiResponse['attachment_id'] ?? null,
                $mediaUrlSave,
                $media,
                $uploadedFile->getClientOriginalName(),
                $replyToMessageId
            );

            return ['success' => true, 'message' => $savedMessage];

        } catch (\Exception $e) {
            Log::error("Failed to send file at index {$index}", [
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => "Exception at index {$index}: " . $e->getMessage()];
        }
    }

    private function sendToFacebookApi(
        MetaPage $metaPage,
        string $recipientPsid,
        string $type,
        string $url,
        string $accessToken,
        ?string $replyToMessageId = null
    ): array {
        try {
            $apiUrl = "https://graph.facebook.com/v24.0/{$metaPage->id}/messages";

            $message = [
                'attachment' => [
                    'type' => $type,
                    'payload' => [
                        'url' => $url,
                        'is_reusable' => false,
                    ],
                ],
            ];

            // Add reply_to if provided
            $payload = [
                'recipient' => ['id' => $recipientPsid],
                'message' => $message,
            ];

            if ($replyToMessageId) {
                 $payload['reply_to'] = ['mid' => $replyToMessageId];
            }

            $response = Http::retry(3, 100)
                ->timeout(75)
                ->withToken($accessToken)
                ->post($apiUrl, $payload);

            if (!$response->successful()) {
                $errorData = $response->json();
                $errorMessage = $errorData['error']['message'] ?? 'Unknown error from Facebook API';

                Log::error('Failed to send Messenger attachment', [
                    'page_id' => $metaPage->id,
                    'psid' => $recipientPsid,
                    'type' => $type,
                    'status_code' => $response->status(),
                    'response' => $errorData,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'message_id' => null,
                ];
            }

            $responseData = $response->json();

            return [
                'success' => true,
                'error' => null,
                'message_id' => $responseData['message_id'] ?? null,
                'recipient_id' => $responseData['recipient_id'] ?? null,
                'attachment_id' => $responseData['attachment_id'] ?? null,
            ];

        } catch (\Exception $e) {
            Log::error('Exception while sending Messenger attachment', [
                'page_id' => $metaPage->id,
                'psid' => $recipientPsid,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message_id' => null,
            ];
        }
    }

    private function saveMessage(
        MetaPage $metaPage,
        MessengerConsumer $consumer,
        ?string $conversationId,
        string $fileType,
        ?string $caption,
        string $messageId,
        ?string $attachmentId,
        string $mediaUrl,
        \Spatie\MediaLibrary\MediaCollections\Models\Media $media,
        string $filename,
        ?string $replyToMessageId = null
    ): MessengerMessage {
        // Create main message
        $message = $this->repository->createOutgoingMessage([
            'id' => $messageId,
            'meta_page_id' => $metaPage->id,
            'conversation_id' => $conversationId,
            'sender_type' => MetaPage::class,
            'sender_id' => $metaPage->id,
            'recipient_type' => MessengerConsumer::class,
            'recipient_id' => $consumer->id,
            'sender_role' => MessengerMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => $fileType,
            'direction' => MessengerMessage::MESSAGE_DIRECTION_SENT,
            'status' => MessengerMessage::MESSAGE_STATUS_SENT,
            'replied_to_message_id' => $replyToMessageId,
        ]);

        // Save initial status
        $this->repository->saveMessageStatus($messageId, MessengerMessage::MESSAGE_STATUS_SENT);

        // Create attachment message record
        $attachmentMessage = MessengerAttachmentMessage::create([
            'messenger_message_id' => $messageId,
            'type' => $fileType,
            'attachment_id' => $attachmentId,
            'url' => $mediaUrl,
            'filename' => $filename,
            'caption' => $caption,
            'media_id' => $media->id,
        ]);

        // Update messageable relation
        $this->repository->updateMessageable($messageId, $attachmentMessage);

        // Update media model_id and model_type
        $media->model_id = $attachmentMessage->id;
        $media->model_type = MessengerAttachmentMessage::class;
        $media->save();

        return $this->repository->findMessage($messageId);
    }
}
