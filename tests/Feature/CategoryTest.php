<?php

use App\Models\Account;
use App\Models\Category;
use App\Models\User;

test('a category in use by a transaction cannot be deleted', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'type' => 'bank_account', 'balance' => 10000]);
    $category = Category::create(['user_id' => $user->id, 'name' => 'Groceries', 'type' => 'expense']);

    $this->actingAs($user)->post(route('transactions.store'), [
        'type' => 'expense',
        'amount' => 500,
        'fee' => 0,
        'date' => now()->toDateString(),
        'account_id' => $account->id,
        'category' => 'Groceries',
    ]);

    $response = $this->actingAs($user)->delete(route('categories.destroy', $category));

    $response->assertSessionHasErrors(['category']);
    $this->assertDatabaseHas('categories', ['id' => $category->id]);
});

test('an unused category owned by the user can be deleted', function () {
    $user = User::factory()->create();
    $category = Category::create(['user_id' => $user->id, 'name' => 'Unused Category', 'type' => 'expense']);

    $response = $this->actingAs($user)->delete(route('categories.destroy', $category));

    $response->assertSessionHasNoErrors();
    $this->assertSoftDeleted('categories', ['id' => $category->id]);
});

test('a shared system category cannot be deleted or renamed', function () {
    $user = User::factory()->create();
    $shared = Category::create(['user_id' => null, 'name' => 'Shared Category', 'type' => 'expense']);

    $this->actingAs($user)->delete(route('categories.destroy', $shared))->assertForbidden();
    $this->actingAs($user)->put(route('categories.update', $shared), ['name' => 'Renamed'])->assertForbidden();

    $this->assertDatabaseHas('categories', ['id' => $shared->id, 'name' => 'Shared Category']);
});

test('a user cannot delete or rename another user\'s category', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $category = Category::create(['user_id' => $user1->id, 'name' => 'User 1 Category', 'type' => 'expense']);

    $this->actingAs($user2)->delete(route('categories.destroy', $category))->assertNotFound();
});

test('a user can rename their own category', function () {
    $user = User::factory()->create();
    $category = Category::create(['user_id' => $user->id, 'name' => 'Old Name', 'type' => 'expense']);

    $response = $this->actingAs($user)->put(route('categories.update', $category), [
        'name' => 'New Name',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
});

test('categories index shows shared categories and only the current user\'s own', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    Category::create(['user_id' => null, 'name' => 'Shared One', 'type' => 'expense']);
    Category::create(['user_id' => $user1->id, 'name' => 'User 1 Only', 'type' => 'expense']);
    Category::create(['user_id' => $user2->id, 'name' => 'User 2 Only', 'type' => 'expense']);

    $response = $this->actingAs($user1)->get(route('categories.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('categories', 2)
    );
});

test('a name can be reused after the original category was soft-deleted', function () {
    $user = User::factory()->create();
    $category = Category::create(['user_id' => $user->id, 'name' => 'Reusable Name', 'type' => 'expense']);

    $this->actingAs($user)->delete(route('categories.destroy', $category))->assertSessionHasNoErrors();

    $response = $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Reusable Name',
        'type' => 'expense',
    ]);

    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('categories', ['user_id' => $user->id, 'name' => 'Reusable Name', 'deleted_at' => null]);
});
