<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
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
    TrendingDown,
    Wallet, 
    Banknote, 
    CreditCard,
    Plus,
    ArrowDownLeft,
    ArrowUpRight,
    Receipt
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
        monthlyIncome: number;
        monthlyExpenses: number;
        monthlyFees: number;
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
        fee: string;
        description: string | null;
        date: string;
        account: {
            name: string;
        };
    }>;
    categoryBreakdown: Array<{
        category: string;
        total: number;
        count: number;
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
    fee: '',
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
                type: form.type,
                amount: '',
                fee: '',
                date: todayStr,
                account_id: '',
                to_account_id: '',
                category: '',
                description: '',
            });
        },
    });
};

// === PIE CHART (Pure Canvas) ===
const pieCanvas = ref<HTMLCanvasElement | null>(null);

const pieColors = [
    '#ef4444', '#f97316', '#eab308', '#22c55e', '#06b6d4',
    '#3b82f6', '#8b5cf6', '#ec4899', '#f43f5e', '#14b8a6',
    '#a855f7', '#6366f1', '#d946ef', '#84cc16', '#fb923c',
];

const totalSpending = computed(() => props.categoryBreakdown.reduce((sum, c) => sum + c.total, 0));

const drawPieChart = () => {
    const canvas = pieCanvas.value;
    if (!canvas || props.categoryBreakdown.length === 0) return;

    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const dpr = window.devicePixelRatio || 1;
    const displaySize = 220;
    canvas.width = displaySize * dpr;
    canvas.height = displaySize * dpr;
    canvas.style.width = displaySize + 'px';
    canvas.style.height = displaySize + 'px';
    ctx.scale(dpr, dpr);

    const centerX = displaySize / 2;
    const centerY = displaySize / 2;
    const outerRadius = 100;
    const innerRadius = 60; // Donut hole

    let startAngle = -Math.PI / 2; // Start from top

    props.categoryBreakdown.forEach((item, index) => {
        const sliceAngle = (item.total / totalSpending.value) * 2 * Math.PI;
        const endAngle = startAngle + sliceAngle;

        ctx.beginPath();
        ctx.arc(centerX, centerY, outerRadius, startAngle, endAngle);
        ctx.arc(centerX, centerY, innerRadius, endAngle, startAngle, true);
        ctx.closePath();
        ctx.fillStyle = pieColors[index % pieColors.length];
        ctx.fill();

        startAngle = endAngle;
    });

    // Center text
    ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--foreground').trim() || '#18181b';
    ctx.font = 'bold 18px Inter, system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    
    const formattedTotal = totalSpending.value >= 1000 
        ? (totalSpending.value / 1000).toFixed(1) + 'K' 
        : totalSpending.value.toFixed(0);
    ctx.fillText(formattedTotal, centerX, centerY - 8);
    
    ctx.font = '11px Inter, system-ui, sans-serif';
    ctx.fillStyle = '#71717a';
    ctx.fillText('This Month', centerX, centerY + 12);
};

onMounted(() => {
    drawPieChart();
});

// Current month name
const currentMonth = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
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

        <!-- Monthly Flow + Category Pie Chart Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            <!-- Monthly Flow Summary Cards (3 small cards stacked) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-1 gap-4">
                <!-- Monthly Income -->
                <Card class="transition duration-200 hover:shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-1 pt-4 px-5">
                        <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Income this month</CardTitle>
                        <div class="p-1.5 bg-emerald-500/10 text-emerald-500 rounded-md">
                            <ArrowDownLeft class="size-3.5" />
                        </div>
                    </CardHeader>
                    <CardContent class="px-5 pb-4">
                        <div class="text-xl font-bold tracking-tight text-emerald-600 dark:text-emerald-500">
                            + {{ formatCurrency(metrics.monthlyIncome) }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Monthly Expenses -->
                <Card class="transition duration-200 hover:shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-1 pt-4 px-5">
                        <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Expenses this month</CardTitle>
                        <div class="p-1.5 bg-red-500/10 text-red-500 rounded-md">
                            <ArrowUpRight class="size-3.5" />
                        </div>
                    </CardHeader>
                    <CardContent class="px-5 pb-4">
                        <div class="text-xl font-bold tracking-tight text-red-600 dark:text-red-500">
                            - {{ formatCurrency(metrics.monthlyExpenses) }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Monthly Fees -->
                <Card class="transition duration-200 hover:shadow-sm">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-1 pt-4 px-5">
                        <CardTitle class="text-xs font-semibold text-muted-foreground uppercase tracking-wider">Fees & Charges</CardTitle>
                        <div class="p-1.5 bg-amber-500/10 text-amber-500 rounded-md">
                            <Receipt class="size-3.5" />
                        </div>
                    </CardHeader>
                    <CardContent class="px-5 pb-4">
                        <div class="text-xl font-bold tracking-tight text-amber-600 dark:text-amber-500">
                            {{ formatCurrency(metrics.monthlyFees) }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Category Pie Chart -->
            <Card class="lg:col-span-2 transition duration-200 hover:shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle class="text-sm font-semibold tracking-tight text-foreground">
                        Spending by Category
                    </CardTitle>
                    <p class="text-xs text-muted-foreground font-medium">{{ currentMonth }}</p>
                </CardHeader>
                <CardContent>
                    <div v-if="categoryBreakdown.length > 0" class="flex flex-col sm:flex-row items-center gap-6">
                        <!-- Donut Chart -->
                        <div class="shrink-0">
                            <canvas ref="pieCanvas" width="220" height="220"></canvas>
                        </div>

                        <!-- Legend & Breakdown -->
                        <div class="flex-1 w-full space-y-2 max-h-[220px] overflow-y-auto pr-1">
                            <div 
                                v-for="(item, index) in categoryBreakdown" 
                                :key="item.category"
                                class="flex items-center justify-between py-1.5 px-2 rounded-md hover:bg-muted/50 transition-colors"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span 
                                        class="size-2.5 rounded-full shrink-0" 
                                        :style="{ backgroundColor: pieColors[index % pieColors.length] }"
                                    ></span>
                                    <span class="text-sm font-medium text-foreground truncate">{{ item.category }}</span>
                                </div>
                                <div class="text-right shrink-0 ml-3">
                                    <span class="text-sm font-semibold text-foreground tabular-nums">
                                        {{ formatCurrency(item.total) }}
                                    </span>
                                    <span class="text-[10px] text-muted-foreground ml-1.5 tabular-nums">
                                        {{ totalSpending > 0 ? ((item.total / totalSpending) * 100).toFixed(1) : 0 }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="flex flex-col items-center justify-center py-10 text-center">
                        <div class="p-3 bg-muted rounded-full text-muted-foreground mb-3">
                            <TrendingDown class="size-6" />
                        </div>
                        <p class="text-sm font-medium text-muted-foreground">No expense data for this month yet.</p>
                        <p class="text-xs text-muted-foreground mt-0.5">Start adding transactions to see your spending breakdown.</p>
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
                    <!-- Error messages from server validation -->
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

                    <!-- Amount + Fee Row -->
                    <div class="grid grid-cols-3 gap-3">
                        <div class="col-span-2 grid gap-2">
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
                        <div class="grid gap-2">
                            <Label for="fee">Fee</Label>
                            <Input 
                                id="fee" 
                                type="number" 
                                step="0.01" 
                                v-model="form.fee" 
                                placeholder="0.00"
                            />
                            <div v-if="form.errors.fee" class="text-xs text-red-500">{{ form.errors.fee }}</div>
                        </div>
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

                    <!-- Category (from DB) -->
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
                            placeholder="Keells shopping, ATM withdrawal, etc."
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
