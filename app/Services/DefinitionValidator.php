<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class DefinitionValidator
{
    public function validate(array $definition, bool $publish = false): array
    {
        Validator::make(['definition' => $definition], [
            'definition' => ['required', 'array:nodes,edges'],
            'definition.nodes' => ['present', 'array', 'list', 'max:100'],
            'definition.nodes.*' => ['array:id,type,position,data'],
            'definition.nodes.*.id' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_-]+$/', 'distinct:strict'],
            'definition.nodes.*.type' => ['required', Rule::in(['start', 'form', 'approval', 'condition', 'notification', 'end'])],
            'definition.nodes.*.position' => ['required', 'array:x,y'],
            'definition.nodes.*.position.x' => ['required', 'numeric', 'between:-100000,100000'],
            'definition.nodes.*.position.y' => ['required', 'numeric', 'between:-100000,100000'],
            'definition.nodes.*.data' => ['required', 'array:label,fields,assignee_type,assignee_id,deadline_hours,escalation_user_id,condition,message,outcome'],
            'definition.nodes.*.data.label' => ['required', 'string', 'max:255'],
            'definition.nodes.*.data.fields' => ['sometimes', 'array', 'list', 'max:100'],
            'definition.nodes.*.data.fields.*' => ['array:key,label,type,required,placeholder,default_value,options,min,max'],
            'definition.nodes.*.data.fields.*.key' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'definition.nodes.*.data.fields.*.label' => ['required', 'string', 'max:255'],
            'definition.nodes.*.data.fields.*.type' => ['required', Rule::in(['text', 'textarea', 'number', 'date', 'select', 'radio', 'checkbox', 'file'])],
            'definition.nodes.*.data.fields.*.required' => ['required', 'boolean'],
            'definition.nodes.*.data.fields.*.placeholder' => ['sometimes', 'nullable', 'string', 'max:255'],
            'definition.nodes.*.data.fields.*.options' => ['sometimes', 'array', 'list', 'max:100'],
            'definition.nodes.*.data.fields.*.options.*' => ['required', 'string', 'max:255', 'distinct:strict'],
            'definition.nodes.*.data.fields.*.min' => ['sometimes', 'numeric', 'between:-1000000000000,1000000000000'],
            'definition.nodes.*.data.fields.*.max' => ['sometimes', 'numeric', 'between:-1000000000000,1000000000000'],
            'definition.nodes.*.data.assignee_type' => ['sometimes', 'nullable', Rule::in(['user', 'role'])],
            'definition.nodes.*.data.assignee_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'definition.nodes.*.data.deadline_hours' => ['sometimes', 'nullable', 'numeric', 'min:0.01', 'max:8760'],
            'definition.nodes.*.data.escalation_user_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'definition.nodes.*.data.condition' => ['sometimes', 'nullable', 'array:field,operator,value'],
            'definition.nodes.*.data.condition.field' => ['required_with:definition.nodes.*.data.condition', 'string', 'max:64'],
            'definition.nodes.*.data.condition.operator' => ['required_with:definition.nodes.*.data.condition', Rule::in(['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'in'])],
            'definition.nodes.*.data.message' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'definition.nodes.*.data.outcome' => ['sometimes', 'nullable', Rule::in(['completed', 'rejected'])],
            'definition.edges' => ['present', 'array', 'list', 'max:300'],
            'definition.edges.*' => ['array:id,source,target,sourceHandle'],
            'definition.edges.*.id' => ['required', 'string', 'max:100', 'distinct:strict'],
            'definition.edges.*.source' => ['required', 'string', 'max:100'],
            'definition.edges.*.target' => ['required', 'string', 'max:100'],
            'definition.edges.*.sourceHandle' => ['sometimes', 'nullable', Rule::in(['next', 'approved', 'rejected', 'true', 'false'])],
        ])->validate();
        $errors = [];
        if (strlen(json_encode($definition, JSON_THROW_ON_ERROR)) > 1048576) {
            $errors[] = 'Definition exceeds the 1 MB limit.';
        }
        $fields = [];
        foreach ($definition['nodes'] as $node) {
            $conditionValue = $node['data']['condition']['value'] ?? null;
            if ($conditionValue !== null && ! is_scalar($conditionValue) && (! is_array($conditionValue) || ! array_is_list($conditionValue) || count($conditionValue) > 100 || collect($conditionValue)->contains(fn ($value) => ! is_scalar($value) && $value !== null))) {
                $errors[] = 'Condition values must be scalars or a bounded list of scalars.';
            }
            foreach ($node['data']['fields'] ?? [] as $field) {
                if (isset($fields[$field['key']])) {
                    $errors[] = 'Field keys must be unique: '.$field['key'];
                }
                $fields[$field['key']] = $field;
                if (in_array($field['type'], ['text', 'textarea'], true)) {
                    foreach (['min', 'max'] as $bound) {
                        if (isset($field[$bound]) && (filter_var($field[$bound], FILTER_VALIDATE_INT) === false || $field[$bound] < 0 || $field[$bound] > 10000)) {
                            $errors[] = 'Text bounds must be integers between 0 and 10000: '.$field['key'];
                        }
                    }
                }
                if (isset($field['min'], $field['max']) && $field['min'] > $field['max']) {
                    $errors[] = 'Invalid field bounds: '.$field['key'];
                }
                if (array_key_exists('default_value', $field)) {
                    if (! $this->matches($field, $field['default_value'])) {
                        $errors[] = 'Invalid default value: '.$field['key'];
                    } elseif (in_array($field['type'], ['text', 'textarea', 'number'], true)) {
                        $size = $field['type'] === 'number' ? $field['default_value'] : mb_strlen($field['default_value']);
                        if ((isset($field['min']) && $size < $field['min']) || (isset($field['max']) && $size > $field['max']) || ($field['required'] && $size === 0 && $field['type'] !== 'number')) {
                            $errors[] = 'Default value violates field bounds: '.$field['key'];
                        }
                    }
                }
                if ($publish && in_array($field['type'], ['select', 'radio']) && empty($field['options'])) {
                    $errors[] = 'Selection fields require options: '.$field['key'];
                }
            }
        }
        if ($publish) {
            $nodes = collect($definition['nodes'])->keyBy('id');
            $starts = $nodes->where('type', 'start');
            if ($starts->count() !== 1) {
                $errors[] = 'Exactly one start node is required.';
            }
            if ($nodes->where('type', 'end')->isEmpty()) {
                $errors[] = 'At least one end node is required.';
            }
            foreach ($definition['edges'] as $edge) {
                if (! $nodes->has($edge['source']) || ! $nodes->has($edge['target'])) {
                    $errors[] = 'Dangling edge: '.$edge['id'];
                }
                if (($nodes->get($edge['target'])['type'] ?? null) === 'start') {
                    $errors[] = 'Start nodes cannot have incoming edges.';
                }
            }
            foreach ($nodes as $node) {
                $data = $node['data'];
                $handles = collect($definition['edges'])->where('source', $node['id'])->map(fn ($edge) => $edge['sourceHandle'] ?? 'next')->sort()->values()->all();
                $expected = match ($node['type']) {
                    'approval' => ['approved', 'rejected'], 'condition' => ['false', 'true'], 'end' => [], default => ['next'],
                };
                if ($handles !== $expected) {
                    $errors[] = 'Invalid outgoing branches for '.$node['id'];
                }
                if ($node['type'] !== 'form' && ! empty($data['fields'])) {
                    $errors[] = 'Only form nodes may define fields.';
                }
                if ($node['type'] === 'approval') {
                    $type = $data['assignee_type'] ?? '';
                    $id = $data['assignee_id'] ?? 0;
                    $valid = $type === 'user' ? $this->eligible(User::find($id)) : ($type === 'role' && Role::whereKey($id)->where('guard_name', 'web')->exists() && User::role(Role::find($id)->name)->get()->contains(fn ($user) => $this->eligible($user)));
                    if (! $valid) {
                        $errors[] = 'Approval must have an authorized user or role: '.$node['id'];
                    }
                    if (! empty($data['escalation_user_id']) && ! $this->eligible(User::find($data['escalation_user_id']))) {
                        $errors[] = 'Escalation user must be authorized: '.$node['id'];
                    }
                }
                if ($node['type'] === 'condition') {
                    $condition = $data['condition'] ?? [];
                    $field = $fields[$condition['field'] ?? ''] ?? null;
                    $value = $condition['value'] ?? null;
                    $operator = $condition['operator'] ?? '';
                    $values = $operator === 'in' && is_array($value) ? $value : [$value];
                    if (! array_key_exists('value', $condition) || ! $field || $field['type'] === 'file' || ($operator === 'in' && (! is_array($value) || ! array_is_list($value) || count($value) < 1 || count($value) > 100)) || (in_array($operator, ['gt', 'gte', 'lt', 'lte']) && ($field['type'] ?? '') !== 'number')) {
                        $errors[] = 'Invalid condition: '.$node['id'];
                    } elseif (collect($values)->contains(fn ($value) => ! $this->matches($field, $value))) {
                        $errors[] = 'Condition value has the wrong type: '.$node['id'];
                    }
                }
            }
            $visited = [];
            $active = [];
            $walk = function ($id) use (&$walk, &$visited, &$active, &$errors, $definition, $nodes) {
                if (isset($active[$id])) {
                    $errors[] = 'Cycles are not allowed.';

                    return;
                }
                if (isset($visited[$id]) || ! $nodes->has($id)) {
                    return;
                }
                $active[$id] = true;
                $visited[$id] = true;
                foreach ($definition['edges'] as $edge) {
                    if ($edge['source'] === $id) {
                        $walk($edge['target']);
                    }
                }
                unset($active[$id]);
            };
            if ($starts->count() === 1) {
                $walk($starts->first()['id']);
            }
            if (count($visited) !== $nodes->count()) {
                $errors[] = 'All nodes must be reachable from the start.';
            }
        }
        if ($errors) {
            throw ValidationException::withMessages(['definition' => array_values(array_unique($errors))]);
        }

        return $definition;
    }

    public function eligible(?User $user): bool
    {
        return $user && $user->hasRole('Approver') && $user->can('approvals.decide');
    }

    public function fields(array $definition): array
    {
        return collect($definition['nodes'])->where('type', 'form')->flatMap(fn ($node) => $node['data']['fields'] ?? [])->values()->all();
    }

    public function matches(array $field, mixed $value): bool
    {
        return match ($field['type']) {
            'number' => is_int($value) || (is_float($value) && is_finite($value)),
            'checkbox' => is_bool($value),
            'select', 'radio' => is_string($value) && in_array($value, $field['options'] ?? [], true),
            'date' => is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) && ($date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value)) && $date->format('Y-m-d') === $value,
            'file' => $value === null,
            default => is_string($value) && mb_strlen($value) <= 10000,
        };
    }
}
