<?php

namespace App\Http\Controllers\Whatsapp;

use App\Constants\Meta;
use App\Http\Controllers\BaseApiController;
use App\Http\Responses\ConversationMessage;
use App\Http\Responses\ValidatorErrorResponse;
use App\Models\Channel;
use App\Models\InteractiveMessageDraft;
use App\Models\WhatsappConsumerPhoneNumber;
use App\Models\WhatsappInteractiveMessage;
use App\Models\WhatsappMessage;
use App\Models\WhatsappPhoneNumber;
use App\Rules\WhatsappValidPhoneNumber;
use App\Traits\BusinessTokenManager;
use App\Traits\WhatsappMessageManager;
use App\Traits\WhatsappPhoneNumberManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class InteractiveMessageDraftController extends BaseApiController
{
    use BusinessTokenManager, WhatsappPhoneNumberManager, WhatsappMessageManager;

    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    /**
     * Get all interactive message drafts for a workspace.
     */
    public function index(Request $request, string $workspaceId): JsonResponse
    {
        $query = InteractiveMessageDraft::where('workspace_id', $workspaceId)
            ->when($request->get('type'), fn($q, $type) => $q->ofType($type))
            ->when($request->has('active'), fn($q) => $q->active())
            ->orderBy('created_at', 'desc');

        if ($request->boolean('all')) {
            $drafts = $query->get();
            return $this->response(true, 'Interactive message drafts fetched successfully', $drafts);
        }

        $drafts = $query->paginate($request->get('per_page', 15));
        return $this->paginateResponse(true, 'Interactive message drafts fetched successfully', $drafts);
    }

    /**
     * Store a new interactive message draft.
     */
    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $validator = $this->validateDraft($request);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $draft = InteractiveMessageDraft::create([
            'workspace_id' => $workspaceId,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'interactive_type' => $request->input('interactive_type'),
            'header' => $request->input('header'),
            'body' => $request->input('body'),
            'footer' => $request->input('footer'),
            'buttons' => $request->input('buttons'),
            'list_button_text' => $request->input('list_button_text'),
            'sections' => $request->input('sections'),
            'is_active' => $request->input('is_active', true),
        ]);

        return $this->response(true, 'Interactive message draft created successfully', $draft, 201);
    }

    /**
     * Get a single interactive message draft.
     */
    public function show(string $workspaceId, string $draftId): JsonResponse
    {
        $draft = InteractiveMessageDraft::where('workspace_id', $workspaceId)
            ->where('id', $draftId)
            ->first();

        if (!$draft) {
            return $this->response(false, 'Draft not found', null, 404);
        }

        return $this->response(true, 'Interactive message draft fetched successfully', $draft);
    }

    /**
     * Update an interactive message draft.
     */
    public function update(Request $request, string $workspaceId, string $draftId): JsonResponse
    {
        $draft = InteractiveMessageDraft::where('workspace_id', $workspaceId)
            ->where('id', $draftId)
            ->first();

        if (!$draft) {
            return $this->response(false, 'Draft not found', null, 404);
        }

        $validator = $this->validateDraft($request, true);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $draft->update($request->only([
            'name', 'description', 'interactive_type', 'header', 'body',
            'footer', 'buttons', 'list_button_text', 'sections', 'is_active'
        ]));

        return $this->response(true, 'Interactive message draft updated successfully', $draft->fresh());
    }

    /**
     * Delete an interactive message draft.
     */
    public function destroy(string $workspaceId, string $draftId): JsonResponse
    {
        $draft = InteractiveMessageDraft::where('workspace_id', $workspaceId)
            ->where('id', $draftId)
            ->first();

        if (!$draft) {
            return $this->response(false, 'Draft not found', null, 404);
        }

        $draft->delete();

        return $this->response(true, 'Interactive message draft deleted successfully');
    }

    /**
     * Send an interactive message using a draft.
     */
    public function send(Request $request, string $workspaceId, string $draftId): JsonResponse
    {
        $draft = InteractiveMessageDraft::where('workspace_id', $workspaceId)
            ->where('id', $draftId)
            ->active()
            ->first();

        if (!$draft) {
            return $this->response(false, 'Draft not found or inactive', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'from' => ['required', 'string', 'exists:whatsapp_phone_numbers,id'],
            'to' => ['required', 'string', new WhatsappValidPhoneNumber()],
            'conversation_id' => 'nullable|string|exists:conversations,id',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }

        $fromPhoneNumberId = $request->input('from');
        $toPhoneNumber = $request->input('to');

        // Get access token
        $whatsappPhoneNumber = WhatsappPhoneNumber::find($fromPhoneNumberId);
        $whatsappBusinessAccount = $whatsappPhoneNumber->whatsappBusinessAccount;

        if ($whatsappBusinessAccount->name === 'Dreams SMS') {
            $accessToken = Meta::ACCESS_TOKEN;
        } else {
            $accessToken = $this->getValidAccessToken($whatsappBusinessAccount->business_manager_account_id);
        }

        if (!$accessToken) {
            return $this->response(false, 'Failed to get a valid access token', null, 401);
        }

        // Build and send the message
        $interactivePayload = $draft->buildPayload();
        $result = $this->sendInteractiveToWhatsapp(
            $fromPhoneNumberId,
            $toPhoneNumber,
            $interactivePayload,
            $accessToken
        );

        if (!$result['success']) {
            return $this->response(false, $result['error'], null, 400);
        }

        // Save the message
        $whatsappMessage = $this->saveInteractiveMessageRecord(
            $result['data'],
            $draft,
            $fromPhoneNumberId,
            $toPhoneNumber,
            $whatsappBusinessAccount->id,
            $request->input('conversation_id')
        );

        return $this->response(
            true,
            'Interactive message sent successfully',
            new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM),
            200
        );
    }

    /**
     * Send interactive message to WhatsApp API.
     */
    protected function sendInteractiveToWhatsapp(
        string $fromPhoneNumberId,
        string $toPhoneNumber,
        array $interactivePayload,
        string $accessToken
    ): array {
        $baseUrl = env('FACEBOOK_GRAPH_API_BASE_URL');
        $version = env('FACEBOOK_GRAPH_API_VERSION', 'v20.0');
        $url = "$baseUrl/$version/$fromPhoneNumberId/messages";

        $message = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $toPhoneNumber,
            'type' => 'interactive',
            'interactive' => $interactivePayload,
        ];

        $response = Http::withToken($accessToken)->post($url, $message);

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => $response->json()['error']['message'] ?? 'Failed to send message',
            ];
        }

        return [
            'success' => true,
            'data' => $response->json(),
        ];
    }

    /**
     * Save the interactive message to the database.
     */
    protected function saveInteractiveMessageRecord(
        array $responseData,
        InteractiveMessageDraft $draft,
        string $fromPhoneNumberId,
        string $toPhoneNumber,
        string $whatsappBusinessAccountId,
        ?string $conversationId
    ): WhatsappMessage {
        $recipient = WhatsappConsumerPhoneNumber::firstOrCreate(
            [
                'phone_number' => $this->normalizePhoneNumber($toPhoneNumber),
                'whatsapp_business_account_id' => $whatsappBusinessAccountId,
            ],
            ['wa_id' => $responseData['contacts'][0]['wa_id'] ?? $toPhoneNumber]
        );

        $whatsappMessage = WhatsappMessage::create([
            'id' => $responseData['messages'][0]['id'],
            'whatsapp_phone_number_id' => $fromPhoneNumberId,
            'sender_type' => WhatsappPhoneNumber::class,
            'sender_id' => $fromPhoneNumberId,
            'agent_id' => auth('api')->user()->id ?? null,
            'recipient_id' => $recipient->id,
            'recipient_type' => WhatsappConsumerPhoneNumber::class,
            'sender_role' => WhatsappMessage::MESSAGE_SENDER_ROLE_BUSINESS,
            'type' => WhatsappMessage::MESSAGE_TYPE_INTERACTIVE,
            'direction' => WhatsappMessage::MESSAGE_DIRECTION_SENT,
            'status' => WhatsappMessage::MESSAGE_STATUS_INITIATED,
            'conversation_id' => $conversationId,
        ]);

        $interactiveMessage = WhatsappInteractiveMessage::create([
            'whatsapp_message_id' => $responseData['messages'][0]['id'],
            'interactive_type' => $draft->interactive_type,
            'payload' => $draft->buildPayload(),
        ]);

        $whatsappMessage->update([
            'messageable_id' => $interactiveMessage->id,
            'messageable_type' => WhatsappInteractiveMessage::class,
        ]);

        $this->saveMessageStatus($whatsappMessage->id, WhatsappMessage::MESSAGE_STATUS_INITIATED);

        return $whatsappMessage->fresh(['messageable', 'statuses']);
    }

    /**
     * Validate draft request.
     */
    protected function validateDraft(Request $request, bool $isUpdate = false): \Illuminate\Validation\Validator
    {
        $rules = [
            'name' => [($isUpdate ? 'sometimes' : 'required'), 'string', 'max:255'],
            'description' => 'nullable|string',
            'interactive_type' => [($isUpdate ? 'sometimes' : 'required'), 'string', 'in:button,list'],
            'header' => 'nullable|array',
            'header.type' => 'nullable|string|in:text,image,video,document',
            'header.text' => 'required_if:header.type,text|nullable|string|max:60',
            'body' => [($isUpdate ? 'sometimes' : 'required'), 'string', 'max:1024'],
            'footer' => 'nullable|string|max:60',
            'is_active' => 'boolean',
        ];

        // Conditional validation based on type
        if ($request->input('interactive_type') === 'button') {
            $rules['buttons'] = [($isUpdate ? 'sometimes' : 'required'), 'array', 'min:1', 'max:3'];
            $rules['buttons.*.id'] = 'required|string|max:256';
            $rules['buttons.*.title'] = 'required|string|max:20';
        } elseif ($request->input('interactive_type') === 'list') {
            $rules['list_button_text'] = [($isUpdate ? 'sometimes' : 'required'), 'string', 'max:20'];
            $rules['sections'] = [($isUpdate ? 'sometimes' : 'required'), 'array', 'min:1', 'max:10'];
            $rules['sections.*.title'] = 'required|string|max:24';
            $rules['sections.*.rows'] = 'required|array|min:1|max:10';
            $rules['sections.*.rows.*.id'] = 'required|string|max:200';
            $rules['sections.*.rows.*.title'] = 'required|string|max:24';
            $rules['sections.*.rows.*.description'] = 'nullable|string|max:72';
        }

        return Validator::make($request->all(), $rules);
    }
}

