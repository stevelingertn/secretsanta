<div class="mb-4 space-y-1 avoid-break">
    <h1 class="text-3xl uppercase sm:text-4xl">{{ $title }}</h1>
    <p class="text-sm text-muted">{{ $event->name }} {{ $event->year }} &middot; Generated {{ $generatedAt->inShowTz()->format('M j, Y g:i A') }} &middot; Results: {{ $status }}</p>
</div>
@unless ($print)
    <div class="no-print mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.reports.show', array_merge(['report' => $report, 'print' => 1], request()->query())) }}" class="btn btn-sm btn-ghost">Print</a>
        <a href="{{ route('admin.reports.show', array_merge(['report' => $report, 'format' => 'csv'], request()->except('format', 'print'))) }}" class="btn btn-sm btn-secondary">Download CSV</a>
    </div>
@endunless
