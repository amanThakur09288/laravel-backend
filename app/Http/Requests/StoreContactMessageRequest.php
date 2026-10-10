<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public form
    }

    protected function prepareForValidation(): void
    {
        // Single-line fields: remove line breaks and tabs.
        // This blocks email header injection.
        foreach (['name', 'subject'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $this->merge([$field => preg_replace('/\s+/', ' ', $value)]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'min:2', 'max:100'],
            'email'   => ['required', 'email:rfc', 'max:150'],
            // 'company' => ['nullable', 'string', 'max:150'],
            'subject' => ['required', 'string', 'min:3', 'max:150'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}