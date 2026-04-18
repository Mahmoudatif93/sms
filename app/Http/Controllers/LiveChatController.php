<?php

namespace App\Http\Controllers;

use App\Models\AttributeDefinition;
use App\Models\Channel;
use App\Models\PostChatFormFieldResponse;
use App\Models\ContactEntity;
use App\Models\User;
use App\Models\Conversation;
use App\Models\PreChatFormFieldResponse;
use App\Models\LiveChatTextMessage;
use App\Models\LiveChatFileMessage;
use App\Models\LiveChatMessage;
use App\Models\PreChatForm;
use App\Models\PostChatForm;
use App\Models\Widget;
use App\Models\Workspace;
use App\Models\Identifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Traits\ConversationManager;
use App\Traits\ContactManager;
use App\Traits\LiveChatMessageManager;
use App\Traits\SimpleContactManager;
use App\Services\Messaging\LiveChatMessageHandler;
use App\Services\FileUploadService;
use App\Helpers\Sms\MessageHelper;
use Throwable;

class LiveChatController extends BaseApiController
{
    use ConversationManager, ContactManager, LiveChatMessageManager, SimpleContactManager;
    protected $liveChatMessageHandler;
    protected FileUploadService $fileUploadService;
    public function __construct(LiveChatMessageHandler $liveChatMessageHandler, FileUploadService $fileUploadService)
    {
        $this->liveChatMessageHandler = $liveChatMessageHandler;
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Update widget settings
     */
    public function updateWidgetSettings(Request $request, Workspace $workspace, $id): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'language' => 'nullable|string|max:10',
                'welcome_message' => 'nullable|string|max:255',
                'message_placeholder' => 'nullable|string|max:255',
                'theme_color' => 'nullable|string|max:7',
                'name' => 'nullable|string|max:255',
                'allowed_domains' => 'nullable|array',
                'position' => 'nullable|string|in:left,right',
                'logo' => 'nullable',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $widget = Widget::findOrFail($id);

            $updateData = [
                'language' => $request->input('language', $widget->language),
                'position' => $request->input('position', $widget->position),
                'welcome_message' => $request->input('welcome_message', $widget->welcome_message),
                'message_placeholder' => $request->input('message_placeholder', $widget->message_placeholder),
                'theme_color' => $request->input('theme_color', $widget->theme_color),
                'allowed_domains' => ($request->input('allowed_domains') && !empty($request->input('allowed_domains'))) ? json_encode($request->input('allowed_domains')) : null,
            ];

            $widget->update($updateData);

            // Handle logo upload (file or base64) using Spatie Media Library
            if ($request->hasFile('logo')) {
                $widget->clearMediaCollection('logo');
                $widget->addMediaFromRequest('logo')->toMediaCollection('logo', 'oss');
            } elseif ($request->has('logo') && is_string($request->input('logo'))) {
                $logoData = $request->input('logo');

                if (preg_match('/^data:image\/(\w+);base64,/', $logoData, $matches)) {
                    $extension = strtolower($matches[1]);
                    $allowedExtensions = ['jpeg', 'jpg', 'png', 'gif', 'svg', 'webp'];

                    if (!in_array($extension, $allowedExtensions)) {
                        return $this->response(false, 'Invalid image format. Allowed: jpeg, png, jpg, gif, svg, webp', null, 400);
                    }

                    $widget->clearMediaCollection('logo');
                    $widget->addMediaFromBase64($logoData)
                        ->usingFileName(Str::uuid() . '.' . $extension)
                        ->toMediaCollection('logo', 'oss');
                }
            }

            if ($request->has('name')) {
                $channel = $widget->liveChatConfiguration->connector->channel;
                if ($channel) {
                    $channel->update(['name' => $request->input('name')]);
                }
            }

            $widget->refresh();

            return $this->response(true, 'Widget settings updated successfully', [
                'widget' => [
                    'id' => $widget->id,
                    'language' => $widget->language,
                    'welcome_message' => $widget->welcome_message,
                    'message_placeholder' => $widget->message_placeholder,
                    'theme_color' => $widget->theme_color,
                    'allowed_domains' => $widget->allowed_domains,
                    'logo_url' => $widget->logo_url,
                ],
            ]);
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }
    /**
     * Initialize chat and get widget configuration
     */
    public function initializeChat(Request $request): JsonResponse
    {

        try {
            $validator = Validator::make($request->all(), [
                'widget_id' => 'required|uuid|exists:widgets,id',
                'fingerprint' => 'required|string',
                'referrer' => 'nullable|string',
                'browser' => 'nullable|string',
                'session_id' => 'nullable|uuid' // Allow passing a conversation ID for continuity
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $widgetId = $request->input('widget_id');
            $widget = Widget::findOrFail($widgetId);

            // Check if the widget is active
            if (!$widget->is_active) {
                return $this->response(false, 'This chat widget is not active', null, 400);
            }

            // Check domain restrictions if set
            if (!empty($widget->allowed_domains)) {

                $allowedDomainsRaw = json_decode($widget->allowed_domains, true);

                // Extract HOST from each allowed domain
                $allowedHosts = array_map(function ($domain) {
                    return parse_url($domain, PHP_URL_HOST);
                }, $allowedDomainsRaw);

                // Extract host from referrer sent by frontend
                $referrerHost = parse_url($request->input('referrer'), PHP_URL_HOST);

                if (!in_array($referrerHost, $allowedHosts)) {
                    return $this->response(
                        false,
                        __('messages.chat_widget_not_allowed'),
                        null,
                        403
                    );
                }
            }



            // Get the channel for this widget
            $channelInfo = $this->getChannelForWidget($widget->id);

            if (!$channelInfo) {
                return $this->response(
                    false,
                    __('messages.no_channel_configured'),
                    null,
                    400
                );
            }


            // Visitor data to be stored
            $visitorData = [
                'browser' => $request->input('browser'),
                'ip-address' => $request->header('CF-Connecting-IP') ?? $request->ip(),
                'referrer' => $request->input('referrer'),
                'last-seen' => now(),
            ];
            // Find or create contact entity based on fingerprint
            if ($channelInfo->organization()?->id) {
                $contact = $this->findOrCreateContact_($channelInfo->organization()?->id, ['fingerprint' => $request->input('fingerprint')], $visitorData);
            }



            $isContinuation = false;
            // Check for existing active conversation
            $activeConversation = $this->getActiveConversation($contact, Channel::LIVECHAT_PLATFORM, $channelInfo);
            // If no active conversation, create a new one
            if (!$activeConversation) {
                $workspace = $channelInfo->defaultWorkspace ?? $channelInfo->workspaces()->first();
                //TODO: get inbox agent
                $activeConversation = $this->startConversation(Channel::LIVECHAT_PLATFORM, $channelInfo, $contact, "", null, Conversation::STATUS_PENDING, $workspace->id);
            }
            // Get pre-chat form if enabled
            $preChatForm = $this->getPreChatForm($channelInfo->id, $widget->id);
            $preChatEnabled = $preChatForm && $preChatForm->enabled;

            $postChatForm = $this->getPostChatForm($channelInfo->id, $widget->id);
            $postChatEnabled = $postChatForm && $postChatForm->enabled;

            // Response data
            $response = [
                'widget' => [
                    'id' => $widget->id,
                    'theme_color' => $widget->theme_color,
                    'logo_url' => $widget->logo_url,
                    'welcome_message' => $widget->welcome_message,
                    'offline_message' => $widget->offline_message,
                    'message_placeholder' => $widget->message_placeholder,
                    'show_agent_avatar' => $widget->show_agent_avatar,
                    'show_agent_name' => $widget->show_agent_name,
                    'show_file_upload' => $widget->show_file_upload,
                    'position' => $widget->position,
                    'language' => $widget->language,
                    'sound_enabled' => $widget->sound_enabled,
                    'auto_open' => $widget->auto_open,
                    'auto_open_delay' => $widget->auto_open_delay,
                ],
                'session' => [
                    'id' => $activeConversation->id,
                    'status' => $activeConversation->status,
                    'is_continuation' => in_array($activeConversation->status, [Conversation::STATUS_WAITING, Conversation::STATUS_ACTIVE, Conversation::STATUS_OPEN]),
                ],
                'contact' => [
                    'id' => $contact->id,
                    'name' => 'vistor',
                ],
                'pre_chat_form' => $preChatEnabled ? [
                    'enabled' => true,
                    'id' => $preChatForm->id,
                    'title' => $preChatForm->title,
                    'description' => $preChatForm->description,
                    'submit_button_text' => $preChatForm->submit_button_text,
                    'fields' => $preChatForm->fields()->get()->map(function ($field) {
                        return [
                            'id' => $field->id,
                            'type' => $field->type,
                            'name' => $field->name,
                            'label' => $field->label,
                            'placeholder' => $field->placeholder,
                            'required' => $field->required,
                            'options' => $field->options,
                            'validation' => $field->validation,
                            'order' => $field->order,
                        ];
                    }),
                ] : ['enabled' => false],
                'post_chat_form' => $postChatForm ? [
                    'enabled' => true,
                    'id' => $postChatForm->id,
                    'title' => $postChatForm->title,
                    'description' => $postChatForm->description,
                    'submit_button_text' => $postChatForm->submit_button_text,
                    'fields' => $postChatForm->fields()->get()->map(function ($field) {
                        return [
                            'id' => $field->id,
                            'type' => $field->type,
                            'name' => $field->name,
                            'label' => $field->label,
                            'placeholder' => $field->placeholder,
                            'required' => $field->required,
                            'options' => $field->options,
                            'validation' => $field->validation,
                            'order' => $field->order,
                        ];
                    }),
                ] : ['enabled' => false],
                'has_previous_conversations' => $this->hasEndedactiveConversations($contact, Channel::LIVECHAT_PLATFORM, $channelInfo),
            ];

            return $this->response(true, 'Chat initialized successfully', $response);
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get or create attribute definition
     */
    protected function getOrCreateAttributeDefinition(string $workspaceId, string $key, string $displayName, string $type): AttributeDefinition
    {
        return AttributeDefinition::firstOrCreate(
            [
                'workspace_id' => $workspaceId,
                'key' => $key,
            ],
            [
                'id' => (string) Str::uuid(),
                'display_name' => $displayName,
                'cardinality' => 'one',
                'type' => $type,
                'pii' => false,
                'read_only' => false,
                'builtin' => false,
            ]
        );
    }

    /**
     * Submit pre-chat form
     */
    public function submitPreChatForm(Request $request): JsonResponse
    {
        // try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|uuid|exists:conversations,id',
                'form_data' => 'required|array',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $conversationId = $request->input('session_id');
            $formData = $request->input('form_data');
            $conversation = Conversation::findOrFail($conversationId);
            $contact = ContactEntity::findOrFail($conversation->contact_id);

            $this->updateContact_(
                $contact,
                [
                    Identifier::EMAIL_KEY => $formData['email'] ?? null,
                    Identifier::PHONE_NUMBER_KEY => $formData['phone'] ?? null
                ],
                [
                    ContactEntity::ATTRIBUTE_NAME => $formData['name'] ?? null,
                    ContactEntity::ATTRIBUTE_TYPE_DISPALY_NAME => $formData['name'] ?? null,
                ]
            );

            // Update conversation with form data
            $conversation->update([
                'status' => Conversation::STATUS_WAITING,
                // 'started_at' => now(),
            ]);
            $widget = $conversation->channel->connector->liveChatConfiguration->widget;

            // Get the pre-chat form
            $preChatForm = $this->getPreChatForm($conversation->channel_id, $widget->id);
            if (!$preChatForm) {
                throw new \Exception('Pre-chat form not found.');
            }

            // Create individual field responses
            $responses = PreChatFormFieldResponse::createFromFormData(
                $conversation->id,
                $conversation->contact_id,
                $formData,
                $preChatForm
            );
            // Get the first response to use as the messageable
            $firstResponse = $responses[0] ?? null;

            if (!$firstResponse) {
                throw new \Exception('Failed to create form responses.');
            }

            // Create the message record
            $message = LiveChatMessage::create([
                'channel_id' => $conversation->channel_id,
                'widget_id' => $widget->id,
                'conversation_id' => $conversation->id,
                'sender_type' => ContactEntity::class,
                'sender_id' => $conversation->contact_id,
                'direction' => 'RECEIVED',
                'type' => 'pre_form_submission',
                'messageable_type' => get_class($firstResponse),
                'messageable_id' => $firstResponse->id,
                'is_read' => false,
            ]);
            $this->saveMessageStatus($message->id, 'delivered');

            $this->liveChatMessageHandler->handleIncomingMessage($message, $conversation);

            // Send Telegram notification for new live chat conversation
            $channelName = $conversation->channel->name ?? null;
            MessageHelper::sendLiveChatNotification($formData, $conversation->id, $channelName);

            if ($widget->welcome_message) {
                // Create the text message with welcome message content
                $textMessage = LiveChatTextMessage::create([
                    'text' => $widget->welcome_message,
                ]);

                // Create the message record
                $welcomeMessage = LiveChatMessage::create([
                    'channel_id' => $conversation->channel_id,
                    'widget_id' => $widget->id,
                    'conversation_id' => $conversation->id,
                    'sender_type' => Widget::class,
                    'sender_id' => $widget->id, // Use default agent or null
                    'type' => 'text',
                    'direction' => 'SENT',
                    'status' => 'sent',
                    'messageable_type' => get_class($textMessage),
                    'messageable_id' => $textMessage->id,
                    'is_read' => false,
                ]);

                // Update message status and process it through the handler
                // $this->liveChatMessageHandler->handleAgentIncomingMessage($welcomeMessage, $conversation);
            }

            return $this->response(true, 'Pre-chat form submitted successfully', [
                'session' => [
                    'id' => $conversation->id,
                    'status' => $conversation->status,
                ],
            ]);
        // } catch (Throwable $e) {
        //     return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        // }
    }

    /**
     * Submit post-chat form (feedback form)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function submitPostChatForm(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|uuid|exists:conversations,id',
                'form_data' => 'required|array',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $conversationId = $request->input('session_id');
            $formData = $request->input('form_data');
            $conversation = Conversation::findOrFail($conversationId);
            // Ensure the conversation is in a proper state (ended)
            if (!in_array($conversation->status, [Conversation::STATUS_CLOSED, Conversation::STATUS_ARCHIVED, Conversation::STATUS_ENDED])) {
                return $this->response(
                    false,
                    __('messages.post_chat_form_invalid'),
                    null,
                    400
                );
            }

            // Retrieve the widget and post-chat form
            $widget = $conversation->channel->connector->liveChatConfiguration->widget;
            $postChatForm = $this->getPostChatForm($conversation->channel_id, $widget->id);

            if (!$postChatForm) {
                return $this->response(false, 'Post-chat form not found', null, 404);
            }

            // Validate required fields if the form requires them
            if ($postChatForm->require_fields) {
                $requiredFields = $postChatForm->fields()
                    ->where('required', true)
                    ->where('enabled', true)
                    ->get();

                foreach ($requiredFields as $field) {
                    if (!isset($formData[$field->name]) || empty($formData[$field->name])) {
                        return $this->response(false, "Field '{$field->label}' is required", null, 422);
                    }
                }
            }

            // Create PostChatFormResponse model and table if it doesn't exist yet
            // For now, we'll store responses in a generic format

            // Create individual field responses
            $responses = PostChatFormFieldResponse::createFromFormData(
                $conversation->id,
                $conversation->contact_id,
                $formData,
                $postChatForm
            );
            // Get the first response to use as the messageable
            $firstResponse = $responses[0] ?? null;

            if (!$firstResponse) {
                throw new \Exception('Failed to create form responses.');
            }

            // Create the message record
            $message = LiveChatMessage::create([
                'channel_id' => $conversation->channel_id,
                'widget_id' => $widget->id,
                'conversation_id' => $conversation->id,
                'sender_type' => ContactEntity::class,
                'sender_id' => $conversation->contact_id,
                'type' => 'post_form_submission',
                'messageable_type' => get_class($firstResponse),
                'messageable_id' => $firstResponse->id,
                'is_read' => false,
            ]);

            $this->saveMessageStatus($message->id, 'sent');
            $this->liveChatMessageHandler->handleIncomingMessage($message, $conversation);

            return $this->response(true, 'Post-chat form submitted successfully', [
                'session' => [
                    'id' => $conversation->id,
                    'status' => $conversation->status,
                ],
            ]);



            // Mark the conversation as having feedback
            // $conversation->update([
            //     'has_feedback' => true,
            //     'feedback_submitted_at' => now(),
            // ]);

            // Maybe trigger an event or notification for the feedback
            // event(new LiveChatFeedbackSubmitted($conversation, $formData));

        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }
    /**
     * Get a channel for a specific widget
     */
    protected function getChannelForWidget(string $widgetId)
    {
        $liveChatConfig = \App\Models\LiveChatConfiguration::where('widget_id', $widgetId)->first();

        if (!$liveChatConfig) {
            return null;
        }

        return Channel::where('connector_id', $liveChatConfig->connector_id)
            ->where('platform', Channel::LIVECHAT_PLATFORM)
            ->where('status', Channel::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Get pre-chat form for a channel
     */
    protected function getPreChatForm(string $channelId, string $widgetId)
    {
        return PreChatForm::where('channel_id', $channelId)
            ->where('widget_id', $widgetId)
            ->first();
    }

    /**
     * Get post-chat form for a channel
     */
    protected function getPostChatForm(string $channelId, string $widgetId)
    {
        return PostChatForm::where('channel_id', $channelId)
            ->where('widget_id', $widgetId)
            ->first();
    }

    /**
     * Send a message from contact
     */
    public function sendMessage(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|uuid|exists:conversations,id',
                'content_type' => 'required|string|in:text,file',
                'message' => 'required_if:content_type,text|string',
                'file' => 'required_if:content_type,file|file|max:10240', // 10MB max
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $conversationId = $request->input('session_id');
            $contentType = $request->input('content_type');

            $conversation = Conversation::findOrFail($conversationId);

            // If conversation isn't active yet and pre-chat form not required, start it
            $widget = $conversation->channel->connector->liveChatConfiguration->widget;
            if ($conversation->status === Conversation::STATUS_PENDING) {
                $preChatForm = $this->getPreChatForm($conversation->channel_id, $widget->id);

                if (!$preChatForm || !$preChatForm->enabled) {
                    $conversation->update([
                        'status' => Conversation::STATUS_WAITING,
                        'started_at' => now(),
                    ]);
                } else {
                    return $this->response(false, 'Pre-chat form must be submitted first', null, 400);
                }
            }

            // Process the message based on content type
            if ($contentType === 'text') {
                $messageText = $request->input('message');
                // Create the text message
                $textMessage = LiveChatTextMessage::create([
                    'text' => $messageText,
                ]);

                // Create the message record
                $message = LiveChatMessage::create([
                    'channel_id' => $conversation->channel_id,
                    'widget_id' => $widget->id,
                    'conversation_id' => $conversation->id,
                    'sender_type' => ContactEntity::class,
                    'sender_id' => $conversation->contact_id,
                    'type' => 'text',
                    'status' => 'sent',
                    'direction' => LiveChatMessage::MESSAGE_DIRECTION_RECEIVED,
                    'messageable_type' => get_class($textMessage),
                    'messageable_id' => $textMessage->id,
                    'replied_to_message_id' => $request->replied_message_id ?? null,
                    'is_read' => false,
                ]);
            } else if ($contentType === 'file') {
                $file = $request->file('file');
                $caption = $request->input('caption');

                // Create the file message
                $fileMessage = LiveChatFileMessage::create([
                    'caption' => $caption,
                ]);

                // Upload file to media collection
                $media = $fileMessage
                    ->addMedia($file)
                    ->toMediaCollection('livechat_media', 'oss');

                // Create the message record
                $message = LiveChatMessage::create([
                    'channel_id' => $conversation->channel_id,
                    'widget_id' => $widget->id,
                    'conversation_id' => $conversation->id,
                    'sender_type' => ContactEntity::class,
                    'sender_id' => $conversation->contact_id,
                    'type' => 'file',
                    'direction' => LiveChatMessage::MESSAGE_DIRECTION_RECEIVED,
                    'messageable_type' => get_class($fileMessage),
                    'messageable_id' => $fileMessage->id,
                    'is_read' => false,
                    'status' => 'sent',
                ]);
            }
            $conversation = $message->conversation;

            $this->saveMessageStatus($message->id, 'sent');
            $this->liveChatMessageHandler->handleIncomingMessage($message, $conversation);

            // Format message response similar to getChatHistory
            $messageContent = null;
            if ($message->messageable) {
                if ($message->messageable_type === get_class(new LiveChatTextMessage())) {
                    $messageContent = [
                        'type' => 'text',
                        'text' => $message->messageable->text,
                    ];
                } else if ($message->messageable_type === get_class(new LiveChatFileMessage())) {
                    $media = $message->messageable->getFirstMedia('*');
                    if ($media && str_starts_with($media->mime_type, 'image/')) {
                        $messageContent = [
                            'type' => 'image',
                            'file_url' => $message->messageable->getSignedMediaUrlForPreview(),
                            'file_name' => $media->file_name,
                            'mime_type' => $media->mime_type,
                            'file_size' => $media->size,
                        ];
                    } else if ($media && str_starts_with($media->mime_type, 'video/')) {
                        $messageContent = [
                            'type' => 'video',
                            'file_url' => $message->messageable->getSignedMediaUrlForPreview(),
                            'file_name' => $media->file_name,
                            'mime_type' => $media->mime_type,
                            'file_size' => $media->size,
                        ];
                    } else {
                        $messageContent = [
                            'type' => 'file',
                            'file_url' => $message->messageable->getMediaUrl(),
                            'file_name' => $media->file_name,
                            'mime_type' => $media->mime_type,
                            'file_size' => $media->size,
                        ];
                    }
                }
            }

            $senderInfo = [
                'type' => 'visitor',
                'name' => 'Visitor',
            ];

            $formattedMessage = [
                'id' => $message->id,
                'session_id' => $message->conversation_id,
                'timestamp' => $message->created_at,
                'sender' => $senderInfo,
                'content' => $messageContent,
                'status' => $message->status,
                'is_read' => $message->is_read,
                'read_at' => $message->read_at,
                'reactions' => null,
            ];

            // Add replied_to_message if exists
            if ($message->repliedToMessage) {
                $repliedMessage = $message->repliedToMessage;
                $repliedContent = null;
                if ($repliedMessage->messageable_type === get_class(new LiveChatTextMessage())) {
                    $repliedContent = [
                        'type' => 'text',
                        'text' => $repliedMessage->messageable->text,
                    ];
                }
                $formattedMessage['replied_to_message'] = [
                    'id' => $repliedMessage->id,
                    'session_id' => $repliedMessage->conversation_id,
                    'timestamp' => $repliedMessage->created_at,
                    'content' => $repliedContent,
                ];
            }

            return $this->response(true, 'Message sent successfully', $formattedMessage);
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Get chat history
     */
    public function getChatHistory(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|uuid|exists:conversations,id',
                'before_id' => 'nullable|uuid',
                'limit' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $conversationId = $request->input('session_id');
            $beforeId = $request->input('before_id');
            $limit = $request->input('limit', 50);

            $conversation = Conversation::findOrFail($conversationId);
            // Query for messages with reactions
            $messagesQuery = LiveChatMessage::where('conversation_id', $conversationId)
                ->with('reactionMessage')
                ->orderBy('created_at', 'desc');

            if ($beforeId) {
                $beforeMessage = LiveChatMessage::findOrFail($beforeId);
                $messagesQuery->where('created_at', '<', $beforeMessage->created_at);
            }

            $messages = $messagesQuery->limit($limit)->get();
            // Format and load the message content
            // Helper function to format a message
            $formatMessage = function ($message, $includeRepliedTo = true) use (&$formatMessage) {
                $messageContent = null;

                // Load the message content based on type
                if ($message->messageable) {
                    if ($message->messageable_type === get_class(new LiveChatTextMessage())) {
                        $textMessage = $message->messageable;
                        $messageContent = [
                            'type' => 'text',
                            'text' => $textMessage->text,
                        ];
                    } else if ($message->messageable_type === get_class(new LiveChatFileMessage())) {
                        $media = $message->messageable->getFirstMedia('*');
                        if ($media && str_starts_with($media->mime_type, 'image/')) {
                            $messageContent = [
                                'type' => 'image',
                                'file_url' => $message->messageable->getSignedMediaUrlForPreview(),
                                'file_name' => $media->file_name,
                                'mime_type' => $media->mime_type,
                                'file_size' => $media->size,
                            ];
                        } else if ($media && str_starts_with($media->mime_type, 'video/')) {
                            $messageContent = [
                                'type' => 'video',
                                'file_url' => $message->messageable->getSignedMediaUrlForPreview(),
                                'file_name' => $media->file_name,
                                'mime_type' => $media->mime_type,
                                'file_size' => $media->size,
                            ];
                        } else {
                            $messageContent = [
                                'type' => 'file',
                                'file_url' => $message->messageable->getMediaUrl(),
                                'file_name' => $media->file_name,
                                'mime_type' => $media->mime_type,
                                'file_size' => $media->size,
                            ];
                        }
                    } else if ($message->messageable_type === get_class(new PreChatFormFieldResponse())) {
                        $fieldResponse = $message->messageable;

                        // Get all responses for this conversation
                        $allResponses = PreChatFormFieldResponse::getConversationResponses($message->conversation_id);

                        $messageContent = [
                            'type' => 'pre_chat_form',
                            'field_id' => $fieldResponse->field_id,
                            'field_name' => optional($fieldResponse->field)->name,
                            'field_label' => optional($fieldResponse->field)->label,
                            'responses' => $allResponses
                        ];
                    }
                }
                // Get sender info
                $senderInfo = null;
                if ($message->sender_type === ContactEntity::class) {
                    $senderInfo = [
                        'type' => 'visitor',
                        'name' => 'Visitor',
                    ];
                } else if ($message->sender_type === get_class(new Widget())) {
                    $senderInfo = [
                        'type' => 'agent',
                        'name' => optional($message->agent)->name ?? 'Agent',
                        'avatar' => optional($message->sender)->avatar ?? null,
                    ];
                }

                // Get reactions if exists
                $reactions = $message->reactionMessage;

                $result = [
                    'id' => $message->id,
                    'session_id' => $message->conversation_id,
                    'timestamp' => $message->created_at,
                    'sender' => $senderInfo,
                    'content' => $messageContent,
                    'status' => $message->status,
                    'is_read' => $message->is_read,
                    'read_at' => $message->read_at,
                    'reactions' => $reactions->isNotEmpty()
                        ? $reactions->map(fn($r) => ['emoji' => $r->emoji, 'direction' => $r->direction])->values()->toArray()
                        : null,
                ];

                // Add replied_to_message if exists and includeRepliedTo is true
                if ($includeRepliedTo && $message->repliedToMessage) {
                    $result['replied_to_message'] = $formatMessage($message->repliedToMessage, false);
                }

                return $result;
            };

            $formattedMessages = $messages->map(function ($message) use ($formatMessage) {
                return $formatMessage($message);
            });

            // Mark visitor messages as read if agent is requesting
            if (auth()->check() && auth()->user()->hasRole('agent')) {
                LiveChatMessage::where('conversation_id', $conversationId)
                    ->where('sender_type', ContactEntity::class)
                    ->where('is_read', false)
                    ->update([
                        'is_read' => true,
                        'read_at' => now(),
                    ]);
            }

            return $this->response(true, 'Chat history retrieved successfully', [
                'messages' => $formattedMessages,
                'has_more' => count($formattedMessages) >= $limit,
            ]);
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }
    /**
     * Get previous conversations for a contact
     */
    public function getPreviousConversations(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'contact_id' => 'required|uuid|exists:contacts,id',
                'widget_id' => 'required|uuid|exists:widgets,id',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $contactId = $request->input('contact_id');
            $widgetId = $request->input('widget_id');

            // Get previous conversations for this contact and widget
            $previousConversation = Conversation::where('contact_id', $contactId)
                ->where('widget_id', $widgetId)
                ->where('status', Conversation::STATUS_ENDED)
                ->orderBy('ended_at', 'desc')
                ->limit(5)
                ->get();

            $formattedConversations = $previousConversation->map(function ($conversation) {
                // Get the first message in the conversation
                $firstMessage = LiveChatMessage::where('conversation_id', $conversation->id)
                    ->orderBy('created_at', 'asc')
                    ->first();

                $firstMessagePreview = null;
                if ($firstMessage && $firstMessage->messageable_type === get_class(new LiveChatTextMessage())) {
                    $firstMessagePreview = substr($firstMessage->messageable->text, 0, 100);
                } else if ($firstMessage && $firstMessage->messageable_type === get_class(new PreChatFormFieldResponse())) {
                    $firstMessagePreview = 'Pre-chat information submitted';
                } else if ($firstMessage && $firstMessage->messageable_type === get_class(new LiveChatFileMessage())) {
                    $firstMessagePreview = 'File: ' . $firstMessage->messageable->file_name;
                }

                return [
                    'id' => $conversation->id,
                    'started_at' => $conversation->started_at,
                    'ended_at' => $conversation->ended_at,
                    'duration' => $conversation->started_at && $conversation->ended_at
                        ? $conversation->ended_at->diffInMinutes($conversation->started_at)
                        : null,
                    'message_preview' => $firstMessagePreview,
                    'agent_name' => optional($conversation->agent)->name,
                ];
            });

            return $this->response(true, 'Previous conversations retrieved successfully', [
                'sessions' => $formattedConversations,
            ]);
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * End chat conversation
     */
    public function endConversation(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|uuid|exists:conversations,id',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $conversationId = $request->input('session_id');
            $conversation = Conversation::findOrFail($conversationId);

            // Only allow ending active or waiting conversations
            if (!in_array($conversation->status, [Conversation::STATUS_ACTIVE, Conversation::STATUS_WAITING])) {
                return $this->response(false, 'Cannot end a conversatio that is not active or waiting', null, 400);
            }

            $conversation->update([
                'status' => Conversation::STATUS_ENDED,
                'ended_at' => now(),
            ]);

            return $this->response(true, 'Chat conversation ended successfully');
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Mark messages as read
     */
    public function markMessagesAsRead(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|uuid|exists:conversations,id',
                'message_ids' => 'nullable|array',
                'message_ids.*' => 'required|uuid|exists:livechat_messages,id',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $conversationId = $request->input('session_id');
            $messageIds = $request->input('message_ids');
            if (true) { //empty($messageIds)
                $unreadMessages = LiveChatMessage::where('conversation_id', $conversationId)
                    ->where('direction', LiveChatMessage::MESSAGE_STATUS_SENT)
                    ->where(function ($query) {
                        $query->where('status', '!=', LiveChatMessage::MESSAGE_STATUS_READ)
                            ->orWhere('is_read', false);
                    })
                    ->get();
                foreach ($unreadMessages as $message) {
                    $message->markAsRead();
                    $this->liveChatMessageHandler->handleStatusUpdate($message, LiveChatMessage::MESSAGE_STATUS_READ);
                }
                return $this->response(true, 'Messages marked as read successfully');
            }
            foreach ($messageIds as $messageId) {
                $message = LiveChatMessage::find($messageId);
                if ($message) {
                    $message->markAsRead();
                    $this->liveChatMessageHandler->handleStatusUpdate($message, LiveChatMessage::MESSAGE_STATUS_READ);
                }
            }
            return $this->response(true, 'Messages marked as read successfully');
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Mark messages as deliverd
     */
    public function markMessagesAsDeliverd(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|uuid|exists:conversations,id',
                'message_ids' => 'required|array',
                'message_ids.*' => 'required|uuid|exists:livechat_messages,id',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $messageIds = $request->input('message_ids');
            foreach ($messageIds as $messageId) {
                $message = LiveChatMessage::find($messageId);
                if ($message) {
                    // Only update messages that are currently in "sent" status
                    if ($message->status === LiveChatMessage::MESSAGE_STATUS_SENT) {
                        $message->update([
                            'status' => LiveChatMessage::MESSAGE_STATUS_DELIVERED
                        ]);
                        $this->saveMessageStatus($message->id, LiveChatMessage::MESSAGE_STATUS_DELIVERED);
                        $this->liveChatMessageHandler->handleStatusUpdate($message, LiveChatMessage::MESSAGE_STATUS_DELIVERED);
                    }
                }
            }
            return $this->response(true, 'Messages marked as delivered successfully');
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }




    /**
     * Update session heartbeat
     */
    public function sessionHeartbeat(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|uuid|exists:conversations,id',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $conversationId = $request->input('session_id');
            $conversation = Conversation::findOrFail($conversationId);

            // Check if conversation has ended
            if ($conversation->status === Conversation::STATUS_ENDED) {
                return $this->response(false, 'Conversation has ended', null, 400);
            }

            // Update last activity time (you may need to add this column to your conversations table)
            $conversation->touch();

            // Update contact's last seen time
            if ($conversation->contact_id) {
                // $this->updateContactLastSeen($conversation->contact_id);
            }

            return $this->response(true, 'Conversation heartbeat updated');
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }


    /**
     * Handle user closing the chat
     */
    public function closeChat(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|uuid|exists:conversations,id',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $conversationId = $request->input('session_id');
            $conversation = Conversation::findOrFail($conversationId);
            $widget = $conversation->channel->connector->liveChatConfiguration->widget;

            // Only allow closing active or waiting conversations
            if (!in_array($conversation->status, [Conversation::STATUS_ACTIVE, Conversation::STATUS_WAITING, Conversation::STATUS_OPEN])) {
                return $this->response(false, 'Cannot close a conversation that is not active or waiting', null, 400);
            }

            $conversation->update([
                'status' => Conversation::STATUS_ARCHIVED,
                'closed_at' => now(),
            ]);
            $textMessage = LiveChatTextMessage::create([
                'text' => "Archived - customer left the chat",
            ]);

            // Create the message record
            $message = LiveChatMessage::create([
                'channel_id' => $conversation->channel_id,
                'widget_id' => $widget->id,
                'conversation_id' => $conversation->id,
                'sender_type' => ContactEntity::class,
                'sender_id' => $conversation->contact_id,
                'type' => 'text',
                'status' => 'sent',
                'messageable_type' => get_class($textMessage),
                'messageable_id' => $textMessage->id,
                'is_read' => false,
            ]);
            $this->saveMessageStatus($message->id, 'sent');
            $this->liveChatMessageHandler->handleIncomingMessage($message, $conversation);
            $this->liveChatMessageHandler->handleConversationClosed($conversation);
            // Get post-chat form if enabled
            $postChatForm = $this->getPostChatForm($conversation->channel_id, $widget->id);
            $postChatEnabled = $postChatForm && $postChatForm->enabled;
            $response = [
                'session' => [
                    'id' => $conversation->id,
                    'status' => $conversation->status,
                ],
                'post_chat_form' => $postChatForm ? [
                    'enabled' => $postChatEnabled,
                    'id' => $postChatForm->id,
                    'title' => $postChatForm->title,
                    'description' => $postChatForm->description,
                    'submit_button_text' => $postChatForm->submit_button_text,
                    'fields' => $postChatForm->fields()->get()->map(function ($field) {
                        return [
                            'id' => $field->id,
                            'type' => $field->type,
                            'name' => $field->name,
                            'label' => $field->label,
                            'placeholder' => $field->placeholder,
                            'required' => $field->required,
                            'options' => $field->options,
                            'validation' => $field->validation,
                            'order' => $field->order,
                        ];
                    }),
                ] : ['enabled' => false],
            ];
            return $this->response(true, 'Chat conversation closed successfully', $response);
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Send a reaction to a message
     */
    public function sendReaction(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'session_id' => 'required|uuid|exists:conversations,id',
                'message_id' => 'required|uuid|exists:livechat_messages,id',
                'emoji' => 'nullable|string|max:10',
            ]);

            if ($validator->fails()) {
                return $this->response(false, 'Validation Error(s)', $validator->errors(), 400);
            }

            $conversationId = $request->input('session_id');
            $conversation = Conversation::findOrFail($conversationId);

            $messageId = $request->input('message_id');
            $emoji = $request->input('emoji', '');

            $message = LiveChatMessage::where('id', $messageId)
                ->where('conversation_id', $conversationId)
                ->firstOrFail();

            // If emoji is empty, delete the reaction
            if (empty($emoji)) {
                \App\Models\LiveChatReactionMessage::where('livechat_message_id', $message->id)
                    ->where('direction', LiveChatMessage::MESSAGE_DIRECTION_RECEIVED)
                    ->delete();
            } else {
                // Update or create the reaction
                \App\Models\LiveChatReactionMessage::updateOrCreate(
                    ['livechat_message_id' => $message->id, 'direction' => LiveChatMessage::MESSAGE_DIRECTION_RECEIVED],
                    ['emoji' => $emoji]
                );
                $conversation->last_message_at = now();
                $conversation->save();
            }

            // Notify via Pusher about the reaction update
            $this->liveChatMessageHandler->handleReactionUpdate($message, $conversation, $emoji);

            return $this->response(true, empty($emoji) ? 'Reaction removed successfully' : 'Reaction sent successfully', [
                'message_id' => $message->id,
                'emoji' => $emoji,
            ]);
        } catch (Throwable $e) {
            return $this->response(false, 'An error occurred: ' . $e->getMessage(), null, 500);
        }
    }
}
