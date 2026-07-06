<?php

namespace App\Modules\Calendar\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddGroupEventParticipantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['nullable', 'integer'],
            'participant_name' => ['required_without:patient_id', 'nullable', 'string', 'max:255'],
            'participant_email' => ['nullable', 'email', 'max:255'],
            'participant_phone' => ['nullable', 'string', 'max:255'],
            'occurrence_starts_at' => ['nullable', 'date'],
            'occurrence_ends_at' => ['nullable', 'date', 'after:occurrence_starts_at'],
            'status' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
