<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Online batches have no venue, so ignore any location that was sent
        if ($this->input('mode') === 'Online') {
            $this->merge(['location' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'course_id'          => ['required', 'integer', 'exists:courses,id'],
            'batch_name'         => ['required', 'string', 'max:255'],
            'short_description'  => ['required', 'string', 'max:1000'],
            'course_description' => ['nullable', 'string', 'max:10000'],
            'batch_overview'     => ['nullable', 'string', 'max:10000'],

            'start_date'       => ['required', 'date'],
            'end_date'         => ['required', 'date', 'after_or_equal:start_date'],
            'duration_text'    => ['required', 'string', 'max:255'],
            'class_days'       => ['required', 'string', 'max:255'],
            'class_start_time' => ['required', 'date_format:H:i'],
            'class_end_time'   => ['required', 'date_format:H:i', 'after:class_start_time'],
            'total_classes'    => ['required', 'integer', 'min:1', 'max:10000'],

            'mode'     => ['required', 'in:Online,Offline,Hybrid'],
            'location' => ['required_if:mode,Offline,Hybrid', 'nullable', 'string', 'max:500'],

            'total_seats'     => ['required', 'integer', 'min:1'],
            'available_seats' => ['required', 'integer', 'min:0', 'lte:total_seats'],
            'status'          => ['required', 'in:Enrolling Open,Filling Fast,Few Seats Left,Coming Soon,Sold Out'],

            'fee'            => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'discounted_fee' => ['nullable', 'numeric', 'min:0', 'lt:fee'],
            'offer_text'     => ['nullable', 'string', 'max:1000'],

            'instructor_name'        => ['nullable', 'string', 'max:255'],
            'instructor_designation' => ['nullable', 'string', 'max:255'],
            'instructor_experience'  => ['nullable', 'string', 'max:255'],
            'instructor_bio'         => ['nullable', 'string', 'max:5000'],

            // Simple lists
            'learning_outcomes'   => ['nullable', 'array'],
            'learning_outcomes.*' => ['required', 'string', 'max:1000'],
            'prerequisites'       => ['nullable', 'array'],
            'prerequisites.*'     => ['required', 'string', 'max:1000'],
            'who_should_join'     => ['nullable', 'array'],
            'who_should_join.*'   => ['required', 'string', 'max:1000'],
            'payment_info'        => ['nullable', 'array'],
            'payment_info.*'      => ['required', 'string', 'max:1000'],

            // Curriculum
            'curriculum'               => ['nullable', 'array'],
            'curriculum.*'             => ['required', 'array'],
            'curriculum.*.title'       => ['required', 'string', 'max:255'],
            'curriculum.*.description' => ['required', 'string', 'max:2000'],
            'curriculum.*.topics'      => ['required', 'array', 'min:1'],
            'curriculum.*.topics.*'    => ['required', 'string', 'max:255'],

            // FAQs
            'faqs'            => ['nullable', 'array'],
            'faqs.*'          => ['required', 'array'],
            'faqs.*.question' => ['required', 'string', 'max:1000'],
            'faqs.*.answer'   => ['required', 'string', 'max:5000'],

            'live_class_info'     => ['nullable', 'string', 'max:2000'],
            'recorded_class_info' => ['nullable', 'string', 'max:2000'],
            'doubt_support'       => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'course_id.required'           => 'Please select a course.',
            'course_id.exists'             => 'The selected course does not exist.',
            'end_date.after_or_equal'      => 'End date must be on or after the start date.',
            'class_start_time.date_format' => 'Class start time must be a valid time (HH:MM).',
            'class_end_time.date_format'   => 'Class end time must be a valid time (HH:MM).',
            'class_end_time.after'         => 'Class end time must be after class start time.',
            'location.required_if'         => 'Location is required for offline or hybrid batches.',
            'available_seats.lte'          => 'Available seats cannot exceed total seats.',
            'discounted_fee.lt'            => 'Discounted fee must be less than the original fee.',
            'curriculum.*.topics.required' => 'At least one curriculum topic is required.',
            'curriculum.*.topics.min'      => 'At least one curriculum topic is required.',
        ];
    }

    // Friendly names so default messages read well, e.g. "The batch name field is required."
    public function attributes(): array
    {
        return [
            'course_id'         => 'course',
            'batch_name'        => 'batch name',
            'short_description' => 'short description',
            'duration_text'     => 'duration',
            'class_days'        => 'class days',
            'class_start_time'  => 'class start time',
            'class_end_time'    => 'class end time',
            'total_classes'     => 'total classes',
            'total_seats'       => 'total seats',
            'available_seats'   => 'available seats',
            'discounted_fee'    => 'discounted fee',

            'learning_outcomes.*'      => 'learning outcome',
            'prerequisites.*'          => 'prerequisite',
            'who_should_join.*'        => 'who should join item',
            'payment_info.*'           => 'payment info item',
            'curriculum.*.title'       => 'curriculum module title',
            'curriculum.*.description' => 'curriculum module description',
            'curriculum.*.topics.*'    => 'curriculum topic',
            'faqs.*.question'          => 'FAQ question',
            'faqs.*.answer'            => 'FAQ answer',
        ];
    }
}