<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\ContactEntity;
use App\Models\Conversation;
use App\Models\TicketActivityLog;
use App\Models\TicketEntity;
use App\Models\TicketMessage;
use App\Models\TicketTag;
use App\Models\User;
use App\Models\TicketTextMessage;
use App\Models\Workspace;
use App\Services\FileUploadService;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use App\Traits\TicketManager;

class TicketEntityController extends BaseApiController
{
    use TicketManager;
    /**
     * Display a listing of the tickets.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request, Workspace $workspace): JsonResponse
    {
        try {
            // Get query parameters for filtering
            $status = $request->input('status');
            $priority = $request->input('priority');
            $assignedTo = $request->input('assigned_to');
            $source = $request->input('source');
            $search = $request->input('search');
            $perPage = $request->input('per_page', 15);
            $overdue = $request->boolean('overdue', false);
            $tags = $request->input('tags');

            // Start building the query
            $query = $workspace->tickets();
            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($priority) {
                $query->where('priority', $priority);
            }

            if ($assignedTo) {
                if ($assignedTo === 'unassigned') {
                    $query->whereNull('assigned_to');
                } else {
                    $query->where('assigned_to', $assignedTo);
                }
            }

            if ($source) {
                $query->where('source', $source);
            }

            if ($overdue) {
                $query->overdue();
            }

            if ($tags) {
                $tagIds = explode(',', $tags);
                $query->whereHas('tags', function ($q) use ($tagIds) {
                    $q->whereIn('ticket_tags.id', $tagIds);
                });
            }

            // Apply search if provided
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('contact', function ($contactQuery) use ($search) {
                            $contactQuery->whereHas('identifiers', function ($idQuery) use ($search) {
                                $idQuery->where('value', 'like', "%{$search}%");
                            });
                        });
                });
            }

            // Order by creation date (most recent first)
            $query->orderBy('created_at', 'desc');

            // Get paginated results with relationships
            $tickets = $query->with([
                'contact',
                'assignedAgent',
            ])->paginate($perPage);

            $response = $tickets->getCollection()->map(function ($ticket) {
                return new \App\Http\Responses\Ticket($ticket);
            });
            $tickets->setCollection($response);

            return $this->paginateResponse(true, 'items', $tickets);

        } catch (Exception $e) {
            Log::error('Error fetching tickets: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch tickets',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a newly created ticket in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'subject' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|string|in:' . implode(',', [
                    TicketEntity::STATUS_OPEN,
                    TicketEntity::STATUS_IN_PROGRESS,
                    TicketEntity::STATUS_RESOLVED,
                    TicketEntity::STATUS_CLOSED,
                ]),
                'priority' => 'required|string|in:' . implode(',', [
                    TicketEntity::PRIORITY_LOW,
                    TicketEntity::PRIORITY_MEDIUM,
                    TicketEntity::PRIORITY_HIGH,
                    TicketEntity::PRIORITY_URGENT,
                ]),
                'source' => 'required|string|in:' . implode(',', [
                    TicketEntity::SOURCE_CONVERSATION,
                    TicketEntity::SOURCE_EMAIL,
                    TicketEntity::SOURCE_IFRAME,
                ]),
                'contact_id' => 'nullable|string|exists:contacts,id',
                'conversation_id' => 'nullable|string|exists:conversations,id',
                'channel_id' => 'nullable|string|exists:channels,id',
                'email' => 'nullable|email|max:255',
                'assigned_to' => 'nullable|string|exists:users,id',
                'due_date' => 'nullable|date',
                'tags' => 'nullable|array',
                'tags.*' => 'exists:ticket_tags,id',
                'message' => 'nullable|string',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240', // 10MB max file size
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $workspace = $user->currentWorkspace();

            // Begin transaction
            return DB::transaction(function () use ($request, $user, $workspace) {
                // Create the ticket
                $ticket = new TicketEntity([
                    'workspace_id' => $workspace->id,
                    'subject' => $request->input('subject'),
                    'description' => $request->input('description'),
                    'status' => $request->input('status'),
                    'priority' => $request->input('priority'),
                    'source' => $request->input('source'),
                    'contact_id' => $request->input('contact_id'),
                    'channel_id' => $request->input('channel_id'),
                    'conversation_id' => $request->input('conversation_id'),
                    'email' => $request->input('email'),
                    'assigned_to' => $request->input('assigned_to'),
                    'due_date' => $request->input('due_date'),
                ]);

                $ticket->save();

                // Add tags if provided
                if ($request->has('tags')) {
                    $ticket->tags()->attach($request->input('tags'));
                }

                // Log the ticket creation
                TicketActivityLog::logTicketCreation($ticket, $user);

                // Add initial message if provided
                if ($request->has('message')) {
                    $ticketMessage = new TicketMessage([
                        'ticket_id' => $ticket->id,
                        'content' => $request->input('message'),
                        'is_private' => false,
                    ]);

                    $ticketMessage->sender()->associate($user);
                    $ticketMessage->save();

                    // Handle file attachments if any
                    if ($request->hasFile('attachments')) {
                        $fileUploadService = app(FileUploadService::class);

                        foreach ($request->file('attachments') as $file) {
                            $filePath = $fileUploadService->uploadFile($file, "tickets/{$ticket->id}/attachments");

                            $ticketMessage->attachments()->create([
                                'file_name' => $file->getClientOriginalName(),
                                'file_path' => $filePath,
                                'mime_type' => $file->getMimeType(),
                                'file_size' => $file->getSize(),
                            ]);
                        }
                    }
                }

                // If this ticket was created from a conversation, update the conversation status
                if ($request->has('conversation_id')) {
                    $conversation = Conversation::find($request->input('conversation_id'));
                    if ($conversation) {
                        $conversation->update(['status' => Conversation::STATUS_CLOSED]);
                    }
                }

                // Load relationships for the response
                $ticket->load([
                    'contact',
                    'assignedAgent',
                    'tags',
                    'conversation',
                    'channel',
                    'messages.attachments',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket created successfully',
                    'data' => $ticket,
                ], 201);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->validator->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error creating ticket: ' . $e->getMessage(), [
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
     * Display the specified ticket.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $workspace = $user->currentWorkspace();

            $ticketMessages = TicketEntity::where('workspace_id', $workspace->id)
                ->where('id', $id)
                ->with([
                    'contact',
                    'assignedAgent',
                    'tags',
                    'conversation',
                    'channel',
                    'messages' => function ($query) {
                        $query->orderBy('created_at', 'asc');
                    },
                    'messages.sender',
                    'messages.attachments',
                    'activityLogs' => function ($query) {
                        $query->orderBy('created_at', 'desc');
                    },
                    'activityLogs.user',
                ])
                ->firstOrFail();
            $ticketMessages = $ticketMessages->messages->map(function ($message) {
                return new \App\Http\Responses\TicketMessage($message);
            });
            return $this->response(true, 'items', $ticketMessages);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        } catch (Exception $e) {
            Log::error('Error fetching ticket: ' . $e->getMessage(), [
                'exception' => $e,
                'ticket_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified ticket.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, Workspace $workspace, string $id): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'subject' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|required|string|in:' . implode(',', [
                    TicketEntity::STATUS_OPEN,
                    TicketEntity::STATUS_IN_PROGRESS,
                    TicketEntity::STATUS_RESOLVED,
                    TicketEntity::STATUS_CLOSED,
                    TicketEntity::STATUS_SPAM,
                ]),
                'priority' => 'sometimes|required|string|in:' . implode(',', [
                    TicketEntity::PRIORITY_LOW,
                    TicketEntity::PRIORITY_MEDIUM,
                    TicketEntity::PRIORITY_HIGH,
                    TicketEntity::PRIORITY_URGENT,
                ]),
                'assigned_to' => 'nullable|string|exists:users,id',
                'due_date' => 'nullable|date',
                'tags' => 'nullable|array',
                'tags.*' => 'exists:ticket_tags,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $workspace = $user->currentWorkspace();

            // Find the ticket
            $ticket = TicketEntity::where('workspace_id', $workspace->id)
                ->where('id', $id)
                ->firstOrFail();
            // Begin transaction
            return DB::transaction(function () use ($request, $ticket, $user) {
                $oldValues = $ticket->only([
                    'subject',
                    'description',
                    'status',
                    'priority',
                    'assigned_to',
                    'due_date',
                ]);

                // Update ticket fields
                if ($request->has('subject')) {
                    $ticket->subject = $request->input('subject');
                }

                if ($request->has('description')) {
                    $ticket->description = $request->input('description');
                }

                // Handle status change
                if ($request->has('status') && $ticket->status !== $request->input('status')) {
                    $oldStatus = $ticket->status;
                    $newStatus = $request->input('status');
                    $ticket->status = $newStatus;

                    // Log status change
                    TicketActivityLog::logStatusChange($ticket, $oldStatus, $newStatus, $user);
                }

                // Handle priority change
                if ($request->has('priority') && $ticket->priority !== $request->input('priority')) {
                    $oldPriority = $ticket->priority;
                    $newPriority = $request->input('priority');
                    $ticket->priority = $newPriority;

                    // Log priority change
                    TicketActivityLog::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'activity_type' => TicketActivityLog::ACTIVITY_PRIORITY_CHANGED,
                        'description' => "Priority changed from '{$oldPriority}' to '{$newPriority}'",
                        'old_values' => ['priority' => $oldPriority],
                        'new_values' => ['priority' => $newPriority],
                    ]);
                }

                // Handle assignment change
                $oldAssignedTo = $ticket->assigned_to;
                $newAssignedTo = $request->input('assigned_to');

                if ($request->has('assigned_to') && $oldAssignedTo !== $newAssignedTo) {
                    $ticket->assigned_to = $newAssignedTo;

                    // If assigned to someone
                    if ($newAssignedTo) {
                        $newAgent = User::find($newAssignedTo);
                        $oldAgent = $oldAssignedTo ? User::find($oldAssignedTo) : null;

                        // Log assignment
                        TicketActivityLog::create([
                            'ticket_id' => $ticket->id,
                            'user_id' => $user->id,
                            'activity_type' => TicketActivityLog::ACTIVITY_ASSIGNED,
                            'description' => "Ticket assigned to {$newAgent->name}",
                            'old_values' => $oldAgent ? ['assigned_to' => $oldAgent->id, 'agent_name' => $oldAgent->name] : null,
                            'new_values' => ['assigned_to' => $newAgent->id, 'agent_name' => $newAgent->name],
                        ]);
                    } else {
                        // If unassigned
                        $oldAgent = User::find($oldAssignedTo);

                        // Log unassignment
                        TicketActivityLog::create([
                            'ticket_id' => $ticket->id,
                            'user_id' => $user->id,
                            'activity_type' => TicketActivityLog::ACTIVITY_UNASSIGNED,
                            'description' => "Ticket unassigned from {$oldAgent->name}",
                            'old_values' => ['assigned_to' => $oldAgent->id, 'agent_name' => $oldAgent->name],
                            'new_values' => null,
                        ]);
                    }
                }

                // Handle due date change
                if ($request->has('due_date') && $ticket->due_date != $request->input('due_date')) {
                    $oldDueDate = $ticket->due_date;
                    $newDueDate = $request->input('due_date');
                    $ticket->due_date = $newDueDate;

                    // Log due date change
                    TicketActivityLog::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'activity_type' => TicketActivityLog::ACTIVITY_DUE_DATE_CHANGED,
                        'description' => "Due date " . ($newDueDate ? "changed to " . date('Y-m-d', strtotime($newDueDate)) : "removed"),
                        'old_values' => ['due_date' => $oldDueDate],
                        'new_values' => ['due_date' => $newDueDate],
                    ]);
                }

                // Save ticket changes
                $ticket->save();

                // Handle tag changes if provided
                if ($request->has('tags')) {
                    $oldTags = $ticket->tags->pluck('id')->toArray();
                    $newTags = $request->input('tags', []);

                    // Sync tags
                    $ticket->tags()->sync($newTags);

                    // Find added and removed tags
                    $addedTags = array_diff($newTags, $oldTags);
                    $removedTags = array_diff($oldTags, $newTags);

                    // Log added tags
                    foreach ($addedTags as $tagId) {
                        $tag = TicketTag::find($tagId);
                        TicketActivityLog::create([
                            'ticket_id' => $ticket->id,
                            'user_id' => $user->id,
                            'activity_type' => TicketActivityLog::ACTIVITY_TAG_ADDED,
                            'description' => "Tag '{$tag->name}' added",
                            'new_values' => ['tag_id' => $tag->id, 'tag_name' => $tag->name],
                        ]);
                    }

                    // Log removed tags
                    foreach ($removedTags as $tagId) {
                        $tag = TicketTag::find($tagId);
                        TicketActivityLog::create([
                            'ticket_id' => $ticket->id,
                            'user_id' => $user->id,
                            'activity_type' => TicketActivityLog::ACTIVITY_TAG_REMOVED,
                            'description' => "Tag '{$tag->name}' removed",
                            'old_values' => ['tag_id' => $tag->id, 'tag_name' => $tag->name],
                        ]);
                    }
                }

                // If ticket was updated (not just tags), log general update
                $newValues = $ticket->only([
                    'subject',
                    'description',
                    'status',
                    'priority',
                    'assigned_to',
                    'due_date',
                ]);

                // Check if any main fields changed (excluding status, priority, assignment, due date which are logged separately)
                $mainFieldsChanged = array_diff_assoc(
                    array_intersect_key($newValues, array_flip(['subject', 'description'])),
                    array_intersect_key($oldValues, array_flip(['subject', 'description']))
                );

                if (!empty($mainFieldsChanged)) {
                    TicketActivityLog::create([
                        'ticket_id' => $ticket->id,
                        'user_id' => $user->id,
                        'activity_type' => TicketActivityLog::ACTIVITY_UPDATED,
                        'description' => "Ticket details updated",
                        'old_values' => array_intersect_key($oldValues, $mainFieldsChanged),
                        'new_values' => array_intersect_key($newValues, $mainFieldsChanged),
                    ]);
                }

                // Load relationships for the response
                $ticket->load([
                    'contact',
                    'assignedAgent',
                    'tags',
                    'conversation',
                    'channel',
                    'activityLogs' => function ($query) {
                        $query->orderBy('created_at', 'desc');
                    },
                    'activityLogs.user',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket updated successfully',
                    'data' => $ticket,
                ]);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->validator->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error updating ticket: ' . $e->getMessage(), [
                'exception' => $e,
                'ticket_id' => $id,
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a message to a ticket.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function addMessage(Request $request, Workspace $workspace, string $id): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'content' => 'required|string',
                'message_type' => 'sometimes|in:message,private_note',
                'is_private' => 'boolean',
                'attachments' => 'nullable|array',
                'attachments.*' => 'file|max:10240', // 10MB max file size
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $workspace = $user->currentWorkspace();
            // Find the ticket
            $ticket = TicketEntity::where('workspace_id', $workspace->id)
                ->where('id', $id)
                ->firstOrFail();

            // Begin transaction
            return DB::transaction(function () use ($request, $ticket, $user) {
                $messageType = $request->input('message_type', 'text');
                if ($messageType === 'text') {
                    $textMessage = new TicketTextMessage([
                        'content' => $request->input('content'),
                    ]);
                    $textMessage->save();
                }
                // Create the message
                $ticketMessage = new TicketMessage([
                    'ticket_id' => $ticket->id,
                    'content' => $request->input('content'),
                    'message_type' => $request->input('message_type', 'message'),
                    'is_private' => $request->boolean('is_private', false) || $request->input('message_type') === 'private_note',
                ]);

                $ticketMessage->sender()->associate($user);
                // $ticketMessage->messageable()->associate($textMessage);
                $ticketMessage->save();
                if (!$ticketMessage->is_private) {
                    $this->sendTicketEmailNotification(
                        $ticket,
                        $ticketMessage,
                        false // Set to false as this is not a new ticket
                    );
                }
                // Load the message with its relationships
                $ticketMessage->load([
                    'sender',
                    'messageable'
                ]);//attachments

                return response()->json([
                    'success' => true,
                    'message' => 'Message added successfully',
                    'data' => new \App\Http\Responses\TicketMessage($ticketMessage),
                ], 201);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->validator->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error adding message to ticket: ' . $e->getMessage(), [
                'exception' => $e,
                'ticket_id' => $id,
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add message',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a ticket from a conversation.
     *
     * @param Request $request
     * @param string $conversationId
     * @return JsonResponse
     */
    public function createFromConversation(Request $request, Workspace $workspace, string $conversationId): JsonResponse
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'subject' => 'required|string|max:255',
                'description' => 'nullable|string',
                'priority' => 'required|string|in:' . implode(',', [
                    TicketEntity::PRIORITY_LOW,
                    TicketEntity::PRIORITY_MEDIUM,
                    TicketEntity::PRIORITY_HIGH,
                    TicketEntity::PRIORITY_URGENT,
                ]),
                'status' => 'required|string|in:' . implode(',', [
                    TicketEntity::STATUS_OPEN,
                    TicketEntity::STATUS_IN_PROGRESS,
                    TicketEntity::STATUS_RESOLVED,
                    TicketEntity::STATUS_CLOSED,
                ]),
                'assigned_to' => 'nullable|string|exists:users,id',
                'send_notification' => 'required|boolean',
                'tags' => 'nullable|array',
                'tags.*' => 'exists:ticket_tags,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = Auth::user();
            $workspace = $user->currentWorkspace();

