@extends('layouts.public', ['title' => 'Review votes'])

@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="text-4xl uppercase sm:text-5xl">Review your votes</h1>
    <p class="mt-2 text-lg">
        You are voting for <strong>{{ $preview['count'] }} {{ Str::plural('car', $preview['count']) }}</strong>.
        After this you will have <strong>{{ $preview['remaining'] - $preview['count'] }}</strong> of {{ $preview['allowance'] }} votes left.
    </p>

    <ul class="mt-4 divide-y divide-line overflow-hidden rounded-xl border border-line bg-white" role="list">
        @foreach ($preview['lines'] as $line)
            <li class="flex items-center gap-3 px-3 py-3">
                <span class="plate plate-sm">{{ $line['car']->entry_number }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block font-semibold">{{ $line['car']->description }}</span>
                    <span class="block text-sm text-muted">{{ $line['car']->category->name }}</span>
                </span>
            </li>
        @endforeach
    </ul>

    <div class="mt-5 rounded-lg border-2 border-ink bg-brand-yellow px-4 py-3 font-semibold">
        Votes are final once confirmed. They cannot be changed or taken back.
    </div>

    <form method="POST" action="{{ route('ballot.store') }}" class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end"
          x-data="{ sending: false }" x-on:submit="sending = true">
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        @foreach ($numbers as $number)
            <input type="hidden" name="cars[]" value="{{ $number }}">
        @endforeach
        <a href="{{ route('ballot.index', ['cars' => $numbers]) }}" class="btn btn-ghost">Go back and change</a>
        <button class="btn btn-primary" x-bind:disabled="sending">
            <span x-show="!sending">Confirm {{ $preview['count'] }} {{ Str::plural('vote', $preview['count']) }}</span>
            <span x-cloak x-show="sending">Saving, please wait</span>
        </button>
    </form>
    <p class="mt-3 text-sm text-muted">Your votes are saved only when the next page says "Saved". If your connection drops, reload your ballot to check before trying again.</p>
</div>
@endsection
