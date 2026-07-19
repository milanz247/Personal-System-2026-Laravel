<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('authenticated user can view accounts dashboard', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('accounts.index'));

    $response->assertOk();
});

test('authenticated user can create an account and user_id is automatically associated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('accounts.store'), [
            'name' => 'Commercial Bank Savings',
            'type' => 'bank_account',
            'balance' => 50000.50,
            'currency' => 'LKR',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'Commercial Bank Savings',
        'type' => 'bank_account',
        'balance' => 50000.50,
    ]);
});

test('strict data isolation prevents users from viewing other users accounts', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    // Create accounts for both users
    $account1 = Account::factory()->create(['user_id' => $user1->id, 'name' => 'User 1 Bank']);
    $account2 = Account::factory()->create(['user_id' => $user2->id, 'name' => 'User 2 Bank']);

    // Act as user1 and get index
    $response = $this
        ->actingAs($user1)
        ->get(route('accounts.index'));

    $response->assertOk();

    // Assert that only user1's accounts are passed to Inertia view
    $response->assertInertia(fn ($page) => $page
        ->has('accounts', 1)
        ->where('accounts.0.name', 'User 1 Bank')
    );
});

test('authenticated user can update their own account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'name' => 'Old Wallet Name', 'type' => 'bank_account', 'balance' => 1000]);

    $response = $this
        ->actingAs($user)
        ->put(route('accounts.update', $account), [
            'name' => 'New Wallet Name',
            'type' => 'bank_account',
        ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'name' => 'New Wallet Name',
        // Balance is not editable via this endpoint — only updateBalance() may change
        // it, so it stays reconcilable against the transaction ledger.
        'balance' => 1000,
    ]);
});

test('updating an account does not accept or change the balance', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank_account', 'balance' => 1000]);

    $response = $this
        ->actingAs($user)
        ->put(route('accounts.update', $account), [
            'name' => $account->name,
            'type' => 'bank_account',
            'balance' => 999999,
        ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'balance' => 1000,
    ]);
});

test('strict data isolation prevents users from updating other users accounts', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $accountOfUser1 = Account::factory()->create(['user_id' => $user1->id, 'name' => 'Secret Account']);

    // Attempt update as user2
    $response = $this
        ->actingAs($user2)
        ->put(route('accounts.update', $accountOfUser1), [
            'name' => 'Hacked Name',
            'type' => 'cash_wallet',
            'balance' => 999999,
        ]);

    // The global scope filters out account1 for user2, resulting in a 404
    $response->assertNotFound();
    $this->assertDatabaseHas('accounts', [
        'id' => $accountOfUser1->id,
        'name' => 'Secret Account',
    ]);
});

test('authenticated user can delete their own account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('accounts.destroy', $account));

    $response->assertRedirect();
    $this->assertDatabaseMissing('accounts', [
        'id' => $account->id,
    ]);
});

test('strict data isolation prevents users from deleting other users accounts', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $accountOfUser1 = Account::factory()->create(['user_id' => $user1->id]);

    // Attempt delete as user2
    $response = $this
        ->actingAs($user2)
        ->delete(route('accounts.destroy', $accountOfUser1));

    $response->assertNotFound();
    $this->assertDatabaseHas('accounts', [
        'id' => $accountOfUser1->id,
    ]);
});

test('authenticated user can create a credit card with limit and negative balance is stored', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('accounts.store'), [
            'name' => 'HSBC Cashback',
            'type' => 'credit_card',
            'balance' => 35000.00, // outstanding debt entered as positive
            'credit_limit' => 100000.00,
            'currency' => 'LKR',
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('accounts', [
        'user_id' => $user->id,
        'name' => 'HSBC Cashback',
        'type' => 'credit_card',
        'balance' => -35000.00, // stored as negative in DB
        'credit_limit' => 100000.00,
    ]);
});

test('authenticated user can update a credit card name and credit limit but not its balance', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'type' => 'credit_card',
        'balance' => -20000.00,
        'credit_limit' => 50000.00,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('accounts.update', $account), [
            'name' => 'HSBC Premium CC',
            'type' => 'credit_card',
            'balance' => 45000.00, // ignored — balance can only change via updateBalance()
            'credit_limit' => 150000.00,
        ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'name' => 'HSBC Premium CC',
        'balance' => -20000.00, // unchanged
        'credit_limit' => 150000.00,
    ]);
});

test('credit card validation prevents transaction exceeding available credit limit', function () {
    $account = Account::factory()->create([
        'type' => 'credit_card',
        'balance' => -40000.00, // used credit = 40,000
        'credit_limit' => 100000.00, // credit limit = 100,000
    ]);

    // Available credit = 60,000
    // Expense of 50,000 should be allowed
    expect($account->hasAvailableCredit(50000))->toBeTrue();
    $account->validateExpense(50000); // should not throw exception

    // Expense of 70,000 should be declined
    expect($account->hasAvailableCredit(70000))->toBeFalse();

    $this->expectException(ValidationException::class);
    $account->validateExpense(70000); // should throw ValidationException
});

test('authenticated user can update balance of an asset which automatically creates an income transaction if balance increases', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'type' => 'bank_account',
        'balance' => 10000.00,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('accounts.update-balance', $account), [
            'balance' => 12500.00, // Balance increased by 2500
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'balance' => 12500.00,
    ]);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'income',
        'category' => 'Balance Adjustment / Interest',
        'amount' => 2500.00,
    ]);
});

test('authenticated user can update balance of an asset which automatically creates an expense transaction if balance decreases', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'type' => 'bank_account',
        'balance' => 10000.00,
    ]);

    $response = $this
        ->actingAs($user)
        ->put(route('accounts.update-balance', $account), [
            'balance' => 8500.00, // Balance decreased by 1500
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'balance' => 8500.00,
    ]);

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'expense',
        'category' => 'Balance Adjustment / Loss',
        'amount' => 1500.00,
    ]);
});

test('authenticated user can update credit card outstanding debt which automatically creates adjustment transaction', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'type' => 'credit_card',
        'balance' => -20000.00, // Current Outstanding debt = 20000
        'credit_limit' => 50000.00,
    ]);

    // Update Outstanding debt to 35000 (meaning balance becomes -35000, decreasing by 15000)
    $response = $this
        ->actingAs($user)
        ->put(route('accounts.update-balance', $account), [
            'balance' => 35000.00,
        ]);

    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'balance' => -35000.00,
    ]);

    // Decreased balance = Expense transaction
    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id,
        'account_id' => $account->id,
        'type' => 'expense',
        'category' => 'Balance Adjustment / Loss',
        'amount' => 15000.00,
    ]);
});

test('updating credit card balance above credit limit is blocked by validation', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create([
        'user_id' => $user->id,
        'type' => 'credit_card',
        'balance' => -20000.00,
        'credit_limit' => 50000.00,
    ]);

    // Update Outstanding debt to 60000 (exceeds limit of 50000)
    $response = $this
        ->actingAs($user)
        ->put(route('accounts.update-balance', $account), [
            'balance' => 60000.00,
        ]);

    $response->assertSessionHasErrors(['balance']);

    // Balance should remain unchanged in database
    $this->assertDatabaseHas('accounts', [
        'id' => $account->id,
        'balance' => -20000.00,
    ]);
});
