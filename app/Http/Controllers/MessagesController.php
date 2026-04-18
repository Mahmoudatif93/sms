<?php

namespace App\Http\Controllers;

use App\Helpers\CurrencyHelper;
use App\Http\Controllers\SmsUsers\SmsController;
use App\Http\Controllers\SmsUsers\RefactoredSmsController;
use App\Http\Controllers\Whatsapp\WhatsappMessageController;
use App\Http\Responses\ConversationMessage;
use App\Models\Channel;
use App\Models\Sender;
use App\Models\Service;
use App\Models\WhatsappMessage;
use App\Models\WhatsappRateLine;
use App\Models\Workspace;
use App\Models\WorldCountry;
use App\Traits\ContactManager;
use App\Traits\ConversationManager;
use App\Traits\WalletManager;
use App\Traits\WhatsappTemplateManager;
use App\Traits\WhatsappWalletManager;
use App\Http\Requests\SmsValidationRequest;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Http\Request;

class MessagesController extends BaseApiController
{
    use ConversationManager, ContactManager, WhatsappTemplateManager, WalletManager, WhatsappWalletManager;

    public function sendMessage(Request $request, Workspace $workspace, Channel $channel)
    {
        return match ($channel->platform) {
            Channel::SMS_PLATFORM => $this->sendSmsMessage($request, $channel),
            Channel::WHATSAPP_PLATFORM => $this->sendWhatsappMessage($request, $workspace, $channel),
            default => response()->json(['error' => 'Unsupported Platform'], 400),
        };
    }

    private function sendSmsMessage(Request $request, Channel $channel)
    {

        $connector = $channel->connector;
        $workspace = $connector->workspace;

        if (!$workspace) {
            return response()->json(['error' => 'Workspace is missing or incomplete'], 400);
        }
        $organization = $workspace->organization;
        // Retrieve the Sender Name from SMSConfiguration
        $SmsConfiguration = $connector->SmsConfiguration;
        if ((!$SmsConfiguration || !$SmsConfiguration->sender_id) && $SmsConfiguration->sender_id !== 0) {
            return response()->json(['error' => 'sms Configuration is missing or incomplete'], 400);
        }
        // Retrieve the Sender from SmsConfiguration
        $sender = $SmsConfiguration->sender;
        if (!$sender || $sender->status != Sender::STATUS_APPROVED) { //TODO: check if the sender is active
            return response()->json(['error' => 'Sender information is missing or incomplete'], 400);
        }
        $request->merge([
            'from' => (string) $SmsConfiguration->sender->name,
            'owner_id' => $organization->owner_id,
            'workspace' => $workspace,
            'workspace_id' => $workspace->id
        ]);

        if ($sender->is_test) {
            $requestData['message'] = $sender->default_text;
            $request->merge(['message' => $sender->default_text]);
        }
        $requestData['workspace_id'] = $workspace->id;
        $validatedRequest = app(SmsValidationRequest::class);

        // Replace the input data
        $validatedRequest->replace($requestData);

        // Set route parameters if needed
        $validatedRequest->route()->setParameter('workspace', $workspace);

        // Validate
        $validatedRequest->validateResolved();
        $smsController = app(RefactoredSmsController::class);
        return $smsController->send($validatedRequest);
    }

    private function sendWhatsappMessage(Request $request, Workspace $workspace, Channel $channel)
    {
        
        $connector = $channel->connector;

        // Retrieve the phone_number_id from WhatsappConfiguration
        $whatsappConfiguration = $connector->whatsappConfiguration;

        if (!$whatsappConfiguration || !$whatsappConfiguration->primary_whatsapp_phone_number_id) {
            return response()->json(['error' => 'WhatsApp Configuration is missing or incomplete'], 400);
        }

        // Add the `from` field to the request data
        $request->merge(['from' => (string) $whatsappConfiguration->primary_whatsapp_phone_number_id]);

        $result = $this->resolveWhatsappReceiverPhoneOrFail($request->input('to'), $workspace->organization_id);

        if (!$result['success']) {
            return $this->response(false, $result['error'], null, 422);
        }

        $contact = $result['contact'];
        $senderPhone = $result['phone'];


        // ✅ Ensure contact is attached to this workspace (org-scoped lookup may return one from another workspace)
        $contact->workspaces()->syncWithoutDetaching([$workspace->id]);

        // Start the conversation
        $conversation = $this->startConversation(
            platform: $channel->platform,
            channel: $channel,
            contact: $contact,
            workspaceId: $workspace->id
        );

        $request->merge(['conversation_id' => $conversation->id]);
        $request->merge(['to' => $senderPhone]);

        // Handle the message type
        $messageType = $request->input('type');
        // check for cost and wallet
        if ($messageType == 'template') {
            try {
                $transaction = $this->prepareWalletTransactionForTemplate(
                    channel: $channel,
                    conversation: $conversation,
                    workspace: $workspace,
                    contact: $contact,
                    senderPhone: $senderPhone,
                    templateId: $request->get('template_id')
                );

                if ($transaction) {
                    $request->merge(['transaction_id' => $transaction->id]);
                }
            } catch (Exception $e) {
                return $this->response(false, $e->getMessage(), null, 422);
            }

        }


        return match ($messageType) {
            'text' => $this->sendTextMessage($request),
            'location' => $this->sendLocationMessage($request),
            'template' => $this->sendTemplateMessage($request),
            'image' => $this->sendImageMessage($request),
            'video' => $this->sendVideoMessage($request),
            'audio' => $this->sendAudioMessage($request),
            'document' => $this->sendDocumentMessage($request),
            'reaction' => $this->sendReactionMessage($request),
            default => response()->json(['error' => 'Unsupported message type'], 400),
        };
    }

