<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServicePrice extends Model
{
    protected $fillable = [
        'branch_service_id',
        'price_type',
        'amount',
        'currency',
        'note',
        'is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function branchService(): BelongsTo
    {
        return $this->belongsTo(BranchService::class);
    }
}