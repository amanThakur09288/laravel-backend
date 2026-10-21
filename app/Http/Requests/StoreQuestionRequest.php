<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'question_text' => [
                'required',
                'string',
            ],

            'type' => [
                'required',
                'in:single_choice,multiple_choice',
            ],

            'marks' => [
                'required',
                'integer',
                'min:1',
            ],

            'explanation' => [
                'nullable',
                'string',
            ],

            'options' => [
                'required',
                'array',
                'min:2',
            ],

            'options.*.option_text' => [
                'required',
                'string',
                'max:1000',
            ],

            'options.*.is_correct' => [
                'required',
                'boolean',
            ],

            'options.*.sort_order' => [
                'required',
                'integer',
                'min:0',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {

                $options = $this->input('options', []);

                $correctCount = collect($options)
                    ->filter(fn ($option) => filter_var(
                        $option['is_correct'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    ))
                    ->count();

                if ($this->input('type') === 'single_choice') {

                    if ($correctCount !== 1) {
                        $validator->errors()->add(
                            'options',
                            'A single-choice question must have exactly one correct option.'
                        );
                    }
                }

                if ($this->input('type') === 'multiple_choice') {

                    if ($correctCount < 2) {
                        $validator->errors()->add(
                            'options',
                            'A multiple-choice question must have at least two correct options.'
                        );
                    }
                }
            },
        ];
    }
}