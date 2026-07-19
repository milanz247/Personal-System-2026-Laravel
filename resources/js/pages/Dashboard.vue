<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { 
    TrendingUp, 
    Wallet, 
    Banknote, 
    CreditCard
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

defineProps<{
    metrics: {
        liquidCash: number;
        cashInHand: number;
        virtualPockets: number;
        totalDebt: number;
        totalAssets: number;
        netWorth: number;
    };
    accounts: Array<{
        id: number;
        name: string;
        type: 'cash_wallet' | 'bank_account' | 'credit_card' | 'investment';
        balance: string;
        credit_limit: string | null;
        currency: string;
    }>;
    recentTransactions: Array<{
        id: number;
        account_id: number;
        type: 'income' | 'expense';
        category: string;
        amount: string;
        description: string | null;
        date: string;
        account: {
            name: string;
        };
    }>;
}>();

// Helper to format currency values cleanly in LKR
const formatCurrency = (val: number, currency: string = 'LKR') => {
    return new Intl.NumberFormat('en-LK', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(val);
};
</script>

<template>
    <Head title="Dashboard Overview" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <!-- Professional 4-Card Grid Layout with Formula Labels -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Card 1: Net Worth -->
            <Card class="transition duration-200 hover:shadow-sm">
                <CardHeader class="flex flex-row items-start justify-between space-y-0 pb-2">
                    <div class="space-y-0.5">
                        <CardTitle class="text-sm font-semibold tracking-tight text-foreground">
                            Net Worth
                        </CardTitle>
                        <p class="text-[10px] text-muted-foreground font-medium">
                            Total Assets - Total Liabilities
                        </p>
                    </div>
                    <div class="p-2 bg-amber-500/10 text-amber-500 dark:text-amber-400 rounded-lg shrink-0">
                        <TrendingUp class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-foreground">
                        {{ formatCurrency(metrics.netWorth) }}
                    </div>
                </CardContent>
            </Card>

            <!-- Card 2: Liquid Cash -->
            <Card class="transition duration-200 hover:shadow-sm">
                <CardHeader class="flex flex-row items-start justify-between space-y-0 pb-2">
                    <div class="space-y-0.5">
                        <CardTitle class="text-sm font-semibold tracking-tight text-foreground">
                            Liquid Cash
                        </CardTitle>
                        <p class="text-[10px] text-muted-foreground font-medium">
                            Bank Accounts + Cash Wallet
                        </p>
                    </div>
                    <div class="p-2 bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 rounded-lg shrink-0">
                        <Wallet class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-foreground">
                        {{ formatCurrency(metrics.liquidCash) }}
                    </div>
                </CardContent>
            </Card>

            <!-- Card 3: Cash in Hand -->
            <Card class="transition duration-200 hover:shadow-sm">
                <CardHeader class="flex flex-row items-start justify-between space-y-0 pb-2">
                    <div class="space-y-0.5">
                        <CardTitle class="text-sm font-semibold tracking-tight text-foreground">
                            Cash in Hand
                        </CardTitle>
                        <p class="text-[10px] text-muted-foreground font-medium">
                            Cash Wallet
                        </p>
                    </div>
                    <div class="p-2 bg-violet-500/10 text-violet-500 dark:text-violet-400 rounded-lg shrink-0">
                        <Banknote class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-foreground">
                        {{ formatCurrency(metrics.cashInHand) }}
                    </div>
                </CardContent>
            </Card>

            <!-- Card 4: Total Debt -->
            <Card class="bg-red-50/50 dark:bg-red-950/10 border-red-200 dark:border-red-900/50 transition duration-200 hover:shadow-sm">
                <CardHeader class="flex flex-row items-start justify-between space-y-0 pb-2">
                    <div class="space-y-0.5">
                        <CardTitle class="text-sm font-semibold tracking-tight text-red-950 dark:text-red-400">
                            Total Debt
                        </CardTitle>
                        <p class="text-[10px] text-red-900/60 dark:text-red-400/60 font-medium">
                            Credit Card Liabilities
                        </p>
                    </div>
                    <div class="p-2 bg-red-500/10 text-red-500 dark:text-red-400 rounded-lg shrink-0">
                        <CreditCard class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-red-600 dark:text-red-400">
                        {{ formatCurrency(metrics.totalDebt) }}
                    </div>
                </CardContent>
            </Card>

        </div>
    </div>
</template>
