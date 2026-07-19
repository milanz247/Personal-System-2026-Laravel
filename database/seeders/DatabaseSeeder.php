<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create the specific Milan user
        $user = User::factory()->create([
            'email' => 'milan@gmail.com',
            'password' => Hash::make('1q2w1q2w'),
            'mobile_number' => '0777123456',
        ]);

        $user->profile->update([
            'full_name' => 'Milan',
            'current_address' => 'Galle Road, Colombo',
            'permanent_address' => 'Galle Road, Colombo',
        ]);

        // 2. Create financial accounts for Milan
        
        // A. Cash Wallet (Default)
        $cashWallet = Account::create([
            'user_id' => $user->id,
            'name' => 'Cash Wallet',
            'type' => 'cash_wallet',
            'balance' => 15000.00,
            'currency' => 'LKR',
        ]);

        // B. Bank Accounts
        $bocSavings = Account::create([
            'user_id' => $user->id,
            'name' => 'BOC Savings',
            'type' => 'bank_account',
            'balance' => 120000.00,
            'currency' => 'LKR',
        ]);

        $comBank = Account::create([
            'user_id' => $user->id,
            'name' => 'Commercial Current',
            'type' => 'bank_account',
            'balance' => 45000.00,
            'currency' => 'LKR',
        ]);

        // C. Credit Cards (outstanding stored as negative in DB)
        $ntbAmex = Account::create([
            'user_id' => $user->id,
            'name' => 'Sampath Mastercard',
            'type' => 'credit_card',
            'balance' => -35000.00,
            'credit_limit' => 150000.00,
            'currency' => 'LKR',
        ]);

        // D. Virtual Pockets (type is cash_wallet but name is different)
        $emergencyPocket = Account::create([
            'user_id' => $user->id,
            'name' => 'Emergency Pocket',
            'type' => 'cash_wallet',
            'balance' => 25000.00,
            'currency' => 'LKR',
        ]);

        $techFund = Account::create([
            'user_id' => $user->id,
            'name' => 'Tech Fund Pocket',
            'type' => 'cash_wallet',
            'balance' => 12000.00,
            'currency' => 'LKR',
        ]);

        // E. Investments
        $ndbWealth = Account::create([
            'user_id' => $user->id,
            'name' => 'NDB Wealth Fund',
            'type' => 'investment',
            'balance' => 350000.00,
            'currency' => 'LKR',
        ]);

        // 3. Create dummy transactions
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $bocSavings->id,
            'type' => 'income',
            'category' => 'Salary',
            'amount' => 150000.00,
            'description' => 'Monthly Salary Deposit',
            'date' => now()->subDays(5),
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $cashWallet->id,
            'type' => 'expense',
            'category' => 'Food & Dining',
            'amount' => 1250.00,
            'description' => 'Dinner at Restaurant',
            'date' => now()->subDays(3),
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $ntbAmex->id,
            'type' => 'expense',
            'category' => 'Shopping',
            'amount' => 8500.00,
            'description' => 'Clothing & Shoes',
            'date' => now()->subDays(2),
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $comBank->id,
            'type' => 'expense',
            'category' => 'Utilities',
            'amount' => 4500.00,
            'description' => 'Electricity Bill',
            'date' => now()->subDay(),
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $ndbWealth->id,
            'type' => 'income',
            'category' => 'Balance Adjustment / Interest',
            'amount' => 7500.00,
            'description' => 'NDB Monthly Dividend Yield',
            'date' => now(),
        ]);
    }
}
