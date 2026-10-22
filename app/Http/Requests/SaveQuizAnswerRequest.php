<?php

namespace App\Http\Requests;

use App\Models\QuizAttempt;
use Illuminate\Foundation\Http\FormRequest;

class SaveQuizAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $attempt = $this->route('attempt');

        return $attempt instanceof QuizAttempt
            && $this->user()?->id === $attempt->user_id;
    }

    public function rules(): array
    {
        return [
            'quiz_question_id' => [
                'required',
                'integer',
                'exists:quiz_questions,id',
            ],

            /*
            |--------------------------------------------------------------------------
            | present instead of required
            |--------------------------------------------------------------------------
            |
            | An empty array is valid because the user may clear an answer.
            |
            */

            'selected_option_ids' => [
                'present',
                'array',
                'max:20',
            ],

            'selected_option_ids.*' => [
                'integer',
                'distinct',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'quiz_question_id.required' =>
                'Question ID is required.',

            'quiz_question_id.integer' =>
                'Question ID must be a valid number.',

            'quiz_question_id.exists' =>
                'The selected question does not exist.',

            'selected_option_ids.array' =>
                'Selected options must be an array.',

            'selected_option_ids.max' =>
                'Too many options were selected.',

            'selected_option_ids.*.integer' =>
                'Each selected option must be a valid ID.',

            'selected_option_ids.*.distinct' =>
                'Duplicate options are not allowed.',
        ];
    }
}