    private function sendDocumentMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendDocumentMessage($request);
    }

    private function sendTextMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendTextMessage($request);
    }

    private function sendLocationMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendLocationMessage($request);
    }

    private function sendTemplateMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendTemplateMessage($request);
    }

    private function sendImageMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendImageMessage($request);
    }

    private function sendVideoMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendVideoMessage($request);
    }

    private function sendAudioMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendAudioMessage($request);
    }

    private function sendReactionMessage(Request $request)
    {
        // Delegate to your existing WhatsApp text message function
        $whatsappController = app(WhatsappMessageController::class);

        return $whatsappController->sendReactionMessage($request);
    }

    public function statisticsMessage(Request $request, Workspace $workspace, Channel $channel)
    {
        return match ($channel->platform) {
            Channel::SMS_PLATFORM => $this->statisticsSmsMessage($request, $channel),
            default => response()->json(['error' => 'Unsupported Platform'], 400),
        };
    }

    private function statisticsSmsMessage(Request $request, Channel $channel)
    {
        $connector = $channel->connector;
        $workspace = $connector->workspace;

        if (!$workspace) {
            return response()->json(['error' => 'Workspace is missing or incomplete'], 400);
        }
        $organization = $workspace->organization;

        $SmsConfiguration = $connector->SmsConfiguration;
        if ((!$SmsConfiguration || !$SmsConfiguration->sender_id) && $SmsConfiguration->sender_id !== 0) {
            return response()->json(['error' => 'sms Configuration is missing or incomplete'], 400);
        }

        $sender = $SmsConfiguration->sender;
        if (!$sender || $sender->status != Sender::STATUS_APPROVED) {
            return response()->json(['error' => 'Sender information is missing or incomplete'], 400);
        }



        if ($sender->is_test) {
            $requestData['message'] = $sender->default_text;
            $request->merge(['message' => $sender->default_text]);
        }
        $requestData['workspace_id'] = $workspace->id;
        $request->merge(['channel' => $channel]);
        $request->merge(['from' => (string) $SmsConfiguration->sender->name]);
        $request->merge(['owner_id' => $organization->owner_id]);
        $request->merge(['workspace' => $workspace]);
        $request->merge(['workspace_id' => $workspace->id]);


        $validatedRequest = app(SmsValidationRequest::class);

        // Replace the input data
        $validatedRequest->replace($requestData);

        // Set route parameters if needed
        $validatedRequest->route()->setParameter('workspace', $workspace);

        // Validate
        $validatedRequest->validateResolved();

        $smsController = app(RefactoredSmsController::class);
        return $smsController->statistics($validatedRequest);

        // // Create and validate request data
        // $validatedRequest = new SmsValidationRequest();
        // $validatedRequest->setContainer(app());
        // $validatedRequest->merge($requestData);
        // $validatedRequest->validateResolved();

        // $smsController = app(RefactoredSmsController::class);
        // return $smsController->statistics($validatedRequest);
    }

    public function getMessage(Workspace $workspace, Channel $channel, $message)
    {
        if ($channel->platform == Channel::WHATSAPP_PLATFORM) {
            $whatsappMessage = WhatsappMessage::whereId($message)->first();
            if (!$whatsappMessage) {
                return response()->json(['error' => 'Whatsapp message not found'], 404);
            }

            return $this->response(
                true,
                'Whatsapp message retrieved successfully',
                new ConversationMessage($whatsappMessage, Channel::WHATSAPP_PLATFORM)
            );
        }
        return response()->json(['error' => 'Unsupported platform'], 400);
    }


}
