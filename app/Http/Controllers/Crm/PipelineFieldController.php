<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\PipelineField;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;

class PipelineFieldController extends BaseApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:api')];
    }
    public function index($pipeline_tabs)
    {
        $fields = PipelineField::where('pipeline_tab_id', $pipeline_tabs)->orderBy('position')->get();
        return $this->response(true, 'pipeline Field retrieved successfully', $fields);
    }


    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'pipeline_tab_id' => 'exists:pipeline_tabs,id',
            'position'    => 'nullable|integer',
            'name'            => 'required|string|max:255',
            'type'            => 'required|string|max:50',
            'options'         => 'nullable|array',
            'required'        => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data'    => $validator->errors()
            ], 400);
        }

        try {
            // Retrieve validated data
            $validatedData = $validator->validated();

            // Create the PipelineField
            $field = PipelineField::create([
                'id'             => (string) Str::uuid(),
                'pipeline_tab_id' => $validatedData['pipeline_tab_id'] ?? null,
                'name'           => $validatedData['name'],
                'type'           => $validatedData['type'],
                'options'        => $validatedData['options'] ?? [],
                'required'       => $validatedData['required'],
            ]);

            return $this->response(true, 'Field created successfully', $field, 201);
        } catch (\Exception $e) {
            return $this->response(false, 'Error creating field', ['error' => $e->getMessage()], 500);
        }
    }


    public function update(Request $request, PipelineField $field)
    {
        $validator = Validator::make($request->all(), [
            'pipeline_tab_id' => 'sometimes|exists:pipeline_tabs,id',
            'position'    => 'nullable|integer',
            'name'            => 'sometimes|string|max:255',
            'type'            => 'sometimes|string|max:50',
            'options'         => 'nullable|array',
            'required'        => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data'    => $validator->errors()
            ], 400);
        }

        try {
            $validatedData = $validator->validated();

            // Update the PipelineField
            $field->update($validatedData);

            return $this->response(true, 'Field updated successfully', $field, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating field', ['error' => $e->getMessage()], 500);
        }
    }


    public function destroy(PipelineField $field)
    {
        try {
            $field->delete();
            return $this->response(true, 'Field deleted successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error deleting field', ['error' => $e->getMessage()], 500);
        }
    }


    public function toggleEnable($pipeline_tabs, $field_id)
    {

        $field = PipelineField::where('pipeline_tab_id', $pipeline_tabs)->where('id', $field_id)->firstOrFail();
        $field->enabled = !$field->enabled;
        $field->save();

        return $this->response(true, 'pipeline_tabs Field  updated successfully', $field, 200);
    }
}
