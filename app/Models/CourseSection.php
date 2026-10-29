<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSection extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Section belongs to one course.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Section has many lessons.
     */
    public function lessons(): HasMany
    {
        return $this->hasMany(CourseLesson::class)
            ->orderBy('sort_order');
    }
}