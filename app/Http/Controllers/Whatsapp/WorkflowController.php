<?php

namespace App\Http\Controllers\Whatsapp;

use App\Enums\Workflow\ActionType;
use App\Enums\Workflow\TriggerType;
use App\Http\Controllers\BaseApiController;
use App\Http\Responses\ValidatorErrorResponse;
use App\Models\InteractiveMessageDraft;
use App\Models\WhatsappMessageTemplate;
use App\Models\WhatsappWorkflow;
use App\Models\WhatsappWorkflowAction;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WorkflowController extends BaseApiController
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:api')
        ];
    }

    /**
     * Store a complete workflow chain.
     *
     * Example request:
     * {
     *   "name": "Welcome Flow",
     *   "description": "Complete welcome workflow",
     *   "steps": [
     *     {
     *       "step_type": "template_trigger",
     *       "template_id": 123,
     *       "trigger_status": "read",
     *       "action": {
     *         "action_type": "send_interactive",
     *         "action_config": {"interactive_draft_id": 1}
     *       }
     *     },
     *     {
     *       "step_type": "button_reply",
     *       "interactive_message_draft_id": 1,
     *       "trigger_reply_id": "arabic_lang",
     *       "action": {
     *         "action_type": "send_interactive",
     *         "action_config": {"interactive_draft_id": 2}
     *       }
     *     },
     *     {
     *       "step_type": "button_reply",
     *       "interactive_message_draft_id": 1,
     *       "trigger_reply_id": "english_lang",
     *       "action": {
     *         "action_type": "send_text",
     *         "action_config": {"text": "Welcome!"}
     *       }
     *     }
     *   ]
     * }
     */
    public function store(Request $request, string $workspaceId): JsonResponse
    {
        $validator = $this->validateWorkflowChain($request, $workspaceId);
        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 422);
        }
        try {
            DB::beginTransaction();

            $createdWorkflows = $this->createWorkflowChain($request, $workspaceId);

            DB::commit();

            return $this->response(true, 'Workflow chain created successfully', [
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'workflows_created' => $createdWorkflows,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->response(false, 'Failed to create workflow: ' . $e->getMessage(), null, 500);
        }
    }

    /**
     * Create the complete workflow chain.
     */
    protected function createWorkflowChain(Request $request, string $workspaceId): array
    {
        $steps = $request->input('steps', []);
        $flowName = $request->input('name');
        $flowDescription = $request->input('description');
        $flowId = (string) \Illuminate\Support\Str::uuid();
        $createdWorkflows = [];

        foreach ($steps as $index => $step) {
            $stepType = $step['step_type'];
            $stepName = $step['name'] ?? "{$flowName} - Step " . ($index + 1);
            $stepDescription = $step['description'] ?? $flowDescription;

            // Build trigger config based on step type
            $triggerType = $this->mapStepTypeToTriggerType($stepType);
            $triggerConfig = $this->buildTriggerConfig($step, $stepType);

            // Create unified workflow
            $workflow = WhatsappWorkflow::create([
                'workspace_id' => $workspaceId,
                'flow_id' => $flowId,
                'flow_name' => $flowName,
                'flow_description' => $flowDescription,
                'name' => $stepName,
                'description' => $stepDescription,
                'trigger_type' => $triggerType->value,
                'trigger_config' => $triggerConfig,
                'is_active' => $step['is_active'] ?? true,
                'delay_seconds' => $step['delay_seconds'] ?? 0,
                'priority' => $step['priority'] ?? 0,
            ]);

            // Create action
            WhatsappWorkflowAction::create([
                'whatsapp_workflow_id' => $workflow->id,
                'action_type' => $step['action']['action_type'],
                'action_config' => $step['action']['action_config'] ?? [],
                'order' => 1,
                'is_active' => $step['action']['is_active'] ?? true,
                'delay_seconds' => $step['action']['delay_seconds'] ?? 0,
            ]);

            $createdWorkflows[] = [
                'type' => $triggerType->value,
                'id' => $workflow->id,
                'name' => $stepName,
                'trigger_config' => $triggerConfig,
            ];
        }

        return $createdWorkflows;
    }

    /**
     * Map step type to TriggerType enum.
     */
    protected function mapStepTypeToTriggerType(string $stepType): TriggerType
    {
        return match ($stepType) {
            'template_trigger' => TriggerType::TEMPLATE_STATUS,
            'button_reply' => TriggerType::BUTTON_REPLY,
            'list_reply' => TriggerType::LIST_REPLY,
            'conversation_start' => TriggerType::START_CONVERSATION,
            default => throw new \InvalidArgumentException("Unknown step type: {$stepType}"),
        };
    }

    /**
     * Build trigger config based on step type.
     */
    protected function buildTriggerConfig(array $step, string $stepType): array
    {
        return match ($stepType) {
            'template_trigger' => [
                'template_id' => $step['template_id'],
                'status' => $step['trigger_status'],
            ],
            'button_reply' => [
                'interactive_draft_id' => $step['interactive_message_draft_id'],
                'button_id' => $step['trigger_reply_id'],
            ],
            'list_reply' => [
                'interactive_draft_id' => $step['interactive_message_draft_id'],
                'row_id' => $step['trigger_reply_id'],
            ],
            'conversation_start' => [
                'template_id' => $step['template_id'],
            ],
            default => [],
        };
    }

    /**
     * Validate workflow chain request.
     */
    protected function validateWorkflowChain(Request $request, string $workspaceId): \Illuminate\Validation\Validator
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'steps' => 'required|array|min:1',
            'steps.*.step_type' => 'required|string|in:template_trigger,button_reply,list_reply,conversation_start',
            'steps.*.name' => 'nullable|string|max:255',
            'steps.*.description' => 'nullable|string',
            'steps.*.is_active' => 'boolean',
            'steps.*.delay_seconds' => 'integer|min:0',
            'steps.*.priority' => 'integer|min:0',

            // Action validation
            'steps.*.action' => 'required|array',
            'steps.*.action.action_type' => 'required|string|in:send_template,send_interactive,send_text,add_to_list,remove_from_list,update_contact,webhook',
            'steps.*.action.action_config' => 'required|array',
            'steps.*.action.is_active' => 'boolean',
            'steps.*.action.delay_seconds' => 'integer|min:0',
        ];

        // Custom validation for each step
        $validator = Validator::make($request->all(), $rules);
        
        $validator->after(function ($validator) use ($request, $workspaceId) {
            $steps = $request->input('steps', []);

            foreach ($steps as $index => $step) {
                $stepType = $step['step_type'] ?? null;

                if ($stepType === 'template_trigger') {
                    // Validate template_id
                    if (empty($step['template_id'])) {
                        $validator->errors()->add("steps.{$index}.template_id", 'Template ID is required for template_trigger.');
                    } else {
                        // Get the whatsapp_business_account_ids linked to this workspace through channels
                        $wabaIds = Workspace::where('id', $workspaceId)
                            ->first()
                            ?->channels()
                            ->with('whatsappConfiguration')
                            ->get()
                            ->pluck('whatsappConfiguration.whatsapp_business_account_id')
                            ->filter()
                            ->unique()
                            ->values()
                            ->toArray();

                        $exists = WhatsappMessageTemplate::where('id', $step['template_id'])
                            ->whereIn('whatsapp_business_account_id', $wabaIds)
                            ->exists();
                        if (!$exists) {
                            $validator->errors()->add("steps.{$index}.template_id", 'Template not found in this workspace.');
                        }
                    }

                    // Validate trigger_status
                    if (empty($step['trigger_status']) || !in_array($step['trigger_status'], ['sent', 'delivered', 'read'])) {
                        $validator->errors()->add("steps.{$index}.trigger_status", 'Valid trigger_status (sent, delivered, read) is required.');
                    }

                } elseif (in_array($stepType, ['button_reply', 'list_reply'])) {
                    // Validate interactive_message_draft_id
                    if (empty($step['interactive_message_draft_id'])) {
                        $validator->errors()->add("steps.{$index}.interactive_message_draft_id", 'Interactive message draft ID is required.');
                    } else {
                        $draft = InteractiveMessageDraft::where('id', $step['interactive_message_draft_id'])
                            ->where('workspace_id', $workspaceId)
                            ->first();
                        if (!$draft) {
                            $validator->errors()->add("steps.{$index}.interactive_message_draft_id", 'Interactive message draft not found in this workspace.');
                        } else {
                            // Validate that trigger_reply_id exists in the draft
                            $this->validateReplyIdInDraft($validator, $draft, $step, $index);
                        }
                    }

                    // Validate trigger_reply_id
                    if (empty($step['trigger_reply_id'])) {
                        $validator->errors()->add("steps.{$index}.trigger_reply_id", 'Trigger reply ID is required.');
                    }
                }elseif($stepType === 'conversation_start'){
                    // No additional validation needed for conversation_start
                }
            }
        });

        return $validator;
    }

    /**
     * Validate that trigger_reply_id exists in the draft's buttons/sections.
     */
    protected function validateReplyIdInDraft($validator, InteractiveMessageDraft $draft, array $step, int $index): void
    {
        $replyId = $step['trigger_reply_id'] ?? null;
        if (!$replyId) return;

        $validIds = [];

        if ($step['step_type'] === 'button_reply' && $draft->buttons) {
            $validIds = collect($draft->buttons)->pluck('id')->toArray();
        } elseif ($step['step_type'] === 'list_reply' && $draft->sections) {
            foreach ($draft->sections as $section) {
                foreach ($section['rows'] ?? [] as $row) {
                    $validIds[] = $row['id'];
                }
            }
        }

        if (!empty($validIds) && !in_array($replyId, $validIds)) {
            $validator->errors()->add(
                "steps.{$index}.trigger_reply_id",
                "Reply ID '{$replyId}' not found in draft. Available: " . implode(', ', $validIds)
            );
        }
    }

    /**
     * Get available action types.
     */
    public function getActionTypes(): JsonResponse
    {
        return $this->response(true, 'Action types fetched successfully', [
            'action_types' => ActionType::toSelectArray(),
            'trigger_types' => TriggerType::toSelectArray(),
            'step_types' => [
                'template_trigger' => 'Template Status Trigger (sent, delivered, read)',
                'button_reply' => 'Interactive Button Reply',
                'list_reply' => 'Interactive List Reply',
            ],
            'trigger_statuses' => [
                'sent' => 'Message Sent',
                'delivered' => 'Message Delivered',
                'read' => 'Message Read',
            ],
        ]);
    }

    /**
     * Get all workflows for a workspace grouped by flow_id.
     */
    public function index(Request $request, string $workspaceId): JsonResponse
    {
        $workflows = WhatsappWorkflow::with(['actions'])
            ->forWorkspace($workspaceId)
            ->orderByDesc('priority')
            ->get();

        // Group workflows by flow_id
        $flows = $workflows->groupBy('flow_id')->map(function ($groupedWorkflows, $flowId) {
            $firstWorkflow = $groupedWorkflows->first();

            return [
                'flow_id' => $flowId,
                'flow_name' => $firstWorkflow->flow_name,
                'flow_description' => $firstWorkflow->flow_description,
                'steps' => $groupedWorkflows->map(function ($workflow) {
                    return [
                        'id' => $workflow->id,
                        'name' => $workflow->name,
                        'description' => $workflow->description,
                        'trigger_type' => $workflow->trigger_type,
                        'trigger_config' => $workflow->trigger_config,
                        'is_active' => $workflow->is_active,
                        'priority' => $workflow->priority,
                        'delay_seconds' => $workflow->delay_seconds,
                        'actions' => $workflow->actions->map(function ($action) {
                            return [
                                'id' => $action->id,
                                'action_type' => $action->action_type,
                                'action_config' => $action->action_config,
                                'order' => $action->order,
                                'is_active' => $action->is_active,
                                'delay_seconds' => $action->delay_seconds,
                                'template' => $action->template,
                            ];
                        }),
                        'created_at' => $workflow->created_at,
                        'updated_at' => $workflow->updated_at,
                    ];
                })->values(),
                'created_at' => $firstWorkflow->created_at,
                'updated_at' => $groupedWorkflows->max('updated_at'),
            ];
        })->values();

        return $this->response(true, 'Workflows fetched successfully', [
            'flows' => $flows,
        ]);
    }

    /**
     * Get a single flow with all its steps and templates.
     */
    public function show(Request $request, string $workspaceId, string $flowId): JsonResponse
    {
        $workflows = WhatsappWorkflow::with(['actions'])
            ->forWorkspace($workspaceId)
            ->where('flow_id', $flowId)
            ->orderByDesc('priority')
            ->get();

        if ($workflows->isEmpty()) {
            return $this->response(false, 'Flow not found', null, 404);
        }

        $firstWorkflow = $workflows->first();

        $flow = [
            'flow_id' => $flowId,
            'flow_name' => $firstWorkflow->flow_name,
            'flow_description' => $firstWorkflow->flow_description,
            'steps' => $workflows->map(function ($workflow) {
                return [
                    'id' => $workflow->id,
                    'name' => $workflow->name,
                    'description' => $workflow->description,
                    'trigger_type' => $workflow->trigger_type,
                    'trigger_config' => $workflow->trigger_config,
                    'is_active' => $workflow->is_active,
                    'priority' => $workflow->priority,
                    'delay_seconds' => $workflow->delay_seconds,
                    'actions' => $workflow->actions->map(function ($action) {
                        return [
                            'id' => $action->id,
                            'action_type' => $action->action_type,
                            'action_config' => $action->action_config,
                            'order' => $action->order,
                            'is_active' => $action->is_active,
                            'delay_seconds' => $action->delay_seconds,
                            'template' => $action->template,
                        ];
                    }),
                    'created_at' => $workflow->created_at,
                    'updated_at' => $workflow->updated_at,
                ];
            })->values(),
            'created_at' => $firstWorkflow->created_at,
            'updated_at' => $workflows->max('updated_at'),
        ];

        return $this->response(true, 'Flow fetched successfully', [
            'flow' => $flow,
        ]);
    }

    /**
     * Delete all workflows with the same flow_id.
     */
    public function destroy(Request $request, string $workspaceId, string $flowId): JsonResponse
    {
        $workflows = WhatsappWorkflow::forWorkspace($workspaceId)
            ->where('flow_id', $flowId)
            ->get();

        if ($workflows->isEmpty()) {
            return $this->response(false, 'Flow not found', null, 404);
        }

        $deletedCount = WhatsappWorkflow::forWorkspace($workspaceId)
            ->where('flow_id', $flowId)
            ->delete();

        return $this->response(true, 'Flow deleted successfully', [
            'deleted_steps' => $deletedCount
        ]);
    }
}

