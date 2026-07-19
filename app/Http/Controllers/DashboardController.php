<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the financial dashboard overview.
     */
    public function index(Request $request): Response
    {
        // Fetch user's accounts (scoped automatically by user_isolation global scope)
        $accounts = Account::all();

        // There is no exchange-rate mechanism anywhere in the system, so summing
        // balances across different currencies would silently produce a meaningless
        // number. Instead, the headline metrics only ever combine accounts that share
        // the user's most common currency; anything else is reported as excluded so
        // the UI can warn rather than lie.
        $primaryCurrency = $accounts->isEmpty()
            ? 'LKR'
            : $accounts->countBy('currency')->sortDesc()->keys()->first();

        $primaryAccounts = $accounts->where('currency', $primaryCurrency);
        $excludedAccountsCount = $accounts->count() - $primaryAccounts->count();

        // 1. Liquid Cash:
        // Sum of balances of 'bank_account' type PLUS default 'Cash Wallet'.
        $liquidCash = (float) $primaryAccounts->filter(function ($account) {
            return $account->type === 'bank_account' ||
                ($account->type === 'cash_wallet' && $account->name === 'Cash Wallet');
        })->sum('balance');

        // 2. Cash in Hand:
        // Balance of the default 'Cash Wallet'.
        $cashInHand = (float) $primaryAccounts->filter(function ($account) {
            return $account->type === 'cash_wallet' && $account->name === 'Cash Wallet';
        })->sum('balance');

        // 3. Virtual Pockets:
        // Sum of balances of budgeting wallets (type 'cash_wallet' and name NOT 'Cash Wallet').
        $virtualPockets = (float) $primaryAccounts->filter(function ($account) {
            return $account->type === 'cash_wallet' && $account->name !== 'Cash Wallet';
        })->sum('balance');

        // 4. Total Debt:
        // Sum of all credit card balances (returned as positive number).
        $totalDebt = (float) abs($primaryAccounts->where('type', 'credit_card')->sum('balance'));

        // 5. Total Assets:
        // Calculated as (liquidCash + virtualPockets + any investments).
        $investments = (float) $primaryAccounts->where('type', 'investment')->sum('balance');
        $totalAssets = $liquidCash + $virtualPockets + $investments;

        // 6. Net Worth:
        // Calculated as (totalAssets - totalDebt).
        $netWorth = $totalAssets - $totalDebt;

        // Recent transaction history (limit 5)
        $recentTransactions = Transaction::with('account')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // === CATEGORY-WISE SPENDING BREAKDOWN (this month) ===
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $categoryBreakdown = Transaction::query()
            ->where('type', 'expense')
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->whereNotNull('category')
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total' => (float) $row->total,
                'count' => (int) $row->count,
            ])
            ->values()
            ->toArray();

        // === MONTHLY TOTALS (this month, primary-currency accounts only) ===
        $primaryAccountIds = $primaryAccounts->pluck('id');

        $monthlyIncome = (float) Transaction::query()
            ->where('type', 'income')
            ->whereIn('account_id', $primaryAccountIds)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyExpenses = (float) Transaction::query()
            ->where('type', 'expense')
            ->whereIn('account_id', $primaryAccountIds)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('amount');

        $monthlyFees = (float) Transaction::query()
            ->whereIn('account_id', $primaryAccountIds)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->sum('fee');

        return Inertia::render('Dashboard', [
            'metrics' => [
                'liquidCash' => $liquidCash,
                'cashInHand' => $cashInHand,
                'virtualPockets' => $virtualPockets,
                'totalDebt' => $totalDebt,
                'totalAssets' => $totalAssets,
                'netWorth' => $netWorth,
                'monthlyIncome' => $monthlyIncome,
                'monthlyExpenses' => $monthlyExpenses,
                'monthlyFees' => $monthlyFees,
                'primaryCurrency' => $primaryCurrency,
                'excludedAccountsCount' => $excludedAccountsCount,
            ],
            'accounts' => $accounts,
            'recentTransactions' => $recentTransactions,
            'categoryBreakdown' => $categoryBreakdown,
        ]);
    }
}
