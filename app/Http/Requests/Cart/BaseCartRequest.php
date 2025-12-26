<?php

namespace App\Http\Requests\Cart;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseCartRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the base quantity validation rules.
     *
     * @return array
     */
    protected function quantityRules(): array
    {
        return [
            'required',
            'integer',
            'min:1',
            'max:1000'
        ];
    }

    /**
     * Get custom error messages for quantity validation.
     *
     * @return array<string, string>
     */
    protected function quantityMessages(): array
    {
        return [
            'quantity.required' => 'Please specify the quantity.',
            'quantity.integer' => 'Quantity must be a valid number.',
            'quantity.min' => 'Quantity must be at least 1.',
            'quantity.max' => 'Quantity cannot exceed 1000 items.',
        ];
    }
}

