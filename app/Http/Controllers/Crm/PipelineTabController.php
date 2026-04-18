<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\PipelineTab;
use App\Models\Pipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;

class PipelineTabController extends BaseApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:api')];
    }


    public function index($pipelineId)
    {
        $tabs = PipelineTab::where('pipeline_id', $pipelineId)->orderBy('position')->get();
        return $this->response(true, 'Tabs retrieved successfully', $tabs);
    }

    public function store(Request $request, $pipelineId)
    {
        $validator = Validator::make($request->all(), [
            'pipeline_id' => 'required|exists:pipelines,id',
            'name'        => 'required|string|max:255',
            'enabled' => 'boolean',
            'position'    => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data'    => $validator->errors(),
            ], 400);
        }

        $validated = $validator->validated();

        try {
            $tab = PipelineTab::create([
                'id'          => Str::uuid(),
                'pipeline_id' => $validated['pipeline_id'],
                'name'        => $validated['name'],
                'enabled' => $request->enabled ?? true,
                'position'    => $validated['position'],
            ]);


            return $this->response(true, 'Tab created successfully', $tab, 200);
        } catch (\Exception $e) {

            return $this->response(false, 'Error creating tab', ['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing pipeline tab.
     */
    public function update(Request $request, $pipelineId, $id)
    {
        $validator = Validator::make($request->all(), [
            'name'        => 'sometimes|required|string|max:255',
            'enabled' => 'sometimes|boolean',
            'position'    => 'sometimes|required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'data'    => $validator->errors(),
            ], 400);
        }

        try {
            $tab = PipelineTab::findOrFail($id);
            $tab->update($validator->validated());
            return $this->response(true, 'Tab updated successfully', $tab, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating tab', ['error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Pipeline $pipeline, PipelineTab $tab)
    {
        try {
            $tab->delete();
            return $this->response(true, 'Tab deleted successfully', null, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error deleting tab', ['error' => $e->getMessage()], 500);
        }
    }

    public function toggleEnable(Pipeline $pipeline, PipelineTab $tab)
    {
        $tab->enabled = !$tab->enabled;
        $tab->save();

        return $this->response(true, 'Tab status updated successfully', $tab, 200);
    }
}
