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
            'date' => ['required', 'date', 'after_or_equal:today'],
            'time' => ['required', 'date_format:H:i'],
            'number_of_guests' => ['required', 'integer', 'min:1', 'max:20']
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'table_id.required' => 'Table selection is required.',
            'table_id.exists' => 'Selected table does not exist.',
            'date.required' => 'Reservation date is required.',
            'date.after_or_equal' => 'Reservation date must be today or in the future.',
            'time.required' => 'Reservation time is required.',
            'time.date_format' => 'Time must be in HH:MM format.',
            'number_of_guests.required' => 'Number of guests is required.',
            'number_of_guests.min' => 'At least 1 guest is required.',
            'number_of_guests.max' => 'Maximum 20 guests allowed.'
        ];
    }
}
