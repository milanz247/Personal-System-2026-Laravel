<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => 'required|in:income,expense,transfer',
            'amount' => 'required|numeric|min:0.01|max:99999999999.99',
            'date' => 'required|date|before_or_equal:today',
            'account_id' => 'required|integer|exists:accounts,id',
            'to_account_id' => 'required_if:type,transfer|nullable|integer|exists:accounts,id|different:account_id',
            'category' => 'required_unless:type,transfer|nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.max' => 'The transaction amount exceeds the maximum allowed limit.',
            'date.before_or_equal' => 'The transaction date cannot be in the future.',
            'to_account_id.different' => 'The source and destination accounts must be different.',
            'to_account_id.required_if' => 'A destination account is required for transfers.',
            'category.required_unless' => 'A category is required for income and expense transactions.',
        ];
    }
}
