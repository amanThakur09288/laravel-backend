<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function rules(): array
    {
        return [
            'course_id' => [
                'required',
                'integer',
                'exists:courses,id',
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

            'type' => [
                'required',
                Rule::in(['video']),
            ],

            'sort_order' => [
                'required',
                'integer',
                'min:1',
            ],

            'duration_seconds' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_preview' => [
                'nullable',
                'boolean',
            ],

            'status' => [
                'nullable',
                Rule::in([
                    'draft',
                    'published',
                ]),
            ],
        ];
    }
}