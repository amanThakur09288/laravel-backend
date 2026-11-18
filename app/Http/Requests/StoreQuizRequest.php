<?php

namespace App\Http\Requests;

use App\Models\CourseLesson;
use App\Models\CourseSection;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuizRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStaff() ?? false;
    }

    public function rules(): array
    {
        return [
            'batch_id' => [
                'nullable',
                'integer',
                'exists:batches,id',
            ],

            'course_id' => [
                'nullable',
                'integer',
                'exists:courses,id',
            ],

            'course_section_id' => [
                'nullable',
                'integer',
                'exists:course_sections,id',
            ],

            'course_lesson_id' => [
                'nullable',
                'integer',
                'exists:course_lessons,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'duration_minutes' => [
                'required',
                'integer',
                'min:1',
                'max:1440',
            ],

            'passing_marks' => [
                'required',
                'integer',
                'min:0',
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

            'questions' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],

            'questions.*.question_text' => [
                'required',
                'string',
                'max:5000',
            ],

            'questions.*.question_image' => [
                'nullable',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'max:5120',
            ],

            'questions.*.type' => [
                'required',
                'in:single_select,multi_select',
            ],

            'questions.*.marks' => [
                'required',
                'integer',
                'min:1',
                'max:1000',
            ],

            'questions.*.explanation' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'questions.*.options' => [
                'required',
                'array',
                'min:2',
                'max:20',
            ],

            'questions.*.options.*.option_text' => [
                'required',
                'string',
                'max:1000',
            ],

            'questions.*.options.*.is_correct' => [
                'required',
                'boolean',
            ],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator) {
                $this->validatePlacement($validator);

                $questions = $this->input('questions', []);

                $totalMarks = 0;

                foreach ($questions as $questionIndex => $question) {
                    $totalMarks += (int) ($question['marks'] ?? 0);

                    $options = $question['options'] ?? [];

                    $correctOptions = collect($options)
                        ->filter(
                            fn ($option) =>
                                filter_var(
                                    $option['is_correct'] ?? false,
                                    FILTER_VALIDATE_BOOLEAN
                                )
                        )
                        ->values();

                    if (
                        ($question['type'] ?? null) === 'single_select'
                        && $correctOptions->count() !== 1
                    ) {
                        $validator->errors()->add(
                            "questions.$questionIndex.options",
                            'Single select questions must have exactly one correct option.'
                        );
                    }

                    if (
                        ($question['type'] ?? null) === 'multi_select'
                        && $correctOptions->count() < 2
                    ) {
                        $validator->errors()->add(
                            "questions.$questionIndex.options",
                            'Multi select questions must have at least two correct options.'
                        );
                    }

                    $optionTexts = collect($options)
                        ->map(
                            fn ($option) =>
                                mb_strtolower(
                                    trim($option['option_text'] ?? '')
                                )
                        )
                        ->filter()
                        ->values();

                    if ($optionTexts->duplicates()->isNotEmpty()) {
                        $validator->errors()->add(
                            "questions.$questionIndex.options",
                            'Options inside the same question must be unique.'
                        );
                    }
                }

                $passingMarks = (int) $this->input(
                    'passing_marks',
                    0
                );

                if ($passingMarks > $totalMarks) {
                    $validator->errors()->add(
                        'passing_marks',
                        'Passing marks cannot be greater than total marks.'
                    );
                }

                $showResult = $this->boolean('show_result');

                $showCorrectAnswers = $this->boolean(
                    'show_correct_answers'
                );

                if (!$showResult && $showCorrectAnswers) {
                    $validator->errors()->add(
                        'show_correct_answers',
                        'Correct answers cannot be shown when quiz results are disabled.'
                    );
                }
            },
        ];
    }

    private function validatePlacement($validator): void
    {
        $courseId = $this->input('course_id');
        $batchId = $this->input('batch_id');
        $sectionId = $this->input('course_section_id');
        $lessonId = $this->input('course_lesson_id');

        if (!$courseId && !$batchId) {
            $validator->errors()->add(
                'course_id',
                'Select a course for this quiz.'
            );

            return;
        }

        if ($sectionId && !$courseId) {
            $validator->errors()->add(
                'course_id',
                'Select a course before selecting a module.'
            );

            return;
        }

        if ($lessonId && !$sectionId) {
            $validator->errors()->add(
                'course_section_id',
                'Select a module before selecting a lesson.'
            );

            return;
        }

        if ($sectionId && $courseId) {
            $section = CourseSection::query()->find($sectionId);

            if (
                !$section ||
                (int) $section->course_id !== (int) $courseId
            ) {
                $validator->errors()->add(
                    'course_section_id',
                    'The selected module does not belong to the selected course.'
                );

                return;
            }
        }

        if ($lessonId && $sectionId) {
            $lesson = CourseLesson::query()->find($lessonId);

            if (
                !$lesson ||
                (int) $lesson->course_section_id !== (int) $sectionId
            ) {
                $validator->errors()->add(
                    'course_lesson_id',
                    'The selected lesson does not belong to the selected module.'
                );
            }
        }
    }
}
