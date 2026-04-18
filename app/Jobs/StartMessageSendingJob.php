<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\Sms;
use App\Class\LargeSmsProcessor;
use Illuminate\Support\Facades\Log;

class StartMessageSendingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $messageId;
    protected $sms;
    protected $largeSmsProcessor;
    protected $message;
    public $timeout = 300; // 5 minutes timeout
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(Message $message, Sms $sms, LargeSmsProcessor $largeSmsProcessor)
    {
        $this->message = $message;
        $this->sms = $sms;
        $this->largeSmsProcessor = $largeSmsProcessor;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting message sending for message ID: {$this->message->id}");

            // Start the SMS campaign
            $this->largeSmsProcessor->StartMessageSend($this->message);

            if ($this->message->sending_datetime == null && !$this->message->advertising == 1) {//&& $message->variables_message == 0
                $this->largeSmsProcessor->sendMessage($this->message->id);
            }

            Log::info("Message sending started successfully for message ID: {$this->message->id}");

        } catch (\Exception $e) {
            Log::error("Failed to start message sending for message ID: {$this->message->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("StartMessageSendingJob failed for message ID: {$this->message->id}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
