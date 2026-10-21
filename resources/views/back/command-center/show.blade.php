@extends('layouts.back')

@section('title', 'HeatAlert - ' . $case->title)

@section('content')

@include('partials.command-center.room')

@endsection
