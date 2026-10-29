<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseLesson extends Model
{
    protected $fillable = [
        'course_section_id',
        'title',
        'description',
        'type',
        'video_path',
        'video_original_name',
        'sort_order',
        'duration_seconds',
        'is_preview',
        'status',
    ];

    protected $hidden = [
        'video_path',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'duration_seconds' => 'integer',
        'is_preview' => 'boolean',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(
            CourseSection::class,
            'course_section_id'
        );
    }
}