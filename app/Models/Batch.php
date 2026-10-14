<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Batch extends Model
{
    protected $fillable = [
        'course_id',

        'slug',
        'batch_name',

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

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',

        'learning_outcomes' => 'array',
        'curriculum' => 'array',
        'prerequisites' => 'array',
        'who_should_join' => 'array',
        'faqs' => 'array',
        'payment_info' => 'array',

        'total_classes' => 'integer',
        'total_seats' => 'integer',
        'available_seats' => 'integer',

        'fee' => 'decimal:2',
        'discounted_fee' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Batch belongs to Course
    |--------------------------------------------------------------------------
    */

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}