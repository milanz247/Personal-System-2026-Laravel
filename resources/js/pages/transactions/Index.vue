<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { index as transactionsIndex } from '@/routes/transactions';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetFooter,
} from '@/components/ui/sheet';
import {
    AlertDialog,
    AlertDialogContent,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogCancel,
    AlertDialogAction,
} from '@/components/ui/alert-dialog';
import { 
    Plus,
    Calendar,
    ArrowRightLeft,
    TrendingUp,
    TrendingDown,
    Search,
    Pencil,
    Trash2
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Transactions',
                href: transactionsIndex().url,
            },
        ],
    },
});

const props = defineProps<{
    transactions: Array<{
        id: number;
        user_id: number;
        type: 'income' | 'expense' | 'transfer';
        amount: string;
        fee: string;
        date: string;
        account_id: number | null;
        to_account_id: number | null;
        category: string | null;
        description: string | null;
        updated_at: string;
        account: {
            id: number;
            name: string;
            currency: string;
        } | null;
        to_account: {
            id: number;
            name: string;
        } | null;
    }>;
    accounts: Array<{
        id: number;
        name: string;
        type: 'cash_wallet' | 'bank_account' | 'credit_card' | 'investment';
        balance: string;
        credit_limit: string | null;
        currency: string;
    }>;
    categories: Array<{
        id: number;
        user_id: number | null;
        name: string;
        type: 'expense' | 'income';
        icon: string | null;
    }>;
}>();

// Form & Modal States
const isAddDialogOpen = ref(false);
const todayStr = new Date().toISOString().split('T')[0];

const form = useForm({
    type: 'expense' as 'income' | 'expense' | 'transfer',
    amount: '',
    fee: '',
    date: todayStr,
    account_id: '',
    to_account_id: '',
    category: '',
    description: '',
});

// Dynamic categories filtered by current form type
const availableCategories = computed(() => {
    return props.categories.filter(c => c.type === form.type);
});

// Interactive Search and Filtering
const searchQuery = ref('');
const filterType = ref<'all' | 'income' | 'expense' | 'transfer'>('all');

const filteredTransactions = computed(() => {
    return props.transactions.filter(tx => {
        // Type filter
        if (filterType.value !== 'all' && tx.type !== filterType.value) {
            return false;
        }
        
        // Search query
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase();
            const desc = (tx.description || '').toLowerCase();
            const cat = (tx.category || '').toLowerCase();
            const accName = (tx.account?.name || '').toLowerCase();
            const toAccName = (tx.to_account?.name || '').toLowerCase();
            
            return desc.includes(query) || 
                   cat.includes(query) || 
                   accName.includes(query) || 
                   toAccName.includes(query);
        }
        
        return true;
    });
});

// There is no currency conversion in the system, so the summary cards only ever
// combine transactions whose source account shares the most common account currency
// — matching the Dashboard's approach — rather than silently summing mixed currencies.
const primaryCurrency = computed(() => {
    if (props.accounts.length === 0) return 'LKR';
    const counts = new Map<string, number>();
    for (const acc of props.accounts) {
        counts.set(acc.currency, (counts.get(acc.currency) ?? 0) + 1);
    }
    return [...counts.entries()].sort((a, b) => b[1] - a[1])[0][0];
});

const isPrimaryCurrencyTx = (tx: (typeof props.transactions)[number]) => {
    return (tx.account?.currency ?? primaryCurrency.value) === primaryCurrency.value;
};

// Financial Stats Summary (based on all loaded transactions in the primary currency)
const totalInflow = computed(() => {
    return props.transactions
        .filter(t => t.type === 'income' && isPrimaryCurrencyTx(t))
        .reduce((sum, t) => sum + parseFloat(t.amount), 0);
});

const totalOutflow = computed(() => {
    return props.transactions
        .filter(t => t.type === 'expense' && isPrimaryCurrencyTx(t))
        .reduce((sum, t) => sum + parseFloat(t.amount), 0);
});

const netFlow = computed(() => {
    return totalInflow.value - totalOutflow.value;
});

const excludedCurrencyCount = computed(() => props.transactions.filter(t => !isPrimaryCurrencyTx(t)).length);

// Formatting Utilities
const formatCurrency = (val: number, currency: string = 'LKR') => {
    return new Intl.NumberFormat('en-LK', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(val);
};

const formatDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
};

