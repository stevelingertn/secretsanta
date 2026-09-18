<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VoteSource;
use App\Models\Category;
use App\Models\Vote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Admin-only vote list and detail. Votes are final: no edit or delete routes exist here.
 * Never displays voter identity: only car, class, source, entering admin, and timestamp.
 */
class VoteController extends AdminController
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Vote::class);
        $event = $this->event($request);

        $query = Vote::query()->where('event_id', $event->id)
            ->with(['car.category', 'enteredBy']);

        if ($source = $request->string('source')->trim()->toString()) {
            $query->where('source', $source);
        }
        if ($carNumber = $request->string('car')->trim()->toString()) {
            $query->whereHas('car', fn ($q) => $q->where('entry_number', $carNumber));
        }
        if ($categoryId = $request->integer('category')) {
            $query->whereHas('car', fn ($q) => $q->where('category_id', $categoryId));
        }

        $votes = $query->orderByDesc('created_at')->paginate(50)->withQueryString();

        $counts = [
            'online' => Vote::query()->where('event_id', $event->id)->where('source', VoteSource::Online->value)->count(),
            'manual' => Vote::query()->where('event_id', $event->id)->where('source', VoteSource::Manual->value)->count(),
        ];

        return view('admin.votes.index', [
            'event' => $event,
            'votes' => $votes,
            'counts' => $counts,
            'categories' => Category::query()->orderBy('name')->get(),
            'filters' => [
                'source' => $request->string('source')->toString(),
                'car' => $request->string('car')->toString(),
                'category' => $request->string('category')->toString(),
            ],
        ]);
    }

    public function show(Request $request, Vote $vote): View
    {
        Gate::authorize('view', $vote);
        $event = $this->event($request);
        abort_unless($vote->event_id === $event->id, 404);

        $vote->load(['car.category', 'enteredBy', 'ballotSubmission']);

        return view('admin.votes.show', ['event' => $event, 'vote' => $vote]);
    }
}
