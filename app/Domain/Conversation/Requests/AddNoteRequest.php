<?php

namespace App\Domain\Conversation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:10000',
        ];
    }

    public function messages(): array
    {
        return [
            'content.required' => 'Note content is required.',
            'content.max' => 'Note content cannot exceed 10,000 characters.',
        ];
    }
}
