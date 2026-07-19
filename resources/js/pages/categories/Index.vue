<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card';
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
    Plus,
    Trash2,
    Tags,
    Search,
    Lock,
    ArrowUpRight,
    ArrowDownLeft,
    Tag
} from '@lucide/vue';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Categories',
                href: '/categories',
            },
        ],
    },
});

const props = defineProps<{
    categories: Array<{
        id: number;
        user_id: number | null;
        name: string;
        type: 'expense' | 'income';
        icon: string | null;
        created_at: string;
        updated_at: string;
    }>;
}>();

// UI States
const isAddDialogOpen = ref(false);
const searchQuery = ref('');
const activeTab = ref<'all' | 'expense' | 'income'>('all');

// Form for adding a category
const form = useForm({
    name: '',
    type: 'expense' as 'expense' | 'income',
    icon: '',
});

// Category metrics
const totalCount = computed(() => props.categories.length);
const expenseCount = computed(() => props.categories.filter(c => c.type === 'expense').length);
const incomeCount = computed(() => props.categories.filter(c => c.type === 'income').length);

// Search & filter logic
const filteredCategories = computed(() => {
    return props.categories.filter(category => {
        // Tab type filter
        if (activeTab.value !== 'all' && category.type !== activeTab.value) {
            return false;
        }

        // Search text filter
        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase();
            return category.name.toLowerCase().includes(query);
        }

        return true;
    });
});

const submitCategory = () => {
    form.post('/categories', {
        onSuccess: () => {
            isAddDialogOpen.value = false;
            form.reset();
        },
    });
};

const deleteCategory = (id: number) => {
    if (confirm('Are you sure you want to delete this category? Any associated transaction will retain its label, but the category category record itself will be permanently deleted.')) {
        router.delete(`/categories/${id}`);
    }
};
</script>

