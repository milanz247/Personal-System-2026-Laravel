<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    TrendingUp,
    TrendingDown,
    Wallet,
    Banknote,
    CreditCard,
    Plus,
    ArrowDownLeft,
    ArrowUpRight,
    ArrowRight,
    Inbox,
} from '@lucide/vue';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as transactionsIndex } from '@/routes/transactions';

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
        primaryCurrency: string;
        excludedAccountsCount: number;
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
    debtTrend: number[];
    monthlyTrend: Array<{
        month: string;
        income: number;
        expense: number;
        incomeByCategory: Record<string, number>;
        expenseByCategory: Record<string, number>;
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

// Helper to format short transaction dates for the recent activity list
const formatShortDate = (dateStr: string) => {
    return new Date(dateStr).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
    });
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
    '#ef4444',
    '#f97316',
    '#eab308',
    '#22c55e',
    '#06b6d4',
    '#3b82f6',
    '#8b5cf6',
    '#ec4899',
    '#f43f5e',
    '#14b8a6',
    '#a855f7',
    '#6366f1',
    '#d946ef',
    '#84cc16',
    '#fb923c',
];

const totalSpending = computed(() =>
    props.categoryBreakdown.reduce((sum, c) => sum + c.total, 0),
);

// Index of the largest category, used to pop that slice out of the donut for emphasis.
const largestCategoryIndex = computed(() => {
    if (props.categoryBreakdown.length === 0) {
        return -1;
    }

    let maxIndex = 0;
    props.categoryBreakdown.forEach((item, index) => {
        if (item.total > props.categoryBreakdown[maxIndex].total) {
            maxIndex = index;
        }
    });

    return maxIndex;
});

const drawPieChart = () => {
    const canvas = pieCanvas.value;

    if (!canvas || props.categoryBreakdown.length === 0) {
        return;
    }

    const ctx = canvas.getContext('2d');

    if (!ctx) {
        return;
    }

    const dpr = window.devicePixelRatio || 1;
    const displaySize = 220;
    canvas.width = displaySize * dpr;
    canvas.height = displaySize * dpr;
    canvas.style.width = displaySize + 'px';
    canvas.style.height = displaySize + 'px';
    ctx.scale(dpr, dpr);

    const baseCenterX = displaySize / 2;
    const baseCenterY = displaySize / 2;
    const outerRadius = 100;
    const innerRadius = 60; // Donut hole
    const popOut = 10; // px the largest slice is pushed outward by

    let startAngle = -Math.PI / 2; // Start from top

    props.categoryBreakdown.forEach((item, index) => {
        const sliceAngle = (item.total / totalSpending.value) * 2 * Math.PI;
        const endAngle = startAngle + sliceAngle;
        const midAngle = (startAngle + endAngle) / 2;

        const isLargest = index === largestCategoryIndex.value;
        const centerX = isLargest
            ? baseCenterX + Math.cos(midAngle) * popOut
            : baseCenterX;
        const centerY = isLargest
            ? baseCenterY + Math.sin(midAngle) * popOut
            : baseCenterY;

        ctx.beginPath();
        ctx.arc(centerX, centerY, outerRadius, startAngle, endAngle);
        ctx.arc(centerX, centerY, innerRadius, endAngle, startAngle, true);
        ctx.closePath();
        ctx.fillStyle = pieColors[index % pieColors.length];

        if (isLargest) {
            ctx.shadowColor = 'rgba(0, 0, 0, 0.25)';
            ctx.shadowBlur = 8;
        }

        ctx.fill();
        ctx.shadowColor = 'transparent';
        ctx.shadowBlur = 0;

        startAngle = endAngle;
    });

    // Center text
    ctx.fillStyle =
        getComputedStyle(document.documentElement)
            .getPropertyValue('--foreground')
            .trim() || '#18181b';
    ctx.font = 'bold 18px Inter, system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';

    const formattedTotal =
        totalSpending.value >= 1000
            ? (totalSpending.value / 1000).toFixed(1) + 'K'
            : totalSpending.value.toFixed(0);
    ctx.fillText(formattedTotal, baseCenterX, baseCenterY - 8);

    ctx.font = '11px Inter, system-ui, sans-serif';
    ctx.fillStyle = '#71717a';
    ctx.fillText('This Month', baseCenterX, baseCenterY + 12);
};

