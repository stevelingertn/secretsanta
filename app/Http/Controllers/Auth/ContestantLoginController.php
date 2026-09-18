<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\LoginCodeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ContestantLoginController extends Controller
{
    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request, LoginCodeService $codes): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:40']]);
        $event = $request->attributes->get('event');

        $participant = $event ? $codes->findParticipant($request->string('code'), $event) : null;
        if (! $participant || $participant->user->isAdmin()) {
            throw ValidationException::withMessages(['code' => 'That login code was not recognized. Check the code on your ballot and try again.']);
        }

        Auth::login($participant->user);
        $request->session()->regenerate();
        $request->session()->put([
            'participant_id' => $participant->id,
            'participant_version' => $participant->session_version,
        ]);

        return redirect()->route('ballot.index');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('gallery')->with('status', 'You are signed out.');
    }
}
