<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Course extends Model
{
    public const PUBLISHED_STATUS = 'Active';

    protected $fillable = [
        'course_name',
        'slug',
        'category',
        'level',

        'short_description',
        'course_description',

        'duration',
        'total_hours',

        'learning_outcomes',
        'curriculum',
        'prerequisites',
        'who_should_join',
        'course_features',
        'faqs',

        'status',
    ];

    protected $casts = [
        'total_hours' => 'integer',

        'learning_outcomes' => 'array',
        'curriculum' => 'array',
        'prerequisites' => 'array',
        'who_should_join' => 'array',
        'course_features' => 'array',
        'faqs' => 'array',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::PUBLISHED_STATUS
        );
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }
}