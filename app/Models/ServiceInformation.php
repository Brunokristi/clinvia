<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceInformation extends Model
{
    protected $fillable = [
        'service_id',
        'text',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}