<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessIncomingEmail;
use App\Models\ContactEntity;
use App\Models\Identifier;
use App\Models\TicketActivityLog;
use App\Models\TicketEmailConfiguration;
use App\Models\TicketEntity;
use App\Models\TicketMessage;
use App\Models\Workspace;
use App\Services\FileUploadService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Webklex\IMAP\Facades\Client;

class TicketEmailController extends Controller
{
    /**
     * Process incoming emails and create tickets.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function processIncomingEmail(Request $request): JsonResponse
    {
        try {
            $to = $request->to;
            $from = $request->from;
            $subject = $request->subject;
            $html = $request->html;
            $text = $request->text;
            // Queue the job to process the email
            ProcessIncomingEmail::dispatch(['to' => $to, 'from' => $from, 'subject' => $subject, 'text' => $text]);

            return response()->json([
                'success' => true,
                'message' => 'Email received and queued for processing',
            ]);
        } catch (Exception $e) {
            Log::error('Error processing incoming email: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process incoming email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a ticket from an email.
     *
     * @param array $emailData
     * @return TicketEntity|null
     */
    public function createTicketFromEmail(array $emailData): ?TicketEntity
    {
        try {
            // Find the email configuration for the recipient email
            $emailConfig = TicketEmailConfiguration::where('email_address', $emailData['to'])
                ->where('is_active', true)
                ->first();

            if (!$emailConfig) {
                Log::error('No active email configuration found for recipient', [
                    'to' => $emailData['to'],
                ]);
                return null;
            }

            $workspace = Workspace::find($emailConfig->workspace_id);
            if (!$workspace) {
                Log::error('Workspace not found for email configuration', [
                    'workspace_id' => $emailConfig->workspace_id,
                ]);
                return null;
            }

            // Begin transaction
            return DB::transaction(function () use ($emailData, $workspace) {
                // Find or create contact based on email
                $contact = $this->findOrCreateContact($emailData['from'], $workspace->id);

                // Extract name from email if available
                $fromName = $this->extractNameFromEmail($emailData);

                // Create the ticket
                $ticket = new TicketEntity([
                    'workspace_id' => $workspace->id,
                    'subject' => $emailData['subject'] ?? 'No Subject',
                    'status' => TicketEntity::STATUS_OPEN,
                    'priority' => TicketEntity::PRIORITY_MEDIUM,
                    'source' => TicketEntity::SOURCE_EMAIL,
                    'contact_id' => $contact ? $contact->id : null,
                    'email' => $emailData['from'],
                ]);

                // Add headers and metadata to the description
                $description = "Email received from: " . ($fromName ?: $emailData['from']) . "\n\n";

                // Add relevant headers if available
                if (isset($emailData['headers']) && is_array($emailData['headers'])) {
                    if (isset($emailData['headers']['date'])) {
                        $description .= "Date: " . $emailData['headers']['date'] . "\n";
                    }
                    if (isset($emailData['headers']['message-id'])) {
                        $description .= "Message-ID: " . $emailData['headers']['message-id'] . "\n";
                    }
                }

                $ticket->description = $description;
                $ticket->save();

                // Log the ticket creation
                TicketActivityLog::logTicketCreation($ticket, null);

                // Create the initial message
                $messageContent = $this->processEmailContent($emailData);
                $ticketMessage = new TicketMessage([
                    'ticket_id' => $ticket->id,
                    'content' => $messageContent,
                    'is_private' => false,
                ]);

                if ($contact) {
                    $ticketMessage->sender()->associate($contact);
                }

                $ticketMessage->save();

                // Process attachments if any
                if (!empty($emailData['attachments'])) {
                    $this->processEmailAttachments($emailData['attachments'], $ticketMessage);
                }

                // Log message added activity
                TicketActivityLog::create([
                    'ticket_id' => $ticket->id,
                    'activity_type' => TicketActivityLog::ACTIVITY_MESSAGE_ADDED,
                    'description' => 'Initial email message added',
                ]);

                return $ticket;
            });
        } catch (Exception $e) {
            Log::error('Error creating ticket from email: ' . $e->getMessage(), [
                'exception' => $e,
                'email_data' => $emailData,
            ]);
            return null;
        }
    }

