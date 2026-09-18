<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Car;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CarController extends Controller
{
    public function show(Request $request, int $entryNumber): View
    {
        $event = $request->attributes->get('event');
        abort_unless($event, 404);

        $car = Car::query()
            ->where('event_id', $event->id)
            ->where('entry_number', $entryNumber)
            ->with('category')
            ->withCount('votes')
            ->firstOrFail();

        return view('public.car', [
            'car' => $car,
            'refreshedAt' => now(),
        ]);
    }
}
