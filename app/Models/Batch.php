<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $fillable = [
        'slug',
        'batch_name',
        'course_name',
        'short_description',
        'course_description',
        'batch_overview',

        'start_date',
        'end_date',
        'duration_text',
        'class_days',
        'class_start_time',
        'class_end_time',
        'total_classes',

        'mode',
        'location',

        'total_seats',
        'available_seats',
        'status',

        'fee',
        'discounted_fee',
        'offer_text',

        'instructor_name',
        'instructor_designation',
        'instructor_experience',
        'instructor_bio',

        'learning_outcomes',
        'curriculum',
        'prerequisites',
        'who_should_join',
        'faqs',
        'payment_info',

        'live_class_info',
        'recorded_class_info',
        'doubt_support',
    ];

    protected function casts(): array
    {
        return [
            // 'start_date' => 'date',
            // 'end_date' => 'date',

            'fee' => 'decimal:2',
            'discounted_fee' => 'decimal:2',
            'start_date' => 'date:Y-m-d',
            'end_date'   => 'date:Y-m-d',
            'learning_outcomes' => 'array',
            'curriculum' => 'array',
            'prerequisites' => 'array',
            'who_should_join' => 'array',
            'faqs' => 'array',
            'payment_info' => 'array',
        ];
    }
}