<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RescheduleBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_time' => ['required', 'date'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'reason' => ['sometimes', 'string', 'max:500'],
            'timezone' => ['sometimes', 'string', 'timezone'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'The end time must be strictly after the start time.',
        ];
    }
}
