<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\Deal;
use App\Models\PipelineField;
use App\Models\PipelineTab;
use App\Models\DealFile;
use App\Models\DealHistory;
use App\Models\DealReminder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;
use App\Services\FileUploadService;

class DealController extends BaseApiController implements HasMiddleware
{

    public static function middleware(): array
    {
        return [new Middleware('auth:api')];
    }
    protected $fileUploadService;

    public function __construct(
        FileUploadService $fileUploadService,

    ) {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * List all deals with pagination and search.
     */
    public function index(Request $request)
    {
        $search  = $request->query('search', null);
        $perPage = $request->query('per_page', 15);
        $page    = $request->query('page', 1);

        Paginator::currentPageResolver(fn() => $page);

        $deals = Deal::with([
            'contacts:id',
            'products',
            // 'files',
            'pipeline',
            'stage',
            'history.user:id,username',
            'reminders'
        ])
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                        ->orWhere('deal_type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('pipeline_stage_id', $search);
                });
            })
            ->orderBy('position')
            ->paginate($perPage);



        return $this->response(true, 'Deals retrieved successfully', [
            'data'       => $deals->toArray()['data'],
            'pagination' => [
                'total'        => $deals->total(),
                'per_page'     => $deals->perPage(),
                'current_page' => $deals->currentPage(),
                'last_page'    => $deals->lastPage(),
                'from'         => $deals->firstItem(),
                'to'           => $deals->lastItem(),
            ],
        ]);
    }
    public function show(Deal $deal)
    {
        try {
            $deal->load([
                'contacts',
                'products',
                // 'files',
                'history.user:id,username'
            ]); // Load related data

            return $this->response(true, 'Deal retrieved successfully', $deal, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error retrieving deal', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a new deal.
     */
    /* public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'         => 'required|string|max:255',
            'status'      => 'nullable|string|max:255', // Ensure status exists in PipelineStage
            'position'    => 'nullable|integer',
            'pipeline_stage_id' => 'required|exists:pipeline_stages,id',
            'due_date'      => 'required|date_format:Y-m-d H:i:s',
            'deal_type'     => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
            'custom_fields.*.field_name'  => 'required|string',
            'custom_fields.*.field_value' => 'nullable',
            'workspace_id'  => 'nullable|uuid|exists:workspaces,id',
            'contacts'      => 'nullable|array',
            'contacts.*'    => 'uuid|exists:contacts,id',
            'products'      => 'nullable|array',
            'products.*'    => 'exists:products,id',
            'files'         => 'nullable|array',
            'files.*'       => 'file|max:2048',
            'pipeline_id'   => 'required|uuid|exists:pipelines,id',
            'amount'        => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        $validatedData = $validator->validated();

        // Find the related pipeline tabs
        $pipelineTabIds = PipelineTab::where('pipeline_id', $validatedData['pipeline_id'])->where('name', 'general')->pluck('id');

        // Fetch pipeline fields under the 'general' tab
        $pipelineFields = PipelineField::whereIn('pipeline_tab_id', $pipelineTabIds)
            ->get();

        $requiredFields = $pipelineFields->where('required', true)->pluck('name')->toArray();
        $validFieldNames = $pipelineFields->pluck('name')->toArray();

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
            $uuid = (string) Str::uuid();
            $validatedData['id'] = $uuid;
            $validatedData['custom_fields'] = json_encode($customFields); // Store as JSON

            // Create the deal
            $deal = Deal::create($validatedData);

            // Attach contacts
            if (!empty($validatedData['contacts'])) {
                $deal->contacts()->sync($validatedData['contacts']);
            }

            // Attach products
            if (!empty($validatedData['products'])) {
                $deal->products()->sync($validatedData['products']);
            }

            // Handle File Uploads
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $filePath = $this->fileUploadService->upload($file);
                    DealFile::create([
                        'deal_id'   => $deal->id,
                        'file_path' => $filePath
                    ]);
                }
            }

            $history = DealHistory::create([
                'deal_id'  => $deal->id,
                'user_id'  => auth()->id(),
                'action'   => "Deal created  ID" . $deal->id,
            ]);
            return $this->response(true, 'Deal created successfully', $deal, 201);
        } catch (\Exception $e) {
            return $this->response(false, 'Error creating deal', ['error' => $e->getMessage()], 500);
        }
    }*/

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'              => 'required|string|max:255',
            'status'             => 'nullable|string|max:255',
            'position'           => 'nullable|integer',
            'pipeline_stage_id'  => 'required|exists:pipeline_stages,id',
            'due_date'           => 'required|date_format:Y-m-d H:i:s',
            'deal_type'          => 'nullable|string|max:255',
            // 'custom_fields'      => 'nullable|array', // الآن يقبل associative array مباشرة
            'workspace_id'       => 'nullable|uuid|exists:workspaces,id',
            'contacts'           => 'nullable|array',
            'contacts.*'         => 'uuid|exists:contacts,id',
            'products'           => 'nullable|array',
            'products.*'         => 'exists:products,id',
            'files'              => 'nullable|array',
            'files.*'            => 'file|max:2048',
            'pipeline_id'        => 'required|uuid|exists:pipelines,id',
            'amount'             => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        $validatedData = $validator->validated();

        // لو فيه custom_fields، حولها مباشرة إلى JSON
        $validatedData['custom_fields'] = isset($validatedData['custom_fields'])
            ? json_encode($validatedData['custom_fields'])
            : null;

        try {
            $uuid = (string) Str::uuid();
            $validatedData['id'] = $uuid;

            // Create the deal
            $deal = Deal::create($validatedData);

            // Attach contacts
            if (!empty($validatedData['contacts'])) {
                $deal->contacts()->sync($validatedData['contacts']);
            }

            // Attach products
            if (!empty($validatedData['products'])) {
                $deal->products()->sync($validatedData['products']);
            }

            // Handle File Uploads
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $filePath = $this->fileUploadService->upload($file);
                    DealFile::create([
                        'deal_id'   => $deal->id,
                        'file_path' => $filePath,
                        'size'      => $file->getSize()
                    ]);
                }
            }

            DealHistory::create([
                'deal_id'  => $deal->id,
                'user_id'  => auth()->id(),
                'action'   => "Deal created ID " . $deal->id,
            ]);

            return $this->response(true, 'Deal created successfully', $deal, 201);
        } catch (\Exception $e) {
            return $this->response(false, 'Error creating deal', ['error' => $e->getMessage()], 500);
        }
    }




    /**
     * Update an existing deal.
     */

    /*public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title'         => 'sometimes|string|max:255',
            //'status'      => 'required|exists:pipeline_stages,name', // Ensure status exists as a name in PipelineStage
            'status'     => 'nullable|string|max:255',
            'position'    => 'nullable|integer',
            'pipeline_stage_id'      => 'required|exists:pipeline_stages,id',
            'due_date'      => 'sometimes|date_format:Y-m-d H:i:s',
            'deal_type'     => 'nullable|string|max:255',
            'custom_fields'      => 'nullable|array',
            'workspace_id'  => 'nullable|uuid|exists:workspaces,id',
            'contacts'      => 'nullable|array',
            'contacts.*'    => 'uuid|exists:contacts,id',
            'products'      => 'nullable|array',
            'products.*'    => 'exists:products,id',
            'files'         => 'nullable|array',
            'files.*'       => 'file|max:2048',
            'pipeline_id'   => 'sometimes|uuid|exists:pipelines,id',
            'amount'        => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        try {
            $deal = Deal::findOrFail($id);
            $validatedData = $validator->validated();

            // If pipeline_id is provided, fetch its pipeline fields
            if (isset($validatedData['pipeline_id'])) {
                // Get pipeline tab IDs linked to the pipeline
                $pipelineTabIds = PipelineTab::where('pipeline_id', $validatedData['pipeline_id'])->where('name', 'general')->pluck('id');

                // Get pipeline fields in 'general' tab
                $pipelineFields = PipelineField::whereIn('pipeline_tab_id', $pipelineTabIds)

                    ->get();

                $requiredFields = $pipelineFields->where('required', true)->pluck('name')->toArray();
                $validFieldNames = $pipelineFields->pluck('name')->toArray();

                $customFields = $validatedData['custom_fields'] ?? json_decode($deal->custom_fields, true) ?? [];

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

            // Update the deal
            $deal->update($validatedData);

            // Sync contacts if provided
            if (isset($validatedData['contacts'])) {
                $deal->contacts()->sync($validatedData['contacts']);
            }

            // Sync products if provided
            if (isset($validatedData['products'])) {
                $deal->products()->sync($validatedData['products']);
            }
            // Handle File Uploads
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $filePath = app(FileUploadService::class)->upload($file);

                    // Store file details in the database
                    DealFile::create([
                        'deal_id'   => $deal->id,
                        'file_path' => $filePath
                    ]);
                }
            }

            return $this->response(true, 'Deal updated successfully', $deal, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating deal', ['error' => $e->getMessage()], 500);
        }
    }*/


    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'title'              => 'sometimes|string|max:255',
            'status'             => 'nullable|string|max:255',
            'position'           => 'nullable|integer',
            'pipeline_stage_id'  => 'sometimes|exists:pipeline_stages,id',
            'due_date'           => 'sometimes|date_format:Y-m-d H:i:s',
            'deal_type'          => 'nullable|string|max:255',
            //   'custom_fields'      => 'nullable|array',
            'workspace_id'       => 'nullable|uuid|exists:workspaces,id',
            'contacts'           => 'nullable|array',
            'contacts.*'         => 'uuid|exists:contacts,id',
            'products'           => 'nullable|array',
            'products.*'         => 'exists:products,id',
            'files'              => 'nullable|array',
            'files.*'            => 'file|max:2048',
            'pipeline_id'        => 'sometimes|uuid|exists:pipelines,id',
            'amount'             => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        try {
            $deal = Deal::findOrFail($id);
            $validatedData = $validator->validated();


            if (array_key_exists('custom_fields', $validatedData)) {
                $validatedData['custom_fields'] = json_encode($validatedData['custom_fields']);
            }

            /**
             *  Update deal main data
             */
            $deal->update($validatedData);

            /**
             *  Sync contacts
             */
            if (isset($validatedData['contacts'])) {
                $deal->contacts()->sync($validatedData['contacts']);
            }

            /**
             *  Sync products
             */
            if (isset($validatedData['products'])) {
                $deal->products()->sync($validatedData['products']);
            }



            if ($request->hasFile('files')) {

                // لو عايز تمسح الملفات القديمة
                if ($request->boolean('replace_files')) {
                    // حذف الملفات من الداتا بيز
                    DealFile::where('task_id', $deal->id)->delete();
                }

                foreach ($request->file('files') as $file) {
                    $filePath = $this->fileUploadService->upload($file);

                    DealFile::create([
                        'task_id'   => $deal->id,
                        'file_path' => $filePath,
                        'size'      => $file->getSize(),
                    ]);
                }
            }

            DealHistory::create([
                'deal_id' => $deal->id,
                'user_id' => auth()->id(),
                'action'  => 'Deal updated',
            ]);

            return $this->response(true, 'Deal updated successfully', $deal, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating deal', [
                'error' => $e->getMessage()
            ], 500);
        }
    }





    /**
     * Delete a deal.
     */
    public function destroy(Deal $deal)
    {
        try {
            $deal->delete();
            return $this->response(true, 'Deal deleted successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error deleting deal', ['error' => $e->getMessage()], 500);
        }
    }



    public function changeStatus(Request $request, Deal $deal)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|exists:pipeline_stages,name',

        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data' => $validator->errors()
            ], 400);
        }

        try {
            $deal->update(['status' => $validator->validated()['status']]);

            return $this->response(true, 'Deal status updated successfully', $deal, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating deal status', ['error' => $e->getMessage()], 500);
        }
    }


    public function closedDeals(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $page    = $request->query('page', 1);

        Paginator::currentPageResolver(fn() => $page);

        $deals = Deal::with(['contacts', 'products', 'files'])
            ->where('status', 'closed') // Fetch only closed deals
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return $this->response(true, 'Closed deals retrieved successfully', [
            'data'       => $deals->items(),
            'pagination' => [
                'total'        => $deals->total(),
                'per_page'     => $deals->perPage(),
                'current_page' => $deals->currentPage(),
                'last_page'    => $deals->lastPage(),
                'from'         => $deals->firstItem(),
                'to'           => $deals->lastItem(),
            ],
        ]);
    }

    public function generalFields($pipeline_id)
    {
        $fields = PipelineField::where('name', 'General')->whereHas('tab', function ($query) use ($pipeline_id) {
            $query->where('pipeline_id', $pipeline_id);
        })->get();
        return $this->response(true, 'All fields in "General" tab retrieved successfully', $fields, 200);
    }

    public function nonGeneralFields($pipeline_id)
    {

        $fields = PipelineField::where('name', '!=', 'General')->whereHas('tab', function ($query) use ($pipeline_id) {
            $query->where('pipeline_id', $pipeline_id);
        })->get();
        return $this->response(true, 'All fields in non-General tabs retrieved successfully', $fields, 200);
    }

    public function storeHistory(Request $request, $dealId)
    {
        $validator = Validator::make($request->all(), [
            'action' => 'required|string|max:500',
            'file'   => 'nullable|file|max:2048',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        try {
            $deal = Deal::findOrFail($dealId);
            $validatedData = $validator->validated();


            // Handle File Upload (if provided)
            $filePath = $request->hasFile('file')
                ? app(FileUploadService::class)->upload($request->file('file'))
                : null;


            $history = DealHistory::create([
                'deal_id'  => $deal->id,
                'user_id'  => auth()->id(),
                'action'   => $validatedData['action'],
                'file_path' => $filePath,
            ]);

            return $this->response(true, 'History added successfully', $history, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error adding history', ['error' => $e->getMessage()], 500);
        }
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
            $deal = Deal::findOrFail($id);
            $validatedData = $validator->validated();
            $validatedData['deal_id'] = $deal->id;

            $reminder = DealReminder::create($validatedData);

            return $this->response(true, 'Reminder added successfully', $reminder, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error adding reminder', ['error' => $e->getMessage()], 500);
        }
    }

    public function getReminders($id)
    {
        try {
            $deal = Deal::findOrFail($id);
            $reminders = $deal->reminders()->orderBy('reminder_date', 'asc')->get();

            return $this->response(true, 'Reminder retrieved successfully', $reminders, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error retrieving reminders', ['error' => $e->getMessage()], 500);
        }
    }


    public function deleteFile(Deal $deal, DealFile $file)
    {
        try {
            // Ensure file belongs to deal
            if ($file->deal_id !== $deal->id) {
                return $this->response(
                    false,
                    'File does not belong to this deal',
                    null,
                    403
                );
            }

            // Delete from OSS + local
            $this->fileUploadService->deleteFileOss($file->file_path);

            // Delete from database
            $file->delete();

            // Log history
            DealHistory::create([
                'deal_id' => $deal->id,
                'user_id' => auth()->id(),
                'action'  => 'Deal file deleted',
            ]);

            return $this->response(
                true,
                'File deleted successfully',
                null,
                200
            );
        } catch (\Exception $e) {
            return $this->response(
                false,
                'Error deleting file',
                ['error' => $e->getMessage()],
                500
            );
        }
    }
}
