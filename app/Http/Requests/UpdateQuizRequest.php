<?php

namespace App\Http\Requests;

class UpdateQuizRequest extends StoreQuizRequest
{
    public function rules(): array
    {
        return array_merge(
            parent::rules(),
            [
                /*
                |--------------------------------------------------------------------------
                | Existing Question ID
                |--------------------------------------------------------------------------
                */

                'questions.*.id' => [
                    'nullable',
                    'integer',
                    'exists:quiz_questions,id',
                ],


                /*
                |--------------------------------------------------------------------------
                | Existing Option ID
                |--------------------------------------------------------------------------
                */

                'questions.*.options.*.id' => [
                    'nullable',
                    'integer',
                    'exists:quiz_options,id',
                ],
            ]
        );
    }
}