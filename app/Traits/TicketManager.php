<?php
namespace App\Traits;
use App\Models\TicketAgent;
use App\Models\TicketEntity;
use App\Models\TicketMessage;
use App\Models\ContactEntity;
use App\Models\TicketEmailConfiguration;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Mail;

trait TicketManager
{

    /**
     * Send an email notification for a ticket update.
     *
     * @param TicketEntity $ticket
     * @param TicketMessage $message
     * @param bool $isNewTicket
     * @return bool
     */
    public function sendTicketEmailNotification(TicketEntity $ticket, TicketMessage $message, bool $isNewTicket = false): bool
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
            $locale = $ticket->language ?? config('app.locale');
            app()->setLocale($locale);

            // Generate the email subject
            $subject = $isNewTicket
                ? __('notification.email.ticket.subject-email-created', ['subject' => $ticket->subject, 'ticket_number' => $ticket->ticket_number])
                : __('notification.email.ticket.subject-email-replay', ['subject' => $ticket->subject, 'ticket_number' => $ticket->ticket_number]);

            // Generate the email body
            $htmlBody = view('emails.ticket_notification', [
                'ticket' => $ticket,
                'message' => $message,
                'title' => $isNewTicket ? __('notification.email.ticket.new_ticket_created') : __('notification.email.ticket.new_reply'),
                'isNewTicket' => $isNewTicket,
                'actionUrl' => '#',
                'actionText' => __('notification.email.ticket.view'),
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
                'reply_to' => "123312@wfilter.dreams.sa",
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

    /**
     * Create a default contact form.
     *
     * @param string $licenseId
     * @param string $workspaceId
     * @return \App\Models\TicketForm
     */
    private function createDefaultContactForm(string $ticketFormID): void
    {

        // Add default fields
        $defaultFields = [
            [
                'label' => 'Name',
                'type' => 'text',
                'placeholder' => 'Your full name',
                'is_required' => true,
                'order' => 0,
            ],
            [
                'label' => 'Email',
                'type' => 'email',
                'placeholder' => 'Your email address',
                'is_required' => true,
                'order' => 1,
            ],
            [
                'label' => 'Subject',
                'type' => 'text',
                'placeholder' => 'Brief description of your issue',
                'is_required' => true,
                'order' => 2,
            ],
            [
                'label' => 'Message',
                'type' => 'textarea',
                'placeholder' => 'Please describe your issue in detail',
                'is_required' => true,
                'order' => 3,
            ]
            // [
            //     'label' => 'Priority',
            //     'type' => 'select',
            //     'options' => json_encode([
            //         ['value' => 'low', 'label' => 'Low'],
            //         ['value' => 'medium', 'label' => 'Medium'],
            //         ['value' => 'high', 'label' => 'High'],
            //         ['value' => 'urgent', 'label' => 'Urgent'],
            //     ]),
            //     'placeholder' => 'Select priority',
            //     'is_required' => true,
            //     'order' => 4,
            // ],
        ];

        foreach ($defaultFields as $field) {
            \App\Models\TicketFormField::create(array_merge($field, [
                'ticket_form_id' => $ticketFormID,
            ]));
        }
    }

     /**
     * Assign an inbox agent to a ticket.
     *
     * @param User $inboxAgent The agent to assign.
     * @param TicketEntity $ticket The ticket to assign the agent to.
     * @return bool
     */
    public function assignInboxAgentToTicket(User $inboxAgent, TicketEntity $ticket): bool
    {
        // Check if the agent is already assigned
        $existingAssignment = TicketAgent::where('ticket_id', $ticket->id)
            ->where('inbox_agent_id', $inboxAgent->id)
            ->whereNull('removed_at')
            ->exists();

        if ($existingAssignment) {
            return false; // Agent is already assigned
        }

        // Assign the agent
        TicketAgent::create([
            'ticket_id' => $ticket->id,
            'inbox_agent_id' => $inboxAgent->id,
            'assigned_at' => now()
        ]);

        return true;
    }

     /**
     * Remove an inbox agent from a ticket.
     *
     * @param User $inboxAgent The agent to remove.
     * @param TicketEntity $ticket The ticket to remove the agent from.
     * @return bool
     */
    public function removeInboxAgentFromTicket(User $inboxAgent, TicketEntity $ticket): bool
    {
        // Check if the agent is assigned
        $existingAssignment = TicketAgent::where('ticket_id', $ticket->id)
            ->where('inbox_agent_id', $inboxAgent->id)
            ->whereNull('removed_at')
            ->first();

        if (!$existingAssignment) {
            return false; // Agent is not currently assigned
        }

        // Mark the agent as removed (keeping history)
        $existingAssignment->update([
            'removed_at' => now()
        ]);

        return true;
    }
}