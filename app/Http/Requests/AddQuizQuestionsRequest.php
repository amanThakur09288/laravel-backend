<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddQuizQuestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'question_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'question_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:questions,id',
            ],
        ];
    }
}