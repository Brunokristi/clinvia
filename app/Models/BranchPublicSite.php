<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchPublicSite extends Model
{
    protected $fillable = [
        'branch_id',
        'is_enabled',
        'template',
        'custom_domain',
        'primary_color',
        'secondary_color',
        'logo_path',
        'meta_title',
        'meta_description',
        'faq_items',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'faq_items' => 'array',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}