<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProcessPaymentRequest extends FormRequest
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
            'payment_option' => 'required|integer|in:1,2',
            'payment_gateway' => 'required|string|in:paypal',
            'payment_data' => 'sometimes|array',

            // PayPal specific validation
            'payment_data.currency' => 'sometimes|string|in:USD,EUR,GBP,CAD,AUD,JPY',
            'payment_data.description' => 'sometimes|string|max:255',
            'payment_data.metadata' => 'sometimes|array',

            // General items validation
            'payment_data.items' => 'sometimes|array',
            'payment_data.items.*.name' => 'required_with:payment_data.items|string',
            'payment_data.items.*.price' => 'required_with:payment_data.items|numeric|min:0',
            'payment_data.items.*.quantity' => 'required_with:payment_data.items|integer|min:1',
        ];
    }

    /**
     * Get custom error messages.
     */
    public function messages(): array
    {
        return [
            'payment_option.required' => 'Payment option is required.',
            'payment_option.in' => 'Payment option must be 1 (Full Service Package) or 2 (Service Only).',
            'payment_gateway.required' => 'Payment gateway is required.',
            'payment_gateway.in' => 'Payment gateway must be PayPal.',
            'payment_data.currency.in' => 'Currency must be USD, EUR, GBP, CAD, AUD, or JPY.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'payment_option' => 'payment option',
            'payment_gateway' => 'payment gateway',
            'payment_data.currency' => 'currency',
            'payment_data.description' => 'payment description',
        ];
    }
}
