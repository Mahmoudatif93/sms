<?php

namespace App\Http\Controllers;

use App\Models\PreChatForm;
use App\Services\LiveChat\PreChatFormService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;


class PreChatFormController extends Controller
{
    protected $formService;

    public function __construct(PreChatFormService $formService)
    {
        $this->formService = $formService;
    }

    /**
     * Update an existing pre-chat form
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Find the form
        $preChatForm = PreChatForm::findOrFail($id);

        // Validate the request
        $validated = $request->validate([
            'form.enabled' => 'sometimes|boolean',
            'form.title' => 'sometimes|string|max:255',
            'form.description' => 'sometimes|nullable|string',
            'form.submit_button_text' => 'sometimes|string|max:100',
            'form.require_fields' => 'sometimes|boolean',
            'fields' => 'sometimes|array',
            'fields.*.id' => 'sometimes|exists:pre_chat_form_fields,id',
            'fields.*.type' => [
                'sometimes',
                Rule::in(['text', 'email', 'textarea', 'select', 'checkbox', 'radio', 'date', 'phone'])
            ],
            'fields.*.name' => 'sometimes|string|max:50',
            'fields.*.label' => 'sometimes|string|max:100',
            'fields.*.placeholder' => 'sometimes|nullable|string|max:100',
            'fields.*.required' => 'sometimes|boolean',
            'fields.*.enabled' => 'sometimes|boolean',
            'fields.*.options' => 'sometimes|nullable|json',
            'fields.*.validation' => 'sometimes|nullable|json',
            'fields.*.order' => 'sometimes|integer|min:0',
            'delete_field_ids' => 'sometimes|array',
            'delete_field_ids.*' => 'exists:pre_chat_form_fields,id',
        ]);

        try {
            // Update the form using the service
            $updatedForm = $this->formService->updateForm($preChatForm, $validated);

            return response()->json([
                'message' => 'Pre-chat form updated successfully',
                'pre_chat_form' => $updatedForm
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update pre-chat form',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
