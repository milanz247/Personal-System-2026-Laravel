<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    /**
     * Display a listing of the categories (shared + the user's own — see Category::booted()).
     */
    public function index(): Response
    {
        $categories = Category::query()->orderBy('name')->get();

        return Inertia::render('categories/Index', [
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Prevent duplicates against both the user's own categories and shared/system ones.
        $exists = Category::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->where('name', $validated['name'])
            ->where('type', $validated['type'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A category with this name and type already exists.'],
            ]);
        }

        $request->user()->categories()->create($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category created successfully.'),
        ]);

        return back();
    }

    /**
     * Rename a category the user owns. Shared/system categories cannot be edited.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $validated = $request->validated();

        $duplicate = Category::query()
            ->where(function ($query) use ($request) {
                $query->whereNull('user_id')->orWhere('user_id', $request->user()->id);
            })
            ->where('name', $validated['name'])
            ->where('type', $category->type)
            ->where('id', '!=', $category->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => ['A category with this name and type already exists.'],
            ]);
        }

        $category->update($validated);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category updated successfully.'),
        ]);

        return back();
    }

    /**
     * Remove the specified category from storage, unless it's a shared/system
     * category or is still referenced by transaction history.
     */
    public function destroy(Request $request, Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        $inUse = Transaction::query()->where('category', $category->name)->exists();

        if ($inUse) {
            throw ValidationException::withMessages([
                'category' => ['This category is used by existing transactions and cannot be deleted. Reassign or remove those transactions first.'],
            ]);
        }

        $category->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Category deleted successfully.'),
        ]);

        return back();
    }
}