// === TOTAL DEBT SPARKLINE (Pure Canvas) ===
const debtSparklineCanvas = ref<HTMLCanvasElement | null>(null);

const drawDebtSparkline = () => {
    const canvas = debtSparklineCanvas.value;

    if (!canvas || props.debtTrend.length < 2) {
        return;
    }

    const ctx = canvas.getContext('2d');

    if (!ctx) {
        return;
    }

    const dpr = window.devicePixelRatio || 1;
    const width = canvas.clientWidth || 96;
    const height = canvas.clientHeight || 32;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, width, height);

    const max = Math.max(...props.debtTrend, 1);
    const min = Math.min(...props.debtTrend, 0);
    const range = max - min || 1;
    const padding = 4;

    const points = props.debtTrend.map((val, i) => ({
        x: (i / (props.debtTrend.length - 1)) * (width - padding * 2) + padding,
        y: height - padding - ((val - min) / range) * (height - padding * 2),
    }));

    // Filled area under the line
    ctx.beginPath();
    ctx.moveTo(points[0].x, height);
    points.forEach((p) => ctx.lineTo(p.x, p.y));
    ctx.lineTo(points[points.length - 1].x, height);
    ctx.closePath();
    ctx.fillStyle = 'rgba(239, 68, 68, 0.12)';
    ctx.fill();

    // Line
    ctx.beginPath();
    points.forEach((p, i) =>
        i === 0 ? ctx.moveTo(p.x, p.y) : ctx.lineTo(p.x, p.y),
    );
    ctx.strokeStyle = '#ef4444';
    ctx.lineWidth = 1.5;
    ctx.stroke();

    // Last point dot
    const last = points[points.length - 1];
    ctx.beginPath();
    ctx.arc(last.x, last.y, 2.5, 0, 2 * Math.PI);
    ctx.fillStyle = '#ef4444';
    ctx.fill();
};

// === MONTHLY INCOME / EXPENSE BY CATEGORY (Diverging Stacked Bar, Pure Canvas) ===
const trendCanvas = ref<HTMLCanvasElement | null>(null);

// Consistent color per category name, shared across the pie chart and this trend chart.
const trendCategoryOrder = computed(() => {
    const seen: string[] = [];
    props.monthlyTrend.forEach((m) => {
        Object.keys(m.incomeByCategory).forEach((c) => {
            if (!seen.includes(c)) {
                seen.push(c);
            }
        });
        Object.keys(m.expenseByCategory).forEach((c) => {
            if (!seen.includes(c)) {
                seen.push(c);
            }
        });
    });

    return seen;
});
const trendCategoryColor = (category: string) => {
    const index = trendCategoryOrder.value.indexOf(category);

    return pieColors[index >= 0 ? index % pieColors.length : 0];
};

const drawTrendChart = () => {
    const canvas = trendCanvas.value;

    if (!canvas || props.monthlyTrend.length === 0) {
        return;
    }

    const ctx = canvas.getContext('2d');

    if (!ctx) {
        return;
    }

    const dpr = window.devicePixelRatio || 1;
    const width = canvas.clientWidth || 600;
    const height = canvas.clientHeight || 220;
    canvas.width = width * dpr;
    canvas.height = height * dpr;
    ctx.scale(dpr, dpr);
    ctx.clearRect(0, 0, width, height);

    const maxValue = Math.max(
        ...props.monthlyTrend.map((m) => Math.max(m.income, m.expense)),
        1,
    );

    const labelHeight = 20;
    const chartHeight = height - labelHeight;
    const zeroY = chartHeight / 2;
    const usableHalf = zeroY - 8;

    const barCount = props.monthlyTrend.length;
    const slotWidth = width / barCount;
    const barWidth = Math.min(36, slotWidth * 0.5);

    // Zero axis line
    ctx.beginPath();
    ctx.moveTo(0, zeroY);
    ctx.lineTo(width, zeroY);
    ctx.strokeStyle = 'rgba(113, 113, 122, 0.25)';
    ctx.lineWidth = 1;
    ctx.stroke();

    props.monthlyTrend.forEach((m, i) => {
        const centerX = slotWidth * i + slotWidth / 2;
        const barX = centerX - barWidth / 2;

        // Income segments stack upward from the zero line
        let cursorY = zeroY;
        Object.entries(m.incomeByCategory).forEach(([category, amount]) => {
            const segHeight = (amount / maxValue) * usableHalf;
            ctx.fillStyle = trendCategoryColor(category);
            ctx.fillRect(barX, cursorY - segHeight, barWidth, segHeight);
            cursorY -= segHeight;
        });

        // Expense segments stack downward from the zero line
        cursorY = zeroY;
        Object.entries(m.expenseByCategory).forEach(([category, amount]) => {
            const segHeight = (amount / maxValue) * usableHalf;
            ctx.fillStyle = trendCategoryColor(category);
            ctx.globalAlpha = 0.65;
            ctx.fillRect(barX, cursorY, barWidth, segHeight);
            ctx.globalAlpha = 1;
            cursorY += segHeight;
        });

        // Month label
        ctx.fillStyle =
            getComputedStyle(document.documentElement)
                .getPropertyValue('--muted-foreground')
                .trim() || '#71717a';
        ctx.font = '10px Inter, system-ui, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'top';
        ctx.fillText(m.month, centerX, chartHeight + 4);
    });
};

