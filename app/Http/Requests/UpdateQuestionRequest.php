<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'question_text' => [
                'sometimes',
                'string',
            ],

            'type' => [
                'sometimes',
                'in:single_choice,multiple_choice',
            ],

            'marks' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'explanation' => [
                'nullable',
                'string',
            ],

            'options' => [
                'sometimes',
                'array',
                'min:2',
            ],

            'options.*.option_text' => [
                'required_with:options',
                'string',
                'max:1000',
            ],

            'options.*.is_correct' => [
                'required_with:options',
                'boolean',
            ],

            'options.*.sort_order' => [
                'required_with:options',
                'integer',
                'min:0',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {

                if (!$this->has('options')) {
                    return;
                }

                $options = $this->input('options', []);

                $correctCount = collect($options)
                    ->filter(fn ($option) => filter_var(
                        $option['is_correct'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    ))
                    ->count();

                $type = $this->input('type');

                if ($type === 'single_choice' && $correctCount !== 1) {
                    $validator->errors()->add(
                        'options',
                        'A single-choice question must have exactly one correct option.'
                    );
                }

                if ($type === 'multiple_choice' && $correctCount < 2) {
                    $validator->errors()->add(
                        'options',
                        'A multiple-choice question must have at least two correct options.'
                    );
                }
            },
        ];
    }
}