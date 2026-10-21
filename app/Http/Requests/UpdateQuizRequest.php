<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuizRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'batch_id' => [
                'sometimes',
                'integer',
                'exists:batches,id',
            ],

            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'duration_minutes' => [
                'sometimes',
                'integer',
                'min:1',
                'max:1440',
            ],

            'total_marks' => [
                'sometimes',
                'integer',
                'min:1',
            ],

            'passing_marks' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'attempts_allowed' => [
                'sometimes',
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
        ];
    }

    /**
     * Additional validation after the normal rules.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $totalMarks = $this->input('total_marks');
                $passingMarks = $this->input('passing_marks');

                if (
                    $totalMarks !== null &&
                    $passingMarks !== null &&
                    $passingMarks > $totalMarks
                ) {
                    $validator->errors()->add(
                        'passing_marks',
                        'Passing marks cannot be greater than total marks.'
                    );
                }
            },
        ];
    }
}