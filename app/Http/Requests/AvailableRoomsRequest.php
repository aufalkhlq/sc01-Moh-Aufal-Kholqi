<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AvailableRoomsRequest extends FormRequest
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
            'min_capacity' => ['sometimes', 'integer', 'min:1'],
            'search_type' => ['sometimes', 'in:single,recurring'],
            'start_time' => ['required_without:start_date', 'date'],
            'end_time' => ['required_without:end_date', 'date'],
            'start_date' => ['required_without:start_time', 'date_format:Y-m-d'],
            'end_date' => ['required_without:end_time', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'start_time_of_day' => ['required_with:start_date', 'string'],
            'end_time_of_day' => ['required_with:start_date', 'string'],
            'frequency' => ['sometimes', 'in:daily,weekly'],
            'min_capacity' => ['sometimes', 'nullable', 'integer', 'min:1'],
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
