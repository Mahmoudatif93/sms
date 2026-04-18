<?php

namespace App\Http\Requests\Livechats;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdatePreChatFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Assuming authorization is handled elsewhere or not needed
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'enabled' => 'sometimes|boolean',
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'submit_button_text' => 'sometimes|string|max:100',
            'fields' => 'sometimes|array',
            'fields.*.id' => 'sometimes|exists:pre_chat_form_fields,id',
            'fields.*.type' => [
                'sometimes',
                Rule::in(['text', 'email', 'textarea', 'select', 'checkbox', 'radio', 'date', 'phone', 'name'])
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
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check if there is at most one "email" field and at most one "name" field
            if ($this->has('fields')) {
                $nameFieldsCount = 0;
                $emailFieldsCount = 0;

                foreach ($this->input('fields') as $field) {
                    if (isset($field['type'])) {
                        if ($field['type'] === 'name') {
                            $nameFieldsCount++;
                        } elseif ($field['type'] === 'email') {
                            $emailFieldsCount++;
                        }
                    }
                }

                if ($nameFieldsCount > 1) {
                    $validator->errors()->add('fields', 'Only one field of type "name" is allowed');
                }

                if ($emailFieldsCount > 1) {
                    $validator->errors()->add('fields', 'Only one field of type "email" is allowed');
                }
            }
        });
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'fields.*.type.in' => 'The field type must be one of: text, email, textarea, select, checkbox, radio, date, phone, name.',
        ];
    }
}
