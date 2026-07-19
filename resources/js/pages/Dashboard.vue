<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import {
    TrendingUp,
    TrendingDown,
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

// Slice currently under the cursor, popped out and highlighted on hover.
const hoveredCategoryIndex = ref<number | null>(null);
const pieTooltip = ref<{
    visible: boolean;
    x: number;
    y: number;
    category: string;
    amount: number;
    percent: number;
}>({
    visible: false,
    x: 0,
    y: 0,
    category: '',
    amount: 0,
    percent: 0,
});

const pieOuterRadius = 100;
const pieInnerRadius = 60; // Donut hole

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
    const popOut = 10; // px a popped slice is pushed outward by

    let startAngle = -Math.PI / 2; // Start from top

    props.categoryBreakdown.forEach((item, index) => {
        const sliceAngle = (item.total / totalSpending.value) * 2 * Math.PI;
        const endAngle = startAngle + sliceAngle;
        const midAngle = (startAngle + endAngle) / 2;

        const isHovered = index === hoveredCategoryIndex.value;
        const isPopped = index === largestCategoryIndex.value || isHovered;
        const centerX = isPopped
            ? baseCenterX + Math.cos(midAngle) * popOut
            : baseCenterX;
        const centerY = isPopped
            ? baseCenterY + Math.sin(midAngle) * popOut
            : baseCenterY;

        ctx.beginPath();
        ctx.arc(centerX, centerY, pieOuterRadius, startAngle, endAngle);
        ctx.arc(centerX, centerY, pieInnerRadius, endAngle, startAngle, true);
        ctx.closePath();
        ctx.fillStyle = pieColors[index % pieColors.length];

        if (isPopped) {
            ctx.shadowColor = isHovered
                ? 'rgba(0, 0, 0, 0.35)'
                : 'rgba(0, 0, 0, 0.25)';
            ctx.shadowBlur = isHovered ? 12 : 8;
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

// Determines which slice (if any) the cursor is over and updates the hover tooltip.
const handlePieMouseMove = (event: MouseEvent) => {
    const canvas = pieCanvas.value;

    if (!canvas || props.categoryBreakdown.length === 0) {
        return;
    }

    const rect = canvas.getBoundingClientRect();
    const scale = rect.width / 220;
    const x = event.clientX - rect.left;
    const y = event.clientY - rect.top;
    const dx = x - rect.width / 2;
    const dy = y - rect.height / 2;
    const dist = Math.sqrt(dx * dx + dy * dy);

    if (
        dist < pieInnerRadius * scale ||
        dist > pieOuterRadius * scale ||
        totalSpending.value <= 0
    ) {
        handlePieMouseLeave();

        return;
    }

    let angle = Math.atan2(dy, dx) + Math.PI / 2;

    if (angle < 0) {
        angle += 2 * Math.PI;
    }

    let cumulative = 0;
    let foundIndex = -1;

    for (let i = 0; i < props.categoryBreakdown.length; i++) {
        const sliceAngle =
            (props.categoryBreakdown[i].total / totalSpending.value) *
            2 *
            Math.PI;

        if (angle >= cumulative && angle < cumulative + sliceAngle) {
            foundIndex = i;
            break;
        }

        cumulative += sliceAngle;
    }

    if (foundIndex === -1) {
        handlePieMouseLeave();

        return;
    }

    if (hoveredCategoryIndex.value !== foundIndex) {
        hoveredCategoryIndex.value = foundIndex;
        drawPieChart();
    }

    const item = props.categoryBreakdown[foundIndex];
    pieTooltip.value = {
        visible: true,
        x,
        y,
        category: item.category,
        amount: item.total,
        percent: (item.total / totalSpending.value) * 100,
    };
};

const handlePieMouseLeave = () => {
    if (hoveredCategoryIndex.value !== null) {
        hoveredCategoryIndex.value = null;
        drawPieChart();
    }

    pieTooltip.value.visible = false;
};

// Mirrors the slice pop-out when the cursor is over a legend row instead of the donut itself.
const handleLegendHover = (index: number) => {
    hoveredCategoryIndex.value = index;
    drawPieChart();
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

// === MONTHLY INCOME / EXPENSE TREND (Pure Canvas) ===
const trendCanvas = ref<HTMLCanvasElement | null>(null);

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
    const baseY = chartHeight - 4;
    const usableHeight = chartHeight - 12;

    const barCount = props.monthlyTrend.length;
    const slotWidth = width / barCount;
    const barWidth = Math.min(22, slotWidth * 0.28);
    const barGap = 4;

    props.monthlyTrend.forEach((m, i) => {
        const centerX = slotWidth * i + slotWidth / 2;
        const incomeX = centerX - barGap / 2 - barWidth;
        const expenseX = centerX + barGap / 2;

        const incomeHeight = (m.income / maxValue) * usableHeight;
        const expenseHeight = (m.expense / maxValue) * usableHeight;

        ctx.fillStyle = '#22c55e';
        ctx.fillRect(incomeX, baseY - incomeHeight, barWidth, incomeHeight);

        ctx.fillStyle = '#ef4444';
        ctx.fillRect(expenseX, baseY - expenseHeight, barWidth, expenseHeight);

        // Baseline tick under each month's bar pair
        ctx.beginPath();
        ctx.moveTo(incomeX, baseY);
        ctx.lineTo(expenseX + barWidth, baseY);
        ctx.strokeStyle = 'rgba(113, 113, 122, 0.25)';
        ctx.lineWidth = 1;
        ctx.stroke();

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

        <!-- Top Row: Income/Expenses (combined), Net Worth -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <!-- Income + Expenses (split card, centered) -->
            <Card
                class="gap-0 overflow-hidden py-0 transition duration-200 hover:shadow-sm"
            >
                <div class="grid h-full grid-cols-2 divide-x divide-border">
                    <div
                        class="flex flex-col items-center justify-center gap-2 bg-emerald-500/5 p-6 text-center dark:bg-emerald-500/10"
                    >
                        <div
                            class="rounded-full bg-emerald-500/10 p-3 text-emerald-500"
                        >
                            <ArrowDownLeft class="h-6 w-6" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-semibold tracking-tight text-foreground"
                            >
                                Income
                            </p>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                This month
                            </p>
                        </div>
                        <div
                            class="text-2xl font-bold tracking-tight text-emerald-600 tabular-nums dark:text-emerald-500"
                        >
                            +
                            {{
                                formatCurrency(
                                    metrics.monthlyIncome,
                                    metrics.primaryCurrency,
                                )
                            }}
                        </div>
                    </div>

                    <div
                        class="flex flex-col items-center justify-center gap-2 bg-red-500/5 p-6 text-center dark:bg-red-500/10"
                    >
                        <div
                            class="rounded-full bg-red-500/10 p-3 text-red-500"
                        >
                            <ArrowUpRight class="h-6 w-6" />
                        </div>
                        <div>
                            <p
                                class="text-sm font-semibold tracking-tight text-foreground"
                            >
                                Expenses
                            </p>
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                This month
                            </p>
                        </div>
                        <div
                            class="text-2xl font-bold tracking-tight text-red-600 tabular-nums dark:text-red-500"
                        >
                            -
                            {{
                                formatCurrency(
                                    metrics.monthlyExpenses,
                                    metrics.primaryCurrency,
                                )
                            }}
                        </div>
                    </div>
                </div>
            </Card>

            <!-- Net Worth (with Liquid Cash / Cash in Hand / Total Debt breakdown, centered) -->
            <Card
                class="gap-3 border-l-4 border-l-primary/30 py-4 text-center transition duration-200 hover:shadow-sm"
            >
                <CardHeader class="flex flex-col items-center space-y-0 px-4">
                    <div
                        class="mb-1 rounded-full bg-primary/10 p-2.5 text-primary"
                    >
                        <TrendingUp class="h-5 w-5" />
                    </div>
                    <CardTitle
                        class="text-base font-bold tracking-tight text-foreground"
                    >
                        Net Worth
                    </CardTitle>
                    <p class="text-xs font-medium text-muted-foreground">
                        Total Assets − Total Liabilities
                    </p>
                </CardHeader>
                <CardContent class="px-4">
                    <div
                        class="text-3xl font-bold tracking-tight text-foreground tabular-nums"
                    >
                        {{
                            formatCurrency(
                                metrics.netWorth,
                                metrics.primaryCurrency,
                            )
                        }}
                    </div>

                    <div
                        class="mt-3 grid grid-cols-3 divide-x divide-border border-t border-border pt-3"
                    >
                        <div class="flex flex-col items-center gap-1 px-1">
                            <span
                                class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                            >
                                <span
                                    class="size-2 rounded-full bg-emerald-500"
                                ></span>
                                Liquid Cash
                            </span>
                            <span
                                class="text-sm font-semibold text-foreground tabular-nums"
                            >
                                {{
                                    formatCurrency(
                                        metrics.liquidCash,
                                        metrics.primaryCurrency,
                                    )
                                }}
                            </span>
                        </div>
                        <div class="flex flex-col items-center gap-1 px-1">
                            <span
                                class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                            >
                                <span
                                    class="size-2 rounded-full bg-violet-500"
                                ></span>
                                Cash in Hand
                            </span>
                            <span
                                class="text-sm font-semibold text-foreground tabular-nums"
                            >
                                {{
                                    formatCurrency(
                                        metrics.cashInHand,
                                        metrics.primaryCurrency,
                                    )
                                }}
                            </span>
                        </div>
                        <div class="flex flex-col items-center gap-1 px-1">
                            <span
                                class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                            >
                                <span
                                    class="size-2 rounded-full bg-red-500"
                                ></span>
                                Total Debt
                            </span>
                            <span
                                class="text-sm font-semibold text-red-600 tabular-nums dark:text-red-400"
                            >
                                −
                                {{
                                    formatCurrency(
                                        metrics.totalDebt,
                                        metrics.primaryCurrency,
                                    )
                                }}
                            </span>
                        </div>
                    </div>
                    <canvas
                        v-if="debtTrend.length > 1"
                        ref="debtSparklineCanvas"
                        class="mt-3 h-5 w-full"
                    ></canvas>
                </CardContent>
            </Card>
        </div>

        <!-- Charts Row: Spending by Category + Income vs Expenses -->
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <!-- Category Pie Chart -->
            <Card class="transition duration-200 hover:shadow-sm">
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
                        <div class="relative shrink-0">
                            <canvas
                                ref="pieCanvas"
                                width="220"
                                height="220"
                                class="cursor-pointer"
                                @mousemove="handlePieMouseMove"
                                @mouseleave="handlePieMouseLeave"
                            ></canvas>

                            <!-- Hover Tooltip -->
                            <div
                                v-if="pieTooltip.visible"
                                class="pointer-events-none absolute z-10 rounded-md border border-border bg-popover px-2.5 py-1.5 text-xs whitespace-nowrap shadow-md transition-opacity duration-150"
                                :style="{
                                    left: pieTooltip.x + 'px',
                                    top: pieTooltip.y + 'px',
                                    transform: 'translate(-50%, -125%)',
                                }"
                            >
                                <p
                                    class="font-semibold text-popover-foreground"
                                >
                                    {{ pieTooltip.category }}
                                </p>
                                <p class="text-muted-foreground tabular-nums">
                                    {{ formatCurrency(pieTooltip.amount) }} ·
                                    {{ pieTooltip.percent.toFixed(1) }}%
                                </p>
                            </div>
                        </div>

                        <!-- Legend & Breakdown -->
                        <div
                            class="max-h-[220px] w-full flex-1 space-y-2 overflow-y-auto pr-1"
                        >
                            <div
                                v-for="(item, index) in categoryBreakdown"
                                :key="item.category"
                                class="flex items-center justify-between rounded-md px-2 py-1.5 transition-colors hover:bg-muted/50"
                                @mouseenter="handleLegendHover(index)"
                                @mouseleave="handlePieMouseLeave"
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

            <!-- Monthly Income vs Expense Trend -->
            <Card class="transition duration-200 hover:shadow-sm">
                <CardHeader class="pb-2">
                    <CardTitle
                        class="text-sm font-semibold tracking-tight text-foreground"
                    >
                        Income vs Expenses
                    </CardTitle>
                    <p class="text-xs font-medium text-muted-foreground">
                        Last 6 months
                    </p>
                </CardHeader>
                <CardContent>
                    <div
                        v-if="
                            monthlyTrend.some(
                                (m) => m.income > 0 || m.expense > 0,
                            )
                        "
                    >
                        <canvas ref="trendCanvas" class="h-56 w-full"></canvas>

                        <!-- Legend -->
                        <div
                            class="mt-4 flex items-center gap-4 border-t border-border pt-3"
                        >
                            <span
                                class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                            >
                                <span
                                    class="size-2.5 rounded-full bg-emerald-500"
                                ></span>
                                Income
                            </span>
                            <span
                                class="flex items-center gap-1.5 text-xs font-medium text-muted-foreground"
                            >
                                <span
                                    class="size-2.5 rounded-full bg-red-500"
                                ></span>
                                Expenses
                            </span>
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
        </div>

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
