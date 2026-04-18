<?php

namespace App\Domain\Conversation\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'filter' => 'nullable|string|in:me,unassigned',
            'status' => 'nullable|string|in:archived,not_replied,promotional',
            'sort' => 'nullable|string|in:newest,oldest,waiting_longest',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'filter.in' => 'Filter must be either "me" or "unassigned".',
            'status.in' => 'Status must be one of: archived, not_replied, promotional.',
            'sort.in' => 'Sort must be one of: newest, oldest, waiting_longest.',
        ];
    }
}
