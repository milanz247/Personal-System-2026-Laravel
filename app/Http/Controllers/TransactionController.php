<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionRequest;
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

        $categories = Category::query()
            ->whereNull('user_id')
            ->orWhere('user_id', auth()->id())
            ->orderBy('name')
            ->get();

        return Inertia::render('transactions/Index', [
            'transactions' => $transactions,
            'accounts' => $accounts,
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created transaction in storage.
     *
     * Uses a DB::transaction with pessimistic row-level locking (lockForUpdate)
     * to prevent race conditions where two concurrent requests could both pass
     * the balance check and overdraw an account.
     */
    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $amount = (float) $validated['amount'];

        DB::transaction(function () use ($validated, $amount) {
            // Pessimistic lock: SELECT ... FOR UPDATE prevents concurrent reads
            // from seeing stale balances until this transaction commits.
            $sourceAccount = Account::where('id', $validated['account_id'])
                ->lockForUpdate()
                ->firstOrFail();

            // === BALANCE VERIFICATION ===
            if ($validated['type'] === 'expense' || $validated['type'] === 'transfer') {
                if ($sourceAccount->type === 'credit_card') {
                    // Credit card: validate against credit limit ceiling
                    $sourceAccount->validateExpense($amount);
                } else {
                    // Non-credit accounts: strict insufficient funds check
                    if ((float) $sourceAccount->balance < $amount) {
                        throw ValidationException::withMessages([
                            'balance' => ['Insufficient funds in the selected account.'],
                        ]);
                    }
                }
            }

            // === BUILD TRANSACTION RECORD ===
            $transaction = new Transaction();
            $transaction->type = $validated['type'];
            $transaction->amount = $amount;
            $transaction->date = $validated['date'];
            $transaction->description = $validated['description'] ?? null;

            // === BALANCE ADJUSTMENT (Live Math) ===
            if ($validated['type'] === 'income') {
                $sourceAccount->balance += $amount;
                $sourceAccount->save();

                $transaction->account_id = $sourceAccount->id;
                $transaction->category = $validated['category'];
            } elseif ($validated['type'] === 'expense') {
                $sourceAccount->balance -= $amount;
                $sourceAccount->save();

                $transaction->account_id = $sourceAccount->id;
                $transaction->category = $validated['category'];
            } elseif ($validated['type'] === 'transfer') {
                // Lock destination account row as well to prevent deadlocks
                $destinationAccount = Account::where('id', $validated['to_account_id'])
                    ->lockForUpdate()
                    ->firstOrFail();

                // Debit source
                $sourceAccount->balance -= $amount;
                $sourceAccount->save();

                // Credit destination
                $destinationAccount->balance += $amount;
                $destinationAccount->save();

                $transaction->account_id = $sourceAccount->id;
                $transaction->to_account_id = $destinationAccount->id;
                $transaction->category = null;
            }

            $transaction->save();
        });

        return redirect()->back();
    }
}