            // Find the conversation

            $conversation = Conversation::where('id', $conversationId)
                ->whereHas('channel', function ($query) use ($workspace) {
                    // $query->where('workspace_id', $workspace->id);
                })
                ->with(['contact', 'channel'])
                ->firstOrFail();

            // Check if a ticket already exists for this conversation
            $existingTicket = TicketEntity::where('conversation_id', $conversationId)->first();
            if ($existingTicket) {
                return response()->json([
                    'success' => false,
                    'message' => 'A ticket already exists for this conversation',
                    'data' => $existingTicket,
                ], 409); // Conflict
            }

            // Begin transaction
            return DB::transaction(function () use ($request, $conversation, $user, $workspace) {
                // Create the ticket
                $ticket = new TicketEntity([
                    'workspace_id' => $workspace->id,
                    'subject' => $request->input('subject'),
                    'description' => $request->input('description'),
                    'status' => TicketEntity::STATUS_OPEN,
                    'priority' => $request->input('priority'),
                    'source' => TicketEntity::SOURCE_CONVERSATION,
                    'contact_id' => $conversation->contact_id,
                    'channel_id' => $conversation->channel_id,
                    'conversation_id' => $conversation->id,
                    'assigned_to' => $request->input('assigned_to'),
                    'send_notification' => $request->input('send_notification', false),
                ]);

                $ticket->save();

                // Add tags if provided
                if ($request->has('tags')) {
                    $ticket->tags()->attach($request->input('tags'));
                }

                $ticketMessage = new TicketMessage([
                    'ticket_id' => $ticket->id,
                    'content' => $request->input('description'),
                    'message_type' => $request->input('message_type', 'message'),
                    'is_private' => $request->boolean('is_private', false) || $request->input('message_type') === 'private_note',
                ]);
                $ticketMessage->sender()->associate($user);
                $ticketMessage->save();
                $this->sendTicketEmailNotification(
                    $ticket,
                    $ticketMessage,
                    true // Set to false as this is not a new ticket
                );

                // Log the ticket creation
                TicketActivityLog::logTicketCreation($ticket, $user);

                // Update the conversation status to closed
                $conversation->update(attributes: ['status' => Conversation::STATUS_CLOSED]);

                // Load relationships for the response
                $ticket->load([
                    'contact',
                    'assignedAgent',
                    'tags',
                    'conversation',
                    'channel',
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket created from conversation successfully',
                    'data' => $ticket,
                ], 201);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Conversation not found',
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->validator->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Error creating ticket from conversation: ' . $e->getMessage(), [
                'exception' => $e,
                'conversation_id' => $conversationId,
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create ticket from conversation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a ticket.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $workspace = $user->currentWorkspace();

            // Find the ticket
            $ticket = TicketEntity::where('workspace_id', $workspace->id)
                ->where('id', $id)
                ->firstOrFail();

            // Begin transaction
            return DB::transaction(function () use ($ticket) {
                // Soft delete the ticket
                $ticket->delete();

                return response()->json([
                    'success' => true,
                    'message' => 'Ticket deleted successfully',
                ]);
            });
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        } catch (Exception $e) {
            Log::error('Error deleting ticket: ' . $e->getMessage(), [
                'exception' => $e,
                'ticket_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete ticket',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a combined timeline of messages and activity logs for a ticket.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function getTimeline(Workspace $workspace, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $workspace = $user->currentWorkspace();

            $ticket = TicketEntity::where('workspace_id', $workspace->id)
                ->where('id', $id)
                ->firstOrFail();

            // Get messages with their messageable content
            $messages = TicketMessage::where('ticket_id', $id)
                ->with([
                    'sender',
                    'messageable'  // Load the polymorphic relationship
                ])
                ->get();

            // Get activity logs
            $activityLogs = TicketActivityLog::where('ticket_id', $id)
                ->with('user')
                ->get();

            // Use the TicketTimeLine response class to create the timeline
            $timeline = \App\Http\Responses\TicketTimeLine::createTimeline($messages, $activityLogs);
            $ticket = new \App\Http\Responses\Ticket($ticket);
            // Return response using the BaseApiController's response method
            return $this->response(true, 'items', ['ticket' => $ticket, 'timeline' => $timeline]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket not found',
            ], 404);
        } catch (Exception $e) {
            Log::error('Error fetching ticket timeline: ' . $e->getMessage(), [
                'exception' => $e,
                'ticket_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch ticket timeline',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // 👇 Assign agent
    public function assignAgent(Request $request, Workspace $workspace, TicketEntity $ticket, User $user)
    {
        if (!$user->isInboxAgent()) {
            return $this->response(false, "User is not a valid inbox agent.", null, 403);
        }

        $assigned = $this->assignInboxAgentToTicket($user, $ticket);

        if (!$assigned) {
            return $this->response(false, "Agent is already assigned.", null, 409);
        }

        return $this->response(true, "Agent assigned successfully.");
    }

    public function removeAgent(Request $request, Workspace $workspace, $ticket,User $user)
    {
        if (!$user->isInboxAgent()) {
            return $this->response(false, "User is not a valid inbox agent.", null, 403);
        }
        $ticket = TicketEntity::where('id', $ticket)
            ->firstOrFail();
        $removed = $this->removeInboxAgentFromTicket($user, $ticket);

        if (!$removed) {
            return $this->response(false, "Agent not currently assigned.", null, 409);
        }

        return $this->response(true, "Agent removed successfully.");
    }
}