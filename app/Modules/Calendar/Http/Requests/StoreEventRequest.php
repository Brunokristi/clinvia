<?php

namespace App\Modules\Calendar\Http\Requests;

use App\Modules\Calendar\Enums\EventStatus;
use App\Modules\Calendar\Enums\EventType;
use Illuminate\Foundation\Http\FormRequest;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:' . implode(',', EventType::values())],
            'status' => ['nullable', 'string', 'in:' . implode(',', EventStatus::values())],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'recurrence_rule' => ['nullable', 'array'],
            'metadata' => ['nullable', 'array'],

            'services' => ['nullable', 'array'],
            'services.*.service_id' => ['required_with:services', 'integer', 'exists:services,id'],
            'services.*.duration_minutes_snapshot' => ['nullable', 'integer', 'min:1'],
            'services.*.price_snapshot' => ['nullable', 'numeric', 'min:0'],
            'services.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'services.*.quantity' => ['nullable', 'integer', 'min:1'],

            'booking_detail' => ['nullable', 'array'],
            'booking_detail.patient_id' => ['nullable', 'integer'],
            'booking_detail.patient_name' => ['nullable', 'string', 'max:255'],
            'booking_detail.patient_email' => ['nullable', 'email', 'max:255'],
            'booking_detail.patient_phone' => ['nullable', 'string', 'max:255'],
            'booking_detail.patient_birth_number' => ['nullable', 'string', 'max:255'],
            'booking_detail.booking_source' => ['nullable', 'string', 'max:120'],
            'booking_detail.booking_status' => ['nullable', 'string', 'max:120'],
            'booking_detail.internal_notes' => ['nullable', 'string'],
            'booking_detail.public_notes' => ['nullable', 'string'],

            'availability_rule_detail' => ['nullable', 'array'],
            'availability_rule_detail.capacity_rules' => ['nullable', 'array'],
            'availability_rule_detail.visibility_rules' => ['nullable', 'array'],
            'availability_rule_detail.min_booking_notice_minutes' => ['nullable', 'integer', 'min:0'],
            'availability_rule_detail.max_booking_notice_minutes' => ['nullable', 'integer', 'min:0'],
            'availability_rule_detail.slot_interval_minutes' => ['nullable', 'integer', 'min:1'],
            'availability_rule_detail.buffer_before_minutes' => ['nullable', 'integer', 'min:0'],
            'availability_rule_detail.buffer_after_minutes' => ['nullable', 'integer', 'min:0'],
            'availability_rule_detail.online_booking_rules' => ['nullable', 'array'],

            'group_detail' => ['nullable', 'array'],
            'group_detail.service_id' => ['nullable', 'integer', 'exists:services,id'],
            'group_detail.service_name' => ['nullable', 'string', 'max:255'],
            'group_detail.capacity' => ['nullable', 'integer', 'min:1'],
            'group_detail.group_status' => ['nullable', 'string', 'max:120'],
            'group_detail.notes' => ['nullable', 'string'],
        ];
    }
}
