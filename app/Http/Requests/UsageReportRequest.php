<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class UsageReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $start = $this->startDate();
            $end = $this->endDate();

            if ($start->greaterThan($end)) {
                $validator->errors()->add('start_date', 'The start date must be on or before the end date.');

                return;
            }

            if ($start->diffInDays($end) > 91) {
                $validator->errors()->add('end_date', 'The usage date range may cover at most 92 days.');
            }
        });
    }

    public function startDate(): Carbon
    {
        if ($this->filled('start_date')) {
            return Carbon::createFromFormat('Y-m-d', (string) $this->input('start_date'), 'UTC')->startOfDay();
        }

        return $this->endDate()->copy()->subDays(29)->startOfDay();
    }

    public function endDate(): Carbon
    {
        if ($this->filled('end_date')) {
            return Carbon::createFromFormat('Y-m-d', (string) $this->input('end_date'), 'UTC')->startOfDay();
        }

        return now('UTC')->startOfDay();
    }
}
