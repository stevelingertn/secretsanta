@extends('layouts.admin', ['title' => 'Edit contact'])

@section('content')
<h1 class="text-4xl uppercase">Edit contact</h1>

<form method="POST" action="{{ route('admin.contestants.update', $participant) }}" class="mt-6 grid max-w-2xl gap-4 sm:grid-cols-2">
    @csrf
    @method('PUT')
    <div class="sm:col-span-2">
        <label for="name" class="field-label">Name</label>
        <input id="name" name="name" value="{{ old('name', $participant->user->name) }}" class="field" required>
        @error('name') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="email" class="field-label">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $participant->user->email) }}" class="field">
        @error('email') <p class="field-error">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="phone" class="field-label">Phone</label>
        <input id="phone" name="phone" value="{{ old('phone', $participant->user->phone) }}" class="field">
    </div>
    <div>
        <label for="address" class="field-label">Address</label>
        <input id="address" name="address" value="{{ old('address', $participant->user->address) }}" class="field">
    </div>
    <div>
        <label for="city" class="field-label">City</label>
        <input id="city" name="city" value="{{ old('city', $participant->user->city) }}" class="field">
    </div>
    <div>
        <label for="state" class="field-label">State</label>
        <input id="state" name="state" value="{{ old('state', $participant->user->state) }}" class="field">
    </div>
    <div>
        <label for="zip" class="field-label">Zip</label>
        <input id="zip" name="zip" value="{{ old('zip', $participant->user->zip) }}" class="field">
    </div>
    <div class="sm:col-span-2 flex gap-2">
        <button class="btn btn-primary">Save</button>
        <a href="{{ route('admin.contestants.show', $participant) }}" class="btn btn-ghost">Cancel</a>
    </div>
</form>
@endsection
