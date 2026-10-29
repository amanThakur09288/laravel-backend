<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCourseSectionRequest extends FormRequest
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
            | Section
            |--------------------------------------------------------------------------
            */

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

            'sort_order' => [
                'required',
                'integer',
                'min:1',
            ],

            'status' => [
                'required',
                Rule::in([
                    'draft',
                    'published',
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | Lessons
            |--------------------------------------------------------------------------
            */

            'lessons' => [
                'required',
                'array',
                'min:1',
                'max:20',
            ],

            'lessons.*.title' => [
                'required',
                'string',
                'max:255',
            ],

            'lessons.*.description' => [
                'nullable',
                'string',
            ],

            'lessons.*.type' => [
                'required',
                Rule::in([
                    'video',
                ]),
            ],

            'lessons.*.sort_order' => [
                'nullable',
                'integer',
                'min:1',
            ],

            'lessons.*.duration_seconds' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'lessons.*.is_preview' => [
                'nullable',
                'boolean',
            ],

            'lessons.*.status' => [
                'required',
                Rule::in([
                    'draft',
                    'published',
                ]),
            ],

            /*
            |--------------------------------------------------------------------------
            | Video
            |--------------------------------------------------------------------------
            |
            | Maximum: 500 MB per video
            | 512000 KB = 500 MB
            |
            */

            'lessons.*.video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/webm,video/quicktime,video/x-msvideo,video/x-matroska',
                'max:512000',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'lessons.required' =>
                'At least one lesson is required.',

            'lessons.min' =>
                'At least one lesson is required.',

            'lessons.max' =>
                'A section cannot contain more than 20 lessons.',

            'lessons.*.title.required' =>
                'Each lesson must have a title.',

            'lessons.*.type.in' =>
                'Only video lessons are supported.',

            'lessons.*.video.required' =>
                'Please select a video for every lesson.',

            'lessons.*.video.file' =>
                'The selected lesson video is not a valid file.',

            'lessons.*.video.mimetypes' =>
                'Only valid video files are allowed.',

            'lessons.*.video.max' =>
                'Each lesson video must be 500 MB or smaller.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'course_id' => $this->input('course_id'),
        ]);
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lessons = $this->input('lessons', []);

            foreach ($lessons as $index => $lesson) {
                if (
                    isset($lesson['duration_seconds']) &&
                    $lesson['duration_seconds'] !== '' &&
                    (int) $lesson['duration_seconds'] < 0
                ) {
                    $validator->errors()->add(
                        "lessons.$index.duration_seconds",
                        'Video duration cannot be negative.'
                    );
                }
            }
        });
    }
}