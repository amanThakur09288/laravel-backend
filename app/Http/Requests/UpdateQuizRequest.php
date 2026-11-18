<?php

namespace App\Http\Requests;

class UpdateQuizRequest extends StoreQuizRequest
{
    public function rules(): array
    {
        return array_merge(
            parent::rules(),
            [
                'questions.*.id' => [
                    'nullable',
                    'integer',
                    'exists:quiz_questions,id',
                ],

                'questions.*.options.*.id' => [
                    'nullable',
                    'integer',
                    'exists:quiz_options,id',
                ],
            ]
        );
    }
}
