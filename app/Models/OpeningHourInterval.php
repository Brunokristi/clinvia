<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningHourInterval extends Model
{
    use HasFactory;

    protected $fillable = [
        'opening_hour_id',
        'opens_at',
        'closes_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function openingHour(): BelongsTo
    {
        return $this->belongsTo(OpeningHour::class);
    }
}