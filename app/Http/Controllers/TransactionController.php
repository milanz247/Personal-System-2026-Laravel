<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        return Inertia::render('transactions/Index', [
            'transactions' => $transactions,
            'accounts' => $accounts,
        ]);
    }

    /**
     * Store a newly created transaction in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:income,expense,transfer',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'account_id' => 'required|exists:accounts,id',
            'to_account_id' => 'required_if:type,transfer|nullable|exists:accounts,id|different:account_id',
            'category' => 'required_unless:type,transfer|nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $fromAccount = Account::where('id', $request->account_id)->firstOrFail();
            $amount = (float) $request->amount;

            $transaction = new Transaction();
            $transaction->type = $request->type;
            $transaction->amount = $amount;
            $transaction->date = $request->date;
            $transaction->description = $request->description;

            if ($request->type === 'income') {
                $fromAccount->balance += $amount;
                $fromAccount->save();

                $transaction->account_id = $fromAccount->id;
                $transaction->category = $request->category;
            } elseif ($request->type === 'expense') {
                // If it's a credit card, check limit
                if ($fromAccount->type === 'credit_card') {
                    $fromAccount->validateExpense($amount);
                }

                $fromAccount->balance -= $amount;
                $fromAccount->save();

                $transaction->account_id = $fromAccount->id;
                $transaction->category = $request->category;
            } elseif ($request->type === 'transfer') {
                $toAccount = Account::where('id', $request->to_account_id)->firstOrFail();

                // Subtract from source, add to destination
                $fromAccount->balance -= $amount;
                $fromAccount->save();

                $toAccount->balance += $amount;
                $toAccount->save();

                $transaction->account_id = $fromAccount->id;
                $transaction->to_account_id = $toAccount->id;
                $transaction->category = null;
            }

            $transaction->save();
        });

        return redirect()->back();
    }
}
