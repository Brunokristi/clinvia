<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApiClientDomain extends Model
{
    protected $fillable = [
        'api_client_id',
        'domain',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }
}