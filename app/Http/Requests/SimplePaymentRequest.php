<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SimplePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'payment_option' => 'required|integer|in:1,2',
            'currency' => 'sometimes|string|in:USD,EUR,GBP',
            'description' => 'sometimes|string|max:255',
            'reference_id' => 'sometimes|string|max:50'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'payment_option.required' => 'Payment option is required',
            'payment_option.in' => 'Payment option must be 1 (Full Service) or 2 (Service Only)',
            'currency.in' => 'Currency must be USD, EUR, or GBP'
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'payment_option' => 'payment option',
        ];
    }
}
