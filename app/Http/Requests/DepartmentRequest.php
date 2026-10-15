<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('Super Admin') && $this->user()->can('departments.manage');
    }

    public function rules(): array
    {
        return ['name' => ['required', 'string', 'max:255', Rule::unique('departments')->ignore($this->route('department'))]];
    }
}
