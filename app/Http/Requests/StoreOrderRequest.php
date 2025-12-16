<?php

namespace App\Http\Requests;

use App\Helpers\TradingConfig;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization checked via middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'symbol' => [
                'required',
                'string',
                Rule::in(TradingConfig::supportedSymbols()),
            ],
            'side' => [
                'required',
                'string',
                Rule::in(['buy', 'sell']),
            ],
            'price' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\d+(\.\d{1,8})?$/', // Max 8 decimal places
            ],
            'amount' => [
                'required',
                'numeric',
                'gt:0',
                'regex:/^\d+(\.\d{1,8})?$/', // Max 8 decimal places
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'symbol.in' => 'The selected symbol is not supported. Supported symbols: '.implode(', ', TradingConfig::supportedSymbols()),
            'side.in' => 'The side must be either buy or sell.',
            'price.gt' => 'The price must be greater than 0.',
            'amount.gt' => 'The amount must be greater than 0.',
            'price.regex' => 'The price must have at most 8 decimal places.',
            'amount.regex' => 'The amount must have at most 8 decimal places.',
        ];
    }
}
