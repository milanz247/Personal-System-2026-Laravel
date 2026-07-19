<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountRequest;
use App\Http\Requests\UpdateAccountRequest;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display a listing of the accounts.
     */
    public function index(): Response
    {
        $accounts = Account::orderBy('name')->get();

        return Inertia::render('accounts/Index', [
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a newly created account.
     */
    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $balance = (float) $validated['balance'];
        if ($validated['type'] === 'credit_card') {
            $balance = -$balance;
        }

        DB::transaction(function () use ($validated, $balance) {
            $account = Account::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'balance' => $balance,
                'currency' => $validated['currency'] ?? 'LKR',
                'credit_limit' => $validated['type'] === 'credit_card' ? $validated['credit_limit'] : null,
            ]);

            // Record the starting balance as an explicit ledger entry rather than a
            // number that appears from nowhere — keeps Σ(transactions) reconcilable
            // against the account's balance from day one.
            if ($balance != 0) {
                Transaction::create([
                    'account_id' => $account->id,
                    'type' => $balance > 0 ? 'income' : 'expense',
                    'category' => 'Opening Balance',
                    'amount' => abs($balance),
                    'balance_after' => $balance,
                    'description' => __('Opening balance recorded at account creation.'),
                    'date' => now(),
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account created successfully.'),
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified account's details (name/currency/credit limit only —
     * neither balance nor type can be changed after creation; see UpdateAccountRequest).
     */
    public function update(UpdateAccountRequest $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $validated = $request->validated();

        if (! empty($validated['updated_at']) && ! $account->updated_at->equalTo($validated['updated_at'])) {
            throw ValidationException::withMessages([
                'conflict' => ['This account was changed elsewhere since you opened it. Reload the page and try again.'],
            ]);
        }

        DB::transaction(function () use ($validated, $account) {
            Account::where('id', $account->id)->lockForUpdate()->firstOrFail();

            $account->update([
                'name' => $validated['name'],
                'currency' => $validated['currency'] ?? 'LKR',
                'credit_limit' => $validated['type'] === 'credit_card' ? $validated['credit_limit'] : null,
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account updated successfully.'),
        ]);

        return redirect()->back();
    }

    /**
     * Update the account balance and record adjustment transactions.
     */
    public function updateBalance(Request $request, Account $account): RedirectResponse
    {
        $this->authorize('update', $account);

        $request->validate([
            'balance' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($request, $account) {
            $account = Account::where('id', $account->id)->lockForUpdate()->firstOrFail();

            $newBalance = (float) $request->balance;
            if ($account->type === 'credit_card') {
                $newBalance = -$newBalance;
            }

            if ($account->type === 'credit_card' && abs($newBalance) > (float) $account->credit_limit) {
                throw ValidationException::withMessages([
                    'balance' => ['Transaction Declined! This balance adjustment exceeds your credit limit.'],
                ]);
            }

            $oldBalance = (float) $account->balance;
            $diff = $newBalance - $oldBalance;

            $account->update(['balance' => $newBalance]);

            if ($diff != 0) {
                Transaction::create([
                    'account_id' => $account->id,
                    'type' => $diff > 0 ? 'income' : 'expense',
                    'category' => $diff > 0 ? 'Balance Adjustment / Interest' : 'Balance Adjustment / Loss',
                    'amount' => abs($diff),
                    'balance_after' => $newBalance,
                    'description' => __('Manual balance update adjustment from :old to :new', [
                        'old' => number_format(abs($oldBalance), 2),
                        'new' => number_format(abs($newBalance), 2),
                    ]),
                    'date' => now(),
                ]);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account balance updated successfully.'),
        ]);

        return redirect()->back();
    }

    /**
     * Remove the specified account from storage.
     */
    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        if ($account->name === 'Cash Wallet' && $account->type === 'cash_wallet') {
            throw ValidationException::withMessages([
                'account' => ['The default Cash Wallet cannot be deleted.'],
            ]);
        }

        DB::transaction(function () use ($account) {
            $account = Account::where('id', $account->id)->lockForUpdate()->firstOrFail();

            $hasHistory = $account->transactions()->exists() || $account->incomingTransfers()->exists();

            if ($hasHistory) {
                throw ValidationException::withMessages([
                    'account' => ['This account has transaction history and cannot be deleted. Remove or reassign its transactions first, or keep it as a record of past activity.'],
                ]);
            }

            if ((float) $account->balance !== 0.0) {
                throw ValidationException::withMessages([
                    'account' => ['This account still has a non-zero balance ('.number_format(abs((float) $account->balance), 2).'). Bring the balance to zero before deleting it, so the money is not silently discarded from your net worth.'],
                ]);
            }

            $account->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account deleted successfully.'),
        ]);

        return redirect()->back();
    }
}
