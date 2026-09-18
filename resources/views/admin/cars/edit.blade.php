@extends('layouts.admin', ['title' => 'Edit car'])

@section('content')
<h1 class="text-4xl uppercase">Car #{{ $car->entry_number }}</h1>

@if ($structuralLocked)
    <p class="mt-2 text-muted">Owner, class and car number are locked once voting opens. Description and photo can still be edited until results are final.</p>
@endif

<form method="POST" action="{{ route('admin.cars.update', $car) }}" class="mt-6 grid max-w-2xl gap-4 sm:grid-cols-2" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <div class="sm:col-span-2">
        <label for="participant_id" class="field-label">Owner</label>
        <select id="participant_id" name="participant_id" class="field" @disabled($structuralLocked)>
            @foreach ($participants as $participant)
                <option value="{{ $participant->id }}" @selected(old('participant_id', $car->participant_id) == $participant->id)>
                    {{ $participant->voter_number ? '#'.$participant->voter_number.' ' : '' }}{{ $participant->user->name }}
                </option>
            @endforeach
        </select>
        @error('participant_id') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="category_id" class="field-label">Class</label>
        <select id="category_id" name="category_id" class="field" @disabled($structuralLocked)>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected(old('category_id', $car->category_id) == $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        @error('category_id') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="entry_number" class="field-label">Car number</label>
        <input id="entry_number" name="entry_number" type="number" min="1" value="{{ old('entry_number', $car->entry_number) }}" class="field" @disabled($structuralLocked)>
        @error('entry_number') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="year" class="field-label">Year</label>
        <input id="year" name="year" type="number" value="{{ old('year', $car->year) }}" class="field">
    </div>
    <div>
        <label for="make" class="field-label">Make</label>
        <input id="make" name="make" value="{{ old('make', $car->make) }}" class="field">
    </div>
    <div>
        <label for="model" class="field-label">Model</label>
        <input id="model" name="model" value="{{ old('model', $car->model) }}" class="field">
    </div>
    <div class="sm:col-span-2">
        <label for="description" class="field-label">Description</label>
        <input id="description" name="description" value="{{ old('description', $car->description) }}" class="field">
        @error('description') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2">
        @if ($car->thumbUrl())
            <img src="{{ $car->thumbUrl() }}" alt="" class="mb-2 h-32 w-auto rounded-lg border border-line">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remove_photo" value="1">
                Remove current photo
            </label>
        @endif
        <label for="photo" class="field-label mt-2">Replace photo</label>
        <input id="photo" name="photo" type="file" accept="image/*" class="field">
        @error('photo') <p class="field-error">{{ $message }}</p> @enderror
    </div>

    <div class="sm:col-span-2 flex flex-wrap gap-2">
        <button class="btn btn-primary">Save</button>
        <a href="{{ route('admin.contestants.show', $car->participant) }}" class="btn btn-ghost">Back to contestant</a>
    </div>
</form>

@if (! $structuralLocked)
    <form method="POST" action="{{ route('admin.cars.destroy', $car) }}" class="mt-6" onsubmit="return confirm('Remove car #{{ $car->entry_number }}? This cannot be undone.');">
        @csrf
        @method('DELETE')
        <button class="btn btn-ghost btn-sm text-bad">Delete car</button>
    </form>
@endif
@endsection
