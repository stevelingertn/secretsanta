@extends('layouts.public', ['title' => 'Admin sign in'])

@section('content')
<div class="mx-auto max-w-md">
    <h1 class="text-4xl uppercase">Admin sign in</h1>

    <form method="POST" action="{{ route('admin.login.store') }}" class="card mt-5 space-y-4 p-5">
        @csrf
        <div>
            <label for="username" class="field-label">Username</label>
            <input id="username" name="username" type="text" required autofocus autocomplete="username" class="field" value="{{ old('username') }}"
                   @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
            @error('username')
                <p id="username-error" class="field-error">{{ $message }}</p>
            @enderror
        </div>
        <div>
            <label for="password" class="field-label">Password</label>
            <input id="password" name="password" type="password" required autocomplete="current-password" class="field">
        </div>
        <button class="btn btn-secondary w-full">Sign in</button>
    </form>
</div>
@endsection
