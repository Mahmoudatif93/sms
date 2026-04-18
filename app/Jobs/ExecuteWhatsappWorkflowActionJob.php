<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Models\WhatsappWorkflowAction;
use App\Models\WhatsappWorkflowLog;
use App\Services\Workflow\WhatsappWorkflowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Job to execute a WhatsApp workflow action.
 *
 * This job is dispatched by the WhatsappWorkflowService and handles
 * the actual execution of workflow actions with proper error handling.
 */
class ExecuteWhatsappWorkflowActionJob implements ShouldQueue
{
    use Dispatchable, Queueable, InteractsWithQueue, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public WhatsappWorkflowAction $action,
        public WhatsappMessage $triggerMessage,
        public WhatsappWorkflowLog $log
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(WhatsappWorkflowService $workflowService): void
    {
        Log::info('Executing workflow action', [
            'action_id' => $this->action->id,
            'action_type' => $this->action->action_type->value,
            'trigger_message_id' => $this->triggerMessage->id,
            'log_id' => $this->log->id,
        ]);

        $workflowService->executeAction($this->action, $this->triggerMessage, $this->log);
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('ExecuteWhatsappWorkflowActionJob FAILED', [
            'action_id' => $this->action->id,
            'action_type' => $this->action->action_type->value,
            'trigger_message_id' => $this->triggerMessage->id,
            'log_id' => $this->log->id,
            'error' => $exception->getMessage(),
        ]);

        // Mark the log as failed if not already
        if (!$this->log->isFailed()) {
            $this->log->markAsFailed($exception->getMessage());
        }
    }
}

