@extends('layouts.admin', ['title' => 'Register contestant'])

@section('content')
<h1 class="text-4xl uppercase">Register contestant</h1>

@if (! $event->allowsRegistration())
    <div class="card mt-4 p-4">
        <p class="font-semibold">Registration is closed.</p>
        <p class="text-muted">The event is {{ $event->status->label() }}. New contestants cannot be added.</p>
    </div>
@else

    @if ($matches->isNotEmpty())
        <div class="card mt-4 border-2 border-warn p-4">
            <h2 class="text-2xl uppercase">Possible matches</h2>
            <p class="text-muted">These people already exist. Pick one, or confirm this is someone new.</p>
            <ul class="mt-3 divide-y divide-line" role="list">
                @foreach ($matches as $match)
                    @php($existingParticipant = $match->participants->first())
                    <li class="flex flex-wrap items-center justify-between gap-2 py-3">
                        <span class="font-semibold">{{ $match->name }}</span>
                        @if ($existingParticipant)
                            <span class="flex items-center gap-2">
                                <span class="badge bg-ok-soft text-ok">Already in this event</span>
                                <a href="{{ route('admin.contestants.show', $existingParticipant) }}" class="btn btn-ghost btn-sm">Add car to this contestant</a>
                            </span>
                        @else
                            <form method="POST" action="{{ route('admin.contestants.store') }}">
                                @csrf
                                @foreach ($old as $key => $value)
                                    @if ($key !== 'existing_user_id' && $key !== 'confirm_new')
                                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                    @endif
                                @endforeach
                                <input type="hidden" name="existing_user_id" value="{{ $match->id }}">
                                <button class="btn btn-secondary btn-sm">Use this person</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('admin.contestants.store') }}" class="mt-3">
                @csrf
                @foreach ($old as $key => $value)
                    @if ($key !== 'existing_user_id' && $key !== 'confirm_new')
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <input type="hidden" name="confirm_new" value="1">
                <button class="btn btn-ghost">This is a different person, create new</button>
            </form>
        </div>
    @endif

    <form method="POST" action="{{ route('admin.contestants.store') }}" class="mt-6 grid max-w-2xl gap-4">
        @csrf
        <div>
            <label for="name" class="field-label">Name</label>
            <input id="name" name="name" value="{{ $old['name'] ?? old('name') }}" class="field" required>
            @error('name') <p class="field-error">{{ $message }}</p> @enderror
        </div>

        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="field-label">Contact (optional, private)</legend>
            <div>
                <label for="email" class="field-label">Email</label>
                <input id="email" name="email" type="email" value="{{ $old['email'] ?? old('email') }}" class="field">
                @error('email') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="phone" class="field-label">Phone</label>
                <input id="phone" name="phone" value="{{ $old['phone'] ?? old('phone') }}" class="field">
                @error('phone') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="address" class="field-label">Address</label>
                <input id="address" name="address" value="{{ $old['address'] ?? old('address') }}" class="field">
            </div>
            <div>
                <label for="city" class="field-label">City</label>
                <input id="city" name="city" value="{{ $old['city'] ?? old('city') }}" class="field">
            </div>
            <div>
                <label for="state" class="field-label">State</label>
                <input id="state" name="state" value="{{ $old['state'] ?? old('state') }}" class="field">
            </div>
            <div>
                <label for="zip" class="field-label">Zip</label>
                <input id="zip" name="zip" value="{{ $old['zip'] ?? old('zip') }}" class="field">
            </div>
        </fieldset>

        <fieldset class="grid gap-4 sm:grid-cols-2">
            <legend class="field-label">First car</legend>
            <div>
                <label for="category_id" class="field-label">Class</label>
                <select id="category_id" name="category_id" class="field" required>
                    <option value="">Choose one</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(($old['category_id'] ?? old('category_id')) == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
                @error('category_id') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="entry_number" class="field-label">Car number</label>
                <input id="entry_number" name="entry_number" type="number" min="1" value="{{ $old['entry_number'] ?? old('entry_number') }}" class="field" placeholder="{{ $nextEntryNumber }}">
                @error('entry_number') <p class="field-error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="year" class="field-label">Year</label>
                <input id="year" name="year" type="number" value="{{ $old['year'] ?? old('year') }}" class="field">
            </div>
            <div>
                <label for="make" class="field-label">Make</label>
                <input id="make" name="make" value="{{ $old['make'] ?? old('make') }}" class="field">
            </div>
            <div>
                <label for="model" class="field-label">Model</label>
                <input id="model" name="model" value="{{ $old['model'] ?? old('model') }}" class="field">
            </div>
            <div class="sm:col-span-2">
                <label for="description" class="field-label">Description (optional if year/make/model given)</label>
                <input id="description" name="description" value="{{ $old['description'] ?? old('description') }}" class="field">
            </div>
        </fieldset>

        <div>
            <button class="btn btn-primary">Register</button>
        </div>
    </form>
@endif
@endsection
