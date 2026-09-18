<?php

namespace App\Http\Middleware;

use App\Models\Participant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * A contestant session is valid only for the participant it was opened for, in the active
 * event, with the same session version. Rotating a login code bumps the version and ends it.
 */
class EnsureContestant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $event = $request->attributes->get('event');
        $participant = $user && ! $user->isAdmin() && $event
            ? Participant::query()->whereKey($request->session()->get('participant_id'))->where('user_id', $user->id)->where('event_id', $event->id)->first()
            : null;

        if (! $participant || $participant->session_version !== (int) $request->session()->get('participant_version')) {
            if ($user && ! $user->isAdmin()) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return redirect()->guest(route('login'))->with('status', $user ? 'Please sign in again with your login code.' : null);
        }

        $participant->setRelation('event', $event)->setRelation('user', $user);
        $request->attributes->set('participant', $participant);

        return $next($request);
    }
}
