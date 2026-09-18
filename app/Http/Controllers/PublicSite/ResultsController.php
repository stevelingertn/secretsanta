<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\EventStatus;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Public results show only the finalized award snapshot, never provisional or tied results. */
class ResultsController extends Controller
{
    public function show(Request $request): View
    {
        $event = $request->attributes->get('event');
        abort_unless($event, 404);

        $awards = $event->status === EventStatus::Finalized
            ? $event->awards()->with('car:id,photo_path,thumb_path')->get()
            : collect();

        return view('public.results', ['awards' => $awards]);
    }
}
