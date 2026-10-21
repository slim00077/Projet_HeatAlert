@extends('layouts.back')

@section('title', 'HeatAlert - Add Report')

@section('content')

<h1>Add Report</h1>

<x-flash-messages />

<form action="{{ route('resident-reports.store') }}" method="POST" class="form-card" enctype="multipart/form-data">
    @csrf
    @include('partials.report-form')
</form>

@endsection