const getCategoryStyle = (category: string | null) => {
    if (!category) return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800/40 dark:text-zinc-300';
    
    // Look up the category type from the database categories
    const dbCategory = props.categories.find(c => c.name === category);
    
    if (dbCategory?.type === 'income') {
        return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400 border border-emerald-200/50 dark:border-emerald-900/30';
    }
    
    if (dbCategory?.type === 'expense') {
        return 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-400 border border-red-200/50 dark:border-red-900/30';
    }
    
    // Fallback for categories not found in DB (legacy data)
    return 'bg-zinc-50 text-zinc-700 dark:bg-zinc-800/60 dark:text-zinc-300 border border-zinc-200/50 dark:border-zinc-700/50';
};

const submitTransaction = () => {
    const selectedType = form.type;

    form.post('/transactions', {
        onSuccess: () => {
            isAddDialogOpen.value = false;
            form.reset();
            form.type = selectedType;
            form.date = todayStr;
        },
    });
};

// Edit Transaction
const isEditDialogOpen = ref(false);
const editingTransactionId = ref<number | null>(null);

const editForm = useForm({
    type: 'expense' as 'income' | 'expense' | 'transfer',
    amount: '',
    fee: '',
    date: todayStr,
    account_id: '' as string | number,
    to_account_id: '' as string | number,
    category: '',
    description: '',
    // Optimistic-locking token — the updated_at snapshot taken when this row was
    // opened for editing, so a stale edit (changed elsewhere in the meantime) is
    // rejected instead of silently overwriting someone else's more recent change.
    updated_at: '',
});

const editableCategories = computed(() => {
    return props.categories.filter(c => c.type === editForm.type);
});

const openEditSheet = (tx: (typeof props.transactions)[number]) => {
    editingTransactionId.value = tx.id;
    editForm.clearErrors();
    editForm.type = tx.type;
    editForm.amount = tx.amount;
    editForm.fee = tx.fee;
    editForm.date = tx.date.split('T')[0];
    editForm.account_id = tx.account_id ?? '';
    editForm.to_account_id = tx.to_account_id ?? '';
    editForm.category = tx.category ?? '';
    editForm.description = tx.description ?? '';
    editForm.updated_at = tx.updated_at;
    isEditDialogOpen.value = true;
};

const submitEditTransaction = () => {
    if (!editingTransactionId.value) return;
    editForm.put(`/transactions/${editingTransactionId.value}`, {
        onSuccess: () => {
            isEditDialogOpen.value = false;
            editingTransactionId.value = null;
        },
    });
};

const isDeleteDialogOpen = ref(false);
const pendingDeleteTransactionId = ref<number | null>(null);

const deleteTransaction = (tx: (typeof props.transactions)[number]) => {
    pendingDeleteTransactionId.value = tx.id;
    isDeleteDialogOpen.value = true;
};

const confirmDeleteTransaction = () => {
    if (pendingDeleteTransactionId.value === null) {
        return;
    }

    router.delete(`/transactions/${pendingDeleteTransactionId.value}`);
    isDeleteDialogOpen.value = false;
    pendingDeleteTransactionId.value = null;
};
</script>

