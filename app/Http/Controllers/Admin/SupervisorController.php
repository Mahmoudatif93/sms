<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\SmsApiController;
use App\Models\Supervisor;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Pagination\Paginator;

class SupervisorController extends SmsApiController implements HasMiddleware
{

    public static function middleware(): array
    {
        return [
            new Middleware('check.admin'),
        ];
    }

    public function  index(Request $request)
    {

        $search = $request->search ?? null;
        $perPage = $request->get('per_page', 15); // Default to 15 if not provided
        $page = $request->get('page', 1);
        // Set the current page for the paginator
        Paginator::currentPageResolver(function () use ($page) {
            return $page;
        });

        // Fetch paginated dataa
        $outboxs =  Supervisor::when(!empty($search), function ($query) use ($search) {
            $query->where(function ($subQuery) use ($search) {
                $subQuery->where('username', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('number', 'like', '%' . $search . '%');
            });
        })
            ->orderBy('id', 'DESC')
            ->paginate($perPage);

        // Customize the response
        return response()->json([
            'data' => $outboxs->items(),
            'pagination' => [
                'total' => $outboxs->total(),
                'per_page' => $outboxs->perPage(),
                'current_page' => $outboxs->currentPage(),
                'last_page' => $outboxs->lastPage(),
                'from' => $outboxs->firstItem(),
                'to' => $outboxs->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'username' => 'required|string|unique:supervisor',
            'password' => 'required|string|min:8',
            'email' => 'required|email|unique:supervisor',
            'number' => 'required|string',
            'lang' => 'required|string',
        ]);
        $validated['group_id'] = 1;
        $validated['date'] = now();
        $validated['password'] = Hash::make($validated['password']);

        $supervisor = Supervisor::create($validated);
        return $this->response(true, 'Contact created successfully for Workspace', $supervisor);
    }

    public function show($id)
    {

        try {
            $supervisor = Supervisor::findOrFail($id);
            return $this->response(true, 'supervisor', $supervisor);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->response(false, 'Supervisor not found.', null, 404);
        }
    }

    public function update(Request $request, $id)
    {

        try {
            $supervisor = Supervisor::findOrFail($id);
            $validated = $request->validate([
                //   'group_id' => 'exists:groups,id',
                'username' => 'string|unique:supervisor,username,' . $supervisor->id,
                'password' => 'string|min:8',
                'email' => 'email|unique:supervisor,email,' . $supervisor->id,
                'number' => 'string',
                'lang' => 'string',

            ]);

            if ($request->has('password')) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $supervisor->update($validated);

            return $this->response(true, 'supervisor', $supervisor);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->response(false, 'Supervisor not found.', null, 404);
        }
    }

    public function destroy($id)
    {

        try {
            $supervisor = Supervisor::findOrFail($id);
            $supervisor->delete();
            return $this->response(true, __('message.msg_delete_row'));
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->response(false, 'Supervisor not found.', null, 404);
        }
    }
}
