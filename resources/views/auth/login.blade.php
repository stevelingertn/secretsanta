@extends('layouts.public', ['title' => 'Contestant sign in'])

@section('content')
<div class="mx-auto max-w-md">
    <h1 class="text-4xl uppercase">Contestant sign in</h1>
    <p class="mt-2 text-muted">Enter the login code printed on your ballot. It looks like <span class="font-semibold text-ink">ABCDE-FGHJK</span>. Your car number is not your login code.</p>

    <form method="POST" action="{{ route('login.store') }}" class="card mt-5 p-5">
        @csrf
        <label for="code" class="field-label">Login code</label>
        <input id="code" name="code" type="text" required autofocus
               autocomplete="one-time-code" autocapitalize="characters" spellcheck="false"
               class="field font-display text-2xl uppercase tracking-[0.2em]"
               maxlength="20" value="{{ old('code') }}"
               @error('code') aria-invalid="true" aria-describedby="code-error" @enderror>
        @error('code')
            <p id="code-error" class="field-error">{{ $message }}</p>
        @enderror
        <button class="btn btn-primary mt-4 w-full">Sign in</button>
    </form>

    <p class="mt-4 text-sm text-muted">Lost your ballot? Ask the registration table. They can reprint it.</p>
</div>
@endsection
