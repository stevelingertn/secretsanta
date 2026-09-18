<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Category;
use App\Models\Event;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GalleryController extends Controller
{
    public function index(Request $request): View
    {
        /** @var Event|null $event */
        $event = $request->attributes->get('event');
        if (! $event) {
            return view('public.no-event');
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'class' => ['nullable', 'integer'],
        ]);

        $cars = Car::query()
            ->where('event_id', $event->id)
            ->with('category')
            ->withCount('votes')
            ->search($filters['q'] ?? null)
            ->when($filters['class'] ?? null, fn ($q, $class) => $q->where('category_id', $class))
            ->orderBy('entry_number')
            ->paginate(24)
            ->withQueryString();

        $categories = Category::query()
            ->whereHas('cars', fn ($q) => $q->where('event_id', $event->id))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('public.gallery', [
            'cars' => $cars,
            'categories' => $categories,
            'filters' => $filters,
            'refreshedAt' => now(),
        ]);
    }

    /** Live contestant vote counts by car number. No personal data. */
    public function tallies(Request $request): JsonResponse
    {
        $event = $request->attributes->get('event');
        $counts = $event
            ? Vote::query()->join('cars', 'cars.id', '=', 'votes.car_id')
                ->where('votes.event_id', $event->id)
                ->selectRaw('cars.entry_number, COUNT(*) as total')
                ->groupBy('cars.entry_number')
                ->pluck('total', 'entry_number')
            : collect();

        return response()->json([
            'refreshed_at' => now()->inShowTz()->format('g:i A'),
            'status' => $event?->status->value,
            'counts' => $counts->map(fn ($v) => (int) $v),
        ])->header('Cache-Control', 'no-store');
    }
}
