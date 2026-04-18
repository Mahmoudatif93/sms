<?php

namespace App\Services\Workflow;

use App\Enums\Workflow\TriggerType;
use App\Jobs\ExecuteWhatsappWorkflowActionJob;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplateMessage;
use App\Models\WhatsappWorkflow;
use App\Models\WhatsappWorkflowAction;
use App\Models\WhatsappWorkflowLog;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Unified service for handling all WhatsApp workflow execution.
 *
 * This service handles template status triggers, button replies, and list replies.
 */
class WhatsappWorkflowService
{
    /**
     * Process a template message status update.
     */
    public function processTemplateStatusUpdate(WhatsappMessage $message, string $newStatus): void
    {
        if ($message->type !== WhatsappMessage::MESSAGE_TYPE_TEMPLATE) {
            return;
        }

        $templateId = $this->getTemplateId($message);
        if (!$templateId) {
            return;
        }
        $workflows = WhatsappWorkflow::getForTemplateStatus($templateId, $newStatus);
        if ($workflows->isEmpty()) {
            return;
        }

        Log::info('Triggering workflow for template status', [
            'message_id' => $message->id,
            'template_id' => $templateId,
            'status' => $newStatus,
            'workflows_count' => $workflows->count(),
        ]);

        foreach ($workflows as $workflow) {
            $this->executeWorkflow($workflow, $message);
        }
    }

    /**
     * Process an interactive message reply (button or list).
     */
    public function processInteractiveReply(
        WhatsappMessage $message,
        int $draftId,
        TriggerType $triggerType,
        string $replyId
    ): void {
                $workspaceId = $message->conversation->workspace_id;

        $workflow = WhatsappWorkflow::getForInteractiveReply($draftId, $triggerType, $replyId,$workspaceId);
        if (!$workflow) {
            return;
        }

        Log::info('Triggering workflow for interactive reply', [
            'message_id' => $message->id,
            'draft_id' => $draftId,
            'trigger_type' => $triggerType->value,
            'reply_id' => $replyId,
            'workflow_id' => $workflow->id,
        ]);

        $this->executeWorkflow($workflow, $message);
    }

    public function processConversationStarted(WhatsappMessage $message): void
    {
        $conversationId = $message->conversation_id;
        if (!$conversationId) {
            return;
        }
        $workspaceId = $message->conversation->workspace_id;
        $workflows = WhatsappWorkflow::processConversationStarted($conversationId,$workspaceId);
        if ($workflows->isEmpty()) {
            return;
        }
        Log::info('Triggering workflow for conversation started', [
            'message_id' => $message->id,
            'workflows_count' => $workflows->count(),
        ]);

        foreach ($workflows as $workflow) {
            $this->executeWorkflow($workflow, $message);
        }
    }


    /**
     * Execute a workflow and schedule its actions.
     */
    protected function executeWorkflow(WhatsappWorkflow $workflow, WhatsappMessage $triggerMessage): void
    {
        $actions = $workflow->activeActions;

        if ($actions->isEmpty()) {
            Log::warning('Workflow has no active actions', ['workflow_id' => $workflow->id]);
            return;
        }

        foreach ($actions as $action) {
            $this->scheduleAction($action, $triggerMessage, $workflow);
        }
    }

    /**
     * Schedule an action for execution.
     */
    protected function scheduleAction(
        WhatsappWorkflowAction $action,
        WhatsappMessage $triggerMessage,
        WhatsappWorkflow $workflow
    ): void {
        $totalDelay = $workflow->delay_seconds + $action->delay_seconds;

        $log = WhatsappWorkflowLog::create([
            'whatsapp_workflow_id' => $workflow->id,
            'whatsapp_workflow_action_id' => $action->id,
            'trigger_message_id' => $triggerMessage->id,
            'status' => WhatsappWorkflowLog::STATUS_PENDING,
            'context' => [
                'trigger_type' => $workflow->trigger_type->value,
                'trigger_config' => $workflow->trigger_config,
            ],
        ]);

        if ($totalDelay > 0) {
            ExecuteWhatsappWorkflowActionJob::dispatch($action, $triggerMessage, $log)
                ->delay(now()->addSeconds($totalDelay));
        } else {
            ExecuteWhatsappWorkflowActionJob::dispatch($action, $triggerMessage, $log);
        }

        Log::info('Scheduled workflow action', [
            'action_id' => $action->id,
            'action_type' => $action->action_type->value,
            'delay_seconds' => $totalDelay,
            'log_id' => $log->id,
        ]);
    }

    /**
     * Execute a single action immediately (used by the job).
     */
    public function executeAction(
        WhatsappWorkflowAction $action,
        WhatsappMessage $triggerMessage,
        WhatsappWorkflowLog $log
    ): void {
        try {
            $log->markAsProcessing();

            if (!WorkflowActionFactory::supports($action->action_type)) {
                throw new Exception("Unsupported action type: {$action->action_type->value}");
            }

            $handler = WorkflowActionFactory::create($action->action_type);
            $result = $handler->execute($action, $triggerMessage);

            $log->markAsCompleted($result);

            Log::info('Workflow action executed successfully', [
                'action_id' => $action->id,
                'log_id' => $log->id,
                'result' => $result,
            ]);
        } catch (Exception $e) {
            $log->markAsFailed($e->getMessage());

            Log::error('Workflow action failed', [
                'action_id' => $action->id,
                'log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get the template ID from a message.
     */
    protected function getTemplateId(WhatsappMessage $message): ?int
    {
        if (!$message->relationLoaded('messageable')) {
            $message->load('messageable');
        }

        if ($message->messageable instanceof WhatsappTemplateMessage) {
            return $message->messageable->whatsapp_template_id;
        }

        return null;
    }
}
