<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/** Loads the active event once per request and shares it with every view as $event. */
class ShareActiveEvent
{
    public function handle(Request $request, Closure $next): Response
    {
        $event = Event::active();
        $request->attributes->set('event', $event);
        View::share('event', $event);

        return $next($request);
    }
}