    /**
     * Extract name from email data if available.
     * 
     * @param array $emailData
     * @return string|null
     */
    private function extractNameFromEmail(array $emailData): ?string
    {
        // Try to extract name from "From" header (e.g., "John Doe <john@example.com>")
        if (isset($emailData['headers']['from'])) {
            $from = $emailData['headers']['from'];
            if (preg_match('/^(.+?)\s*<.*>$/', $from, $matches)) {
                return trim($matches[1]);
            }
        }

        // Try to extract from the "from" field if it has a name
        if (isset($emailData['from']) && preg_match('/^(.+?)\s*<.*>$/', $emailData['from'], $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    /**
     * Process email content to get a clean message body.
     * 
     * @param array $emailData
     * @return string
     */
    private function processEmailContent(array $emailData): string
    {
        // Fall back to text content
        if (!empty($emailData['text'])) {
            return nl2br(htmlspecialchars($emailData['text']));
        }
        // Prefer HTML content if available
        if (!empty($emailData['html'])) {
            // Simple HTML cleanup for security
            $html = strip_tags($emailData['html'], '<p><br><a><strong><em><ul><ol><li><blockquote><h1><h2><h3><div>');
            return $html;
        }



        return 'No content';
    }

    /**
     * Find or create a contact based on email address.
     *
     * @param string $email
     * @param string $workspaceId
     * @return ContactEntity|null
     */
    private function findOrCreateContact(string $email, string $workspaceId): ?ContactEntity
    {
        try {
            // Check if a contact with this email already exists
            $identifier = Identifier::where('key', ContactEntity::IDENTIFIER_TYPE_EMAIL)
                ->where('value', $email)
                ->first();

            if ($identifier && $identifier->contact) {
                return $identifier->contact;
            }

            // Create a new contact
            return DB::transaction(function () use ($email, $workspaceId) {
                $contact = ContactEntity::create([
                    'workspace_id' => $workspaceId,
                ]);

                // Add email identifier
                $contact->identifiers()->create([
                    'key' => ContactEntity::IDENTIFIER_TYPE_EMAIL,
                    'value' => $email,
                ]);

                return $contact;
            });
        } catch (Exception $e) {
            Log::error('Error finding or creating contact: ' . $e->getMessage(), [
                'exception' => $e,
                'email' => $email,
                'workspace_id' => $workspaceId,
            ]);
            return null;
        }
    }

    /**
     * Process email attachments and link them to the ticket message.
     *
     * @param array $attachments
     * @param TicketMessage $ticketMessage
     * @return void
     */
    private function processEmailAttachments(array $attachments, TicketMessage $ticketMessage): void
    {
        $fileUploadService = app(FileUploadService::class);

        foreach ($attachments as $attachment) {
            try {
                $fileName = $attachment['filename'] ?? ('attachment-' . Str::random(8));
                $content = $attachment['content'] ?? null;
                $contentType = $attachment['contentType'] ?? 'application/octet-stream';

                if (!$content) {
                    continue;
                }

                // Create a temporary file
                $tempFile = tempnam(sys_get_temp_dir(), 'email_attachment_');
                file_put_contents($tempFile, base64_decode($content));

                // Get file size
                $fileSize = filesize($tempFile);

                // Upload the file
                $uploadPath = "tickets/{$ticketMessage->ticket_id}/attachments/{$fileName}";
                $filePath = $fileUploadService->uploadFromPath($tempFile, $uploadPath);

                // Create attachment record
                $ticketMessage->attachments()->create([
                    'file_name' => $fileName,
                    'file_path' => $filePath,
                    'mime_type' => $contentType,
                    'file_size' => $fileSize,
                ]);

                // Clean up temporary file
                unlink($tempFile);
            } catch (Exception $e) {
                Log::error('Error processing email attachment: ' . $e->getMessage(), [
                    'exception' => $e,
                    'attachment' => $attachment,
                ]);
            }
        }
    }

    /**
     * Add a reply to an existing ticket from an email.
     *
     * @param array $emailData
     * @param TicketEntity $ticket
     * @return bool
     */
    public function addReplyFromEmail(array $emailData, TicketEntity $ticket): bool
    {
        try {
            return DB::transaction(function () use ($emailData, $ticket) {
                // Find the contact
                $contact = null;
                if ($ticket->contact_id) {
                    $contact = ContactEntity::find($ticket->contact_id);
                } else {
                    $contact = $this->findOrCreateContact($emailData['from'], $ticket->workspace_id);

                    // Update ticket with the contact if found/created
                    if ($contact) {
                        $ticket->contact_id = $contact->id;
                        $ticket->save();
                    }
                }

                // Create the message
                $messageContent = $this->processEmailContent($emailData);
                $ticketMessage = new TicketMessage([
                    'ticket_id' => $ticket->id,
                    'content' => $messageContent,
                    'is_private' => false,
                ]);

                if ($contact) {
                    $ticketMessage->sender()->associate($contact);
                }

                $ticketMessage->save();

                // Process attachments if any
                if (!empty($emailData['attachments'])) {
                    $this->processEmailAttachments($emailData['attachments'], $ticketMessage);
                }

                // Log message added activity
                TicketActivityLog::create([
                    'ticket_id' => $ticket->id,
                    'activity_type' => TicketActivityLog::ACTIVITY_MESSAGE_ADDED,
                    'description' => 'Email reply added',
                ]);

                // If the ticket was closed, reopen it
                if (in_array($ticket->status, [TicketEntity::STATUS_RESOLVED, TicketEntity::STATUS_CLOSED])) {
                    $oldStatus = $ticket->status;
                    $ticket->status = TicketEntity::STATUS_OPEN;
                    $ticket->save();

                    // Log status change
                    TicketActivityLog::logStatusChange($ticket, $oldStatus, TicketEntity::STATUS_OPEN, null);
                }

                return true;
            });
        } catch (Exception $e) {
            Log::error('Error adding reply from email: ' . $e->getMessage(), [
                'exception' => $e,
                'email_data' => $emailData,
                'ticket_id' => $ticket->id,
            ]);
            return false;
        }
    }

    /**
     * Extract the ticket number from an email subject.
     *
     * @param string $subject
     * @return string|null
     */
    public function extractTicketNumber(string $subject): ?string
    {
        $pattern = '/\[(?:Ticket:|التذكرة:)?\s*(TKT-[A-Z0-9]+-\d+)\]/i';
        if (preg_match($pattern, $subject, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Fetch emails from the configured mailboxes and process them.
     *
     * @return JsonResponse
     */
    public function fetchEmails(): JsonResponse
    {
        try {
            $emailConfigs = TicketEmailConfiguration::where('is_active', true)->get();
            $processedCount = 0;
            $errorCount = 0;

            foreach ($emailConfigs as $config) {
                try {
                    // Set up IMAP client
                    $client = Client::make([
                        'host' => $config->mail_server,
                        'port' => $config->mail_port,
                        'encryption' => $config->mail_encryption,
                        'validate_cert' => true,
                        'username' => $config->mail_username,
                        'password' => $config->getDecryptedPasswordAttribute(),
                        'protocol' => 'imap'
                    ]);

                    // Connect to the IMAP server
                    $client->connect();

                    // Get the inbox folder
                    $inbox = $client->getFolder('INBOX');

                    // Get all unseen messages
                    $messages = $inbox->query()->unseen()->get();

                    foreach ($messages as $message) {
                        try {
                            $emailData = [
                                'to' => $config->email_address,
                                'from' => $message->getFrom()[0]->mail,
                                'subject' => $message->getSubject(),
                                'text' => $message->getTextBody(),
                                'html' => $message->getHTMLBody(),
                                'headers' => $this->extractHeaders($message),
                                'attachments' => $this->extractAttachments($message),
                            ];

                            // Queue the job to process the email
                            ProcessIncomingEmail::dispatch($emailData);
                            $processedCount++;

                            // Mark message as seen
                            $message->setFlag('Seen');
                        } catch (Exception $e) {
                            Log::error('Error processing message: ' . $e->getMessage(), [
                                'exception' => $e,
                                'message_id' => $message->getMessageId(),
                            ]);
                            $errorCount++;
                        }
                    }
                } catch (Exception $e) {
                    Log::error('Error connecting to mailbox: ' . $e->getMessage(), [
                        'exception' => $e,
                        'config_id' => $config->id,
                        'mail_server' => $config->mail_server,
                    ]);
                    $errorCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Fetched emails successfully: {$processedCount} processed, {$errorCount} errors",
                'data' => [
                    'processed_count' => $processedCount,
                    'error_count' => $errorCount,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching emails: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch emails',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract headers from an email message.
     *
     * @param mixed $message
     * @return array
     */
    private function extractHeaders($message): array
    {
        $headers = [];
        $headerBag = $message->getHeaders();

        // Common headers to extract
        $headerKeys = [
            'date',
            'from',
            'to',
            'cc',
            'reply-to',
            'subject',
            'message-id',
            'in-reply-to',
            'references',
            'content-type'
        ];

        foreach ($headerKeys as $key) {
            if ($headerBag->has($key)) {
                $headers[$key] = $headerBag->get($key);
            }
        }

        return $headers;
    }

    /**
     * Extract attachments from an email message.
     *
     * @param mixed $message
     * @return array
     */
    private function extractAttachments($message): array
    {
        $attachments = [];

        try {
            $attachmentBag = $message->getAttachments();

            foreach ($attachmentBag as $attachment) {
                $attachments[] = [
                    'filename' => $attachment->getName(),
                    'content' => base64_encode($attachment->getContent()),
                    'contentType' => $attachment->getMimeType(),
                    'size' => strlen($attachment->getContent()),
                ];
            }
        } catch (Exception $e) {
            Log::error('Error extracting attachments: ' . $e->getMessage(), [
                'exception' => $e,
                'message_id' => $message->getMessageId(),
            ]);
        }

        return $attachments;
    }

    /**
     * Send an email notification for a ticket update.
     *
     * @param Ticket $ticket
     * @param TicketMessage $message
     * @param bool $isNewTicket
     * @return bool
     */
    public function sendTicketEmailNotification(Ticket $ticket, TicketMessage $message, bool $isNewTicket = false): bool
    {
        try {
            // Don't send notification if the sender is the contact (customer)
            if ($message->sender_type === ContactEntity::class) {
                return true;
            }

            // Make sure we have an email to send to
            $toEmail = $ticket->email;
            if (!$toEmail && $ticket->contact) {
                $toEmail = $ticket->contact->getEmailIdentifier();
            }

            if (!$toEmail) {
                Log::warning('No email address found for ticket notification', [
                    'ticket_id' => $ticket->id,
                ]);
                return false;
            }

            // Determine the appropriate email configuration to use as sender
            $emailConfig = TicketEmailConfiguration::where('workspace_id', $ticket->workspace_id)
                ->where('is_active', true)
                ->first();

            if (!$emailConfig) {
                Log::warning('No active email configuration found for sending notification', [
                    'workspace_id' => $ticket->workspace_id,
                ]);
                return false;
            }

            // Generate the email subject
            $subject = $isNewTicket
                ? "Ticket Created: {$ticket->subject} [Ticket: {$ticket->ticket_number}]"
                : "New Reply: {$ticket->subject} [Ticket: {$ticket->ticket_number}]";

            // Generate the email body
            $htmlBody = view('emails.ticket_notification', [
                'ticket' => $ticket,
                'message' => $message,
                'isNewTicket' => $isNewTicket,
            ])->render();

            // Convert HTML to text
            $textBody = strip_tags($htmlBody);

            // Set up mail data
            $mailData = [
                'to' => [$toEmail],
                'subject' => $subject,
                'html' => $htmlBody,
                'text' => $textBody,
                'from' => [
                    'email' => $emailConfig->email_address,
                    'name' => 'Support Team',
                ],
                'reply_to' => $emailConfig->email_address,
                'headers' => [
                    'X-Ticket-Number' => $ticket->ticket_number,
                ],
            ];

            // Send the email (using a mail service/driver of your choice)
            // Here we're assuming you'll use Laravel's Mail facade or a similar service
            \Mail::send([], [], function ($message) use ($mailData) {
                $message->to($mailData['to'])
                    ->subject($mailData['subject'])
                    ->from($mailData['from']['email'], $mailData['from']['name'])
                    ->replyTo($mailData['reply_to'])
                    ->html($mailData['html']);

                // Add headers
                foreach ($mailData['headers'] as $key => $value) {
                    $message->getHeaders()->addTextHeader($key, $value);
                }
            });

            return true;
        } catch (Exception $e) {
            Log::error('Error sending ticket email notification: ' . $e->getMessage(), [
                'exception' => $e,
                'ticket_id' => $ticket->id,
                'message_id' => $message->id,
            ]);
            return false;
        }
    }
}