<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer', 'exists:tables,id'],
            'reservation_date' => ['sometimes', 'date'],
            'reservation_time' => ['sometimes', 'date_format:H:i'],
            'guests_count' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'special_requests' => ['sometimes', 'string', 'max:500'],
            // Legacy parameter support
            'date' => ['sometimes', 'date'],
            'time' => ['sometimes', 'date_format:H:i'],
            'number_of_guests' => ['sometimes', 'integer', 'min:1', 'max:20']
        ];
    }

    /**
     * Configure the validator instance
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Ensure at least one set of date/time/guests parameters is provided
            $hasNewFormat = $this->has('reservation_date') && $this->has('reservation_time') && $this->has('guests_count');
            $hasLegacyFormat = $this->has('date') && $this->has('time') && $this->has('number_of_guests');

            if (!$hasNewFormat && !$hasLegacyFormat) {
                $validator->errors()->add('reservation', 'Please provide complete reservation details: date, time, and number of guests');
            }
        });
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'table_id.required' => 'Table selection is required.',
            'table_id.exists' => 'Selected table does not exist.',
            'reservation_date.required' => 'Reservation date is required.',
            'reservation_time.required' => 'Reservation time is required.',
            'reservation_time.date_format' => 'Time must be in HH:MM format.',
            'guests_count.required' => 'Number of guests is required.',
            'guests_count.min' => 'At least 1 guest is required.',
            'guests_count.max' => 'Maximum 20 guests allowed.',
            'special_requests.max' => 'Special requests cannot exceed 500 characters.',
            // Legacy support
            'date.required' => 'Reservation date is required.',
            'time.required' => 'Reservation time is required.',
            'time.date_format' => 'Time must be in HH:MM format.',
            'number_of_guests.required' => 'Number of guests is required.',
            'number_of_guests.min' => 'At least 1 guest is required.',
            'number_of_guests.max' => 'Maximum 20 guests allowed.'
        ];
    }

    /**
     * Get the reservation date from either parameter
     */
    public function getReservationDate(): string
    {
        return $this->reservation_date ?? $this->date;
    }

    /**
     * Get the reservation time from either parameter
     */
    public function getReservationTime(): string
    {
        return $this->reservation_time ?? $this->time;
    }

    /**
     * Get the number of guests from either parameter
     */
    public function getGuestsCount(): int
    {
        return $this->guests_count ?? $this->number_of_guests ?? 1;
    }
}
