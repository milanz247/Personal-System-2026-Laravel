<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { index as accountsIndex } from '@/routes/accounts';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle, 
    DialogDescription, 
    DialogFooter 
} from '@/components/ui/dialog';
import { 
    Select, 
    SelectContent, 
    SelectItem, 
    SelectTrigger, 
    SelectValue 
} from '@/components/ui/select';
import { 
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from '@/components/ui/dropdown-menu';
import { 
    Plus, 
    PiggyBank, 
    CreditCard, 
    TrendingUp, 
    MoreVertical, 
    Trash2, 
    Edit2, 
    Wallet,
    Info
} from '@lucide/vue';
import type { BreadcrumbItem } from '@/types';

// Page properties passed from Laravel Controller
const props = defineProps<{
    accounts: Array<{
        id: number;
        name: string;
        type: 'cash_wallet' | 'bank_account' | 'credit_card' | 'investment';
        currency: string;
        balance: string;
        credit_limit: string | null;
        updated_at: string;
    }>;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Accounts & Wallets',
                href: accountsIndex().url,
            },
        ],
    },
});

// Active section tab: 'cash_bank', 'credit_cards', 'investments'
const activeTab = ref<'cash_bank' | 'credit_cards' | 'investments'>('cash_bank');

// Modal CRUD States
const isDialogOpen = ref(false);
const isEditing = ref(false);
const selectedAccountId = ref<number | null>(null);

// Form configuration using Inertia useForm
const form = useForm({
    name: '',
    type: 'cash_wallet' as 'cash_wallet' | 'bank_account' | 'credit_card' | 'investment',
    balance: 0,
    credit_limit: 0,
    currency: 'LKR',
    // Optimistic-locking token, only meaningful (and only sent) on edit.
    updated_at: '',
});

const isUpdateBalanceOpen = ref(false);
const selectedAccountForBalance = ref<typeof props.accounts[0] | null>(null);

const updateBalanceForm = useForm({
    balance: 0,
});

const openUpdateBalanceModal = (account: typeof props.accounts[0]) => {
    selectedAccountForBalance.value = account;
    updateBalanceForm.balance = account.type === 'credit_card'
        ? Math.abs(parseFloat(account.balance))
        : parseFloat(account.balance);
    isUpdateBalanceOpen.value = true;
};

const submitUpdateBalance = () => {
    if (!selectedAccountForBalance.value) return;
    
    updateBalanceForm.put(`/accounts/${selectedAccountForBalance.value.id}/balance`, {
        onSuccess: () => {
            isUpdateBalanceOpen.value = false;
            updateBalanceForm.reset();
            selectedAccountForBalance.value = null;
        },
    });
};

// Open Add Account modal
const openAddModal = () => {
    isEditing.value = false;
    selectedAccountId.value = null;
    form.reset();
    isDialogOpen.value = true;
};

// Open Edit Account modal
const openEditModal = (account: typeof props.accounts[0]) => {
    isEditing.value = true;
    selectedAccountId.value = account.id;
    form.name = account.name;
    form.type = account.type;
    form.balance = account.type === 'credit_card' 
        ? Math.abs(parseFloat(account.balance)) 
        : parseFloat(account.balance);
    form.credit_limit = account.credit_limit ? parseFloat(account.credit_limit) : 0;
    form.currency = account.currency;
    form.updated_at = account.updated_at;
    isDialogOpen.value = true;
};

// Handle Create or Update
const submitForm = () => {
    if (isEditing.value && selectedAccountId.value) {
        form.put(`/accounts/${selectedAccountId.value}`, {
            onSuccess: () => {
                isDialogOpen.value = false;
                form.reset();
            },
        });
    } else {
        form.post('/accounts', {
            onSuccess: () => {
                isDialogOpen.value = false;
                form.reset();
            },
        });
    }
};

// Handle Delete Account
const deleteAccount = (id: number) => {
    if (confirm('Are you sure you want to delete this account? This action cannot be undone.')) {
        form.delete(`/accounts/${id}`);
    }
};

// KPI calculations
const totalAssets = computed(() => {
    return props.accounts
        .filter(acc => acc.type !== 'credit_card')
        .reduce((sum, acc) => sum + parseFloat(acc.balance), 0);
});

const totalLiabilities = computed(() => {
    return props.accounts
        .filter(acc => acc.type === 'credit_card')
        .reduce((sum, acc) => sum + Math.abs(parseFloat(acc.balance)), 0);
});

const netWorth = computed(() => {
    return totalAssets.value - totalLiabilities.value;
});

