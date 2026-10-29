<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCourseSectionRequest extends FormRequest
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

            'lessons.*.id' => [
                'nullable',
                'integer',
                'exists:course_lessons,id',
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
            | Existing lesson:
            |     video = optional
            |
            | New lesson:
            |     video = required
            |
            */

            'lessons.*.video' => [
                'nullable',
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

            'lessons.*.id.exists' =>
                'One of the selected lessons does not exist.',

            'lessons.*.title.required' =>
                'Each lesson must have a title.',

            'lessons.*.type.in' =>
                'Only video lessons are supported.',

            'lessons.*.video.file' =>
                'The selected lesson video is not a valid file.',

            'lessons.*.video.mimetypes' =>
                'Only valid video files are allowed.',

            'lessons.*.video.max' =>
                'Each lesson video must be 500 MB or smaller.',
        ];
    }

    protected function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $lessons = $this->input('lessons', []);

            foreach ($lessons as $index => $lesson) {
                /*
                |--------------------------------------------------------------------------
                | New lesson must have a video
                |--------------------------------------------------------------------------
                */

                $lessonId = $lesson['id'] ?? null;

                $video = $this->file(
                    "lessons.$index.video"
                );

                if (!$lessonId && !$video) {
                    $validator->errors()->add(
                        "lessons.$index.video",
                        'Please select a video for the new lesson.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Duration validation
                |--------------------------------------------------------------------------
                */

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