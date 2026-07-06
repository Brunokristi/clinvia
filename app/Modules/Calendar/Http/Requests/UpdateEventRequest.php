<?php

namespace App\Modules\Calendar\Http\Requests;

use App\Modules\Calendar\Models\Event;
use Illuminate\Validation\Validator;

class UpdateEventRequest extends StoreEventRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['type'] = ['sometimes', 'string', 'in:' . implode(',', \App\Modules\Calendar\Enums\EventType::values())];
        $rules['starts_at'] = ['sometimes', 'date'];
        $rules['ends_at'] = ['sometimes', 'date', 'after:starts_at'];
        $rules['recurrence_scope'] = ['nullable', 'string', 'in:this,this_and_following,series'];
        $rules['occurrence_starts_at'] = ['nullable', 'date'];
        $rules['occurrence_ends_at'] = ['nullable', 'date'];
        $rules['occurrence_date'] = ['nullable', 'date'];
        $rules['reset_exceptions'] = ['nullable', 'boolean'];

        return $rules;
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

            if (in_array($scope, ['this', 'this_and_following'], true) && ! $this->filled('occurrence_starts_at') && ! $this->filled('occurrence_date')) {
                $validator->errors()->add('occurrence_starts_at', 'Occurrence start is required for recurring occurrence changes.');
            }

            if ($scope === 'this' && $this->exists('recurrence_rule')) {
                $validator->errors()->add('recurrence_rule', 'Recurrence rule cannot be changed for a single occurrence.');
            }
        });
    }
}
