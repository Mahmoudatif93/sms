<?php

namespace App\Http\Controllers\LiveChat;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Models\PreChatForm;
use App\Services\LiveChat\PreChatFormService;
use Illuminate\Http\Request;
use App\Http\Requests\Livechats\UpdatePreChatFormRequest;


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
     * @OA\Put(
     *     path="/api/workspaces/{workspace}/livechat/pre-chat-forms/{id}",
     *     summary="Update a pre-chat form",
     *     description="Updates an existing pre-chat form configuration and its fields",
     *     operationId="updatePreChatForm",
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
     *         description="Pre-chat form ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             type="object",
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
     *                     @OA\Property(
     *                         property="id",
     *                         type="integer",
     *                         description="Field ID (if updating existing field)",
     *                         example=1
     *                     ),
     *                     @OA\Property(
     *                         property="type",
     *                         type="string",
     *                         description="Field type",
     *                         example="text",
     *                         enum={"text", "email", "textarea", "select", "checkbox", "radio", "date", "phone"}
     *                     ),
     *                     @OA\Property(
     *                         property="name",
     *                         type="string",
     *                         description="Field identifier",
     *                         example="full_name"
     *                     ),
     *                     @OA\Property(
     *                         property="label",
     *                         type="string",
     *                         description="Field label",
     *                         example="Full Name"
     *                     ),
     *                     @OA\Property(
     *                         property="placeholder",
     *                         type="string",
     *                         description="Field placeholder",
     *                         example="Enter your full name"
     *                     ),
     *                     @OA\Property(
     *                         property="required",
     *                         type="boolean",
     *                         description="Whether field is required",
     *                         example=true
     *                     ),
     *                     @OA\Property(
     *                         property="enabled",
     *                         type="boolean",
     *                         description="Whether field is enabled",
     *                         example=true
     *                     ),
     *                     @OA\Property(
     *                         property="options",
     *                         type="string",
     *                         description="JSON string for select/radio options",
     *                         example="[{'value':'option1','label':'Option 1'}]"
     *                     ),
     *                     @OA\Property(
     *                         property="validation",
     *                         type="string",
     *                         description="JSON string for validation rules",
     *                         example="null"
     *                     ),
     *                     @OA\Property(
     *                         property="order",
     *                         type="integer",
     *                         description="Display order of field",
     *                         example=0
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="delete_field_ids",
     *                 type="array",
     *                 description="IDs of fields to delete",
     *                 @OA\Items(
     *                     type="integer",
     *                     example=3
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Pre-chat form updated successfully"
     *             ),
     *             @OA\Property(
     *                 property="pre_chat_form",
     *                 type="object",
     *                 @OA\Property(
     *                     property="id",
     *                     type="integer",
     *                     example=1
     *                 ),
     *                 @OA\Property(
     *                     property="channel_id",
     *                     type="string",
     *                     example="abc123"
     *                 ),
     *                 @OA\Property(
     *                     property="widget_id",
     *                     type="string",
     *                     example="xyz456"
     *                 ),
     *                 @OA\Property(
     *                     property="enabled",
     *                     type="boolean",
     *                     example=true
     *                 ),
     *                 @OA\Property(
     *                     property="title",
     *                     type="string",
     *                     example="Welcome to our support"
     *                 ),
     *                 @OA\Property(
     *                     property="description",
     *                     type="string",
     *                     example="Please fill out this form to help us assist you better"
     *                 ),
     *                 @OA\Property(
     *                     property="submit_button_text",
     *                     type="string",
     *                     example="Start Chatting"
     *                 ),
     *                 @OA\Property(
     *                     property="require_fields",
     *                     type="boolean",
     *                     example=true
     *                 ),
     *                 @OA\Property(
     *                     property="fields",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(
     *                             property="id",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="pre_chat_form_id",
     *                             type="integer",
     *                             example=1
     *                         ),
     *                         @OA\Property(
     *                             property="type",
     *                             type="string",
     *                             example="text"
     *                         ),
     *                         @OA\Property(
     *                             property="name",
     *                             type="string",
     *                             example="full_name"
     *                         ),
     *                         @OA\Property(
     *                             property="label",
     *                             type="string",
     *                             example="Full Name"
     *                         ),
     *                         @OA\Property(
     *                             property="placeholder",
     *                             type="string",
     *                             example="Enter your full name"
     *                         ),
     *                         @OA\Property(
     *                             property="required",
     *                             type="boolean",
     *                             example=true
     *                         ),
     *                         @OA\Property(
     *                             property="enabled",
     *                             type="boolean",
     *                             example=true
     *                         ),
     *                         @OA\Property(
     *                             property="options",
     *                             type="string",
     *                             example="null"
     *                         ),
     *                         @OA\Property(
     *                             property="validation",
     *                             type="string",
     *                             example="null"
     *                         ),
     *                         @OA\Property(
     *                             property="order",
     *                             type="integer",
     *                             example=0
     *                         ),
     *                         @OA\Property(
     *                             property="created_at",
     *                             type="string",
     *                             format="date-time",
     *                             example="2023-01-01T00:00:00.000000Z"
     *                         ),
     *                         @OA\Property(
     *                             property="updated_at",
     *                             type="string",
     *                             format="date-time",
     *                             example="2023-01-01T00:00:00.000000Z"
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="created_at",
     *                     type="string",
     *                     format="date-time",
     *                     example="2023-01-01T00:00:00.000000Z"
     *                 ),
     *                 @OA\Property(
     *                     property="updated_at",
     *                     type="string",
     *                     format="date-time",
     *                     example="2023-01-01T00:00:00.000000Z"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Unauthenticated"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="This action is unauthorized"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not Found",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Pre-chat form not found"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="The given data was invalid"
     *             ),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties={
     *                     @OA\Schema(
     *                         type="array",
     *                         @OA\Items(type="string")
     *                     )
     *                 }
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server Error",
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Failed to update pre-chat form"
     *             ),
     *             @OA\Property(
     *                 property="error",
     *                 type="string",
     *                 example="Error message"
     *             )
     *         )
     *     )
     * )
     *
     * @param Request $request
     * @param string $workspace Workspace ID
     * @param int $id Pre-chat form ID
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(UpdatePreChatFormRequest $request,Workspace $workspace, $id)
    {
        // Find the form
        $preChatForm = PreChatForm::findOrFail($id);

        // The validation is now handled in the UpdatePreChatFormRequest class
        $validated = $request->validated();

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
