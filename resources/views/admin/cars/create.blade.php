@extends('layouts.admin', ['title' => 'Register car'])

@section('content')
<h1 class="text-4xl uppercase">Register car</h1>

@if ($participants->isEmpty())
    <div class="card mt-4 p-4">
        <p class="font-semibold">There are no contestants yet.</p>
        <a href="{{ route('admin.contestants.create') }}" class="btn btn-primary btn-sm mt-2">Register a contestant</a>
    </div>
@else
    <form method="POST" action="{{ route('admin.cars.store') }}" class="mt-6 grid max-w-2xl gap-4 sm:grid-cols-2" enctype="multipart/form-data">
        @csrf
        <div class="sm:col-span-2">
            <label for="participant_id" class="field-label">Owner</label>
            <select id="participant_id" name="participant_id" class="field" required>
                <option value="">Choose a contestant</option>
                @foreach ($participants as $participant)
                    <option value="{{ $participant->id }}" @selected(old('participant_id', $selectedParticipantId) == $participant->id)>
                        {{ $participant->voter_number ? '#'.$participant->voter_number.' ' : '' }}{{ $participant->user->name }}
                    </option>
                @endforeach
            </select>
            @error('participant_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="category_id" class="field-label">Class</label>
            <select id="category_id" name="category_id" class="field" required>
                <option value="">Choose one</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id') == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="entry_number" class="field-label">Car number</label>
            <input id="entry_number" name="entry_number" type="number" min="1" value="{{ old('entry_number') }}" class="field" placeholder="{{ $nextEntryNumber }}">
            @error('entry_number') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="year" class="field-label">Year</label>
            <input id="year" name="year" type="number" value="{{ old('year') }}" class="field">
        </div>
        <div>
            <label for="make" class="field-label">Make</label>
            <input id="make" name="make" value="{{ old('make') }}" class="field">
        </div>
        <div>
            <label for="model" class="field-label">Model</label>
            <input id="model" name="model" value="{{ old('model') }}" class="field">
        </div>
        <div class="sm:col-span-2">
            <label for="description" class="field-label">Description</label>
            <input id="description" name="description" value="{{ old('description') }}" class="field">
        </div>
        <div class="sm:col-span-2">
            <label for="photo" class="field-label">Photo (optional)</label>
            <input id="photo" name="photo" type="file" accept="image/*" class="field">
            @error('photo') <p class="field-error">{{ $message }}</p> @enderror
        </div>
        <div class="sm:col-span-2">
            <button class="btn btn-primary">Register</button>
        </div>
    </form>
@endif
@endsection
