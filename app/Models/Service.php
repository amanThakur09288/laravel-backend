<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $fillable = [
        'service_name',
        'slug',
        'short_description',
        'description',
        'offerings',
        'process',
        'faqs',
        'status',
    ];

    protected $casts = [
        'offerings' => 'array',
        'process' => 'array',
        'faqs' => 'array',
    ];
}