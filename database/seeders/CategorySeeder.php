<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Seed the application's database with default system-wide categories.
     *
     * These categories have user_id = null, making them available to all users.
     * Users can create their own custom categories on top of these defaults.
     */
    public function run(): void
    {
        $categories = [
            // ────────────────────────────────────────
            // EXPENSE CATEGORIES
            // ────────────────────────────────────────
            ['name' => 'Food & Dining', 'type' => 'expense', 'icon' => 'utensils'],
            ['name' => 'Groceries', 'type' => 'expense', 'icon' => 'shopping-cart'],
            ['name' => 'Transport', 'type' => 'expense', 'icon' => 'car'],
            ['name' => 'Fuel & Petrol', 'type' => 'expense', 'icon' => 'fuel'],
            ['name' => 'Rent', 'type' => 'expense', 'icon' => 'home'],
            ['name' => 'Utilities', 'type' => 'expense', 'icon' => 'zap'],
            ['name' => 'Electricity', 'type' => 'expense', 'icon' => 'lightbulb'],
            ['name' => 'Water', 'type' => 'expense', 'icon' => 'droplets'],
            ['name' => 'Internet & Phone', 'type' => 'expense', 'icon' => 'wifi'],
            ['name' => 'Entertainment', 'type' => 'expense', 'icon' => 'film'],
            ['name' => 'Shopping', 'type' => 'expense', 'icon' => 'shopping-bag'],
            ['name' => 'Clothing & Fashion', 'type' => 'expense', 'icon' => 'shirt'],
            ['name' => 'Medical & Health', 'type' => 'expense', 'icon' => 'heart-pulse'],
            ['name' => 'Pharmacy', 'type' => 'expense', 'icon' => 'pill'],
            ['name' => 'Insurance', 'type' => 'expense', 'icon' => 'shield'],
            ['name' => 'Education', 'type' => 'expense', 'icon' => 'graduation-cap'],
            ['name' => 'Books & Courses', 'type' => 'expense', 'icon' => 'book-open'],
            ['name' => 'Bills & Debt', 'type' => 'expense', 'icon' => 'receipt'],
            ['name' => 'Loan Repayment', 'type' => 'expense', 'icon' => 'banknote'],
            ['name' => 'Credit Card Payment', 'type' => 'expense', 'icon' => 'credit-card'],
            ['name' => 'Subscriptions', 'type' => 'expense', 'icon' => 'repeat'],
            ['name' => 'Gifts & Donations', 'type' => 'expense', 'icon' => 'gift'],
            ['name' => 'Travel & Vacation', 'type' => 'expense', 'icon' => 'plane'],
            ['name' => 'Home Maintenance', 'type' => 'expense', 'icon' => 'wrench'],
            ['name' => 'Personal Care', 'type' => 'expense', 'icon' => 'sparkles'],
            ['name' => 'Pets', 'type' => 'expense', 'icon' => 'paw-print'],
            ['name' => 'Taxes', 'type' => 'expense', 'icon' => 'landmark'],
            ['name' => 'Childcare & Kids', 'type' => 'expense', 'icon' => 'baby'],
            ['name' => 'Fees & Charges', 'type' => 'expense', 'icon' => 'file-text'],
            ['name' => 'Other Expense', 'type' => 'expense', 'icon' => 'circle-dot'],

            // ────────────────────────────────────────
            // INCOME CATEGORIES
            // ────────────────────────────────────────
            ['name' => 'Salary', 'type' => 'income', 'icon' => 'briefcase'],
            ['name' => 'Freelance', 'type' => 'income', 'icon' => 'laptop'],
            ['name' => 'Business Income', 'type' => 'income', 'icon' => 'building'],
            ['name' => 'Investments', 'type' => 'income', 'icon' => 'trending-up'],
            ['name' => 'Dividends', 'type' => 'income', 'icon' => 'bar-chart'],
            ['name' => 'Interest', 'type' => 'income', 'icon' => 'percent'],
            ['name' => 'Rental Income', 'type' => 'income', 'icon' => 'home'],
            ['name' => 'Commission', 'type' => 'income', 'icon' => 'handshake'],
            ['name' => 'Bonus', 'type' => 'income', 'icon' => 'award'],
            ['name' => 'Gifts Received', 'type' => 'income', 'icon' => 'gift'],
            ['name' => 'Refund', 'type' => 'income', 'icon' => 'rotate-ccw'],
            ['name' => 'Side Hustle', 'type' => 'income', 'icon' => 'zap'],
            ['name' => 'Pension', 'type' => 'income', 'icon' => 'clock'],
            ['name' => 'Government Aid', 'type' => 'income', 'icon' => 'landmark'],
            ['name' => 'Other Income', 'type' => 'income', 'icon' => 'circle-dot'],
        ];

        foreach ($categories as $category) {
            Category::firstOrCreate(
                [
                    'name' => $category['name'],
                    'type' => $category['type'],
                    'user_id' => null,
                ],
                [
                    'icon' => $category['icon'],
                ],
            );
        }
    }
}
