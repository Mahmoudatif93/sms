<?php

namespace App\Domain\Conversation\Actions\WhatsApp;

use App\Constants\Meta;
use App\Domain\Conversation\DTOs\WhatsAppMessageResultDTO;
use App\Domain\Conversation\Repositories\WhatsAppMessageRepositoryInterface;
use App\Models\AppMedia;
use App\Models\WhatsappAudioMessage;
use App\Models\WhatsappDocumentMessage;
use App\Models\WhatsappImageMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Models\WhatsappVideoMessage;
use App\Rules\WhatsappValidPhoneNumber;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappPhoneNumberManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SendFilesMessageAction
{
    use BusinessTokenManager, WhatsappPhoneNumberManager;

    public function __construct(
        private WhatsAppMessageRepositoryInterface $repository
    ) {}

    /**
     * Execute sending multiple file messages.
     * Returns success if at least one file was sent successfully.
     */
    public function execute(Request $request): WhatsAppMessageResultDTO
    {
        try {
            // Validate request
            $validation = $this->validateRequest($request);
            if ($validation->fails()) {
                return WhatsAppMessageResultDTO::failure('Validation Error(s)', 422);
            }

            $fromPhoneNumberId = $request->input('from');
            $toPhoneNumber = $request->input('to');
            $conversationId = $request->input('conversation_id');
            $files = $request->input('files');

            // Get access token
            $accessToken = $this->getAccessToken($fromPhoneNumberId);
            if (!$accessToken) {
                return WhatsAppMessageResultDTO::failure('Failed to get a valid access token', 401);
            }

            $sentMessages = [];
            $errors = [];

            foreach ($files as $index => $fileData) {
                $result = $this->sendSingleFile(
                    $request,
                    $index,
                    $fileData,
                    $fromPhoneNumberId,
                    $toPhoneNumber,
                    $conversationId,
                    $accessToken
                );

                if ($result['success']) {
                    $sentMessages[] = $result['message'];
                } else {
                    $errors[] = $result['error'];
                }
            }

            if (empty($sentMessages)) {
                return WhatsAppMessageResultDTO::failure(
                    'All files failed to send: ' . implode('; ', $errors),
                    500
                );
            }

            // Return all sent messages with any errors
            return WhatsAppMessageResultDTO::success($sentMessages, $errors);

        } catch (\Exception $e) {
            Log::error('SendFilesMessageAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return WhatsAppMessageResultDTO::failure('An error occurred: ' . $e->getMessage(), 500);
        }
    }

    private function validateRequest(Request $request): \Illuminate\Validation\Validator
    {
        return Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'files' => 'required|array|min:1',
            'files.*.type' => 'required|string|in:image,video,audio,document',
            'files.*.file' => 'required|file',
            'files.*.caption' => 'nullable|string|max:1024',
        ]);
    }

    private function getAccessToken(string $phoneNumberId): ?string
    {
        $whatsappPhoneNumber = WhatsappPhoneNumber::find($phoneNumberId);
        if (!$whatsappPhoneNumber) {
            return null;
        }

        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        if ($whatsappBusinessAccount->name == 'Dreams SMS') {
            return Meta::ACCESS_TOKEN;
        }

        return $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
    }

    private function sendSingleFile(
        Request $request,
        int $index,
        array $fileData,
        string $fromPhoneNumberId,
        string $toPhoneNumber,
        ?string $conversationId,
        string $accessToken
    ): array {
        try {
            $fileType = $fileData['type'];
            $caption = $fileData['caption'] ?? null;
            $uploadedFile = $request->file("files.{$index}.file");

            if (!$uploadedFile) {
                return ['success' => false, 'error' => "File at index {$index} is missing"];
            }

            // Create AppMedia and upload to OSS
            $appMedia = AppMedia::create([
                'user_identifier' => auth('api')->user()->id ?? 'anonymous_' . time(),
            ]);

            $collectionName = "whatsapp-{$fileType}s";
            $media = $appMedia
                ->addMedia($uploadedFile)
                ->usingFileName(
                    "whatsapp_{$fileType}_" . time() . '_' . $index . '.' . $uploadedFile->getClientOriginalExtension()
                )
                ->toMediaCollection($collectionName, 'oss');

            $mediaLink = $media->getTemporaryUrl(Carbon::now()->addMinutes(5));
            $mediaLinkSave = $media->getUrl();

            // Build WhatsApp message payload
            $message = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $toPhoneNumber,
                'type' => $fileType,
                $fileType => [
                    'link' => $mediaLink,
                ],
            ];

            if (!empty($caption)) {
                $message[$fileType]['caption'] = $caption;
            }

            if ($fileType === 'document') {
                $message[$fileType]['filename'] = $uploadedFile->getClientOriginalName();
            }

            // Send to WhatsApp API
            $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
            $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
            $url = "{$baseUrl}/{$version}/{$fromPhoneNumberId}/messages";

            $response = Http::retry(3, 100)->timeout(30)->withToken($accessToken)->post($url, $message);

            if (!$response->successful()) {
                $appMedia->delete();
                return [
                    'success' => false,
                    'error' => "Failed to send {$fileType} at index {$index}: " . json_encode($response->json()),
                ];
            }

            $responseData = json_decode($response->body());
            $waId = $responseData->contacts[0]->wa_id;

            // Save message to database
            $savedMessage = $this->saveMessage(
                $fromPhoneNumberId,
                $toPhoneNumber,
                $conversationId,
                $fileType,
                $caption,
                $responseData,
                $waId,
                $mediaLinkSave,
                $media,
                $uploadedFile->getClientOriginalName()
            );

            return ['success' => true, 'message' => $savedMessage];

        } catch (\Exception $e) {
            Log::error("Failed to send file at index {$index}", [
                'error' => $e->getMessage(),
            ]);

            return ['success' => false, 'error' => "Exception at index {$index}: " . $e->getMessage()];
        }
    }

    private function saveMessage(
        string $fromPhoneNumberId,
        string $toPhoneNumber,
        ?string $conversationId,
        string $fileType,
        ?string $caption,
        object $responseData,
        string $waId,
        string $mediaLink,
        \Spatie\MediaLibrary\MediaCollections\Models\Media $media,
        string $filename
    ): WhatsappMessage {
        $messageId = $responseData->messages[0]->id;

        $whatsappPhoneNumber = WhatsappPhoneNumber::find($fromPhoneNumberId);
        $businessAccountId = $whatsappPhoneNumber->whatsapp_business_account_id;

        // Get or create recipient
        $recipient = $this->repository->findOrCreateConsumer(
            $this->normalizePhoneNumber($toPhoneNumber),
            $businessAccountId,
            $waId
        );

        // Determine message type constant
        $messageTypeConstant = match ($fileType) {
            'image' => WhatsappMessage::MESSAGE_TYPE_IMAGE,
            'video' => WhatsappMessage::MESSAGE_TYPE_VIDEO,
            'audio' => WhatsappMessage::MESSAGE_TYPE_AUDIO,
            'document' => WhatsappMessage::MESSAGE_TYPE_DOCUMENT,
        };

        // Create main message
        $message = $this->repository->create([
            'id' => $messageId,
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'agent_id' => auth('api')->id(),
            'recipient_id' => $recipient->id,
            'recipient_type' => get_class($recipient),
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => $messageTypeConstant,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $conversationId,
        ]);

        // Create type-specific message record
        $messageableData = [
            'whatsapp_message_id' => $messageId,
            'link' => $mediaLink,
            'media_id' => $media->id,
            'caption' => $caption,
        ];

        $messageable = match ($fileType) {
            'image' => WhatsappImageMessage::create($messageableData),
            'video' => WhatsappVideoMessage::create($messageableData),
            'audio' => WhatsappAudioMessage::create($messageableData),
            'document' => WhatsappDocumentMessage::create(array_merge($messageableData, [
                'filename' => $filename,
            ])),
        };

        // Update messageable relation
        $messageableType = match ($fileType) {
            'image' => WhatsappImageMessage::class,
            'video' => WhatsappVideoMessage::class,
            'audio' => WhatsappAudioMessage::class,
            'document' => WhatsappDocumentMessage::class,
        };

        $this->repository->update($message, [
            'messageable_id' => $messageable->id,
            'messageable_type' => $messageableType,
        ]);

        // Update media model_id and model_type to link to messageable
        $media->model_id = $messageable->id;
        $media->model_type = $messageableType;
        $media->save();

        // Save initial status
        $this->repository->saveMessageStatus($messageId, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        return $message->fresh(['messageable', 'statuses']);
    }
}
