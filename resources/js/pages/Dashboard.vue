<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle, 
    DialogDescription, 
    DialogFooter 
} from '@/components/ui/dialog';
import { 
    TrendingUp, 
    Wallet, 
    Banknote, 
    CreditCard,
    Plus
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

const props = defineProps<{
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

// Modal and Form States
const isTransactionDialogOpen = ref(false);
const todayStr = new Date().toISOString().split('T')[0];

const form = useForm({
    type: 'income' as 'income' | 'expense' | 'transfer',
    amount: '',
    date: todayStr,
    account_id: '',
    to_account_id: '',
    category: '',
    description: '',
});

// Dynamic categories from globally shared Inertia props
const page = usePage<any>();
const availableCategories = computed(() => {
    const allCategories = page.props.auth.categories || [];
    return allCategories.filter((c: any) => c.type === form.type);
});

const submitTransaction = () => {
    form.post('/transactions', {
        onSuccess: () => {
            isTransactionDialogOpen.value = false;
            form.reset({
                type: form.type, // retain selected transaction type
                amount: '',
                date: todayStr,
                account_id: '',
                to_account_id: '',
                category: '',
                description: '',
            });
        },
    });
};
</script>

<template>
    <Head title="Dashboard Overview" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <!-- Header Row with Quick Action -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Dashboard</h1>
                <p class="text-sm text-muted-foreground">Overview of your liquid assets, liabilities, and net worth.</p>
            </div>
            <Button @click="isTransactionDialogOpen = true" class="self-start sm:self-center">
                <Plus class="size-4 mr-2" />
                Add Transaction
            </Button>
        </div>

        <!-- Professional 4-Card Grid Layout with Formula Labels -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Card 1: Net Worth -->
            <Card class="transition duration-200 hover:shadow-sm">
                <CardHeader class="flex flex-row items-start justify-between space-y-0 pb-2">
                    <div class="space-y-0.5">
                        <CardTitle class="text-sm font-semibold tracking-tight text-foreground">
                            Net Worth
                        </CardTitle>
                        <p class="text-xs text-muted-foreground font-medium">
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
                        <p class="text-xs text-muted-foreground font-medium">
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
                        <p class="text-xs text-muted-foreground font-medium">
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
                        <p class="text-xs text-red-900/60 dark:text-red-400/60 font-medium">
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

        <!-- Add Transaction Dialog Form -->
        <Dialog v-model:open="isTransactionDialogOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Add Transaction</DialogTitle>
                    <DialogDescription>
                        Record a new income, expense, or transfer between accounts.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitTransaction" class="space-y-4 py-2">
                    <!-- Error messages from server validation (e.g. credit card limit) -->
                    <div v-if="form.errors.balance" class="p-3 bg-red-50 dark:bg-red-950/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/50 rounded-lg text-xs font-semibold">
                        {{ form.errors.balance }}
                    </div>

                    <!-- Type Tabs -->
                    <div class="grid grid-cols-3 gap-1 rounded-lg bg-muted p-1 text-center text-xs font-semibold">
                        <button 
                            type="button"
                            @click="form.type = 'income'; form.category = '';"
                            class="rounded-md py-1.5 transition-all duration-200"
                            :class="form.type === 'income' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Income
                        </button>
                        <button 
                            type="button"
                            @click="form.type = 'expense'; form.category = '';"
                            class="rounded-md py-1.5 transition-all duration-200"
                            :class="form.type === 'expense' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Expense
                        </button>
                        <button 
                            type="button"
                            @click="form.type = 'transfer'; form.category = '';"
                            class="rounded-md py-1.5 transition-all duration-200"
                            :class="form.type === 'transfer' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                        >
                            Transfer
                        </button>
                    </div>

                    <!-- Amount -->
                    <div class="grid gap-2">
                        <Label for="amount">Amount (LKR)</Label>
                        <Input 
                            id="amount" 
                            type="number" 
                            step="0.01" 
                            v-model="form.amount" 
                            placeholder="0.00"
                            required 
                        />
                        <div v-if="form.errors.amount" class="text-xs text-red-500">{{ form.errors.amount }}</div>
                    </div>

                    <!-- Account / Accounts Selection -->
                    <div v-if="form.type !== 'transfer'" class="grid gap-2">
                        <Label for="account_id">Account</Label>
                        <select 
                            id="account_id"
                            v-model="form.account_id" 
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
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
                            <Label for="from_account_id">From Account</Label>
                            <select 
                                id="from_account_id"
                                v-model="form.account_id" 
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
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
                            <Label for="to_account_id">To Account</Label>
                            <select 
                                id="to_account_id"
                                v-model="form.to_account_id" 
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
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

                    <!-- Category (Hidden on Transfer, from DB) -->
                    <div v-if="form.type !== 'transfer'" class="grid gap-2">
                        <Label for="category">Category</Label>
                        <select 
                            id="category"
                            v-model="form.category" 
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
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
                        <Label for="date">Date</Label>
                        <Input 
                            id="date" 
                            type="date" 
                            v-model="form.date" 
                            required 
                        />
                        <div v-if="form.errors.date" class="text-xs text-red-500">{{ form.errors.date }}</div>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-2">
                        <Label for="description">Description (Optional)</Label>
                        <textarea 
                            id="description" 
                            v-model="form.description" 
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Keells shopping, monthly salary deposit, etc."
                        ></textarea>
                        <div v-if="form.errors.description" class="text-xs text-red-500">{{ form.errors.description }}</div>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button 
                            type="button" 
                            variant="outline" 
                            @click="isTransactionDialogOpen = false"
                            :disabled="form.processing"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Saving...' : 'Add Transaction' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
