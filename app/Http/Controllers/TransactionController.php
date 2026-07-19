<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display a listing of the transactions.
     */
    public function index(): Response
    {
        $transactions = Transaction::with(['account', 'toAccount'])
            ->orderBy('date', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        $accounts = Account::orderBy('name', 'asc')->get();

        $categories = Category::query()->orderBy('name')->get();

        return Inertia::render('transactions/Index', [
            'transactions' => $transactions,
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created transaction in storage.
     *
     * Real-world financial logic:
     * - Income: amount goes INTO the account (fee deducted from incoming amount)
     * - Expense: amount + fee deducted FROM the account
     * - Transfer: amount goes to destination, but source loses (amount + fee)
     *
     * Example: ATM withdrawal of LKR 10,000 with LKR 50 fee
     *   → Bank account loses LKR 10,050 (amount + fee)
     *   → Cash wallet receives LKR 10,000 (just the amount)
     *   → Transaction record shows: amount=10000, fee=50
     */
    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $amount = (float) $validated['amount'];
        $fee = (float) ($validated['fee'] ?? 0);

        // Guard against double-clicks and network-retry double-submits: an identical
        // transaction created moments ago is treated as a duplicate, not a new entry.
        $isDuplicate = Transaction::query()
            ->where('account_id', $validated['account_id'])
            ->where('type', $validated['type'])
            ->where('amount', $amount)
            ->where('fee', $fee)
            ->where('to_account_id', $validated['to_account_id'] ?? null)
            ->where('category', $validated['category'] ?? null)
            ->where('description', $validated['description'] ?? null)
            ->where('created_at', '>=', now()->subSeconds(5))
            ->exists();

        if ($isDuplicate) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('This looks identical to the transaction you just submitted, so it was not recorded again.'),
            ]);

            return redirect()->back();
        }

        DB::transaction(function () use ($validated, $amount, $fee) {
            $transaction = new Transaction;
            $this->applyTransactionEffect($transaction, $validated, $amount, $fee);
            $transaction->save();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Transaction recorded successfully.'),
        ]);

        return redirect()->back();
    }

    /**
     * Update an existing transaction: reverse its original balance effect,
     * then apply the new values as if it were being created fresh.
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $validated = $request->validated();
        $amount = (float) $validated['amount'];
        $fee = (float) ($validated['fee'] ?? 0);

        if (! empty($validated['updated_at']) && ! $transaction->updated_at->equalTo($validated['updated_at'])) {
            throw ValidationException::withMessages([
                'conflict' => ['This transaction was changed elsewhere since you opened it. Reload the page and try again.'],
            ]);
        }

        DB::transaction(function () use ($transaction, $validated, $amount, $fee) {
            $this->reverseTransactionEffect($transaction);
            $this->applyTransactionEffect($transaction, $validated, $amount, $fee);
            $transaction->save();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Transaction updated successfully.'),
        ]);

        return redirect()->back();
    }

    /**
     * Delete a transaction and reverse its balance effect on the affected account(s).
     */
    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        DB::transaction(function () use ($transaction) {
            $this->reverseTransactionEffect($transaction);
            $transaction->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Transaction deleted successfully.'),
        ]);

        return redirect()->back();
    }

    /**
     * Validate and apply a transaction's balance effect on the affected account(s),
     * populating the given (new or being-edited) Transaction instance in the process.
     * Locks every account row it touches to prevent concurrent-write races.
     *
     * @param  array<string, mixed>  $validated
     */
    private function applyTransactionEffect(Transaction $transaction, array $validated, float $amount, float $fee): void
    {
        $sourceAccount = Account::where('id', $validated['account_id'])
            ->lockForUpdate()
            ->firstOrFail();

        $totalDebit = $amount + $fee;

        if ($validated['type'] === 'expense' || $validated['type'] === 'transfer') {
            if ($sourceAccount->type === 'credit_card') {
                $sourceAccount->validateExpense($totalDebit);
            } elseif ((float) $sourceAccount->balance < $totalDebit) {
                throw ValidationException::withMessages([
                    'balance' => ['Insufficient funds. You need '.number_format($totalDebit, 2).' but only have '.number_format((float) $sourceAccount->balance, 2).' available.'],
                ]);
            }
        }

        $transaction->type = $validated['type'];
        $transaction->amount = $amount;
        $transaction->fee = $fee;
        $transaction->date = $validated['date'];
        $transaction->description = $validated['description'] ?? null;

        if ($validated['type'] === 'income') {
            // Income: add amount to account, fee is deducted from incoming
            $netCredit = $amount - $fee;
            $sourceAccount->balance += $netCredit;
            $sourceAccount->save();

            $transaction->account_id = $sourceAccount->id;
            $transaction->to_account_id = null;
            $transaction->category = $validated['category'];
            $transaction->balance_after = $sourceAccount->balance;
        } elseif ($validated['type'] === 'expense') {
            // Expense: deduct amount + fee from account
            $sourceAccount->balance -= $totalDebit;
            $sourceAccount->save();

            $transaction->account_id = $sourceAccount->id;
            $transaction->to_account_id = null;
            $transaction->category = $validated['category'];
            $transaction->balance_after = $sourceAccount->balance;
        } elseif ($validated['type'] === 'transfer') {
            $destinationAccount = Account::where('id', $validated['to_account_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // Cross-currency transfers would silently create/destroy value since no
            // exchange rate is captured anywhere in the system yet — block them.
            if ($destinationAccount->currency !== $sourceAccount->currency) {
                throw ValidationException::withMessages([
                    'to_account_id' => ['Transfers between accounts with different currencies are not supported yet.'],
                ]);
            }

            // Source loses amount + fee (ATM fee, bank transfer charge, etc.)
            $sourceAccount->balance -= $totalDebit;
            $sourceAccount->save();

            // Destination receives only the amount (fee doesn't transfer)
            $destinationAccount->balance += $amount;
            $destinationAccount->save();

            $transaction->account_id = $sourceAccount->id;
            $transaction->to_account_id = $destinationAccount->id;
            $transaction->category = null;
            // Snapshot the source account's balance — the transaction row only has
            // room for one snapshot, and the source is the "primary" side of a transfer.
            $transaction->balance_after = $sourceAccount->balance;
        }
    }

    /**
     * Undo a persisted transaction's balance effect on the account(s) it originally
     * touched, using its stored type/amount/fee — the exact inverse of applyTransactionEffect.
     */
    private function reverseTransactionEffect(Transaction $transaction): void
    {
        $amount = (float) $transaction->amount;
        $fee = (float) $transaction->fee;
        $totalDebit = $amount + $fee;

        if ($transaction->type === 'income') {
            $account = Account::where('id', $transaction->account_id)->lockForUpdate()->firstOrFail();
            $account->balance -= ($amount - $fee);
            $account->save();
        } elseif ($transaction->type === 'expense') {
            $account = Account::where('id', $transaction->account_id)->lockForUpdate()->firstOrFail();
            $account->balance += $totalDebit;
            $account->save();
        } elseif ($transaction->type === 'transfer') {
            $source = Account::where('id', $transaction->account_id)->lockForUpdate()->firstOrFail();
            $destination = Account::where('id', $transaction->to_account_id)->lockForUpdate()->firstOrFail();

            $source->balance += $totalDebit;
            $source->save();

            $destination->balance -= $amount;
            $destination->save();
        }
    }
}
