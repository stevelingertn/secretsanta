@extends('layouts.admin')

@section('content')
<div class="space-y-4">
    <h1 class="text-2xl">Reports</h1>
    <p class="text-muted">{{ $event->name }} {{ $event->year }}. Admin only. No voter identities appear in any report.</p>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($reports as $slug => $info)
            <a href="{{ route('admin.reports.show', $slug) }}" class="card block p-4 hover:bg-paper">
                <h2 class="text-lg font-display font-bold uppercase tracking-wide">{{ $info['title'] }}</h2>
                <p class="mt-1 text-sm text-muted">{{ $info['description'] }}</p>
            </a>
        @endforeach
    </div>
</div>
@endsection