<template>
    <Head title="Transactions Ledger" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-foreground">Transactions</h1>
                <p class="text-sm text-muted-foreground">Manage and track your income, expenses, and transfers.</p>
            </div>
            <Button @click="isAddDialogOpen = true" class="self-start sm:self-center">
                <Plus class="size-4 mr-2" />
                Add Transaction
            </Button>
        </div>

        <div
            v-if="excludedCurrencyCount > 0"
            class="p-3 bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50 rounded-lg text-xs font-semibold"
        >
            {{ excludedCurrencyCount }} transaction{{ excludedCurrencyCount === 1 ? '' : 's' }} in a different currency than {{ primaryCurrency }}
            {{ excludedCurrencyCount === 1 ? 'is' : 'are' }} not included in the totals below — currency conversion isn't supported yet.
        </div>

        <!-- Premium Mini-Stats Row -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <Card class="bg-card/50 backdrop-blur-sm transition-all duration-300 border-border/60 hover:shadow-sm">
                <CardContent class="p-4 flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground">Total Inflow</span>
                        <div class="text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-500 font-mono tabular-nums">
                            + {{ formatCurrency(totalInflow) }}
                        </div>
                    </div>
                    <div class="p-2.5 bg-emerald-500/10 text-emerald-500 rounded-lg">
                        <TrendingUp class="size-4" />
                    </div>
                </CardContent>
            </Card>

            <Card class="bg-card/50 backdrop-blur-sm transition-all duration-300 border-border/60 hover:shadow-sm">
                <CardContent class="p-4 flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground">Total Outflow</span>
                        <div class="text-xl font-bold tracking-tight text-red-600 dark:text-red-400 font-mono tabular-nums">
                            - {{ formatCurrency(totalOutflow) }}
                        </div>
                    </div>
                    <div class="p-2.5 bg-red-500/10 text-red-500 rounded-lg">
                        <TrendingDown class="size-4" />
                    </div>
                </CardContent>
            </Card>

            <Card class="bg-card/50 backdrop-blur-sm transition-all duration-300 border-border/60 hover:shadow-sm">
                <CardContent class="p-4 flex items-center justify-between">
                    <div class="space-y-1">
                        <span class="text-[10px] uppercase tracking-wider font-semibold text-muted-foreground">Net Period Flow</span>
                        <div class="text-xl font-bold tracking-tight font-mono tabular-nums" :class="netFlow >= 0 ? 'text-primary' : 'text-destructive'">
                            {{ netFlow >= 0 ? '+' : '' }}{{ formatCurrency(netFlow) }}
                        </div>
                    </div>
                    <div class="p-2.5 bg-blue-500/10 text-blue-500 rounded-lg">
                        <ArrowRightLeft class="size-4" />
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Table and Filtering Card -->
        <Card class="bg-card border-border/60 overflow-hidden shadow-sm">
            <CardHeader class="p-4 border-b border-border/60 space-y-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <!-- Search Input -->
                    <div class="relative max-w-sm w-full">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                        <Input 
                            v-model="searchQuery" 
                            type="text" 
                            placeholder="Search description or category..." 
                            class="pl-9 text-sm"
                        />
                    </div>

                    <!-- Type Filter Tabs -->
                    <div class="flex items-center gap-1.5 p-0.5 rounded-lg bg-muted border text-sm font-medium">
                        <button 
                            type="button" 
                            @click="filterType = 'all'"
                            class="px-3 py-1.5 rounded-md transition-all"
                            :class="filterType === 'all' ? 'bg-background text-foreground shadow-sm font-semibold' : 'text-muted-foreground hover:text-foreground'"
                        >
                            All
                        </button>
                        <button 
                            type="button" 
                            @click="filterType = 'expense'"
                            class="px-3 py-1.5 rounded-md transition-all"
                            :class="filterType === 'expense' ? 'bg-background text-foreground shadow-sm font-semibold' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Expenses
                        </button>
                        <button 
                            type="button" 
                            @click="filterType = 'income'"
                            class="px-3 py-1.5 rounded-md transition-all"
                            :class="filterType === 'income' ? 'bg-background text-foreground shadow-sm font-semibold' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Incomes
                        </button>
                        <button 
                            type="button" 
                            @click="filterType = 'transfer'"
                            class="px-3 py-1.5 rounded-md transition-all"
                            :class="filterType === 'transfer' ? 'bg-background text-foreground shadow-sm font-semibold' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Transfers
                        </button>
                    </div>
                </div>
            </CardHeader>

            <!-- Table -->
            <div class="relative w-full overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-muted/30 border-b border-border/60">
                        <tr>
                            <th class="px-4 py-3.5 text-left align-middle font-semibold text-muted-foreground uppercase tracking-wider text-[11px]">Date</th>
                            <th class="px-4 py-3.5 text-left align-middle font-semibold text-muted-foreground uppercase tracking-wider text-[11px]">Description</th>
                            <th class="px-4 py-3.5 text-left align-middle font-semibold text-muted-foreground uppercase tracking-wider text-[11px]">Category</th>
                            <th class="px-4 py-3.5 text-left align-middle font-semibold text-muted-foreground uppercase tracking-wider text-[11px]">Account</th>
                            <th class="px-4 py-3.5 text-left align-middle font-semibold text-muted-foreground uppercase tracking-wider text-[11px]">Type</th>
                            <th class="px-4 py-3.5 text-right align-middle font-semibold text-muted-foreground uppercase tracking-wider text-[11px]">Amount</th>
                            <th class="px-4 py-3.5 text-right align-middle font-semibold text-muted-foreground uppercase tracking-wider text-[11px]">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/40">
                        <tr v-if="filteredTransactions.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-muted-foreground font-medium">
                                No matching transactions found.
                            </td>
                        </tr>
                        <tr 
                            v-for="tx in filteredTransactions" 
                            :key="tx.id" 
                            class="transition-colors hover:bg-muted/10"
                        >
                            <td class="px-4 py-3.5 align-middle text-muted-foreground whitespace-nowrap font-medium">
                                {{ formatDate(tx.date) }}
                            </td>
                            <td class="px-4 py-3.5 align-middle font-medium text-foreground max-w-[200px] truncate">
                                {{ tx.description || '-' }}
                            </td>
                            <td class="px-4 py-3.5 align-middle whitespace-nowrap">
                                <span :class="['px-2.5 py-1 rounded text-xs font-bold tracking-wide border', getCategoryStyle(tx.category)]">
                                    {{ tx.category || 'Transfer' }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 align-middle whitespace-nowrap">
                                <div v-if="tx.type === 'transfer'" class="flex items-center gap-2 text-muted-foreground font-medium">
                                    <span>{{ tx.account?.name }}</span>
                                    <span class="text-muted-foreground/30">➔</span>
                                    <span class="text-foreground font-medium">{{ tx.to_account?.name }}</span>
                                </div>
                                <span v-else class="text-foreground font-medium">
                                    {{ tx.account?.name || '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 align-middle whitespace-nowrap">
                                <span 
                                    class="inline-flex items-center gap-1.5 text-xs font-bold capitalize"
                                    :class="tx.type === 'income' ? 'text-emerald-600 dark:text-emerald-400' : (tx.type === 'expense' ? 'text-red-600 dark:text-red-400' : 'text-blue-600 dark:text-blue-400')"
                                >
                                    <span class="size-2 rounded-full" :class="tx.type === 'income' ? 'bg-emerald-500' : (tx.type === 'expense' ? 'bg-red-500' : 'bg-blue-500')"></span>
                                    {{ tx.type }}
                                </span>
                            </td>
                            <td class="px-4 py-3.5 align-middle text-right font-mono font-semibold tracking-tight text-sm tabular-nums whitespace-nowrap">
                                <div>
                                    <span v-if="tx.type === 'income'" class="text-emerald-600 dark:text-emerald-500">
                                        + {{ formatCurrency(parseFloat(tx.amount), tx.account?.currency) }}
                                    </span>
                                    <span v-else-if="tx.type === 'expense'" class="text-foreground">
                                        - {{ formatCurrency(parseFloat(tx.amount), tx.account?.currency) }}
                                    </span>
                                    <span v-else class="text-muted-foreground">
                                        {{ formatCurrency(parseFloat(tx.amount), tx.account?.currency) }}
                                    </span>
                                </div>
                                <div v-if="parseFloat(tx.fee) > 0" class="text-[10px] text-amber-600 dark:text-amber-500 font-semibold mt-0.5">
                                    + {{ formatCurrency(parseFloat(tx.fee), tx.account?.currency) }} fee
                                </div>
                            </td>
                            <td class="px-4 py-3.5 align-middle text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8"
                                        @click="openEditSheet(tx)"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        class="size-8 text-destructive hover:text-destructive"
                                        @click="deleteTransaction(tx)"
                                    >
                                        <Trash2 class="size-3.5" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </Card>

        <!-- Right Side Slide-over Panel (Sheet) -->
        <Sheet v-model:open="isAddDialogOpen">
            <SheetContent side="right" class="w-full sm:max-w-[550px] p-6 sm:p-8 overflow-y-auto space-y-6">
                <SheetHeader>
                    <SheetTitle>New Transaction</SheetTitle>
                    <SheetDescription>
                        Record a new financial movement.
                    </SheetDescription>
                </SheetHeader>

                <form @submit.prevent="submitTransaction" class="space-y-5">
                    <!-- Error messages from server validation (e.g. credit card limit) -->
                    <div v-if="form.errors.balance" class="p-3 bg-red-50 dark:bg-red-950/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/50 rounded-lg text-xs font-semibold">
                        {{ form.errors.balance }}
                    </div>

                    <!-- Type Tabs Toggle -->
                    <div class="grid grid-cols-3 gap-1 rounded-lg bg-muted p-1 text-center text-sm font-semibold">
                        <button 
                            type="button"
                            @click="form.type = 'expense'; form.category = '';"
                            class="rounded-md py-2 transition-all duration-200"
                            :class="form.type === 'expense' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Expense
                        </button>
                        <button 
                            type="button"
                            @click="form.type = 'income'; form.category = '';"
                            class="rounded-md py-2 transition-all duration-200"
                            :class="form.type === 'income' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Income
                        </button>
                        <button 
                            type="button"
                            @click="form.type = 'transfer'; form.category = '';"
                            class="rounded-md py-2 transition-all duration-200"
                            :class="form.type === 'transfer' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Transfer
                        </button>
                    </div>

                    <!-- Amount + Fee Row -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2 grid gap-2">
                            <Label for="sheet_amount">Amount (LKR)</Label>
                            <div class="relative flex items-center">
                                <span class="absolute left-3 text-sm text-muted-foreground font-semibold text-zinc-500">LKR</span>
                                <Input 
                                    id="sheet_amount" 
                                    type="number" 
                                    step="0.01" 
                                    v-model="form.amount" 
                                    placeholder="0.00"
                                    class="pl-12 text-sm w-full"
                                    required 
                                />
                            </div>
                            <div v-if="form.errors.amount" class="text-xs text-red-500">{{ form.errors.amount }}</div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="sheet_fee">Fee</Label>
                            <Input 
                                id="sheet_fee" 
                                type="number" 
                                step="0.01" 
                                v-model="form.fee" 
                                placeholder="0.00"
                                class="text-sm"
                            />
                            <div v-if="form.errors.fee" class="text-xs text-red-500">{{ form.errors.fee }}</div>
                        </div>
                    </div>

                    <!-- Conditional Account Dropdowns -->
                    <div v-if="form.type !== 'transfer'" class="grid gap-2">
                        <Label for="sheet_account_id">Select Account</Label>
                        <select 
                            id="sheet_account_id"
                            v-model="form.account_id" 
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg_xmlns=%22http://www.w3.org/2000/svg%22_fill=%22none%22_viewBox=%220_0_20_20%22%3E%3Cpath_stroke=%22%236b7280%22_stroke-linecap=%22round%22_stroke-linejoin=%22round%22_stroke-width=%221.5%22_d=%22M6_8l4_4_4-4%22/%3E%3C/svg%3E')] bg-[position:right_0.75rem_center] bg-[size:1.25rem] bg-no-repeat pr-10"
                            required
                        >
                            <option value="">Select Account</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                {{ acc.name }} ({{ formatCurrency(parseFloat(acc.balance), acc.currency) }})
                            </option>
                        </select>
                        <div v-if="form.errors.account_id" class="text-xs text-red-500">{{ form.errors.account_id }}</div>
                    </div>

                    <div v-else class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="sheet_from_account_id">From Account</Label>
                            <select 
                                id="sheet_from_account_id"
                                v-model="form.account_id" 
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg_xmlns=%22http://www.w3.org/2000/svg%22_fill=%22none%22_viewBox=%220_0_20_20%22%3E%3Cpath_stroke=%22%236b7280%22_stroke-linecap=%22round%22_stroke-linejoin=%22round%22_stroke-width=%221.5%22_d=%22M6_8l4_4_4-4%22/%3E%3C/svg%3E')] bg-[position:right_0.75rem_center] bg-[size:1.25rem] bg-no-repeat pr-10"
                                required
                            >
                                <option value="">Select From</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                    {{ acc.name }} ({{ formatCurrency(parseFloat(acc.balance), acc.currency) }})
                                </option>
                            </select>
                            <div v-if="form.errors.account_id" class="text-xs text-red-500">{{ form.errors.account_id }}</div>
                        </div>
                        
                        <div class="grid gap-2">
                            <Label for="sheet_to_account_id">To Account</Label>
                            <select 
                                id="sheet_to_account_id"
                                v-model="form.to_account_id" 
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg_xmlns=%22http://www.w3.org/2000/svg%22_fill=%22none%22_viewBox=%220_0_20_20%22%3E%3Cpath_stroke=%22%236b7280%22_stroke-linecap=%22round%22_stroke-linejoin=%22round%22_stroke-width=%221.5%22_d=%22M6_8l4_4_4-4%22/%3E%3C/svg%3E')] bg-[position:right_0.75rem_center] bg-[size:1.25rem] bg-no-repeat pr-10"
                                required
                            >
                                <option value="">Select To</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id" :disabled="acc.id === form.account_id">
                                    {{ acc.name }} ({{ formatCurrency(parseFloat(acc.balance), acc.currency) }})
                                </option>
                            </select>
                            <div v-if="form.errors.to_account_id" class="text-xs text-red-500">{{ form.errors.to_account_id }}</div>
                        </div>
                    </div>

                    <!-- Category (Income/Expense only, from DB) -->
                    <div v-if="form.type !== 'transfer'" class="grid gap-2">
                        <Label for="sheet_category">Category</Label>
                        <select 
                            id="sheet_category"
                            v-model="form.category" 
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg_xmlns=%22http://www.w3.org/2000/svg%22_fill=%22none%22_viewBox=%220_0_20_20%22%3E%3Cpath_stroke=%22%236b7280%22_stroke-linecap=%22round%22_stroke-linejoin=%22round%22_stroke-width=%221.5%22_d=%22M6_8l4_4_4-4%22/%3E%3C/svg%3E')] bg-[position:right_0.75rem_center] bg-[size:1.25rem] bg-no-repeat pr-10"
                            required
                        >
                            <option value="">Select Category</option>
                            <option v-for="cat in availableCategories" :key="cat.id" :value="cat.name">
                                {{ cat.name }}
                            </option>
                        </select>
                        <div v-if="form.errors.category" class="text-xs text-red-500">{{ form.errors.category }}</div>
                    </div>

                    <!-- Date -->
                    <div class="grid gap-2">
                        <Label for="sheet_date">Date</Label>
                        <Input 
                            id="sheet_date" 
                            type="date" 
                            v-model="form.date" 
                            class="text-sm"
                            required 
                        />
                        <div v-if="form.errors.date" class="text-xs text-red-500">{{ form.errors.date }}</div>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-2">
                        <Label for="sheet_description">Description (Optional)</Label>
                        <textarea 
                            id="sheet_description" 
                            v-model="form.description" 
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Describe this transaction..."
                        ></textarea>
                        <div v-if="form.errors.description" class="text-xs text-red-500">{{ form.errors.description }}</div>
                    </div>

                    <SheetFooter class="pt-4 flex flex-row gap-2 justify-end">
                        <Button 
                            type="button" 
                            variant="outline" 
                            @click="isAddDialogOpen = false"
                            :disabled="form.processing"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Save Transaction' }}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>

        <!-- Edit Transaction Slide-over Panel (Sheet) -->
        <Sheet v-model:open="isEditDialogOpen">
            <SheetContent side="right" class="w-full sm:max-w-[550px] p-6 sm:p-8 overflow-y-auto space-y-6">
                <SheetHeader>
                    <SheetTitle>Edit Transaction</SheetTitle>
                    <SheetDescription>
                        Updating this will reverse its original balance effect and reapply the new values.
                    </SheetDescription>
                </SheetHeader>

                <form @submit.prevent="submitEditTransaction" class="space-y-5">
                    <div v-if="editForm.errors.balance" class="p-3 bg-red-50 dark:bg-red-950/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/50 rounded-lg text-xs font-semibold">
                        {{ editForm.errors.balance }}
                    </div>
                    <div v-if="editForm.errors.conflict" class="p-3 bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50 rounded-lg text-xs font-semibold">
                        {{ editForm.errors.conflict }}
                    </div>

                    <!-- Type Tabs Toggle -->
                    <div class="grid grid-cols-3 gap-1 rounded-lg bg-muted p-1 text-center text-sm font-semibold">
                        <button
                            type="button"
                            @click="editForm.type = 'expense'; editForm.category = '';"
                            class="rounded-md py-2 transition-all duration-200"
                            :class="editForm.type === 'expense' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Expense
                        </button>
                        <button
                            type="button"
                            @click="editForm.type = 'income'; editForm.category = '';"
                            class="rounded-md py-2 transition-all duration-200"
                            :class="editForm.type === 'income' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Income
                        </button>
                        <button
                            type="button"
                            @click="editForm.type = 'transfer'; editForm.category = '';"
                            class="rounded-md py-2 transition-all duration-200"
                            :class="editForm.type === 'transfer' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Transfer
                        </button>
                    </div>

                    <!-- Amount + Fee Row -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2 grid gap-2">
                            <Label for="edit_amount">Amount (LKR)</Label>
                            <div class="relative flex items-center">
                                <span class="absolute left-3 text-sm text-muted-foreground font-semibold text-zinc-500">LKR</span>
                                <Input
                                    id="edit_amount"
                                    type="number"
                                    step="0.01"
                                    v-model="editForm.amount"
                                    placeholder="0.00"
                                    class="pl-12 text-sm w-full"
                                    required
                                />
                            </div>
                            <div v-if="editForm.errors.amount" class="text-xs text-red-500">{{ editForm.errors.amount }}</div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="edit_fee">Fee</Label>
                            <Input
                                id="edit_fee"
                                type="number"
                                step="0.01"
                                v-model="editForm.fee"
                                placeholder="0.00"
                                class="text-sm"
                            />
                            <div v-if="editForm.errors.fee" class="text-xs text-red-500">{{ editForm.errors.fee }}</div>
                        </div>
                    </div>

                    <!-- Conditional Account Dropdowns -->
                    <div v-if="editForm.type !== 'transfer'" class="grid gap-2">
                        <Label for="edit_account_id">Select Account</Label>
                        <select
                            id="edit_account_id"
                            v-model="editForm.account_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none pr-10"
                            required
                        >
                            <option value="">Select Account</option>
                            <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                {{ acc.name }} ({{ formatCurrency(parseFloat(acc.balance), acc.currency) }})
                            </option>
                        </select>
                        <div v-if="editForm.errors.account_id" class="text-xs text-red-500">{{ editForm.errors.account_id }}</div>
                    </div>

                    <div v-else class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="edit_from_account_id">From Account</Label>
                            <select
                                id="edit_from_account_id"
                                v-model="editForm.account_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none pr-10"
                                required
                            >
                                <option value="">Select From</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id">
                                    {{ acc.name }} ({{ formatCurrency(parseFloat(acc.balance), acc.currency) }})
                                </option>
                            </select>
                            <div v-if="editForm.errors.account_id" class="text-xs text-red-500">{{ editForm.errors.account_id }}</div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit_to_account_id">To Account</Label>
                            <select
                                id="edit_to_account_id"
                                v-model="editForm.to_account_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none pr-10"
                                required
                            >
                                <option value="">Select To</option>
                                <option v-for="acc in accounts" :key="acc.id" :value="acc.id" :disabled="acc.id === editForm.account_id">
                                    {{ acc.name }} ({{ formatCurrency(parseFloat(acc.balance), acc.currency) }})
                                </option>
                            </select>
                            <div v-if="editForm.errors.to_account_id" class="text-xs text-red-500">{{ editForm.errors.to_account_id }}</div>
                        </div>
                    </div>

                    <!-- Category (Income/Expense only, from DB) -->
                    <div v-if="editForm.type !== 'transfer'" class="grid gap-2">
                        <Label for="edit_category">Category</Label>
                        <select
                            id="edit_category"
                            v-model="editForm.category"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none pr-10"
                            required
                        >
                            <option value="">Select Category</option>
                            <option v-for="cat in editableCategories" :key="cat.id" :value="cat.name">
                                {{ cat.name }}
                            </option>
                        </select>
                        <div v-if="editForm.errors.category" class="text-xs text-red-500">{{ editForm.errors.category }}</div>
                    </div>

                    <!-- Date -->
                    <div class="grid gap-2">
                        <Label for="edit_date">Date</Label>
                        <Input
                            id="edit_date"
                            type="date"
                            v-model="editForm.date"
                            class="text-sm"
                            required
                        />
                        <div v-if="editForm.errors.date" class="text-xs text-red-500">{{ editForm.errors.date }}</div>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-2">
                        <Label for="edit_description">Description (Optional)</Label>
                        <textarea
                            id="edit_description"
                            v-model="editForm.description"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Describe this transaction..."
                        ></textarea>
                        <div v-if="editForm.errors.description" class="text-xs text-red-500">{{ editForm.errors.description }}</div>
                    </div>

                    <SheetFooter class="pt-4 flex flex-row gap-2 justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isEditDialogOpen = false"
                            :disabled="editForm.processing"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            {{ editForm.processing ? 'Saving...' : 'Save Changes' }}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>

        <!-- Delete Transaction Confirmation -->
        <AlertDialog v-model:open="isDeleteDialogOpen">
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Delete this transaction?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Its balance effect will be reversed on the affected account(s). This action cannot be undone.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        class="bg-destructive text-white hover:bg-destructive/90"
                        @click="confirmDeleteTransaction"
                    >
                        Delete
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
