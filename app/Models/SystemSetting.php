<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use LogsActivity;

    protected $fillable = [
        'group',
        'key',
        'label',
        'type',
        'value',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }
}
