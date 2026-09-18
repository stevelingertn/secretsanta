<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $title ?? 'Print' }} | {{ $event?->name ?? config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
{{-- US Letter printouts: reports, results and ballots. Browser Print / Save as PDF. --}}
<body class="bg-white">
    <div class="no-print sticky top-0 z-10 flex flex-wrap items-center gap-3 border-b-2 border-ink bg-paper px-4 py-3">
        <a href="{{ $back ?? url()->previous() }}" class="btn btn-sm btn-ghost">Back</a>
        <button type="button" class="btn btn-sm btn-secondary" onclick="window.print()">Print</button>
        <span class="text-sm text-muted">Letter size. Use Print, or Save as PDF.</span>
    </div>
    <div class="mx-auto max-w-[7.5in] px-4 py-6 print:max-w-none print:p-0">
        @yield('content')
    </div>
</body>
</html>
