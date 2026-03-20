<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TemplatePreset extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'config_json',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'config_json' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];
}
