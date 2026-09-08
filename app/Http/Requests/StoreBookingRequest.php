<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'title' => ['required', 'string', 'max:255'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'is_recurring' => ['sometimes', 'boolean'],
        ];

        if ($this->boolean('is_recurring')) {
            $rules['frequency'] = ['required', 'string', 'in:daily,weekly'];
            $rules['start_date'] = ['required', 'date_format:Y-m-d'];
            $rules['end_date'] = ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'];
            $rules['start_time_of_day'] = ['required', 'string'];
            $rules['end_time_of_day'] = ['required', 'string'];
            $rules['exception_dates'] = ['sometimes', 'array'];
            $rules['exception_dates.*'] = ['date_format:Y-m-d'];
        } else {
            $rules['start_time'] = ['required', 'date'];
            $rules['end_time'] = ['required', 'date', 'after:start_time'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'end_time.after' => 'The end time must be strictly after the start time.',
            'room_id.exists' => 'The specified room does not exist.',
        ];
    }
}
