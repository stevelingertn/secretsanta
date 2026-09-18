<?php

namespace App\Http\Controllers\Admin;

use App\Enums\EventStatus;
use App\Exceptions\VotingException;
use App\Models\AuditLog;
use App\Models\Event;
use App\Services\VotingLifecycleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Event details plus the two lifecycle buttons: Open voting and Voting Finished. */
class EventController extends AdminController
{
    public function edit(Request $request): View
    {
        return view('admin.event.edit');
    }

    public function update(Request $request): RedirectResponse
    {
        $event = $request->attributes->get('event');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'year' => ['required', 'integer', 'between:2000,2100'],
            'location' => ['nullable', 'string', 'max:120'],
            'show_date' => ['nullable', 'date'],
            'details' => ['nullable', 'string', 'max:2000'],
            'votes_per_car' => [Rule::requiredIf(! $event || $event->status === EventStatus::Setup), 'integer', 'between:1,50'],
        ]);

        DB::transaction(function () use (&$event, $data, $request) {
            if (! $event) {
                $event = new Event;
                $event->is_active = true;
                $event->status = EventStatus::Setup;
            } else {
                $event = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            }
            if ($event->status !== EventStatus::Setup) {
                // The per-car allowance defines entitlements; freeze it once voting starts.
                unset($data['votes_per_car']);
            }
            $event->fill($data);
            $event->save();
            AuditLog::record('event.updated', $event, $request->user(), $event);
        });

        return redirect()->route('admin.event.edit')->with('status', 'Event details saved.');
    }

    public function open(Request $request, VotingLifecycleService $lifecycle): RedirectResponse
    {
        $request->validate(['confirm' => ['accepted']], ['confirm.accepted' => 'Tick the box to confirm opening voting.']);

        try {
            $lifecycle->open($this->event($request), $request->user());
        } catch (VotingException $e) {
            return back()->withErrors([$e->getMessage()]);
        }

        return redirect()->route('admin.dashboard')->with('status', 'Voting is open.');
    }

    /** "Voting Finished": closes online and paper voting, freezes registration and allowances. */
    public function close(Request $request, VotingLifecycleService $lifecycle): RedirectResponse
    {
        $request->validate(
            ['confirm_paper' => ['accepted']],
            ['confirm_paper.accepted' => 'Confirm that every returned paper ballot has been entered.']
        );

        try {
            $lifecycle->close($this->event($request), $request->user());
        } catch (VotingException $e) {
            return back()->withErrors([$e->getMessage()]);
        }

        return redirect()->route('admin.results.show')->with('status', 'Voting is finished. Results are calculated below.');
    }
}
