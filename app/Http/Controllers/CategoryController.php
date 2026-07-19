<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories.
     */
    public function index(Request $request): Response
    {
        $categories = Category::query()
            ->whereNull('user_id')
            ->orWhere('user_id', $request->user()->id)
            ->orderBy('name')
            ->get();

        return Inertia::render('categories/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'type' => 'required|in:expense,income',
            'icon' => 'nullable|string|max:50',
        ]);

        // Prevent duplicate user categories with the same name and type
        $exists = Category::query()
            ->where('user_id', $request->user()->id)
            ->where('name', $validated['name'])
            ->where('type', $validated['type'])
            ->exists();

        if ($exists) {
            return back()->withErrors(['name' => 'A category with this name and type already exists.']);
        }

        $request->user()->categories()->create($validated);

        return back()->with('success', 'Category created successfully.');
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        // Enforce ownership checks
        if ($category->user_id !== $request->user()->id) {
            abort(403, 'Unauthorized action. You can only delete your own categories.');
        }

        $category->delete();

        return back()->with('success', 'Category deleted successfully.');
    }
}