<template>
    <Head title="Categories" />

    <div class="flex flex-1 flex-col gap-6 p-6">
        <!-- Header Section -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Categories</h1>
                <p class="text-sm text-muted-foreground mt-1">
                    Manage and organize expense and income categories for transaction sorting.
                </p>
            </div>
            <div>
                <Button @click="isAddDialogOpen = true" class="w-full sm:w-auto flex items-center justify-center gap-2">
                    <Plus class="size-4" />
                    Add Category
                </Button>
            </div>
        </div>

        <!-- Metrics Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Card class="transition duration-200 hover:shadow-sm">
                <CardHeader class="flex flex-row items-start justify-between space-y-0 pb-2">
                    <div class="space-y-0.5">
                        <CardTitle class="text-sm font-semibold tracking-tight text-foreground">
                            Total Categories
                        </CardTitle>
                        <p class="text-xs text-muted-foreground font-medium">System & custom categories</p>
                    </div>
                    <div class="p-2 bg-amber-500/10 text-amber-500 dark:text-amber-400 rounded-lg shrink-0">
                        <Tags class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-foreground">{{ totalCount }}</div>
                </CardContent>
            </Card>

            <Card class="transition duration-200 hover:shadow-sm">
                <CardHeader class="flex flex-row items-start justify-between space-y-0 pb-2">
                    <div class="space-y-0.5">
                        <CardTitle class="text-sm font-semibold tracking-tight text-foreground">
                            Expenses Categories
                        </CardTitle>
                        <p class="text-xs text-muted-foreground font-medium">For sorting system outflows</p>
                    </div>
                    <div class="p-2 bg-red-500/10 text-red-500 dark:text-red-400 rounded-lg shrink-0">
                        <ArrowUpRight class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-foreground text-red-500">{{ expenseCount }}</div>
                </CardContent>
            </Card>

            <Card class="transition duration-200 hover:shadow-sm">
                <CardHeader class="flex flex-row items-start justify-between space-y-0 pb-2">
                    <div class="space-y-0.5">
                        <CardTitle class="text-sm font-semibold tracking-tight text-foreground">
                            Income Categories
                        </CardTitle>
                        <p class="text-xs text-muted-foreground font-medium">For tracking inward flows</p>
                    </div>
                    <div class="p-2 bg-emerald-500/10 text-emerald-500 dark:text-emerald-400 rounded-lg shrink-0">
                        <ArrowDownLeft class="size-4" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold tracking-tight text-foreground text-emerald-500">{{ incomeCount }}</div>
                </CardContent>
            </Card>
        </div>

        <!-- Filter & Search Controls -->
        <Card class="border shadow-sm">
            <CardHeader class="p-5 border-b flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <!-- Tabs -->
                <div class="flex border rounded-lg p-1 bg-muted/30 w-full sm:w-auto">
                    <button
                        type="button"
                        @click="activeTab = 'all'"
                        class="flex-1 sm:flex-none px-4 py-1.5 text-xs font-semibold rounded-md transition-colors"
                        :class="activeTab === 'all' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    >
                        All
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'expense'"
                        class="flex-1 sm:flex-none px-4 py-1.5 text-xs font-semibold rounded-md transition-colors"
                        :class="activeTab === 'expense' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    >
                        Expenses
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'income'"
                        class="flex-1 sm:flex-none px-4 py-1.5 text-xs font-semibold rounded-md transition-colors"
                        :class="activeTab === 'income' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'"
                    >
                        Income
                    </button>
                </div>

                <!-- Search Input -->
                <div class="relative w-full sm:max-w-xs">
                    <Search class="absolute left-3 top-2.5 size-4 text-muted-foreground pointer-events-none" />
                    <Input
                        type="text"
                        v-model="searchQuery"
                        placeholder="Search categories..."
                        class="pl-9 h-9 text-sm"
                    />
                </div>
            </CardHeader>

            <CardContent class="p-6">
                <!-- Categories Grid -->
                <div v-if="filteredCategories.length > 0" class="grid gap-4 grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
                    <div 
                        v-for="category in filteredCategories" 
                        :key="category.id" 
                        class="group relative flex items-center justify-between p-4 border rounded-xl bg-background hover:bg-muted/10 hover:border-border transition-all duration-200"
                    >
                        <div class="flex items-center gap-3">
                            <div 
                                class="p-2.5 rounded-lg shrink-0 animate-none"
                                :class="category.type === 'income' 
                                    ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-500' 
                                    : 'bg-red-500/10 text-red-500'"
                            >
                                <Tag class="size-4" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-foreground truncate pr-6">{{ category.name }}</p>
                                <span 
                                    class="inline-block text-[10px] font-bold uppercase tracking-wider mt-0.5"
                                    :class="category.type === 'income' ? 'text-emerald-600 dark:text-emerald-500' : 'text-red-500'"
                                >
                                    {{ category.type }}
                                </span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-1.5">
                            <span 
                                v-if="category.user_id === null" 
                                class="p-1 text-muted-foreground/60"
                                title="Locked System Default"
                            >
                                <Lock class="size-3.5" />
                            </span>
                            <button
                                v-else
                                @click="deleteCategory(category.id)"
                                class="p-1.5 text-muted-foreground hover:text-destructive hover:bg-destructive/10 rounded-md transition-colors"
                                title="Delete Category"
                            >
                                <Trash2 class="size-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center py-12 text-center">
                    <div class="p-4 bg-muted rounded-full text-muted-foreground">
                        <Tags class="size-8" />
                    </div>
                    <h3 class="text-sm font-bold text-foreground mt-4">No categories found</h3>
                    <p class="text-xs text-muted-foreground mt-1 max-w-xs">
                        Adjust your search query or type filters, or create a new custom category.
                    </p>
                </div>
            </CardContent>
        </Card>

        <!-- Right Side Slide-over Panel (Sheet) -->
        <Sheet v-model:open="isAddDialogOpen">
            <SheetContent side="right" class="w-full sm:max-w-[500px] p-6 sm:p-8 overflow-y-auto space-y-6">
                <SheetHeader>
                    <SheetTitle>Add Category</SheetTitle>
                    <SheetDescription>
                        Create a new custom category to organize transaction records.
                    </SheetDescription>
                </SheetHeader>

                <form @submit.prevent="submitCategory" class="space-y-5">
                    <div class="space-y-1.5">
                        <Label for="cat_name">Category Name</Label>
                        <Input
                            id="cat_name"
                            type="text"
                            v-model="form.name"
                            placeholder="e.g. Subscriptions, Salary"
                            class="text-sm"
                            required
                        />
                        <div v-if="form.errors.name" class="text-xs text-destructive">{{ form.errors.name }}</div>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="cat_type">Type</Label>
                        <select
                            id="cat_type"
                            v-model="form.type"
                            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 appearance-none bg-[url('data:image/svg+xml;charset=utf-8,%3Csvg_xmlns=%22http://www.w3.org/2000/svg%22_fill=%22none%22_viewBox=%220_0_20_20%22%3E%3Cpath_stroke=%22%236b7280%22_stroke-linecap=%22round%22_stroke-linejoin=%22round%22_stroke-width=%221.5%22_d=%22M6_8l4_4_4-4%22/%3E%3C/svg%3E')] bg-[position:right_0.75rem_center] bg-[size:1.25rem] bg-no-repeat pr-10"
                            required
                        >
                            <option value="expense">Expense</option>
                            <option value="income">Income</option>
                        </select>
                        <div v-if="form.errors.type" class="text-xs text-destructive">{{ form.errors.type }}</div>
                    </div>

                    <SheetFooter class="pt-4 flex flex-row gap-3 justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            @click="isAddDialogOpen = false"
                            :disabled="form.processing"
                            class="w-full sm:w-auto"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" :disabled="form.processing" class="w-full sm:w-auto">
                            {{ form.processing ? 'Saving...' : 'Add Category' }}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    </div>
</template>
