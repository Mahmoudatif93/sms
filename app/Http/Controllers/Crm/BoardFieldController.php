<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\BoardField;
use App\Models\BoardTab;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Validator;

class BoardFieldController extends BaseApiController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:api')];
    }

    public function index($board_tab_id)
    {
        $fields = BoardField::where('board_tab_id', $board_tab_id)->get();
        return $this->response(true, 'Board Field retrieved successfully', $fields);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'board_tab_id' => 'exists:board_tabs,id',
            'name'         => 'required|string|max:255',
            'type'         => 'required|string|max:50',
            'options'      => 'nullable|array',
            'required'     => 'required|boolean',
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

            $field = BoardField::create([
                'id'          => (string) Str::uuid(),
                'board_tab_id' => $validatedData['board_tab_id'] ?? null,
                'name'        => $validatedData['name'],
                'type'        => $validatedData['type'],
                'options'     => $validatedData['options'] ?? [],
                'required'    => $validatedData['required'],
            ]);

            return $this->response(true, 'Board Field created successfully', $field, 201);
        } catch (\Exception $e) {
            return $this->response(false, 'Error creating field', ['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $board_tab_id, $field_id)
    {
        $validator = Validator::make($request->all(), [
            'name'         => 'sometimes|string|max:255',
            'type'         => 'sometimes|string|max:50',
            'options'      => 'nullable|array',
            'required'     => 'sometimes|boolean',
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
            $field = BoardField::where('board_tab_id', $board_tab_id)->where('id', $field_id)->firstOrFail();

            $field->update($validatedData);

            return $this->response(true, 'Board Field updated successfully', $field, 200);
        } catch (\Exception $e) {
            return $this->response(false, 'Error updating field', ['error' => $e->getMessage()], 500);
        }
    }



    public function destroy($board_tab_id, $field_id)
    {
        $field = BoardField::where('board_tab_id', $board_tab_id)->where('id', $field_id)->firstOrFail();
        $field->delete();

        return $this->response(true, 'Board Field deleted successfully', [], 200);
    }
    public function toggleEnable($board_tab_id, $field_id)
    {

        $field = BoardField::where('board_tab_id', $board_tab_id)->where('id', $field_id)->firstOrFail();
        $field->enabled = !$field->enabled;
        $field->save();

        return $this->response(true, 'Board Field  updated successfully', $field, 200);
    }
}
