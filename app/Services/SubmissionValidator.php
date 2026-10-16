<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SubmissionValidator
{
    public function validate(array $fields, array $data): array
    {
        foreach ($fields as $field) {
            if (! array_key_exists($field['key'], $data) && array_key_exists('default_value', $field) && $field['type'] !== 'file') {
                $data[$field['key']] = $field['default_value'];
            }
        }
        $rules = ['data' => ['present', count($fields) ? 'array:'.implode(',', array_column($fields, 'key')) : 'array', ...(! count($fields) ? ['size:0'] : [])]];
        foreach ($fields as $field) {
            $key = 'data.'.$field['key'];
            $rules[$key] = [$field['required'] ? 'required' : 'nullable'];
            $rules[$key] = [...$rules[$key], ...match ($field['type']) {
                'number' => ['numeric', 'between:-1000000000000,1000000000000'],
                'date' => ['date_format:Y-m-d'],
                'checkbox' => ['boolean'],
                'select', 'radio' => ['string', Rule::in($field['options'] ?? [])],
                'file' => ['file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,txt', 'mimetypes:application/pdf,image/jpeg,image/png,text/plain'],
                default => ['string', 'max:10000'],
            }];
            if (in_array($field['type'], ['number', 'text', 'textarea'])) {
                foreach (['min', 'max'] as $bound) {
                    if (isset($field[$bound])) {
                        $rules[$key][] = $bound.':'.$field[$bound];
                    }
                }
            }
        }
        $validated = Validator::make(['data' => $data], $rules)->validate()['data'];
        foreach ($fields as $field) {
            $key = $field['key'];
            if (! isset($validated[$key])) {
                continue;
            }
            if ($field['type'] === 'number') {
                $validated[$key] = (float) $validated[$key];
                if (! is_finite($validated[$key])) {
                    throw ValidationException::withMessages(['data.'.$key => 'Number must be finite.']);
                }
            } elseif ($field['type'] === 'checkbox') {
                $validated[$key] = (bool) $validated[$key];
            }
        }

        return $validated;
    }
}
