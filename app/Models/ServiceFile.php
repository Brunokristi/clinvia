<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceFile extends Model
{
    protected $fillable = [
        'service_id',
        'label',
        'file_path',
        'original_name',
        'mime_type',
        'size',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}