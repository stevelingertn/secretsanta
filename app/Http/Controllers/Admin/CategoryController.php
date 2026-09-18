<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\AuditLog;
use App\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CategoryController extends AdminController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Category::class);
        $event = $this->event($request);

        $categories = Category::query()
            ->withCount(['cars' => fn ($q) => $event ? $q->where('event_id', $event->id) : $q->whereRaw('1=0')])
            ->orderBy('id')
            ->get();

        return view('admin.categories.index', [
            'event' => $event,
            'categories' => $categories,
            'canCreate' => $event && $event->allowsRegistration(),
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize('create', Category::class);
        $event = $this->event($request);
        abort_unless($event && $event->allowsRegistration(), 403, 'Classes cannot be created once voting is closed.');

        return view('admin.categories.create', [
            'event' => $event,
            'nextId' => (int) (Category::query()->max('id') ?? 0) + 1,
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Gate::authorize('create', Category::class);
        $event = $this->event($request);
        abort_unless($event && $event->allowsRegistration(), 403, 'Classes cannot be created once voting is closed.');

        $category = Category::query()->create($request->validated());
        AuditLog::record('category.created', $event, $request->user(), $category);

        return redirect()->route('admin.categories.index')->with('status', 'Class created.');
    }

    public function edit(Request $request, Category $category): View
    {
        Gate::authorize('update', $category);

        return view('admin.categories.edit', [
            'event' => $this->event($request),
            'category' => $category,
            'locked' => $category->isLockedByHistory(),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        Gate::authorize('update', $category);

        if ($category->isLockedByHistory()) {
            return back()->withErrors(['name' => 'This class has been used in a past or active show and cannot be renamed.'])->withInput();
        }

        $category->update($request->validated());
        AuditLog::record('category.renamed', $this->event($request), $request->user(), $category);

        return redirect()->route('admin.categories.index')->with('status', 'Class renamed.');
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        Gate::authorize('delete', $category);

        if ($category->cars()->exists()) {
            return back()->withErrors(['category' => 'This class has cars registered against it and cannot be deleted.']);
        }

        try {
            $category->delete();
        } catch (QueryException) {
            return back()->withErrors(['category' => 'This class is referenced elsewhere and cannot be deleted.']);
        }

        AuditLog::record('category.deleted', $this->event($request), $request->user(), $category);

        return redirect()->route('admin.categories.index')->with('status', 'Class deleted.');
    }
}
