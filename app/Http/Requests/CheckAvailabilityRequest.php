<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CheckAvailabilityRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'time' => ['required', 'date_format:H:i'],
            'guests' => ['required', 'integer', 'min:1', 'max:20'],
            // Support both 'guests' and 'number_of_guests' for backward compatibility
            'number_of_guests' => ['sometimes', 'integer', 'min:1', 'max:20']
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'date.required' => 'Reservation date is required.',
            'time.required' => 'Reservation time is required.',
            'time.date_format' => 'Time must be in HH:MM format.',
            'guests.required' => 'Number of guests is required.',
            'guests.min' => 'At least 1 guest is required.',
            'guests.max' => 'Maximum 20 guests allowed.',
            'number_of_guests.required' => 'Number of guests is required.',
            'number_of_guests.min' => 'At least 1 guest is required.',
            'number_of_guests.max' => 'Maximum 20 guests allowed.'
        ];
    }

    /**
     * Get the number of guests from either parameter
     */
    public function getGuestsCount(): int
    {
        return $this->guests ?? $this->number_of_guests ?? 1;
    }
}