// Grouped and filtered accounts list
const filteredAccounts = computed(() => {
    if (activeTab.value === 'cash_bank') {
        return props.accounts.filter(acc => acc.type === 'cash_wallet' || acc.type === 'bank_account');
    }
    if (activeTab.value === 'credit_cards') {
        return props.accounts.filter(acc => acc.type === 'credit_card');
    }
    if (activeTab.value === 'investments') {
        return props.accounts.filter(acc => acc.type === 'investment');
    }
    return [];
});

const cashAndBankAccounts = computed(() => {
    return props.accounts.filter(acc => 
        acc.type === 'bank_account' || 
        (acc.type === 'cash_wallet' && acc.name === 'Cash Wallet')
    );
});

const virtualPockets = computed(() => {
    return props.accounts.filter(acc => 
        acc.type === 'cash_wallet' && acc.name !== 'Cash Wallet'
    );
});

// Format numbers to Currency Display
const formatCurrency = (val: number, currency = 'LKR') => {
    return new Intl.NumberFormat('en-LK', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2
    }).format(val);
};

// Helper badge labels
const getBadgeVariant = (account: typeof props.accounts[0]) => {
    if (account.type === 'cash_wallet') {
        return account.name === 'Cash Wallet' ? 'secondary' : 'outline';
    }
    switch (account.type) {
        case 'bank_account': return 'outline';
        case 'credit_card': return 'destructive';
        case 'investment': return 'default';
        default: return 'secondary';
    }
};

const getBadgeLabel = (account: typeof props.accounts[0]) => {
    if (account.type === 'cash_wallet') {
        return account.name === 'Cash Wallet' ? 'Cash in Hand' : 'Savings Pocket';
    }
    switch (account.type) {
        case 'bank_account': return 'Bank Account';
        case 'credit_card': return 'Credit Card';
        case 'investment': return 'Investment';
        default: return account.type;
    }
};
</script>

