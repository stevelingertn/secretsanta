@extends('layouts.admin', ['title' => 'Paper ballot'])

@section('content')
<p class="no-print"><a href="{{ route('admin.paper.index') }}" class="font-semibold underline">Find another contestant</a></p>

{{-- Contestant identity and allowance stay visible the whole time. --}}
<section class="sticky top-0 z-10 mt-3 rounded-xl border-2 border-ink bg-white p-4 shadow-sm" aria-label="Contestant">
    <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
        <div>
            <p class="font-display text-3xl font-bold leading-none">Voter #{{ $participant->voter_number ?? '-' }}</p>
            <p class="text-lg font-semibold">{{ $participant->user->name }}</p>
        </div>
        <div class="flex flex-wrap gap-1" aria-label="Their cars">
            @foreach ($participant->cars as $car)
                <span class="plate plate-sm" title="Their car">{{ $car->entry_number }}</span>
            @endforeach
        </div>
        <dl class="ml-auto flex gap-5 text-center">
            <div><dt class="text-xs font-semibold uppercase text-muted">Allowance</dt><dd class="font-display text-3xl font-extrabold tabular-nums">{{ $summary['allowance'] }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase text-muted">Used</dt><dd class="font-display text-3xl font-extrabold tabular-nums">{{ $summary['used'] }}</dd></div>
            <div class="rounded-lg bg-brand-yellow px-3"><dt class="text-xs font-semibold uppercase">Left</dt><dd class="font-display text-3xl font-extrabold tabular-nums">{{ $summary['remaining'] }}</dd></div>
        </dl>
    </div>
    @if ($votedNumbers->isNotEmpty())
        <p class="mt-2 text-sm">
            <span class="font-semibold">Already voted for:</span>
            @foreach ($votedNumbers as $v)
                <span class="whitespace-nowrap">#{{ $v['number'] }} <span class="text-muted">({{ $v['source'] }})</span>@if (! $loop->last), @endif</span>
            @endforeach
        </p>
    @endif
</section>

@if (! $event->isVotingOpen())
    <div class="mt-4 rounded-lg border-2 border-warn bg-warn-soft px-4 py-3 font-semibold text-warn">Voting is not open. Nothing can be entered.</div>
@else
    <form method="POST" action="{{ route('admin.paper.preview', $participant) }}" class="card mt-5 p-5">
        @csrf
        <label for="car_numbers" class="field-label">Car numbers written on the ballot</label>
        <input id="car_numbers" name="car_numbers" value="{{ $input }}" class="field font-display text-2xl tracking-wide" @unless ($preview && $preview['valid']) autofocus @endunless autocomplete="off" inputmode="numeric"
               placeholder="12 7 31 4" aria-describedby="car-numbers-help">
        <p id="car-numbers-help" class="mt-1 text-sm text-muted">Separate with spaces or commas, then press Enter to check. Nothing is saved until you confirm.</p>
        @error('car_numbers')<p class="field-error">{{ $message }}</p>@enderror
        <button class="btn btn-secondary mt-3">Check ballot</button>
    </form>

    @if ($preview)
        <section class="mt-5" aria-labelledby="preview-heading">
            <h2 id="preview-heading" class="text-2xl uppercase">Check before saving</h2>
            <div class="mt-2 overflow-x-auto rounded-xl border border-line bg-white">
                <table class="table-plain">
                    <thead><tr><th>Entered</th><th>Car</th><th>Class</th><th>Status</th></tr></thead>
                    <tbody>
                        @foreach ($preview['lines'] as $line)
                            <tr @class(['bg-bad-soft' => $line['status'] !== 'ok'])>
                                <td class="font-display text-xl font-bold">{{ $line['input'] }}</td>
                                <td>{{ $line['car']?->description ?? '' }}</td>
                                <td>{{ $line['car']?->category?->name ?? '' }}</td>
                                <td>
                                    @if ($line['status'] === 'ok')
                                        <span class="badge bg-ok-soft text-ok">Ready</span>
                                    @else
                                        <span class="font-semibold text-bad">{{ $line['message'] }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($preview['valid'])
                <form method="POST" action="{{ route('admin.paper.store', $participant) }}" class="mt-4 flex flex-wrap items-center gap-3"
                      x-data="{ sending: false }" x-on:submit="sending = true">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                    @foreach ($tokens as $token)
                        <input type="hidden" name="tokens[]" value="{{ $token }}">
                    @endforeach
                    <button class="btn btn-primary" autofocus x-bind:disabled="sending">
                        <span x-show="!sending">Save {{ $preview['count'] }} {{ Str::plural('vote', $preview['count']) }} as Manually entered</span>
                        <span x-cloak x-show="sending">Saving</span>
                    </button>
                    <p class="text-sm text-muted">Leaves {{ $preview['remaining'] - $preview['count'] }} of {{ $preview['allowance'] }} votes. Votes are final once saved.</p>
                </form>
            @else
                <div class="mt-4 rounded-lg border-2 border-bad bg-bad-soft px-4 py-3 text-bad" role="alert">
                    <p class="font-bold">This ballot cannot be saved as entered. Nothing was saved.</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach (array_unique($preview['errors']) as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <p class="mt-2 text-sm">Fix the numbers above and check again. If a car was already voted for online, leave it off this entry.</p>
                </div>
            @endif
        </section>
    @endif
@endif
@endsection
