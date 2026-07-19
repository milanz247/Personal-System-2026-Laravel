<?php

use App\Models\Account;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard and see metrics', function () {
    $user = User::factory()->create();
    
    // Create Default Cash Wallet
    Account::create([
        'user_id' => $user->id,
        'name' => 'Cash Wallet',
        'type' => 'cash_wallet',
        'balance' => 5000.00,
        'currency' => 'LKR',
    ]);

    // Create Virtual Pocket
    Account::create([
        'user_id' => $user->id,
        'name' => 'Emergency Fund',
        'type' => 'cash_wallet',
        'balance' => 15000.00,
        'currency' => 'LKR',
    ]);

    // Create Bank Account
    Account::create([
        'user_id' => $user->id,
        'name' => 'HSBC Savings',
        'type' => 'bank_account',
        'balance' => 80000.00,
        'currency' => 'LKR',
    ]);

    // Create Credit Card
    Account::create([
        'user_id' => $user->id,
        'name' => 'Commercial Visa',
        'type' => 'credit_card',
        'balance' => -25000.00,
        'credit_limit' => 100000.00,
        'currency' => 'LKR',
    ]);

    // Create Investment Account
    Account::create([
        'user_id' => $user->id,
        'name' => 'CAL Gilt Fund',
        'type' => 'investment',
        'balance' => 50000.00,
        'currency' => 'LKR',
    ]);

    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->where('metrics.netWorth', 125000.0)
        ->where('metrics.liquidCash', 85000.0)
        ->where('metrics.virtualPockets', 15000.0)
        ->where('metrics.totalDebt', 25000.0)
        ->where('metrics.cashInHand', 5000.0)
        ->where('metrics.totalAssets', 150000.0)
        ->has('accounts', 5)
        ->has('debtTrend', 6)
        ->where('debtTrend.5', 25000.0)
        ->has('monthlyTrend', 6)
        ->where('monthlyTrend.5.income', 0.0)
        ->where('monthlyTrend.5.expense', 0.0)
    );
});

test('dashboard isolation respects user boundaries', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // User 1 account
    Account::create([
        'user_id' => $user1->id,
        'name' => 'Cash Wallet',
        'type' => 'cash_wallet',
        'balance' => 1000.00,
        'currency' => 'LKR',
    ]);

    // User 2 account
    Account::create([
        'user_id' => $user2->id,
        'name' => 'Cash Wallet',
        'type' => 'cash_wallet',
        'balance' => 2000.00,
        'currency' => 'LKR',
    ]);

    $this->actingAs($user1);

    $response = $this->get(route('dashboard'));
    
    $response->assertInertia(fn ($page) => $page
        ->where('metrics.netWorth', 1000.0)
        ->where('metrics.cashInHand', 1000.0)
        ->has('accounts', 1)
        ->where('accounts.0.user_id', $user1->id)
    );
});