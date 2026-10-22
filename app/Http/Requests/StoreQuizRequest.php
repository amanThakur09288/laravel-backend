<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Quiz
            |--------------------------------------------------------------------------
            */

            'batch_id' => [
                'required',
                'integer',
                'exists:batches,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'duration_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:1440',
            ],

            'passing_marks' => [
                'required',
                'integer',
                'min:0',
            ],

            'attempts_allowed' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],

            'shuffle_questions' => [
                'sometimes',
                'boolean',
            ],

            'shuffle_options' => [
                'sometimes',
                'boolean',
            ],

            'auto_submit' => [
                'sometimes',
                'boolean',
            ],

            'show_result' => [
                'sometimes',
                'boolean',
            ],

            'show_correct_answers' => [
                'sometimes',
                'boolean',
            ],

            'status' => [
                'sometimes',
                'in:draft,published,closed',
            ],

            /*
            |--------------------------------------------------------------------------
            | Questions
            |--------------------------------------------------------------------------
            */

            'questions' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],

            'questions.*.question_text' => [
                'required',
                'string',
                'max:5000',
            ],

            'questions.*.type' => [
                'required',
                'in:single_select,multi_select',
            ],

            'questions.*.marks' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],

            'questions.*.explanation' => [
                'nullable',
                'string',
                'max:5000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Options
            |--------------------------------------------------------------------------
            */

            'questions.*.options' => [
                'required',
                'array',
                'min:2',
                'max:20',
            ],

            'questions.*.options.*.option_text' => [
                'required',
                'string',
                'max:1000',
            ],

            'questions.*.options.*.is_correct' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $questions = $this->input('questions', []);

                $totalMarks = 0;

                foreach ($questions as $questionIndex => $question) {
                    $totalMarks += (int) ($question['marks'] ?? 0);

                    $options = $question['options'] ?? [];

                    $correctOptions = collect($options)
                        ->filter(
                            fn ($option) =>
                                filter_var(
                                    $option['is_correct'] ?? false,
                                    FILTER_VALIDATE_BOOLEAN
                                )
                        )
                        ->values();

                    /*
                    |--------------------------------------------------------------------------
                    | Single select
                    |--------------------------------------------------------------------------
                    */

                    if (
                        ($question['type'] ?? null) === 'single_select'
                        && $correctOptions->count() !== 1
                    ) {
                        $validator->errors()->add(
                            "questions.$questionIndex.options",
                            'Single select questions must have exactly one correct option.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Multi select
                    |--------------------------------------------------------------------------
                    */

                    if (
                        ($question['type'] ?? null) === 'multi_select'
                        && $correctOptions->count() < 2
                    ) {
                        $validator->errors()->add(
                            "questions.$questionIndex.options",
                            'Multi select questions must have at least two correct options.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Duplicate option text
                    |--------------------------------------------------------------------------
                    */

                    $optionTexts = collect($options)
                        ->map(
                            fn ($option) =>
                                mb_strtolower(
                                    trim($option['option_text'] ?? '')
                                )
                        )
                        ->filter()
                        ->values();

                    if ($optionTexts->duplicates()->isNotEmpty()) {
                        $validator->errors()->add(
                            "questions.$questionIndex.options",
                            'Options inside the same question must be unique.'
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Passing marks
                |--------------------------------------------------------------------------
                */

                $passingMarks = (int) $this->input(
                    'passing_marks',
                    0
                );

                if ($passingMarks > $totalMarks) {
                    $validator->errors()->add(
                        'passing_marks',
                        'Passing marks cannot be greater than total marks.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Result settings
                |--------------------------------------------------------------------------
                */

                $showResult = $this->boolean('show_result');

                $showCorrectAnswers = $this->boolean(
                    'show_correct_answers'
                );

                if (
                    !$showResult &&
                    $showCorrectAnswers
                ) {
                    $validator->errors()->add(
                        'show_correct_answers',
                        'Correct answers cannot be shown when quiz results are disabled.'
                    );
                }
            },
        ];
    }
}