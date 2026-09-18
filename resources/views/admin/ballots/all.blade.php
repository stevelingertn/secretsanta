@extends('layouts.print', ['title' => 'All ballots'])

@section('content')
@foreach ($ballots as $ballot)
    @php
        $participant = $ballot['participant'];
        $summary = $ballot['summary'];
        $code = $ballot['code'];
    @endphp
    <div @class(['print-break' => ! $loop->last, 'mb-6' => ! $loop->last])>
        @include('admin.ballots._ballot')
    </div>
@endforeach
@endsection
