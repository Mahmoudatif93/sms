<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\ContactEntity;
use App\Models\TicketActivityLog;
use App\Models\TicketEntity;
use App\Models\TicketMessage;
use App\Models\TicketForm;
use App\Services\FileUploadService;
use App\Traits\ContactManager;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TicketIframeController extends Controller
{
    use ContactManager;
    /**
     * Create a ticket from an iframe form submission.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createTicket(Request $request): JsonResponse
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'token' => 'required|string|exists:ticket_forms,iframe_token',
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'subject' => 'required|string|max:255',
                'description' => 'required|string',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240', // 10MB max file size
                'custom_fields' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Get the channel and check if it's valid
            $ticketForm = TicketForm::where('iframe_token', $request->input('token'))->firstOrFail();
            $workspace = $ticketForm->ticketConfigurations->connector->workspace;
            $channel = $ticketForm->ticketConfigurations->connector->channel;

            // Begin transaction
            return DB::transaction(function () use ($request, $channel, $workspace) {
                // Find or create contact based on email
                $contact = $this->CreateContact(
                    $request->input('email'),
                    $request->input('name'),
                    $workspace->organization_id
                );

                // Create the ticket
                $ticket = new TicketEntity([
                    'workspace_id' => $workspace->id,
                    'subject' => $request->input('subject'),
                    'description' => $request->input('description'),
                    'status' => TicketEntity::STATUS_OPEN,
                    'priority' => TicketEntity::PRIORITY_MEDIUM,
                    'source' => TicketEntity::SOURCE_IFRAME,
                    'contact_id' => $contact ? $contact->id : null,
                    'channel_id' => $channel->id,
                    'email' => $request->input('email'),
                ]);

                // If there are custom fields, store them in the description
                if ($request->has('custom_fields') && is_array($request->input('custom_fields'))) {
                    $customFields = $request->input('custom_fields');
                    $description = "Customer Information:\n";

                    foreach ($customFields as $key => $value) {
                        if (is_string($value) || is_numeric($value)) {
                            $description .= "- " . ucfirst(str_replace('_', ' ', $key)) . ": " . $value . "\n";
                        }
                    }

                    $ticket->description = $description;
                }

                $ticket->save();

                // Log the ticket creation
                TicketActivityLog::logTicketCreation($ticket, null);

                // Create the initial message
                $ticketMessage = new TicketMessage([
                    'ticket_id' => $ticket->id,
                    'sender_type' => TicketEntity::class,
                    'sender_id' => $contact ? $contact->id : null,
                    'message_type' => TicketMessage::MESSAGE_TYPE_MESSAGE,
                    'content' => $request->input('description'),
                    'is_private' => false,
                ]);

                // if ($contact) {
                //     $ticketMessage->sender()->associate($contact);
                // }

                $ticketMessage->save();

                // Process attachments if any
                if ($request->hasFile('attachments')) {
                    $this->processAttachments($request->file('attachments'), $ticketMessage);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket created successfully',
                    'ticket_number' => $ticket->ticket_number,
                ], 201);
            });
        } catch (Exception $e) {
            Log::error('Error creating ticket from iframe: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find or create a contact based on email address.
     *
     * @param string $email
     * @param string $name
     * @param string $workspaceId
     * @return ContactEntity|null
     */
    private function CreateContact(string $email, string $name, string $organizationId): ?ContactEntity
    {
        try {

            // Use the unified method from the ContactManager trait
            return $this->findOrCreateContact(
                ['email' => $email],
                ['name' => $name],
                $organizationId
            );
        } catch (Exception $e) {
            Log::error('Error finding or creating contact: ' . $e->getMessage(), [
                'exception' => $e,
                'email' => $email,
                'organization_id' => $organizationId,
            ]);
            return null;
        }
    }

    /**
     * Process uploaded attachments and link them to the ticket message.
     *
     * @param array $attachments
     * @param TicketMessage $ticketMessage
     * @return void
     */
    private function processAttachments(array $attachments, TicketMessage $ticketMessage): void
    {
        $fileUploadService = app(FileUploadService::class);

        foreach ($attachments as $file) {
            try {
                $filePath = $fileUploadService->uploadFile($file, "tickets/{$ticketMessage->ticket_id}/attachments");

                $ticketMessage->attachments()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $filePath,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            } catch (Exception $e) {
                Log::error('Error processing attachment: ' . $e->getMessage(), [
                    'exception' => $e,
                    'file_name' => $file->getClientOriginalName(),
                ]);
            }
        }
    }

    /**
     * Generate the iframe embed code for a specific channel.
     *
     * @param Request $request
     * @param string $channelId
     * @return JsonResponse
     */
    public function generateEmbedCode(Request $request, string $channelId): JsonResponse
    {
        try {
            $channel = Channel::findOrFail($channelId);

            // Generate a unique token for this channel's iframe
            $token = hash('sha256', $channel->id . config('app.key') . time());

            // Store the token with the channel
            $channel->update([
                'iframe_token' => $token,
            ]);

            // Generate the iframe code
            $iframeCode = '<iframe src="' . route('ticket.iframe.form', ['token' => $token]) . '" width="100%" height="600px" frameborder="0"></iframe>';

            // Generate the script tag code
            $scriptCode = '<script src="' . route('ticket.iframe.script', ['token' => $token]) . '"></script><div id="ticket-form-container"></div>';

            return response()->json([
                'success' => true,
                'data' => [
                    'iframe_code' => $iframeCode,
                    'script_code' => $scriptCode,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error generating embed code: ' . $e->getMessage(), [
                'exception' => $e,
                'channel_id' => $channelId,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate embed code',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the iframe form.
     *
     * @param Request $request
     * @param string $token
     * @return \Illuminate\View\View
     */
    public function showIframeForm(Request $request, string $token)
    {
        $ticketForm = TicketForm::where('iframe_token', $token)->firstOrFail();

        return view('tickets.iframe', [
            'token' => $token,
        ]);
    }

    /**
     * Serve the JavaScript file for embedding the ticket form.
     *
     * @param Request $request
     * @param string $token
     * @return \Illuminate\Http\Response
     */
    public function serveEmbedScript(Request $request, string $token)
    {
        $channel = Channel::all()->firstOrFail();

        $script = view('tickets.embed-script', [
            'channel' => $channel,
            'token' => $token,
        ])->render();

        return response($script)
            ->header('Content-Type', 'application/javascript')
            ->header('Cache-Control', 'public, max-age=3600');
    }
}
