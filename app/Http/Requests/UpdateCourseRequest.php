<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $courseId = $this->route('course')->id;

        return [
            'course_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('courses', 'course_name')
                    ->ignore($courseId),
            ],

            'category' => [
                'required',
                'string',
                'max:255',
            ],

            'level' => [
                'required',
                Rule::in([
                    'Beginner',
                    'Intermediate',
                    'Advanced',
                ]),
            ],

            'short_description' => [
                'nullable',
                'string',
            ],

            'course_description' => [
                'nullable',
                'string',
            ],

            'duration' => [
                'nullable',
                'string',
                'max:100',
            ],

            'total_hours' => [
                'nullable',
                'integer',
                'min:1',
            ],

            /*
            |--------------------------------------------------------------------------
            | Learning Outcomes
            |--------------------------------------------------------------------------
            */

            'learning_outcomes' => [
                'nullable',
                'array',
            ],

            'learning_outcomes.*' => [
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Curriculum
            |--------------------------------------------------------------------------
            */

            'curriculum' => [
                'nullable',
                'array',
            ],

            'curriculum.*.title' => [
                'required',
                'string',
                'max:255',
            ],

            'curriculum.*.description' => [
                'nullable',
                'string',
            ],

            'curriculum.*.topics' => [
                'nullable',
                'array',
            ],

            'curriculum.*.topics.*' => [
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Prerequisites
            |--------------------------------------------------------------------------
            */

            'prerequisites' => [
                'nullable',
                'array',
            ],

            'prerequisites.*' => [
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Who Should Join
            |--------------------------------------------------------------------------
            */

            'who_should_join' => [
                'nullable',
                'array',
            ],

            'who_should_join.*' => [
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Course Features
            |--------------------------------------------------------------------------
            */

            'course_features' => [
                'nullable',
                'array',
            ],

            'course_features.*' => [
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | FAQs
            |--------------------------------------------------------------------------
            */

            'faqs' => [
                'nullable',
                'array',
            ],

            'faqs.*.question' => [
                'required',
                'string',
            ],

            'faqs.*.answer' => [
                'required',
                'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            */

            'status' => [
                'nullable',
                Rule::in([
                    'Active',
                    'Inactive',
                ]),
            ],
        ];
    }
}