<?php
namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreAttributeDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)+$/', Rule::unique('attribute_definitions', 'key'),],
            'display_name' => ['required', 'string', 'regex:/^[A-Z][a-zA-Z]+(?:[A-Z][a-zA-Z]+)*$/'],
            'cardinality' => ['required', 'in:one,many'],
            'type' => ['required', 'in:boolean,datetime,number,string'],
            'pii' => ['required', 'boolean'],
            'read_only' => ['sometimes', 'boolean'],
            'builtin' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'The key must contain lowercase words separated by hyphens like ( "subscribed-messenger").',
            'display_name.regex' => 'The display_name must be in CamelCase like ( "MessengerSubscription").',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation Error(s)',
                'errors' => $validator->errors(),
            ], 400)
        );
    }
}
