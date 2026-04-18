<?php

namespace App\Jobs;

use App\Http\Controllers\TicketEmailController;
use App\Models\TicketEntity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessIncomingEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The email data.
     *
     * @var array
     */
    protected $emailData;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array
     */
    public $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     *
     * @param array $emailData
     */
    public function __construct(array $emailData)
    {
        $this->emailData = $emailData;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $emailController = app(TicketEmailController::class);
            
            // Check if this is a reply to an existing ticket
            $subject = $this->emailData['subject'] ?? '';
            $ticketNumber = $emailController->extractTicketNumber($subject);
            if ($ticketNumber) {
                // Try to find the ticket
                $ticket = TicketEntity::where('ticket_number', $ticketNumber)->first();
                
                if ($ticket) {
                    // This is a reply to an existing ticket
                    Log::info('Processing email reply for ticket', [
                        'ticket_number' => $ticketNumber,
                        'from' => $this->emailData['from'] ?? 'unknown',
                    ]);
                    
                    $emailController->addReplyFromEmail($this->emailData, $ticket);
                    return;
                }
            }
            
            // This is a new ticket
            Log::info('Processing email for new ticket creation', [
                'from' => $this->emailData['from'] ?? 'unknown',
                'subject' => $subject,
            ]);
            
            $emailController->createTicketFromEmail($this->emailData);
        } catch (\Exception $e) {
            Log::error('Error processing incoming email job: ' . $e->getMessage(), [
                'exception' => $e,
                'email_data' => $this->emailData,
            ]);
            
            throw $e;
        }
    }
}