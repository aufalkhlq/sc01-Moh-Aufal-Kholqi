<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetOperatingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'hours' => ['sometimes', 'nullable', 'array'],
            'hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'hours.*.open_time' => ['required', 'date_format:H:i,H:i:s'],
            'hours.*.close_time' => ['required', 'date_format:H:i,H:i:s', 'after:hours.*.open_time'],
        ];
    }
}
