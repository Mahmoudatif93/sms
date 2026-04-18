<?php

namespace App\Http\Controllers;

use App\Http\Responses\ValidatorErrorResponse;
use App\Models\Setting;
use Illuminate\Http\Request;
use Validator;

class AdminSettingsController extends BaseApiController
{

    /**
     * Display a listing of the settings with pagination.
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 15);
        $page = $request->get('page', 1);

        // Retrieve search filters
        $search = $request->get('search'); // General search
        $category = $request->get('category'); // Filter by category
        $type = $request->get('type'); // Filter by type

        // Build the query
        $query = Setting::query();

        // Apply general search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('category_en', 'like', "%{$search}%")
                    ->orWhere('category_ar', 'like', "%{$search}%")
                    ->orWhere('desc_en', 'like', "%{$search}%")
                    ->orWhere('desc_ar', 'like', "%{$search}%");
            });
        }

        // Apply specific filters
        if ($category) {
            $query->where('category_en', $category)->orWhere('category_ar', $category);
        }

        if ($type) {
            $query->where('type', $type);
        }

        $settings = $query->paginate($perPage, ['*'], 'page', $page);

        return $this->paginateResponse(
            true,
            'Settings retrieved successfully',
            $settings
        );
    }

    /**
     * Store a newly created setting in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'category_en' => 'required|string|max:255',
            'category_ar' => 'required|string|max:255',
            'name' => 'required|string|unique:setting,name|max:255',
            'caption_en' => 'required|string',
            'caption_ar' => 'required|string',
            'desc_en' => 'required|string',
            'desc_ar' => 'required|string',
            'value' => 'required|string',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $setting = Setting::create($request->all());

        return $this->response(true, 'Setting created successfully', $setting, 201);
    }

    /**
     * Display the specified setting.
     */
    public function show($id)
    {
        $setting = Setting::find($id);

        if (!$setting) {
            return $this->response(false, 'Setting not found', null, 404);
        }

        return $this->response(true, 'Setting retrieved successfully', $setting);
    }

    /**
     * Update the specified setting in storage.
     */
    public function update(Request $request, $id)
    {
        $setting = Setting::find($id);

        if (!$setting) {
            return $this->response(false, 'Setting not found', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'category_en' => 'required|string|max:255',
            'category_ar' => 'required|string|max:255',
            'name' => 'required|string|unique:setting,name,' . $id,
            'caption_en' => 'required|string',
            'caption_ar' => 'required|string',
            'desc_en' => 'required|string',
            'desc_ar' => 'required|string',
            'value' => 'required|string',
            'type' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->response(false, 'Validation Error(s)', new ValidatorErrorResponse($validator->errors()->toArray()), 400);
        }

        $setting->update($request->all());

        return $this->response(true, 'Setting updated successfully', $setting);
    }

    /**
     * Remove the specified setting from storage.
     */
    public function destroy($id)
    {
        $setting = Setting::find($id);

        if (!$setting) {
            return $this->response(false, 'Setting not found', null, 404);
        }

        $setting->delete();

        return $this->response(true, 'Setting deleted successfully');
    }
}
