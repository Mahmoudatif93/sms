<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\Task;
use App\Models\Deal;
use App\Models\ContactEntity;
use App\Models\User;
use App\Models\TaskFile;
use App\Models\TaskHistory;
use App\Models\TaskChecklist;
use App\Models\TaskChecklistItem;
use App\Models\TaskLink;
use App\Models\BoardTab;
use App\Models\BoardField;
use App\Models\TaskRepeat;
use App\Models\TaskReminder;
use App\Models\TaskWatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;
use Illuminate\Support\Str;
use Illuminate\Pagination\Paginator;
use Carbon\Carbon;

class TaskController extends BaseApiController
{

    protected $fileUploadService;

    public function __construct(
        FileUploadService $fileUploadService,

    ) {
        $this->fileUploadService = $fileUploadService;
    }
    /**
     * Get all tasks
     */


    public function index(Request $request)
    {
        $search  = $request->query('search');
        $perPage = $request->query('per_page', 15);
        $page    = $request->query('page', 1);

        Paginator::currentPageResolver(fn() => $page);

        $tasks = Task::with([
            'boardStage',
            'checklists.items',
            // 'files',
            'history.user:id,username',
            'parentTask',
            'links.linkedEntity',
            'tags',
            'repeatSettings',
            'reminders',
            'watchers',
            'observers'
        ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {

                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('priority', 'like', "%{$search}%")
                        ->orWhere('board_stage_id', $search)

                        ->orWhereJsonContains('custom_fields', $search)
                        ->orWhereHas('tags', function ($tagQuery) use ($search) {
                            $tagQuery->where('name', 'like', "%{$search}%");
                        })

                        ->orWhereHas('parentTask', function ($parentQuery) use ($search) {
                            $parentQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('position')
            ->paginate($perPage);

        return $this->response(true, 'Tasks retrieved successfully', [
            'data'       => $tasks->items(),
            'pagination' => [
                'total'        => $tasks->total(),
                'per_page'     => $tasks->perPage(),
                'current_page' => $tasks->currentPage(),
                'last_page'    => $tasks->lastPage(),
                'from'         => $tasks->firstItem(),
                'to'           => $tasks->lastItem(),
            ],
        ]);
    }

    /**
     * Store a new task
     */


    public function store(Request $request)
    {

        $linkedTypeMap = [
            'Task'    => Task::class,
            'Deal'    => Deal::class,
            'contacts' => ContactEntity::class,
        ];
        $validator = Validator::make(
            $request->all(),
            [
                'name'           => 'required|string|max:255',
                'board_id'       => 'required|uuid',
                'board_stage_id' => 'required|exists:board_stages,id',
                'position'       => 'nullable|integer',
                'priority'       => 'nullable|in:High,Normal,Low',
                'start_date'     => 'nullable|date',
                'due_date'       => 'nullable|date',
                //  'custom_fields'  => 'nullable|array',
                'description'    => 'nullable|string',
                'parent_task_id' => 'nullable|uuid|exists:tasks,id',

                /* ---------- Files ---------- */
                'files'   => 'nullable|array',
                'files.*' => 'file|max:2048',

                /* ---------- Checklists ---------- */
                'checklists'        => 'nullable|array',
                'checklists.*.name' => 'required|string|max:255',

                /* ---------- Tags ---------- */
                'tags'   => 'nullable|array',
                'tags.*' => 'uuid|exists:board_tags,id',

                /* ---------- Repeat ---------- */
                'repeat.is_recurring'     => 'boolean',
                'repeat.repeat_frequency' => 'nullable|in:daily,weekly,monthly,yearly',
                'repeat.repeat_interval'  => 'nullable|integer|min:1',
                'repeat.repeat_days'      => 'nullable|array',
                'repeat.repeat_days.*'    => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'repeat.repeat_until'     => 'nullable|date',

                /* ---------- Watchers ---------- */
                'watcher_ids'   => 'nullable|array',
                'watcher_ids.*' => 'exists:users,id',

                /* ---------- Links ---------- */
                'links' => 'nullable|array',

                'links.*.linked_type' => [
                    'required',
                    'string',
                    'in:Task,Deal,contacts',
                ],

                'links.*.linked_id' => [
                    'required',
                    'uuid',
                    function ($attribute, $value, $fail) use ($request, $linkedTypeMap) {
                        $index = explode('.', $attribute)[1];
                        $typeAlias = $request->input("links.$index.linked_type");

                        if (!isset($linkedTypeMap[$typeAlias])) {
                            $fail('Invalid linked_type.');
                            return;
                        }

                        $modelClass = $linkedTypeMap[$typeAlias];

                        if (!$modelClass::where('id', $value)->exists()) {
                            $fail("The related {$typeAlias} does not exist.");
                        }
                    },
                ],
            ]
        );




        if ($validator->fails()) {
            return $this->response(false, 'Validation failed', $validator->errors(), 422);
        }

        $validatedData = $validator->validated();

        $boardTabIds = BoardTab::where('board_id', $validatedData['board_id'])->where('name', 'general')->pluck('id');

        // Fetch pipeline fields under the 'general' tab
        $boardFields = BoardField::whereIn('board_tab_id', $boardTabIds)
            ->get();

        $requiredFields = $boardFields->where('required', true)->pluck('name')->toArray();
        $validFieldNames = $boardFields->pluck('name')->toArray();

        $customFields = $validatedData['custom_fields'] ?? [];

        // Ensure all required pipeline fields are present
        foreach ($requiredFields as $requiredField) {
            if (!array_key_exists($requiredField, $customFields)) {
                return $this->response(false, "Missing required field: {$requiredField}", [], 400);
            }
        }

        // Validate that only allowed pipeline fields are provided
        foreach ($customFields as $fieldName => $fieldValue) {
            if (!in_array($fieldName, $validFieldNames)) {
                return $this->response(false, "Invalid custom field: {$fieldName}", [], 400);
            }
        }

        try {
            $validatedData['custom_fields'] = json_encode($customFields); // Store as JSON
            $uuid = (string) Str::uuid();
            $validatedData['id'] = $uuid;
            $task = Task::create($validatedData);

            // Create repeat settings if provided
            if ($request->has('repeat.is_recurring') && $request->repeat['is_recurring']) {
                TaskRepeat::create(array_merge(
                    $request->repeat,
                    ['task_id' => $task->id]
                ));
            }

            // Add Watchers to the Task
            if ($request->has('watcher_ids')) {
                foreach ($request->watcher_ids as $userId) {
                    TaskWatcher::create([
                        'id' => Str::uuid(),
                        'task_id' => $task->id,
                        'user_id' => $userId
                    ]);
                }
            }

            // Attach tags if provided
            if ($request->has('tags')) {
                $task->tags()->sync($request->tags);
            }
            // Handle File Uploads
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $filePath = $this->fileUploadService->upload($file);

                    TaskFile::create([
                        'task_id'   => $task->id,
                        'file_path' => $filePath,
                        'size'      => $file->getSize()
                    ]);
                }
            }

            // Handle Checklists
            if (!empty($request->checklists)) {
                foreach ($request->checklists as $checklist) {
                    $checklistModel = TaskChecklist::create([
                        'task_id' => $task->id,
                        'name'    => $checklist['name']
                    ]);

                    if (!empty($checklist['items'])) {
                        foreach ($checklist['items'] as $item) {
                            TaskChecklistItem::create([
                                'checklist_id' => $checklistModel->id,
                                'name'         => $item['name'],
                                'is_completed' => $item['is_completed'] ?? false
                            ]);
                        }
                    }
                }
            }

            // Handle Links
            if (!empty($request->links)) {
                foreach ($request->links as $link) {
                    TaskLink::create([
                        'task_id'      => $task->id,
                        'linked_type'  => $link['linked_type'],
                        'linked_id'    => $link['linked_id'],
                    ]);
                }
            }



            // Log Task History
            TaskHistory::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'action'  => "Task created: {$task->name}",
            ]);

            return $this->response(true, 'Task created successfully', $task, 201);
        } catch (\Exception $e) {
            return $this->response(false, 'Error creating task', ['error' => $e->getMessage()], 500);
        }
    }


    /**
     * Show a single task
     */
    public function show(Task $task)
    {
        try {
            $task->load([
                'boardStage',
                'checklists.items',
                //   'files',
                'history.user:id,username',
                'parentTask',
                'links.linkedEntity',
                'tags',
                'repeatSettings',
                'watchers',
                'observers'
            ]);
            return $this->response(true, 'Task retrieved successfully', $task, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error retrieving task', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a task
     */
    public function update(Request $request, Task $task)
    {
        $linkedTypeMap = [
            'Task'    => Task::class,
            'Deal'    => Deal::class,
            'contacts' => ContactEntity::class,
        ];
        $validator = Validator::make(
            $request->all(),
            [
                'name'           => 'sometimes|string|max:255',
                'board_id'       => 'sometimes|uuid',
                'board_stage_id' => 'sometimes|exists:board_stages,id',
                'position'       => 'nullable|integer',
                'priority'       => 'nullable|in:High,Normal,Low',
                'start_date'     => 'nullable|date',
                'due_date'       => 'nullable|date',
                //  'custom_fields'  => 'nullable|array',
                'description'    => 'nullable|string',
                'parent_task_id' => 'nullable|uuid|exists:tasks,id',

                /* ---------- Files ---------- */
                'files'   => 'nullable|array',
                'files.*' => 'file|max:2048',

                /* ---------- Checklists ---------- */
                'checklists'        => 'nullable|array',
                'checklists.*.name' => 'sometimes|string|max:255',

                /* ---------- Tags ---------- */
                'tags'   => 'nullable|array',
                'tags.*' => 'uuid|exists:board_tags,id',

                /* ---------- Repeat ---------- */
                'repeat.is_recurring'     => 'boolean',
                'repeat.repeat_frequency' => 'nullable|in:daily,weekly,monthly,yearly',
                'repeat.repeat_interval'  => 'nullable|integer|min:1',
                'repeat.repeat_days'      => 'nullable|array',
                'repeat.repeat_days.*'    => 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'repeat.repeat_until'     => 'nullable|date',

                /* ---------- Watchers ---------- */
                'watcher_ids'   => 'nullable|array',
                'watcher_ids.*' => 'exists:users,id',

                /* ---------- Links ---------- */
                'links' => 'nullable|array',


                'links.*.linked_type' => [
                    'sometimes',
                    'string',
                    'in:Task,Deal,contacts',
                ],

                'links.*.linked_id' => [
                    'sometimes',
                    'uuid',
                    function ($attribute, $value, $fail) use ($request, $linkedTypeMap) {
                        $index = explode('.', $attribute)[1];
                        $typeAlias = $request->input("links.$index.linked_type");

                        if (!isset($linkedTypeMap[$typeAlias])) {
                            $fail('Invalid linked_type.');
                            return;
                        }

                        $modelClass = $linkedTypeMap[$typeAlias];

                        if (!$modelClass::where('id', $value)->exists()) {
                            $fail("The related {$typeAlias} does not exist.");
                        }
                    },
                ],
            ]
        );

        if ($validator->fails()) {
            return $this->response(false, 'Validation failed', $validator->errors(), 422);
        }

        $validatedData = $validator->validated();

        // Fetch board fields for validation
        if (isset($validatedData['board_id'])) {
            $boardTabIds = BoardTab::where('board_id', $validatedData['board_id'])->where('name', 'general')->pluck('id');

            $boardFields = BoardField::whereIn('board_tab_id', $boardTabIds)->get();
            $requiredFields = $boardFields->where('required', true)->pluck('name')->toArray();
            $validFieldNames = $boardFields->pluck('name')->toArray();

            $customFields = $validatedData['custom_fields'] ?? [];

            // Ensure all required pipeline fields are present
            foreach ($requiredFields as $requiredField) {
                if (!array_key_exists($requiredField, $customFields)) {
                    return $this->response(false, "Missing required field: {$requiredField}", [], 400);
                }
            }

            // Validate that only allowed pipeline fields are provided
            foreach ($customFields as $fieldName => $fieldValue) {
                if (!in_array($fieldName, $validFieldNames)) {
                    return $this->response(false, "Invalid custom field: {$fieldName}", [], 400);
                }
            }

            $validatedData['custom_fields'] = json_encode($customFields); // Store as JSON
        }

        try {
            // Update the task
            $task->update($validatedData);


            if ($request->has('repeat.is_recurring')) {
                $repeatData = $request->repeat;
                if ($repeatData['is_recurring']) {
                    $task->repeatSettings()->updateOrCreate(['task_id' => $task->id], $repeatData);
                } else {
                    $task->repeatSettings()->delete();
                }
            }

            if ($request->has('watchers')) {
                // Remove old watchers and insert new ones
                TaskWatcher::where('task_id', $task->id)->delete();
                foreach ($request->watchers as $userId) {
                    TaskWatcher::create([
                        'task_id' => $task->id,
                        'user_id' => $userId
                    ]);
                }
            }


            // Update tags if provided
            if ($request->has('tags')) {
                $task->tags()->sync($request->tags);
            }

            if ($request->hasFile('files')) {

                // لو عايز تمسح الملفات القديمة
                if ($request->boolean('replace_files')) {
                    // حذف الملفات من الداتا بيز
                    TaskFile::where('task_id', $task->id)->delete();
                }

                foreach ($request->file('files') as $file) {
                    $filePath = $this->fileUploadService->upload($file);
                    TaskFile::create([
                        'task_id'   => $task->id,
                        'file_path' => $filePath,
                        'size'      => $file->getSize(),
                    ]);
                }
            }


            // Handle Checklists (Sync new checklists)
            if (!empty($request->checklists)) {
                TaskChecklist::where('task_id', $task->id)->delete(); // Remove old checklists

                foreach ($request->checklists as $checklist) {
                    $checklistModel = TaskChecklist::create([
                        'task_id' => $task->id,
                        'name'    => $checklist['name']
                    ]);

                    if (!empty($checklist['items'])) {
                        foreach ($checklist['items'] as $item) {
                            TaskChecklistItem::create([
                                'checklist_id' => $checklistModel->id,
                                'name'         => $item['name'],
                                'is_completed' => $item['is_completed'] ?? false
                            ]);
                        }
                    }
                }
            }

            // Handle Links (Remove old & add new)
            if (!empty($request->links)) {
                TaskLink::where('task_id', $task->id)->delete();

                foreach ($request->links as $link) {
                    TaskLink::create([
                        'task_id'     => $task->id,
                        'linked_id'   => $link['linked_id'],
                        'linked_type' => $link['linked_type']
                    ]);
                }
            }


            // Log Task Update
            TaskHistory::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'action'  => "Task updated: {$task->name}",
            ]);

            return $this->response(true, 'Task updated successfully', $task, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating task', ['error' => $e->getMessage()], 500);
        }
    }



    /**
     * Delete a task
     */
    public function destroy(Task $task)
    {
        try {
            // Delete related files
            foreach ($task->files as $file) {
                // Assuming fileUploadService has a delete method
                $this->fileUploadService->deleteFileOss($file->file_path);
                $file->delete();
            }

            // Delete related checklists and their items
            foreach ($task->checklists as $checklist) {
                $checklist->items()->delete();
                $checklist->delete();
            }

            // Delete related links
            $task->links()->delete();

            // Log Task Deletion
            TaskHistory::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'action'  => "Task deleted: {$task->name}",
            ]);

            // Delete the task itself
            $task->delete();

            return $this->response(true, 'Task deleted successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error deleting task', ['error' => $e->getMessage()], 500);
        }
    }


    public function changeBoardStage(Request $request, Task $task)
    {
        $request->validate([
            'board_stage_id' => 'required|exists:board_stages,id'
        ]);

        $task->update(['board_stage_id' => $request->board_stage_id]);

        return $this->response(true, 'Task board stage updated successfully', $task, 201);
    }


    public function storeHistory(Request $request, $taskId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|max:500',
            'file'   => 'nullable|file|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        try {
            $task = Task::findOrFail($taskId);
            $validatedData = $validator->validated();


            // Handle File Upload (if provided)
            $filePath = $request->hasFile('file')
                ? app(FileUploadService::class)->upload($request->file('file'))
                : null;


            $history = TaskHistory::create([
                'task_id'  => $task->id,
                'user_id'  => auth()->id(),
                'action'   => $validatedData['action'],
                'file_path' => $filePath,
            ]);

            return $this->response(true, 'History added successfully', $history, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error adding history', ['error' => $e->getMessage()], 500);
        }
    }

    public function changeStatus(Request $request,  Task $task)
    {
        $validator = Validator::make($request->all(), [
            'board_stage_id' => 'required|exists:board_stages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $task->update(['board_stage_id' => $validator->validated()['board_stage_id']]);

            return $this->response(true, 'task status updated successfully', $task, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating task status', ['error' => $e->getMessage()], 500);
        }
    }


    public function completedTasks(Request $request)
    {

        $perPage = $request->query('per_page', 15);
        $page    = $request->query('page', 1);

        Paginator::currentPageResolver(fn() => $page);
        $tasks = Task::whereHas('boardStage', function ($query) {
            $query->where('name', 'closed');
        })->with(['boardStage', 'checklists.items', 'files', 'history.user:id,username', 'parentTask', 'links.linkedEntity'])
            ->orderByDesc('created_at')
            ->paginate($perPage);


        return $this->response(true, 'Closed tasks retrieved successfully', [
            'data'       => $tasks->items(),
            'pagination' => [
                'total'        => $tasks->total(),
                'per_page'     => $tasks->perPage(),
                'current_page' => $tasks->currentPage(),
                'last_page'    => $tasks->lastPage(),
                'from'         => $tasks->firstItem(),
                'to'           => $tasks->lastItem(),
            ],
        ]);
    }

    public function generalFields($board_id)
    {
        $fields = BoardField::where('name', 'General')->whereHas('tab', function ($query) use ($board_id) {
            $query->where('board_id', $board_id);
        })->get();

        return $this->response(true, 'All fields in "General" tab retrieved successfully', $fields, 200);
    }

    public function nonGeneralFields($board_id)
    {
        $fields = BoardField::where('name', '!=', 'General')->whereHas('tab', function ($query) use ($board_id) {
            $query->where('board_id', $board_id);
        })->get();

        return $this->response(true, 'All fields in non-General tabs retrieved successfully', $fields, 200);
    }


    public function addReminder(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reminder_date' => 'required|date_format:Y-m-d H:i:s',
            'note' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', $validator->errors(), 500);
        }

        try {
            $task = Task::findOrFail($id);
            $validatedData = $validator->validated();
            $validatedData['task_id'] = $task->id;

            $reminder = TaskReminder::create($validatedData);

            return $this->response(true, 'Reminder added successfully', $reminder, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error adding reminder', ['error' => $e->getMessage()], 500);
        }
    }

    public function getReminders($id)
    {
        try {
            $task = Task::findOrFail($id);
            $reminders = $task->reminders()->orderBy('reminder_date', 'asc')->get();

            return $this->response(true, 'Reminder retrieved successfully', $reminders, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error retrieving reminders', ['error' => $e->getMessage()], 500);
        }
    }


    public function addObservers(Request $request, Task $task)
    {
        $validator = Validator::make($request->all(), [
            'observers'   => 'required|array',
            'observers.*' => 'exists:user,id',
        ]);
        $now = Carbon::now();

        if ($validator->fails()) {
            return $this->response(false, 'Validation failed', $validator->errors(), 422);
        }

        foreach ($request->observers as $observerId) {
            $task->observers()->updateOrInsert(
                ['task_id' => $task->id, 'user_id' => $observerId],
                [
                    'id'         => Str::uuid(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );

            // Log history
            TaskHistory::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'action'  => "Observer added: " . User::find($observerId)->name,
            ]);
        }

        return $this->response(true, 'Observers added successfully', $task->observers, 200);
    }

    public function removeObservers(Request $request, Task $task)
    {
        $validator = Validator::make($request->all(), [
            'observers'   => 'required|array',
            'observers.*' => 'exists:user,id',
        ]);


        if ($validator->fails()) {
            return $this->response(false, 'Validation failed', $validator->errors(), 422);
        }

        foreach ($request->observers as $observerId) {
            $task->observers()->where('user_id', $observerId)->delete();

            // Log history
            TaskHistory::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'action'  => "Observer removed: " . User::find($observerId)->name,
            ]);
        }

        return $this->response(true, 'Observers removed successfully', [], 200);
    }


    public function getBoardTasks(Request $request, string $boardId)
    {
        $search  = $request->query('search');
        $perPage = (int) $request->query('per_page', 15);
        $page    = (int) $request->query('page', 1);

        Paginator::currentPageResolver(fn() => $page);

        $tasks = Task::with([
            'boardStage',
            'checklists.items',
            'files',
            'history.user:id,username',
            'parentTask',
            'links.linkedEntity',
            'tags',
            'repeatSettings',
            'reminders',
            'watchers',
            'observers',
        ])
            ->where('board_id', $boardId)
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('priority', 'like', "%{$search}%")
                        ->orWhere('board_stage_id', $search)
                        ->orWhereJsonContains('custom_fields', $search)
                        ->orWhereHas('tags', function ($tagQuery) use ($search) {
                            $tagQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('parentTask', function ($parentQuery) use ($search) {
                            $parentQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('position')
            ->paginate($perPage);

        return $this->response(true, 'Board tasks retrieved successfully', [
            'data'       => $tasks->items(),
            'pagination' => [
                'total'        => $tasks->total(),
                'per_page'     => $tasks->perPage(),
                'current_page' => $tasks->currentPage(),
                'last_page'    => $tasks->lastPage(),
                'from'         => $tasks->firstItem(),
                'to'           => $tasks->lastItem(),
            ],
        ]);
    }



    public function deleteFile(Task $task, TaskFile $file)
    {
        try {
            // تأكد إن الملف تابع للتاسك
            if ($file->task_id !== $task->id) {
                return $this->response(false, 'File does not belong to this task', null, 403);
            }

            // حذف الملف من التخزين (المسار الحقيقي)
            $filePath = $file->getRawOriginal('file_path');

            if ($filePath) {
                $this->fileUploadService->deleteFileOss($filePath);
            }

            // حذف الريكورد من DB
            $file->delete();

            // Log history
            TaskHistory::create([
                'task_id' => $task->id,
                'user_id' => auth()->id(),
                'action'  => 'Task file deleted',
            ]);

            return $this->response(true, 'File deleted successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error deleting file', [
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
