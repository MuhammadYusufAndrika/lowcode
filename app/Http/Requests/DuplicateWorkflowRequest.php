<?php

namespace App\Http\Requests;

use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DuplicateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('duplicate', $this->route('workflow'));
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique(Workflow::class)],
        ];
    }
}
