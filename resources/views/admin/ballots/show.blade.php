@extends('layouts.print', ['title' => 'Ballot: '.$participant->user->name])

@section('content')
@include('admin.ballots._ballot')
@endsection
