<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\ValidSender;

class SmsValidationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'all_numbers' => 'required',
            'from' => ['required', 'string', new ValidSender($this->getOwnerId())],
            'message' => 'required|string',
            'send_time_method' => 'required|in:NOW,LATER',
            'send_time' => 'required_if:send_time_method,LATER|date',
            'sms_type' => 'required|in:NORMAL,VARIABLES,CALENDAR,ADS',
            'repeation_times' => 'sometimes|numeric',
            'excle_file' => 'required_if:all_numbers,excel_file|string',
            'calendar_time' => 'required_if:sms_type,CALENDAR|date',
            'reminder' => 'required_if:sms_type,CALENDAR|integer',
            'reminder_text' => 'required_if:sms_type,CALENDAR|string',
            'location_url' => 'sometimes',
            'workspace' => 'sometimes',
            'channel' => 'sometimes'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'all_numbers.required' => 'Phone numbers are required',
            'from.required' => 'Sender name is required',
            'message.required' => 'Message content is required',
            'send_time_method.required' => 'Send time method is required',
            'send_time_method.in' => 'Send time method must be NOW or LATER',
            'send_time.required_if' => 'Send time is required when method is LATER',
            'sms_type.required' => 'SMS type is required',
            'sms_type.in' => 'SMS type must be NORMAL, VARIABLES, CALENDAR, or ADS',
            'excle_file.required_if' => 'Excel file is required when all_numbers is excel_file',
            'calendar_time.required_if' => 'Calendar time is required for CALENDAR SMS type',
            'reminder.required_if' => 'Reminder is required for CALENDAR SMS type',
            'reminder_text.required_if' => 'Reminder text is required for CALENDAR SMS type',
        ];
    }

    /**
     * Get the owner ID for validation
     */
    private function getOwnerId(): ?string
    {
        // For workspace-based requests
        if ($this->route('workspace')) {
            return $this->route('workspace')->organization_id;
        }
        
        // For direct user requests
        if ($this->has('owner_id')) {
            return $this->input('owner_id');
        }
        
        // For authenticated user requests
        if ($this->user()) {
            return $this->user()->id;
        }
        
        return null;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Decode Unicode escape sequences in message
        if ($this->has('message')) {
            $this->merge([
                'message' => decodeUnicodeEscape($this->input('message'))
            ]);
        }
    }

    /**
     * Get validated data with additional processing
     */
    public function getProcessedData(): array
    {
        $data = $this->validated();
        // Add computed fields
        $data['leng'] = calc_message_length($data['message'], $data['sms_type']);
        $data['sender_name'] = $data['from'] ?? null;
        $data['workspace_id'] = $data['workspace']?->id ?? null; 
        // Add workspace information if available
        if ($this->route('workspace')) {
            $data['workspace'] = $this->route('workspace');
            $data['workspace_id'] = $this->route('workspace')->id;
        }
        // Add user information
        if ($this->user()) {
            $data['user_id'] = $this->user()->id;
        } elseif ($this->has('owner_id')) {
            $data['user_id'] = $this->input('owner_id');
        }
        
        return $data;
    }
}
