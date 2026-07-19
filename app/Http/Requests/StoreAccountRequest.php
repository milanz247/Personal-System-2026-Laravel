<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:cash_wallet,bank_account,credit_card,investment'],
            'balance' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];

        if ($this->input('type') === 'credit_card') {
            $rules['credit_limit'] = ['required', 'numeric', 'min:0'];
        }

        return $rules;
    }

    /**
     * Reserve the "Cash Wallet" name for the single default account created at registration.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('name') === 'Cash Wallet') {
                $validator->errors()->add('name', 'The name "Cash Wallet" is reserved for the default cash wallet.');
            }
        });
    }
}
