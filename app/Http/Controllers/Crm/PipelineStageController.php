<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\PipelineStage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;

class PipelineStageController extends BaseApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:api')];
    }

    public function show($pipelineId)
    {
        $stages = PipelineStage::where('pipeline_id', $pipelineId)->orderBy('position')->get();

        /* if ($stages->isEmpty()) {

            return $this->response(false, 'No stages found for this pipeline.', [], 500);
        }*/

        return $this->response(true, 'Stage created successfully', $stages, 201);
    }


    public function store(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'pipeline_id' => 'required|exists:pipelines,id',
            'name'        => 'required|string|max:255',
            'position'    => 'required|integer',
            'color' => 'string|max:255'

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

            $stage = PipelineStage::create([
                'id'          => Str::uuid(),
                'pipeline_id' => $validatedData['pipeline_id'],
                'name'        => $validatedData['name'],
                'position'    => $validatedData['position'],
                'color'    => $validatedData['color'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stage created successfully',
                'data'    => $stage
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating stage',
                'data'    => ['error' => $e->getMessage()]
            ], 500);
        }
    }

    public function update(Request $request,  $id)
    {
        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'position' => 'sometimes|integer',
            'color' => 'sometimes|string|max:255'

        ]);
        $stage = PipelineStage::where('id', $id)->first();

        try {
            $stage->update($validated);
            return $this->response(true, 'Stage updated successfully', $stage, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating stage', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {

        $stage = PipelineStage::where('id', $id)->first();
        try {
            $stage->delete();
            return $this->response(true, 'Stage deleted successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error deleting stage', ['error' => $e->getMessage()], 500);
        }
    }
}
