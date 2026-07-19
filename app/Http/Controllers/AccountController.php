<?php

namespace App\Http\Controllers;

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
    public function store(Request $request): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:cash_wallet,bank_account,credit_card,investment'],
            'balance' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];

        if ($request->type === 'credit_card') {
            $rules['credit_limit'] = ['required', 'numeric', 'min:0'];
        }

        $request->validate($rules);

        if ($request->name === 'Cash Wallet') {
            throw ValidationException::withMessages([
                'name' => ['The name "Cash Wallet" is reserved for the default cash wallet.'],
            ]);
        }

        $balance = (float) $request->balance;
        if ($request->type === 'credit_card') {
            $balance = -$balance;
        }

        Account::create([
            'name' => $request->name,
            'type' => $request->type,
            'balance' => $balance,
            'currency' => $request->currency ?? 'LKR',
            'credit_limit' => $request->type === 'credit_card' ? $request->credit_limit : null,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account created successfully.'),
        ]);

        return redirect()->back();
    }

    /**
     * Update the specified account's details.
     *
     * Balance is intentionally not editable here — it can only be changed via
     * updateBalance(), which records an offsetting adjustment transaction so the
     * change stays reconcilable against the ledger instead of silently overwriting it.
     */
    public function update(Request $request, Account $account): RedirectResponse
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:cash_wallet,bank_account,credit_card,investment'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];

        if ($request->type === 'credit_card') {
            $rules['credit_limit'] = ['required', 'numeric', 'min:0'];
        }

        $request->validate($rules);

        if ($request->name === 'Cash Wallet' && ($account->name !== 'Cash Wallet' || $account->type !== 'cash_wallet')) {
            throw ValidationException::withMessages([
                'name' => ['The name "Cash Wallet" is reserved for the default cash wallet.'],
            ]);
        }

        $account->update([
            'name' => $request->name,
            'type' => $request->type,
            'currency' => $request->currency ?? 'LKR',
            'credit_limit' => $request->type === 'credit_card' ? $request->credit_limit : null,
        ]);

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
        $request->validate([
            'balance' => ['required', 'numeric', 'min:0'],
        ]);

        $newBalance = (float) $request->balance;
        if ($account->type === 'credit_card') {
            $newBalance = -$newBalance;
        }

        // Validate credit card limit
        if ($account->type === 'credit_card' && abs($newBalance) > (float) $account->credit_limit) {
            throw ValidationException::withMessages([
                'balance' => ['Transaction Declined! This balance adjustment exceeds your credit limit.'],
            ]);
        }

        $oldBalance = (float) $account->balance;
        $diff = $newBalance - $oldBalance;

        DB::transaction(function () use ($account, $newBalance, $diff, $oldBalance) {
            $account->update([
                'balance' => $newBalance,
            ]);

            if ($diff != 0) {
                Transaction::create([
                    'account_id' => $account->id,
                    'type' => $diff > 0 ? 'income' : 'expense',
                    'category' => $diff > 0 ? 'Balance Adjustment / Interest' : 'Balance Adjustment / Loss',
                    'amount' => abs($diff),
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
        if ($account->name === 'Cash Wallet' && $account->type === 'cash_wallet') {
            throw ValidationException::withMessages([
                'account' => ['The default Cash Wallet cannot be deleted.'],
            ]);
        }

        $hasHistory = $account->transactions()->exists() || $account->incomingTransfers()->exists();

        if ($hasHistory) {
            throw ValidationException::withMessages([
                'account' => ['This account has transaction history and cannot be deleted. Remove or reassign its transactions first, or keep it as a record of past activity.'],
            ]);
        }

        $account->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Account deleted successfully.'),
        ]);

        return redirect()->back();
    }
}
