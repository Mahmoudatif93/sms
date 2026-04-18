<?php


namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;
use App\Models\BoardTab;
use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BoardTabController extends BaseApiController
{
    public function index($boardId)
    {
        $tabs = BoardTab::where('board_id', $boardId)->orderBy('position')->get();
        return $this->response(true, 'Tabs retrieved successfully', $tabs);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'board_id' => 'required|exists:boards,id',
            'name' => 'required|string|max:255',
            'enabled' => 'boolean',
            'position' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        $tab = BoardTab::create([
            'id' => (string) Str::uuid(),
            'board_id' => $request->board_id,
            'name' => $request->name,
            'enabled' => $request->enabled ?? true,
            'position' => $request->position,
        ]);

        return $this->response(true, 'Tab created successfully', $tab, 201);
    }

    public function update(Request $request,Board $board, BoardTab $tab)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'enabled' => 'sometimes|boolean',
            'position' => 'sometimes|required|integer',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        $tab->update($validator->validated());

        return $this->response(true, 'Tab updated successfully', $tab, 200);
    }

    public function destroy(Board $board, BoardTab $tab)
    {
        $tab->delete();
        return $this->response(true, 'Tab deleted successfully', [], 200);
    }

    public function toggleEnable(Board $board, BoardTab $tab)
    {
        $tab->enabled = !$tab->enabled;
        $tab->save();

        return $this->response(true, 'Tab status updated successfully', $tab, 200);
    }
}
