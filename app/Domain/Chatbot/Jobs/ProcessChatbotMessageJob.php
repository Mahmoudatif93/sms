<?php

namespace App\Domain\Chatbot\Jobs;

use App\Domain\Chatbot\Actions\ProcessIncomingMessageAction;
use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessChatbotMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [10, 30, 60];

    public function __construct(
        public Conversation $conversation,
        public string $message
    ) {
        $this->onQueue('sms-low');
    }

    public function handle(ProcessIncomingMessageAction $action): void
    {
        try {
            Log::info('Processing chatbot message in background', [
                'conversation_id' => $this->conversation->id,
                'message_preview' => mb_substr($this->message, 0, 50),
            ]);

            $action->execute($this->conversation, $this->message);

        } catch (\Exception $e) {
            Log::error('Chatbot job failed', [
                'conversation_id' => $this->conversation->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Chatbot job permanently failed', [
            'conversation_id' => $this->conversation->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
