@extends('layouts.admin', ['title' => $participant->user->name])

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-4xl uppercase">{{ $participant->user->name }}</h1>
        @if ($participant->voter_number)
            <p class="text-muted">Voter #{{ $participant->voter_number }}</p>
        @endif
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.contestants.ballot', $participant) }}" class="btn btn-secondary btn-sm">Print ballot</a>
        <a href="{{ route('admin.contestants.edit', $participant) }}" class="btn btn-ghost btn-sm">Edit contact</a>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <section class="card p-4">
        <h2 class="text-2xl uppercase">Contact</h2>
        <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
            <dt class="text-muted">Phone</dt><dd>{{ $participant->user->phone ?? 'Not given' }}</dd>
            <dt class="text-muted">Email</dt><dd>{{ $participant->user->email ?? 'Not given' }}</dd>
            <dt class="text-muted">Address</dt><dd>{{ trim(collect([$participant->user->address, $participant->user->city, $participant->user->state, $participant->user->zip])->filter()->implode(', ')) ?: 'Not given' }}</dd>
        </dl>
    </section>

    <section class="card p-4">
        <h2 class="text-2xl uppercase">Login code</h2>
        <p class="text-muted">Codes are only ever shown on the printable ballot.</p>
        <form method="POST" action="{{ route('admin.contestants.rotate-code', $participant) }}" class="mt-3" x-data="{ confirm: false }">
            @csrf
            <button type="button" x-show="!confirm" x-on:click="confirm = true" class="btn btn-ghost btn-sm">Rotate login code</button>
            <div x-cloak x-show="confirm" class="rounded-lg border-2 border-warn bg-warn-soft p-3">
                <p class="text-sm font-semibold">This invalidates the old code and signs the contestant out. Votes already cast are not changed.</p>
                <div class="mt-2 flex gap-2">
                    <button class="btn btn-primary btn-sm">Confirm rotate</button>
                    <button type="button" x-on:click="confirm = false" class="btn btn-ghost btn-sm">Cancel</button>
                </div>
            </div>
        </form>
    </section>
</div>

<section class="mt-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-2xl uppercase">Cars</h2>
    </div>
    <div class="mt-2 overflow-x-auto">
        <table class="table-plain">
            <thead>
                <tr><th>#</th><th>Vehicle</th><th>Class</th><th>Photo</th><th></th></tr>
            </thead>
            <tbody>
                @forelse ($participant->cars as $car)
                    <tr>
                        <td><span class="plate plate-sm">{{ $car->entry_number }}</span></td>
                        <td>{{ $car->description }}</td>
                        <td>{{ $car->category->name }}</td>
                        <td>{{ $car->photo_path ? 'Yes' : 'No' }}</td>
                        <td><a href="{{ route('admin.cars.edit', $car) }}" class="btn btn-ghost btn-sm">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No cars yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($event->allowsRegistration())
        <form method="POST" action="{{ route('admin.cars.store') }}" class="card mt-4 grid gap-4 p-4 sm:grid-cols-2">
            @csrf
            <input type="hidden" name="participant_id" value="{{ $participant->id }}">
            <h3 class="text-xl uppercase sm:col-span-2">Add another car</h3>
            <div>
                <label for="add-category" class="field-label">Class</label>
                <select id="add-category" name="category_id" class="field" required>
                    <option value="">Choose one</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="add-entry-number" class="field-label">Car number</label>
                <input id="add-entry-number" name="entry_number" type="number" min="1" class="field" placeholder="{{ $nextEntryNumber }}">
            </div>
            <div>
                <label for="add-year" class="field-label">Year</label>
                <input id="add-year" name="year" type="number" class="field">
            </div>
            <div>
                <label for="add-make" class="field-label">Make</label>
                <input id="add-make" name="make" class="field">
            </div>
            <div>
                <label for="add-model" class="field-label">Model</label>
                <input id="add-model" name="model" class="field">
            </div>
            <div class="sm:col-span-2">
                <label for="add-description" class="field-label">Description</label>
                <input id="add-description" name="description" class="field">
            </div>
            <div class="sm:col-span-2">
                <label for="add-photo" class="field-label">Photo (optional)</label>
                <input id="add-photo" name="photo" type="file" accept="image/*" class="field">
            </div>
            <div class="sm:col-span-2">
                <button class="btn btn-primary">Add car</button>
            </div>
        </form>
    @endif
</section>

<section class="card mt-6 p-4">
    <h2 class="text-2xl uppercase">Allowance</h2>
    <dl class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
        <div><dt class="text-sm text-muted">Default (cars &times; {{ $event->votes_per_car }})</dt><dd class="text-2xl font-bold tabular-nums">{{ $summary['default'] }}</dd></div>
        <div><dt class="text-sm text-muted">Current total</dt><dd class="text-2xl font-bold tabular-nums">{{ $summary['allowance'] }}</dd></div>
        <div><dt class="text-sm text-muted">Used</dt><dd class="text-2xl font-bold tabular-nums">{{ $summary['used'] }}</dd></div>
        <div><dt class="text-sm text-muted">Remaining</dt><dd class="text-2xl font-bold tabular-nums">{{ $summary['remaining'] }}</dd></div>
    </dl>

    @if ($event->allowsRegistration())
        <form method="POST" action="{{ route('admin.contestants.allowance', $participant) }}" class="mt-4 grid gap-3 sm:grid-cols-[auto_1fr_auto]">
            @csrf
            @method('PUT')
            <div>
                <label for="total" class="field-label">Set total votes</label>
                <input id="total" name="total" type="number" min="0" class="field" value="{{ old('total', $summary['override']) }}">
                @error('total') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="reason" class="field-label">Reason</label>
                <input id="reason" name="reason" class="field" value="{{ old('reason') }}" required>
                @error('reason') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div class="self-end">
                <button class="btn btn-primary">Save</button>
            </div>
        </form>
        <form method="POST" action="{{ route('admin.contestants.allowance', $participant) }}" class="mt-3 flex flex-wrap items-end gap-3">
            @csrf
            @method('PUT')
            <input type="hidden" name="reset" value="1">
            <div class="flex-1">
                <label for="reset-reason" class="field-label">Reason for reset</label>
                <input id="reset-reason" name="reason" class="field" required>
            </div>
            <button class="btn btn-ghost">Reset to default</button>
        </form>
    @else
        <p class="mt-3 text-muted">The event is {{ $event->status->label() }}. Allowances can no longer be changed.</p>
    @endif
</section>
@endsection
