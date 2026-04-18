<?php

namespace App\Http\Controllers\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\PostChatForm;
use App\Models\Workspace;
use App\Services\LiveChat\PostChatFormService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PostChatFormController extends Controller
{
    protected $formService;

    public function __construct(PostChatFormService $formService)
    {
        $this->formService = $formService;
    }

    /**
     * Update an existing post-chat form
     *
     * @OA\Put(
     *     path="/api/workspaces/{workspace}/livechat/post-chat-forms/{id}",
     *     summary="Update a post-chat form",
     *     description="Updates an existing post-chat form configuration and its fields",
     *     operationId="updatePostChatForm",
     *     tags={"LiveChat"},
     *     security={{"Bearer":{}}},
     *     @OA\Parameter(
     *         name="workspace",
     *         in="path",
     *         description="Workspace ID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Post-chat form ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="enabled",
     *                 type="boolean",
     *                 description="Enable/disable the form",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="title",
     *                 type="string",
     *                 description="Form title",
     *                 example="Welcome to our support"
     *             ),
     *             @OA\Property(
     *                 property="description",
     *                 type="string",
     *                 description="Form description",
     *                 example="Please fill out this form to help us assist you better"
     *             ),
     *             @OA\Property(
     *                 property="submit_button_text",
     *                 type="string",
     *                 description="Text for the submit button",
     *                 example="Start Chatting"
     *             ),
     *             @OA\Property(
     *                 property="require_fields",
     *                 type="boolean",
     *                 description="Whether all fields are required",
     *                 example=true
     *             ),
     *             @OA\Property(
     *                 property="fields",
     *                 type="array",
     *                 description="Form fields",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", description="Field ID (if updating existing field)", example=1),
     *                     @OA\Property(property="type", type="string", description="Field type", example="text", enum={"text", "email", "textarea", "select", "checkbox", "radio", "date", "phone"}),
     *                     @OA\Property(property="name", type="string", description="Field identifier", example="full_name"),
     *                     @OA\Property(property="label", type="string", description="Field label", example="Full Name"),
     *                     @OA\Property(property="placeholder", type="string", description="Field placeholder", example="Enter your full name"),
     *                     @OA\Property(property="required", type="boolean", description="Whether field is required", example=true),
     *                     @OA\Property(property="enabled", type="boolean", description="Whether field is enabled", example=true),
     *                  @OA\Property(
     * property="options",
     * type="array",
     * description="Options for select/radio inputs",
     * @OA\Items(
     * type="object",
     * @OA\Property(property="value", type="string", example="option1"),
     * @OA\Property(property="label", type="string", example="Option 1")
     * )
     * ),
     *                     @OA\Property(property="validation", type="string", description="JSON string for validation rules", example="null"),
     *                     @OA\Property(property="order", type="integer", description="Display order of field", example=0)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="delete_field_ids",
     *                 type="array",
     *                 description="IDs of fields to delete",
     *                 @OA\Items(type="integer", example=3)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post-chat form updated successfully"),
     *             @OA\Property(
     *                 property="post_chat_form",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="channel_id", type="string", example="abc123"),
     *                 @OA\Property(property="widget_id", type="string", example="xyz456"),
     *                 @OA\Property(property="enabled", type="boolean", example=true),
     *                 @OA\Property(property="title", type="string", example="Welcome to our support"),
     *                 @OA\Property(property="description", type="string", example="Please fill out this form to help us assist you better"),
     *                 @OA\Property(property="submit_button_text", type="string", example="Start Chatting"),
     *                 @OA\Property(property="require_fields", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="fields",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="post_chat_form_id", type="integer", example=1),
     *                         @OA\Property(property="type", type="string", example="text"),
     *                         @OA\Property(property="name", type="string", example="full_name"),
     *                         @OA\Property(property="label", type="string", example="Full Name"),
     *                         @OA\Property(property="placeholder", type="string", example="Enter your full name"),
     *                         @OA\Property(property="required", type="boolean", example=true),
     *                         @OA\Property(property="enabled", type="boolean", example=true),
     *                         @OA\Property(property="options", type="string", example="null"),
     *                         @OA\Property(property="validation", type="string", example="null"),
     *                         @OA\Property(property="order", type="integer", example=0),
     *                         @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z")
     *                     )
     *                 ),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="This action is unauthorized")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Post-chat form not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\AdditionalProperties(
     *                     type="array",
     *                     @OA\Items(type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Failed to update post-chat form"),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @param string $workspace Workspace ID
     * @param int $id Post-chat form ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Workspace $workspace, $id)
    {
        // Find the form
        $postChatForm = postChatForm::findOrFail($id);

        // Validate the request
        /*  $validated = $request->validate([
            'form.enabled' => 'sometimes|boolean',
            'form.title' => 'sometimes|string|max:255',
            'form.description' => 'sometimes|nullable|string',
            'form.submit_button_text' => 'sometimes|string|max:100',
            'form.require_fields' => 'sometimes|boolean',
            'fields' => 'sometimes|array',
            'fields.*.id' => 'sometimes|exists:post_chat_form_fields,id',
            'fields.*.type' => [
                'sometimes',
                Rule::in(['text', 'email', 'textarea', 'select', 'checkbox', 'radio', 'date', 'phone', 'rating'])
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
            'delete_field_ids.*' => 'exists:post_chat_form_fields,id',
        ]);*/


        $validated = $request->validate([
            // ✅ flat payload
            'enabled' => 'sometimes|boolean',
            'submit_button_text' => 'sometimes|string|max:100',

            // ✅ nested payload
            'form.enabled' => 'sometimes|boolean',
            'form.submit_button_text' => 'sometimes|string|max:100',

            // fields
            'fields' => 'sometimes|array',
            'fields.*.id' => 'sometimes|exists:post_chat_form_fields,id',
            'fields.*.type' => [
                'sometimes',
                Rule::in(['text', 'email', 'textarea', 'select', 'checkbox', 'radio', 'date', 'phone', 'rating','list','dropdown','mlist'])
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
            'delete_field_ids.*' => 'exists:post_chat_form_fields,id',
        ]);


        try {
            // Update the form using the service
            $updatedForm = $this->formService->updateForm($postChatForm, $validated);

            return response()->json([
                'message' => 'Post-chat form updated successfully',
                'post_chat_form' => $updatedForm
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to update post-chat form',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
