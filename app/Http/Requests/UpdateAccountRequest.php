<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Balance is intentionally absent — it can only change via AccountController::updateBalance(),
     * which records an offsetting adjustment transaction so it stays reconcilable against the ledger.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:cash_wallet,bank_account,credit_card,investment'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];

        if ($this->input('type') === 'credit_card') {
            $rules['credit_limit'] = ['required', 'numeric', 'min:0'];
        }

        // Optimistic-locking token: the updated_at the client last saw.
        $rules['updated_at'] = ['nullable', 'string'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $account = $this->route('account');

            if ($this->input('name') === 'Cash Wallet' && ($account->name !== 'Cash Wallet' || $account->type !== 'cash_wallet')) {
                $validator->errors()->add('name', 'The name "Cash Wallet" is reserved for the default cash wallet.');
            }

            // The account's type determines how its balance sign is interpreted (credit
            // cards store debt as negative). Changing it after creation would silently
            // reinterpret the existing balance under the new type's rules.
            if ($this->input('type') !== $account->type) {
                $validator->errors()->add('type', 'An account\'s type cannot be changed after creation. Create a new account instead.');
            }
        });
    }
}
