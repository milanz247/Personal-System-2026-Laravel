<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;

test('updating a transaction reverses the old balance effect before applying the new one', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank_account', 'balance' => 10000]);
    Category::create(['user_id' => $user->id, 'name' => 'Groceries', 'type' => 'expense']);

    $store = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'amount' => 1000,
        'fee' => 0,
        'date' => now()->toDateString(),
        'account_id' => $account->id,
        'category' => 'Groceries',
    ]);
    $store->assertSessionHasNoErrors();

    $account->refresh();
    expect((float) $account->balance)->toBe(9000.0);

    $transaction = Transaction::first();

    $update = $this
        ->actingAs($user)
        ->put(route('transactions.update', $transaction), [
            'type' => 'expense',
            'amount' => 2500,
            'fee' => 0,
            'date' => now()->toDateString(),
            'account_id' => $account->id,
            'category' => 'Groceries',
        ]);
    $update->assertSessionHasNoErrors();

    $account->refresh();
    // 10000 - 2500, not 9000 - 2500: the original 1000 expense was reversed first.
    expect((float) $account->balance)->toBe(7500.0);
});

test('deleting a transaction reverses its balance effect', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank_account', 'balance' => 10000]);
    Category::create(['user_id' => $user->id, 'name' => 'Salary', 'type' => 'income']);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'income',
        'amount' => 5000,
        'fee' => 0,
        'date' => now()->toDateString(),
        'account_id' => $account->id,
        'category' => 'Salary',
    ]);

    $account->refresh();
    expect((float) $account->balance)->toBe(15000.0);

    $transaction = Transaction::first();

    $this->actingAs($user)->delete(route('transactions.destroy', $transaction))
        ->assertSessionHasNoErrors();

    $account->refresh();
    expect((float) $account->balance)->toBe(10000.0);
    $this->assertDatabaseMissing('transactions', ['id' => $transaction->id]);
});

test('deleting a transfer reverses the effect on both accounts', function () {
    $user = User::factory()->create();
    $from = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank_account', 'balance' => 10000]);
    $to = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank_account', 'balance' => 2000]);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'transfer',
        'amount' => 3000,
        'fee' => 0,
        'date' => now()->toDateString(),
        'account_id' => $from->id,
        'to_account_id' => $to->id,
    ]);

    $from->refresh();
    $to->refresh();
    expect((float) $from->balance)->toBe(7000.0);
    expect((float) $to->balance)->toBe(5000.0);

    $transaction = Transaction::first();
    $this->actingAs($user)->delete(route('transactions.destroy', $transaction))
        ->assertSessionHasNoErrors();

    $from->refresh();
    $to->refresh();
    expect((float) $from->balance)->toBe(10000.0);
    expect((float) $to->balance)->toBe(2000.0);
});

test('transfers between accounts with different currencies are blocked', function () {
    $user = User::factory()->create();
    $from = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank_account', 'balance' => 10000, 'currency' => 'LKR']);
    $to = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank_account', 'balance' => 2000, 'currency' => 'USD']);

    $response = $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'transfer',
        'amount' => 1000,
        'fee' => 0,
        'date' => now()->toDateString(),
        'account_id' => $from->id,
        'to_account_id' => $to->id,
    ]);

    $response->assertSessionHasErrors(['to_account_id']);

    $from->refresh();
    $to->refresh();
    expect((float) $from->balance)->toBe(10000.0);
    expect((float) $to->balance)->toBe(2000.0);
});

test('an account with transaction history cannot be deleted', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank_account', 'balance' => 10000]);
    Category::create(['user_id' => $user->id, 'name' => 'Groceries', 'type' => 'expense']);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'amount' => 500,
        'fee' => 0,
        'date' => now()->toDateString(),
        'account_id' => $account->id,
        'category' => 'Groceries',
    ]);

    $response = $this->actingAs($user)->delete(route('accounts.destroy', $account));

    $response->assertSessionHasErrors(['account']);
    $this->assertDatabaseHas('accounts', ['id' => $account->id]);
});
