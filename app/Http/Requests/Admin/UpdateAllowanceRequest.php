<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAllowanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reset' => ['nullable', 'boolean'],
            'total' => [$this->boolean('reset') ? 'nullable' : 'required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:250'],
        ];
    }
}
