<?php

namespace App\Http\Requests;

use App\Models\Workflow;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workflow = $this->route('workflow');

        return $workflow ? $this->user()->can('update', $workflow) : $this->user()->can('create', Workflow::class);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('workflows')->ignore($this->route('workflow'))],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['nullable', 'string', 'max:100'],
            'owner_id' => ['prohibited'],
            'status' => ['prohibited'],
        ];
    }
}
