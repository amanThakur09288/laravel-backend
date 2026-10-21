<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
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
            ],

            'duration_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:1440',
            ],

            'total_marks' => [
                'required',
                'integer',
                'min:1',
            ],

            'passing_marks' => [
                'required',
                'integer',
                'min:0',
                'lte:total_marks',
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
        ];
    }
}