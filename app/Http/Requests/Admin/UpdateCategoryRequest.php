<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'name')->ignore($this->route('category')),
                Rule::notIn(['Best Overall']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.not_in' => 'Best Overall is not a class.',
        ];
    }
}
