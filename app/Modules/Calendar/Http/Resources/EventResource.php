<?php

namespace App\Modules\Calendar\Http\Resources;

use App\Modules\Calendar\Services\EventFrontendMapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var EventFrontendMapper $mapper */
        $mapper = app(EventFrontendMapper::class);

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'type' => $this->type?->value,
            'status' => $this->status,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'timezone' => $this->timezone,
            'title' => $this->title,
            'description' => $this->description,
            'recurrence_rule' => $this->recurrence_rule,
            'recurrence_parent_id' => $this->recurrence_parent_id,
            'recurrence_exception_date' => $this->recurrence_exception_date?->toDateString(),
            'recurrence_original_starts_at' => $this->recurrence_original_starts_at?->toIso8601String(),
            'recurrence_original_ends_at' => $this->recurrence_original_ends_at?->toIso8601String(),
            'is_recurring' => (bool) $this->is_recurring,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'frontend' => $mapper->mapForCalendar($this->resource),
            'legacy' => $mapper->mapForLegacyPayload($this->resource),
        ];
    }
}