const redrawCharts = () => {
    drawPieChart();
    drawDebtSparkline();
    drawTrendChart();
};

onMounted(() => {
    redrawCharts();
    window.addEventListener('resize', redrawCharts);
});

onUnmounted(() => {
    window.removeEventListener('resize', redrawCharts);
});

// Current month name
const currentMonth = new Date().toLocaleDateString('en-US', {
    month: 'long',
    year: 'numeric',
});
</script>

<template>
    <Head title="Dashboard Overview" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <!-- Header Row with Quick Action -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    Overview of your liquid assets, liabilities, and net worth.
                </p>
            </div>
            <Button
                @click="isTransactionDialogOpen = true"
                class="self-start sm:self-center"
            >
                <Plus class="mr-2 size-4" />
                Add Transaction
            </Button>
        </div>

        <!-- Multi-currency warning: totals below only combine same-currency accounts,
             since there is no exchange-rate conversion in the system yet. -->
        <div
            v-if="metrics.excludedAccountsCount > 0"
            class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-semibold text-amber-700 dark:border-amber-900/50 dark:bg-amber-950/20 dark:text-amber-400"
        >
            {{ metrics.excludedAccountsCount }} account{{
                metrics.excludedAccountsCount === 1 ? '' : 's'
            }}
            in a different currency than {{ metrics.primaryCurrency }}
            {{ metrics.excludedAccountsCount === 1 ? 'is' : 'are' }} not
            included in the totals below — currency conversion isn't supported
            yet.
        </div>

        <!-- Net Worth Hero Card -->
        <Card
            class="relative overflow-hidden border-l-4 border-l-primary bg-gradient-to-br from-primary/5 to-transparent transition duration-200 hover:shadow-sm"
        >
            <CardContent
                class="flex flex-col gap-4 py-5 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-4">
                    <div
                        class="shrink-0 rounded-xl bg-primary/10 p-3 text-primary"
                    >
                        <TrendingUp class="h-6 w-6" />
                    </div>
                    <div class="space-y-0.5">
                        <p
                            class="text-sm font-semibold tracking-tight text-foreground"
                        >
                            Net Worth
                        </p>
                        <p class="text-xs font-medium text-muted-foreground">
                            Total Assets − Total Liabilities
                        </p>
                    </div>
                </div>
                <div
                    class="text-3xl font-bold tracking-tight text-foreground tabular-nums sm:text-4xl"
                >
                    {{
                        formatCurrency(
                            metrics.netWorth,
                            metrics.primaryCurrency,
                        )
                    }}
                </div>
            </CardContent>
        </Card>

        <!-- Supporting 3-Card Grid -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <!-- Liquid Cash -->
            <Card
                class="border-l-4 border-l-emerald-500 transition duration-200 hover:shadow-sm"
            >
                <CardHeader
                    class="flex flex-row items-start justify-between space-y-0 pb-2"
                >
                    <div class="space-y-0.5">
                        <CardTitle
                            class="text-sm font-semibold tracking-tight text-foreground"
                        >
                            Liquid Cash
                        </CardTitle>
                        <p class="text-xs font-medium text-muted-foreground">
                            Bank Accounts + Cash Wallet
                        </p>
                    </div>
                    <div
                        class="shrink-0 rounded-lg bg-emerald-500/10 p-2 text-emerald-500 dark:text-emerald-400"
                    >
                        <Wallet class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold tracking-tight text-foreground tabular-nums"
                    >
                        {{
                            formatCurrency(
                                metrics.liquidCash,
                                metrics.primaryCurrency,
                            )
                        }}
                    </div>
                </CardContent>
            </Card>

            <!-- Cash in Hand -->
            <Card
                class="border-l-4 border-l-violet-500 transition duration-200 hover:shadow-sm"
            >
                <CardHeader
                    class="flex flex-row items-start justify-between space-y-0 pb-2"
                >
                    <div class="space-y-0.5">
                        <CardTitle
                            class="text-sm font-semibold tracking-tight text-foreground"
                        >
                            Cash in Hand
                        </CardTitle>
                        <p class="text-xs font-medium text-muted-foreground">
                            Cash Wallet
                        </p>
                    </div>
                    <div
                        class="shrink-0 rounded-lg bg-violet-500/10 p-2 text-violet-500 dark:text-violet-400"
                    >
                        <Banknote class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div
                        class="text-2xl font-bold tracking-tight text-foreground tabular-nums"
                    >
                        {{
                            formatCurrency(
                                metrics.cashInHand,
                                metrics.primaryCurrency,
                            )
                        }}
                    </div>
                </CardContent>
            </Card>

            <!-- Total Debt -->
            <Card
                class="border-l-4 border-red-200 border-l-red-500 bg-red-50/50 transition duration-200 hover:shadow-sm dark:border-red-900/50 dark:bg-red-950/10"
            >
                <CardHeader
                    class="flex flex-row items-start justify-between space-y-0 pb-2"
                >
                    <div class="space-y-0.5">
                        <CardTitle
                            class="text-sm font-semibold tracking-tight text-red-950 dark:text-red-400"
                        >
                            Total Debt
                        </CardTitle>
                        <p
                            class="text-xs font-medium text-red-900/60 dark:text-red-400/60"
                        >
                            Credit Card Liabilities
                        </p>
                    </div>
                    <div
                        class="shrink-0 rounded-lg bg-red-500/10 p-2 text-red-500 dark:text-red-400"
                    >
                        <CreditCard class="h-4 w-4" />
                    </div>
                </CardHeader>
                <CardContent class="flex items-center justify-between gap-3">
                    <div
                        class="text-2xl font-bold tracking-tight text-red-600 tabular-nums dark:text-red-400"
                    >
                        {{
                            formatCurrency(
                                metrics.totalDebt,
                                metrics.primaryCurrency,
                            )
                        }}
                    </div>
                    <canvas
                        v-if="debtTrend.length > 1"
                        ref="debtSparklineCanvas"
                        class="h-8 w-24 shrink-0"
                    ></canvas>
                </CardContent>
            </Card>
        </div>

        <!-- Monthly Flow + Category Pie Chart Row -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <!-- Monthly Flow Summary Cards (2 small cards stacked) -->
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-1">
                <!-- Monthly Income -->
                <Card
                    class="border-l-4 border-l-emerald-500 transition duration-200 hover:shadow-sm"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 px-5 pt-4 pb-1"
                    >
                        <CardTitle
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >Income this month</CardTitle
                        >
                        <div
                            class="rounded-md bg-emerald-500/10 p-1.5 text-emerald-500"
                        >
                            <ArrowDownLeft class="size-3.5" />
                        </div>
                    </CardHeader>
                    <CardContent class="px-5 pb-4">
                        <div
                            class="text-xl font-bold tracking-tight text-emerald-600 tabular-nums dark:text-emerald-500"
                        >
                            +
                            {{
                                formatCurrency(
                                    metrics.monthlyIncome,
                                    metrics.primaryCurrency,
                                )
                            }}
                        </div>
                    </CardContent>
                </Card>

                <!-- Monthly Expenses -->
                <Card
                    class="border-l-4 border-l-red-500 transition duration-200 hover:shadow-sm"
                >
                    <CardHeader
                        class="flex flex-row items-center justify-between space-y-0 px-5 pt-4 pb-1"
                    >
                        <CardTitle
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                            >Expenses this month</CardTitle
                        >
                        <div
                            class="rounded-md bg-red-500/10 p-1.5 text-red-500"
                        >
                            <ArrowUpRight class="size-3.5" />
                        </div>
                    </CardHeader>
                    <CardContent class="px-5 pb-4">
                        <div
                            class="text-xl font-bold tracking-tight text-red-600 tabular-nums dark:text-red-500"
                        >
                            -
                            {{
                                formatCurrency(
                                    metrics.monthlyExpenses,
                                    metrics.primaryCurrency,
                                )
                            }}
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Category Pie Chart -->
            <Card class="transition duration-200 hover:shadow-sm lg:col-span-2">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-semibold tracking-tight text-foreground"
                    >
                        Spending by Category
                    </CardTitle>
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ currentMonth }}
                    </p>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="categoryBreakdown.length > 0"
                        class="flex flex-col items-center gap-6 sm:flex-row"
                    >
                        <!-- Donut Chart -->
                        <div class="shrink-0">
                            <canvas
                                ref="pieCanvas"
                                width="220"
                                height="220"
                            ></canvas>
                        </div>

                        <!-- Legend & Breakdown -->
                        <div
                            class="max-h-[220px] w-full flex-1 space-y-2 overflow-y-auto pr-1"
                        >
                            <div
                                v-for="(item, index) in categoryBreakdown"
                                :key="item.category"
                                class="flex items-center justify-between rounded-md px-2 py-1.5 transition-colors hover:bg-muted/50"
                            >
                                <div class="flex min-w-0 items-center gap-2.5">
                                    <span
                                        class="size-2.5 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                pieColors[
                                                    index % pieColors.length
                                                ],
                                        }"
                                    ></span>
                                    <span
                                        class="truncate text-sm font-medium text-foreground"
                                        >{{ item.category }}</span
                                    >
                                </div>
                                <div class="ml-3 shrink-0 text-right">
                                    <span
                                        class="text-sm font-semibold text-foreground tabular-nums"
                                    >
                                        {{ formatCurrency(item.total) }}
                                    </span>
                                    <span
                                        class="ml-1.5 text-[10px] text-muted-foreground tabular-nums"
                                    >
                                        {{
                                            totalSpending > 0
                                                ? (
                                                      (item.total /
                                                          totalSpending) *
                                                      100
                                                  ).toFixed(1)
                                                : 0
                                        }}%
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div
                        v-else
                        class="flex flex-col items-center justify-center py-10 text-center"
                    >
                        <div
                            class="mb-3 rounded-full bg-muted p-3 text-muted-foreground"
                        >
                            <TrendingDown class="size-6" />
                        </div>
                        <p class="text-sm font-medium text-muted-foreground">
                            No expense data for this month yet.
                        </p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            Start adding transactions to see your spending
                            breakdown.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Monthly Income vs Expense Trend (Diverging Stacked Bar by Category) -->
        <Card class="transition duration-200 hover:shadow-sm">
            <CardHeader class="pb-2">
                <CardTitle
                    class="text-sm font-semibold tracking-tight text-foreground"
                >
                    Income vs Expenses by Category
                </CardTitle>
                <p class="text-xs font-medium text-muted-foreground">
                    Last 6 months
                </p>
            </CardHeader>
            <CardContent>
                <div
                    v-if="
                        monthlyTrend.some((m) => m.income > 0 || m.expense > 0)
                    "
                >
                    <canvas ref="trendCanvas" class="h-56 w-full"></canvas>

                    <!-- Legend -->
                    <div
                        class="mt-4 flex flex-wrap gap-x-4 gap-y-1.5 border-t border-border pt-3"
                    >
                        <div
                            v-for="category in trendCategoryOrder"
                            :key="category"
                            class="flex items-center gap-1.5"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{
                                    backgroundColor:
                                        trendCategoryColor(category),
                                }"
                            ></span>
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >{{ category }}</span
                            >
                        </div>
                        <div
                            class="ml-auto flex items-center gap-3 text-[10px] font-medium text-muted-foreground"
                        >
                            <span class="flex items-center gap-1"
                                ><span
                                    class="size-2.5 rounded-full bg-zinc-400"
                                ></span
                                >Income (above)</span
                            >
                            <span class="flex items-center gap-1"
                                ><span
                                    class="size-2.5 rounded-full bg-zinc-400 opacity-65"
                                ></span
                                >Expense (below)</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="flex flex-col items-center justify-center py-10 text-center"
                >
                    <div
                        class="mb-3 rounded-full bg-muted p-3 text-muted-foreground"
                    >
                        <TrendingUp class="size-6" />
                    </div>
                    <p class="text-sm font-medium text-muted-foreground">
                        No income or expense history yet.
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Add transactions over time to see monthly trends.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Recent Transactions -->
        <Card class="transition duration-200 hover:shadow-sm">
            <CardHeader
                class="flex flex-row items-center justify-between space-y-0"
            >
                <div>
                    <CardTitle
                        class="text-sm font-semibold tracking-tight text-foreground"
                    >
                        Recent Transactions
                    </CardTitle>
                    <p class="mt-0.5 text-xs font-medium text-muted-foreground">
                        Your last few entries
                    </p>
                </div>
                <Link
                    :href="transactionsIndex()"
                    class="flex shrink-0 items-center gap-1 text-xs font-semibold text-primary hover:underline"
                >
                    View all
                    <ArrowRight class="size-3.5" />
                </Link>
            </CardHeader>
            <CardContent>
                <div
                    v-if="recentTransactions.length > 0"
                    class="divide-y divide-border"
                >
                    <div
                        v-for="tx in recentTransactions"
                        :key="tx.id"
                        class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <div
                                class="shrink-0 rounded-lg p-2"
                                :class="
                                    tx.type === 'income'
                                        ? 'bg-emerald-500/10 text-emerald-500'
                                        : 'bg-red-500/10 text-red-500'
                                "
                            >
                                <ArrowDownLeft
                                    v-if="tx.type === 'income'"
                                    class="size-4"
                                />
                                <ArrowUpRight v-else class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-medium text-foreground"
                                >
                                    {{ tx.description || tx.category }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{ tx.category }} · {{ tx.account.name }} ·
                                    {{ formatShortDate(tx.date) }}
                                </p>
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <div
                                class="text-sm font-semibold tabular-nums"
                                :class="
                                    tx.type === 'income'
                                        ? 'text-emerald-600 dark:text-emerald-500'
                                        : 'text-red-600 dark:text-red-500'
                                "
                            >
                                {{ tx.type === 'income' ? '+' : '-' }}
                                {{ formatCurrency(parseFloat(tx.amount)) }}
                            </div>
                            <div
                                v-if="parseFloat(tx.fee) > 0"
                                class="text-[10px] font-semibold text-amber-600 dark:text-amber-500"
                            >
                                + {{ formatCurrency(parseFloat(tx.fee)) }} fee
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="flex flex-col items-center justify-center py-10 text-center"
                >
                    <div
                        class="mb-3 rounded-full bg-muted p-3 text-muted-foreground"
                    >
                        <Inbox class="size-6" />
                    </div>
                    <p class="text-sm font-medium text-muted-foreground">
                        No transactions recorded yet.
                    </p>
                    <p class="mt-0.5 text-xs text-muted-foreground">
                        Add your first transaction to see it here.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Add Transaction Dialog Form -->
        <Dialog v-model:open="isTransactionDialogOpen">
            <DialogContent class="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Add Transaction</DialogTitle>
                    <DialogDescription>
                        Record a new income, expense, or transfer between
                        accounts.
                    </DialogDescription>
                </DialogHeader>

                <form
                    @submit.prevent="submitTransaction"
                    class="space-y-4 py-2"
                >
                    <!-- Error messages from server validation -->
                    <div
                        v-if="form.errors.balance"
                        class="rounded-lg border border-red-200 bg-red-50 p-3 text-xs font-semibold text-red-600 dark:border-red-900/50 dark:bg-red-950/20 dark:text-red-400"
                    >
                        {{ form.errors.balance }}
                    </div>

                    <!-- Type Tabs -->
                    <div
                        class="grid grid-cols-3 gap-1 rounded-lg bg-muted p-1 text-center text-xs font-semibold"
                    >
                        <button
                            type="button"
                            @click="
                                form.type = 'income';
                                form.category = '';
                            "
                            class="rounded-md py-1.5 transition-all duration-200"
                            :class="
                                form.type === 'income'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            Income
                        </button>
                        <button
                            type="button"
                            @click="
                                form.type = 'expense';
                                form.category = '';
                            "
                            class="rounded-md py-1.5 transition-all duration-200"
                            :class="
                                form.type === 'expense'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                        >
                            Expense
                        </button>
                        <button
                            type="button"
                            @click="
                                form.type = 'transfer';
                                form.category = '';
                            "
                            class="rounded-md py-1.5 transition-all duration-200"
                            :class="
                                form.type === 'transfer'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
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
                            <div
                                v-if="form.errors.amount"
                                class="text-xs text-red-500"
                            >
                                {{ form.errors.amount }}
                            </div>
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
                            <div
                                v-if="form.errors.fee"
                                class="text-xs text-red-500"
                            >
                                {{ form.errors.fee }}
                            </div>
                        </div>
                    </div>

                    <!-- Account / Accounts Selection -->
                    <div v-if="form.type !== 'transfer'" class="grid gap-2">
                        <Label for="account_id">Account</Label>
                        <select
                            id="account_id"
                            v-model="form.account_id"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                            required
                        >
                            <option value="">Select Account</option>
                            <option
                                v-for="acc in accounts"
                                :key="acc.id"
                                :value="acc.id"
                            >
                                {{ acc.name }} ({{
                                    formatCurrency(
                                        parseFloat(acc.balance),
                                        acc.currency,
                                    )
                                }})
                            </option>
                        </select>
                        <div
                            v-if="form.errors.account_id"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.account_id }}
                        </div>
                    </div>

                    <div v-else class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="from_account_id">From Account</Label>
                            <select
                                id="from_account_id"
                                v-model="form.account_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                required
                            >
                                <option value="">Select From</option>
                                <option
                                    v-for="acc in accounts"
                                    :key="acc.id"
                                    :value="acc.id"
                                >
                                    {{ acc.name }} ({{
                                        formatCurrency(
                                            parseFloat(acc.balance),
                                            acc.currency,
                                        )
                                    }})
                                </option>
                            </select>
                            <div
                                v-if="form.errors.account_id"
                                class="text-xs text-red-500"
                            >
                                {{ form.errors.account_id }}
                            </div>
                        </div>

                        <div class="grid gap-2">
                            <Label for="to_account_id">To Account</Label>
                            <select
                                id="to_account_id"
                                v-model="form.to_account_id"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                                required
                            >
                                <option value="">Select To</option>
                                <option
                                    v-for="acc in accounts"
                                    :key="acc.id"
                                    :value="acc.id"
                                    :disabled="acc.id === form.account_id"
                                >
                                    {{ acc.name }} ({{
                                        formatCurrency(
                                            parseFloat(acc.balance),
                                            acc.currency,
                                        )
                                    }})
                                </option>
                            </select>
                            <div
                                v-if="form.errors.to_account_id"
                                class="text-xs text-red-500"
                            >
                                {{ form.errors.to_account_id }}
                            </div>
                        </div>
                    </div>

                    <!-- Category (from DB) -->
                    <div v-if="form.type !== 'transfer'" class="grid gap-2">
                        <Label for="category">Category</Label>
                        <select
                            id="category"
                            v-model="form.category"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                            required
                        >
                            <option value="">Select Category</option>
                            <option
                                v-for="cat in availableCategories"
                                :key="cat.id"
                                :value="cat.name"
                            >
                                {{ cat.name }}
                            </option>
                        </select>
                        <div
                            v-if="form.errors.category"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.category }}
                        </div>
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
                        <div
                            v-if="form.errors.date"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.date }}
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="grid gap-2">
                        <Label for="description">Description (Optional)</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            class="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            placeholder="Keells shopping, ATM withdrawal, etc."
                        ></textarea>
                        <div
                            v-if="form.errors.description"
                            class="text-xs text-red-500"
                        >
                            {{ form.errors.description }}
                        </div>
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
                            {{
                                form.processing
                                    ? 'Saving...'
                                    : 'Add Transaction'
                            }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
