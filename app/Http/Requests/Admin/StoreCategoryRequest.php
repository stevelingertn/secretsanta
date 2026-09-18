<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'min:1', Rule::unique('categories', 'id')],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'name'),
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
