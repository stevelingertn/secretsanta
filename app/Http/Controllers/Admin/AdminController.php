<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Participant;
use Illuminate\Http\Request;

abstract class AdminController extends Controller
{
    /** The active event; admin pages other than Event settings require one. */
    protected function event(Request $request): Event
    {
        $event = $request->attributes->get('event');
        abort_unless($event, 404, 'No active event. Create one on the Event page.');

        return $event;
    }

    /** Route-bound participants must belong to the active event. */
    protected function ensureInEvent(Request $request, Participant $participant): Participant
    {
        abort_unless($participant->event_id === $this->event($request)->id, 404);

        return $participant->setRelation('event', $this->event($request));
    }
}
