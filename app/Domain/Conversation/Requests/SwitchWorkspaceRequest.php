<?php

namespace App\Domain\Conversation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SwitchWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_workspace_id' => 'required|uuid|exists:workspaces,id',
        ];
    }

    public function messages(): array
    {
        return [
            'new_workspace_id.required' => 'New workspace ID is required.',
            'new_workspace_id.uuid' => 'New workspace ID must be a valid UUID.',
            'new_workspace_id.exists' => 'The specified workspace does not exist.',
        ];
    }
}
