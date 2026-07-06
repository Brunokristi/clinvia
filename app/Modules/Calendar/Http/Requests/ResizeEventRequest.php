<?php

namespace App\Modules\Calendar\Http\Requests;

use App\Modules\Calendar\Models\Event;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ResizeEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ends_at' => ['required', 'date'],
            'recurrence_scope' => ['nullable', 'string', 'in:this,this_and_following,series'],
            'occurrence_starts_at' => ['nullable', 'date'],
            'occurrence_ends_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Event|null $event */
            $event = $this->route('event');

            if (! $event?->is_recurring) {
                return;
            }

            $scope = (string) $this->input('recurrence_scope');

            if (! in_array($scope, ['this', 'this_and_following', 'series'], true)) {
                $validator->errors()->add('recurrence_scope', 'Recurring events require an explicit scope.');

                return;
            }

            if (in_array($scope, ['this', 'this_and_following'], true) && ! $this->filled('occurrence_starts_at')) {
                $validator->errors()->add('occurrence_starts_at', 'Occurrence start is required for recurring occurrence changes.');
            }
        });
    }
}
