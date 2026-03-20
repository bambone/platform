<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'from_url',
        'to_url',
        'http_code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static function httpCodes(): array
    {
        return [
            301 => '301 Moved Permanently',
            302 => '302 Found',
            307 => '307 Temporary Redirect',
            308 => '308 Permanent Redirect',
        ];
    }
}
