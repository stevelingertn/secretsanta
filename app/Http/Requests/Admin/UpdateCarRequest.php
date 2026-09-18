<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'participant_id' => ['nullable', 'integer', 'exists:participants,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'entry_number' => ['nullable', 'integer', 'min:1'],
            'year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'make' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=10000,max_height=10000'],
            'remove_photo' => ['nullable', 'boolean'],
        ];
    }
}
