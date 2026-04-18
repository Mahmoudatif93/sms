<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\BaseApiController;

use App\Http\Controllers\Controller;
use App\Models\BoardTag;
use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class BoardTagController extends BaseApiController
{
    public function index($board_id)
    {
        $tags = BoardTag::where('board_id', $board_id)->get();
        return $this->response(true, 'tags retrieved successfully', $tags);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'board_id' => 'required|exists:boards,id',
            'name'     => 'required|string|max:255',
            'color'    => 'required|string|max:7',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        $tag = BoardTag::create([
            'id'       => Str::uuid(),
            'board_id' => $request->board_id,
            'name'     => $request->name,
            'color'    => $request->color,
        ]);
        return $this->response(true, 'tag created successfully', $tag, 201);
    }

    public function update(Request $request, $board_id, $tag_id)
    {
        $validator = Validator::make($request->all(), [
            'name'  => 'sometimes|string|max:255',
            'color' => 'sometimes|string|max:7',
        ]);

        if ($validator->fails()) {
            return $this->response(false, "Validation Error(s)", $validator->errors(), 400);
        }

        // Find tag under the given board
        $tag = BoardTag::where('board_id', $board_id)->where('id', $tag_id)->firstOrFail();

        $tag->update($request->only(['name', 'color']));

        return $this->response(true, 'Tag updated successfully', $tag, 200);
    }


    public function destroy($board_id, $tag_id)
    {
        $tag = BoardTag::where('board_id', $board_id)->where('id', $tag_id)->firstOrFail();
        $tag->delete();

        return $this->response(true, 'Tag deleted successfully', [], 200);
    }
}
