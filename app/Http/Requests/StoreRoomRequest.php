<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'capacity' => ['required', 'integer', 'min:1'],
            'location' => ['required', 'string', 'max:255'],
            'buffer_minutes' => ['sometimes', 'integer', 'min:0', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