<template>
    <Head title="Accounts & Wallets" />

    <div class="space-y-6 p-6">
            <!-- Header Section -->
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight">Accounts & Wallets</h1>
                    <p class="text-sm text-muted-foreground">Manage your cash, banks, credit card debts, and investments.</p>
                </div>
                <Button @click="openAddModal" class="self-start sm:self-center">
                    <Plus class="size-4 mr-2" />
                    Add Account
                </Button>
            </div>

            <!-- KPI Cards Overview -->
            <div class="grid gap-4 md:grid-cols-3">
                <!-- Net Worth Card -->
                <Card class="relative overflow-hidden">
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-sm font-medium">Net Worth</CardTitle>
                        <TrendingUp class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold tracking-tight" :class="netWorth >= 0 ? 'text-primary' : 'text-destructive'">
                            {{ formatCurrency(netWorth) }}
                        </div>
                        <p class="text-xs text-muted-foreground">Total Assets - Total Liabilities</p>
                    </CardContent>
                </Card>

                <!-- Total Assets Card -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-sm font-medium">Total Assets</CardTitle>
                        <PiggyBank class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600 dark:text-green-500">
                            {{ formatCurrency(totalAssets) }}
                        </div>
                        <p class="text-xs text-muted-foreground">Cash, Bank Accounts & Investments</p>
                    </CardContent>
                </Card>

                <!-- Total Liabilities Card -->
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between pb-2 space-y-0">
                        <CardTitle class="text-sm font-medium">Total Liabilities</CardTitle>
                        <CreditCard class="size-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-destructive">
                            {{ formatCurrency(totalLiabilities) }}
                        </div>
                        <p class="text-xs text-muted-foreground">Total Credit Card Outstanding Debt</p>
                    </CardContent>
                </Card>
            </div>

            <!-- Tabs Navigation -->
            <div class="space-y-4">
                <div class="flex items-center space-x-1 rounded-lg bg-muted p-1 max-w-md">
                    <button
                        @click="activeTab = 'cash_bank'"
                        :class="[
                            'w-full rounded-md px-3 py-1.5 text-sm font-medium transition duration-200',
                            activeTab === 'cash_bank' ? 'bg-background shadow text-foreground' : 'text-muted-foreground hover:text-foreground'
                        ]"
                    >
                        Cash & Bank
                    </button>
                    <button
                        @click="activeTab = 'credit_cards'"
                        :class="[
                            'w-full rounded-md px-3 py-1.5 text-sm font-medium transition duration-200',
                            activeTab === 'credit_cards' ? 'bg-background shadow text-foreground' : 'text-muted-foreground hover:text-foreground'
                        ]"
                    >
                        Credit Cards
                    </button>
                    <button
                        @click="activeTab = 'investments'"
                        :class="[
                            'w-full rounded-md px-3 py-1.5 text-sm font-medium transition duration-200',
                            activeTab === 'investments' ? 'bg-background shadow text-foreground' : 'text-muted-foreground hover:text-foreground'
                        ]"
                    >
                        Investments
                    </button>
                </div>

                <!-- Tab Section Content -->
                <div v-if="activeTab === 'cash_bank'" class="space-y-8">
                    <!-- Subsection 1: Available Cash & Bank Accounts -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between border-b pb-2">
                            <h2 class="text-lg font-semibold tracking-tight text-foreground">Available Cash & Bank Accounts</h2>
                            <Badge variant="secondary" class="font-normal">
                                {{ cashAndBankAccounts.length }} {{ cashAndBankAccounts.length === 1 ? 'Account' : 'Accounts' }}
                            </Badge>
                        </div>
                        
                        <div v-if="cashAndBankAccounts.length > 0" class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                            <Card v-for="account in cashAndBankAccounts" :key="account.id" class="flex flex-col justify-between hover:shadow-md transition">
                                <CardHeader class="flex flex-row items-start justify-between pb-2 space-y-0">
                                    <div class="space-y-1 pr-2">
                                        <CardTitle class="text-base font-semibold truncate max-w-[180px]">
                                            {{ account.name }}
                                        </CardTitle>
                                        <Badge :variant="getBadgeVariant(account)">
                                            {{ getBadgeLabel(account) }}
                                        </Badge>
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="-mt-1 h-8 w-8">
                                                <MoreVertical class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem @click="openUpdateBalanceModal(account)">
                                                <PiggyBank class="size-3.5 mr-2" />
                                                Update Balance
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="openEditModal(account)">
                                                <Edit2 class="size-3.5 mr-2" />
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="account.name !== 'Cash Wallet'" @click="deleteAccount(account.id)" class="text-destructive focus:text-destructive">
                                                <Trash2 class="size-3.5 mr-2" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </CardHeader>
                                <CardContent class="pt-4 mt-auto">
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-xs text-muted-foreground">Balance</span>
                                        <span class="text-xl font-bold text-foreground">
                                            {{ formatCurrency(Math.abs(parseFloat(account.balance)), account.currency) }}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        
                        <div v-else class="text-sm text-muted-foreground py-8 text-center border-2 border-dashed rounded-xl bg-muted/10 flex flex-col items-center justify-center gap-2">
                            <Wallet class="size-6 text-muted-foreground/60" />
                            <span>No cash or bank accounts available.</span>
                        </div>
                    </div>

                    <!-- Subsection 2: Virtual Savings Pockets -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between border-b pb-2">
                            <h2 class="text-lg font-semibold tracking-tight text-foreground">Virtual Savings Pockets</h2>
                            <Badge variant="outline" class="font-normal text-muted-foreground border-muted-foreground/30">
                                {{ virtualPockets.length }} {{ virtualPockets.length === 1 ? 'Pocket' : 'Pockets' }}
                            </Badge>
                        </div>
                        
                        <div v-if="virtualPockets.length > 0" class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                            <Card v-for="account in virtualPockets" :key="account.id" class="flex flex-col justify-between hover:shadow-md transition">
                                <CardHeader class="flex flex-row items-start justify-between pb-2 space-y-0">
                                    <div class="space-y-1 pr-2">
                                        <CardTitle class="text-base font-semibold truncate max-w-[180px]">
                                            {{ account.name }}
                                        </CardTitle>
                                        <Badge :variant="getBadgeVariant(account)">
                                            {{ getBadgeLabel(account) }}
                                        </Badge>
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="icon" class="-mt-1 h-8 w-8">
                                                <MoreVertical class="size-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem @click="openUpdateBalanceModal(account)">
                                                <PiggyBank class="size-3.5 mr-2" />
                                                Update Balance
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="openEditModal(account)">
                                                <Edit2 class="size-3.5 mr-2" />
                                                Edit
                                            </DropdownMenuItem>
                                            <DropdownMenuItem @click="deleteAccount(account.id)" class="text-destructive focus:text-destructive">
                                                <Trash2 class="size-3.5 mr-2" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </CardHeader>
                                <CardContent class="pt-4 mt-auto">
                                    <div class="flex items-baseline justify-between">
                                        <span class="text-xs text-muted-foreground">Balance</span>
                                        <span class="text-xl font-bold text-foreground">
                                            {{ formatCurrency(Math.abs(parseFloat(account.balance)), account.currency) }}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        
                        <div v-else class="text-sm text-muted-foreground py-8 text-center border-2 border-dashed rounded-xl bg-muted/10 flex flex-col items-center justify-center gap-2">
                            <PiggyBank class="size-6 text-muted-foreground/60" />
                            <span>No virtual budgeting pockets created yet.</span>
                        </div>
                    </div>
                </div>

                <div v-else class="space-y-4">
                    <div v-if="filteredAccounts.length > 0" class="grid gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-3">
                        <Card v-for="account in filteredAccounts" :key="account.id" class="flex flex-col justify-between hover:shadow-md transition">
                            <CardHeader class="flex flex-row items-start justify-between pb-2 space-y-0">
                                <div class="space-y-1 pr-2">
                                    <CardTitle class="text-base font-semibold truncate max-w-[180px]">
                                        {{ account.name }}
                                    </CardTitle>
                                    <Badge :variant="getBadgeVariant(account)">
                                        {{ getBadgeLabel(account) }}
                                    </Badge>
                                </div>
                                <DropdownMenu>
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="ghost" size="icon" class="-mt-1 h-8 w-8">
                                            <MoreVertical class="size-4" />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem @click="openUpdateBalanceModal(account)">
                                            <PiggyBank class="size-3.5 mr-2" />
                                            Update Balance
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="openEditModal(account)">
                                            <Edit2 class="size-3.5 mr-2" />
                                            Edit
                                        </DropdownMenuItem>
                                        <DropdownMenuItem @click="deleteAccount(account.id)" class="text-destructive focus:text-destructive">
                                            <Trash2 class="size-3.5 mr-2" />
                                            Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </CardHeader>
                            <CardContent class="pt-4 mt-auto">
                                <div class="flex items-baseline justify-between">
                                    <span class="text-xs text-muted-foreground">
                                        {{ account.type === 'credit_card' ? 'Outstanding Balance' : 'Balance' }}
                                    </span>
                                    <span 
                                        class="text-xl font-bold"
                                        :class="account.type === 'credit_card' ? 'text-destructive' : 'text-foreground'"
                                    >
                                        {{ account.type === 'credit_card' ? '-' : '' }}{{ formatCurrency(Math.abs(parseFloat(account.balance)), account.currency) }}
                                    </span>
                                </div>

                                <!-- Credit Card Limit and Progress Bar -->
                                <div v-if="account.type === 'credit_card'" class="mt-4 pt-4 border-t border-muted/50 space-y-3">
                                    <div class="flex justify-between text-xs text-muted-foreground">
                                        <span>Used: {{ formatCurrency(Math.abs(parseFloat(account.balance)), account.currency) }}</span>
                                        <span>Limit: {{ formatCurrency(parseFloat(account.credit_limit || '0'), account.currency) }}</span>
                                    </div>
                                    <div class="w-full bg-muted dark:bg-muted/50 rounded-full h-2 overflow-hidden">
                                        <div 
                                            class="bg-destructive h-full transition-all duration-300"
                                            :style="{ width: `${Math.min(100, Math.max(0, (Math.abs(parseFloat(account.balance)) / (parseFloat(account.credit_limit || '1') || 1)) * 100))}%` }"
                                        ></div>
                                    </div>
                                    <div class="flex justify-between text-xs font-semibold">
                                        <span>Available Credit</span>
                                        <span :class="(parseFloat(account.credit_limit || '0') - Math.abs(parseFloat(account.balance))) < 0 ? 'text-destructive' : 'text-foreground'">
                                            {{ formatCurrency(parseFloat(account.credit_limit || '0') - Math.abs(parseFloat(account.balance)), account.currency) }}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    <!-- Empty State View -->
                    <div v-else class="border-2 border-dashed rounded-xl p-12 text-center flex flex-col items-center justify-center gap-4 bg-muted/20">
                        <div class="rounded-full bg-muted p-4">
                            <CreditCard v-if="activeTab === 'credit_cards'" class="size-8 text-muted-foreground" />
                            <TrendingUp v-if="activeTab === 'investments'" class="size-8 text-muted-foreground" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-lg font-semibold">No assets found</h3>
                            <p class="text-sm text-muted-foreground">
                                {{ 
                                    activeTab === 'credit_cards' ? "You don't have any credit cards registered." :
                                    "You don't have any investments registered."
                                }}
                            </p>
                        </div>
                        <Button @click="openAddModal" size="sm" variant="outline">
                            <Plus class="size-3.5 mr-2" />
                            Add Account
                        </Button>
                    </div>
                </div>
            </div>

        <!-- Add/Edit Account Dialog Form -->
        <Dialog v-model:open="isDialogOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>{{ isEditing ? 'Edit Account' : 'Add New Account' }}</DialogTitle>
                    <DialogDescription>
                        {{ isEditing ? 'Update the details for this account below.' : 'Create a new cash wallet, bank account, credit card, or investment tracker.' }}
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitForm" class="space-y-4 py-2">
                    <div v-if="form.errors.conflict" class="p-3 bg-amber-50 dark:bg-amber-950/20 text-amber-700 dark:text-amber-400 border border-amber-200 dark:border-amber-900/50 rounded-lg text-xs font-semibold">
                        {{ form.errors.conflict }}
                    </div>
                    <div v-if="form.errors.type" class="p-3 bg-red-50 dark:bg-red-950/20 text-red-600 dark:text-red-400 border border-red-200 dark:border-red-900/50 rounded-lg text-xs font-semibold">
                        {{ form.errors.type }}
                    </div>
                    <div class="grid gap-2">
                        <Label for="name">Account Name</Label>
                        <Input 
                            id="name" 
                            type="text" 
                            v-model="form.name" 
                            placeholder="e.g., HNB Savings, Personal Cash, Commercial Bank CC"
                            required 
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="type">Account Type</Label>
                        <Select v-model="form.type" :disabled="isEditing">
                            <SelectTrigger id="type">
                                <SelectValue placeholder="Select type" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-if="isEditing && form.name === 'Cash Wallet'" value="cash_wallet">Cash Wallet</SelectItem>
                                <SelectItem v-else value="cash_wallet">Virtual Pocket (Budgeting)</SelectItem>
                                <SelectItem value="bank_account">Bank Account</SelectItem>
                                <SelectItem value="credit_card">Credit Card (Liability)</SelectItem>
                                <SelectItem value="investment">Investment Asset</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="isEditing" class="text-xs text-muted-foreground">Type can't be changed after creation — create a new account instead.</p>
                    </div>

                    <div v-if="!isEditing" class="grid gap-2">
                        <Label for="balance">
                            {{ form.type === 'credit_card' ? 'Current Outstanding Debt (LKR)' : 'Initial/Current Balance (LKR)' }}
                        </Label>
                        <Input
                            id="balance"
                            type="number"
                            step="0.01"
                            v-model="form.balance"
                            min="0"
                            placeholder="0.00"
                            required
                        />
                        <div v-if="form.errors.balance" class="text-sm text-destructive">{{ form.errors.balance }}</div>
                    </div>
                    <p v-else class="text-xs text-muted-foreground -mt-1">
                        Balance can't be edited here — use "Update Balance" from the account menu so the change is recorded as a transaction.
                    </p>

                    <div v-if="form.type === 'credit_card'" class="grid gap-2">
                        <Label for="credit_limit">Credit Limit (LKR)</Label>
                        <Input 
                            id="credit_limit" 
                            type="number" 
                            step="0.01" 
                            v-model="form.credit_limit" 
                            min="0"
                            placeholder="e.g., 100000.00"
                            required 
                        />
                        <div v-if="form.errors.credit_limit" class="text-sm text-destructive">{{ form.errors.credit_limit }}</div>
                    </div>

                    <div v-if="form.type === 'credit_card'" class="flex items-start gap-2 bg-amber-50 dark:bg-amber-950/20 text-amber-800 dark:text-amber-300 p-3 rounded-lg text-xs leading-normal">
                        <Info class="size-4 shrink-0 mt-[2px]" />
                        <span>Credit card balances are liabilities. Entering an outstanding debt here will reduce your calculated net worth.</span>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isDialogOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            Save
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Update Balance Dialog Modal -->
        <Dialog v-model:open="isUpdateBalanceOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Update Balance</DialogTitle>
                    <DialogDescription>
                        Update the balance for <span class="font-semibold text-foreground">{{ selectedAccountForBalance?.name }}</span>. This will automatically log the adjustment difference.
                    </DialogDescription>
                </DialogHeader>

                <form @submit.prevent="submitUpdateBalance" class="space-y-4 py-2">
                    <div class="grid gap-2">
                        <Label for="update_balance_input">
                            {{ selectedAccountForBalance?.type === 'credit_card' ? 'New Outstanding Debt (LKR)' : 'New Balance (LKR)' }}
                        </Label>
                        <Input 
                            id="update_balance_input" 
                            type="number" 
                            step="0.01" 
                            v-model="updateBalanceForm.balance" 
                            min="0"
                            placeholder="0.00"
                            required 
                        />
                        <div v-if="updateBalanceForm.errors.balance" class="text-sm text-destructive">
                            {{ updateBalanceForm.errors.balance }}
                        </div>
                    </div>

                    <DialogFooter class="pt-4">
                        <Button type="button" variant="outline" @click="isUpdateBalanceOpen = false">
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="updateBalanceForm.processing">
                            Update Balance
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
