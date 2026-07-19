<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['cash_wallet', 'bank_account', 'credit_card', 'investment']);
        $balance = $this->faker->randomFloat(2, 500, 50000);

        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true) . ' Wallet',
            'type' => $type,
            'currency' => 'LKR',
            'balance' => $type === 'credit_card' ? -$balance : $balance,
            'credit_limit' => $type === 'credit_card' ? 100000.00 : null,
        ];
    }
}
