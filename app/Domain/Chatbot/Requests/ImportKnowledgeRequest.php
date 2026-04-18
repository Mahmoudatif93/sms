<?php

namespace App\Domain\Chatbot\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportKnowledgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => 'required_without:data|file|mimes:json,csv|max:5120',
            'data' => 'required_without:file|array',
            'data.knowledge' => 'required_with:data|array',
            'data.knowledge.*.intent' => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'The file size cannot exceed 5MB',
            'file.mimes' => 'Only JSON and CSV files are allowed',
        ];
    }
}
