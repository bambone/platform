<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMeta extends Model
{
    use BelongsToTenant;

    protected $table = 'seo_meta';

    protected $fillable = [
        'tenant_id',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'h1',
        'canonical_url',
        'robots',
        'og_title',
        'og_description',
        'og_image',
        'og_type',
        'twitter_card',
        'is_indexable',
        'is_followable',
        'json_ld',
    ];

    protected $casts = [
        'is_indexable' => 'boolean',
        'is_followable' => 'boolean',
        'json_ld' => 'array',
    ];

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }
}
