@extends('layouts.back')

@section('title', 'HeatAlert - Edit Report')

@section('content')

<h1>Edit Report</h1>

<x-flash-messages />

<form action="{{ route('resident-reports.update', $report) }}" method="POST" class="form-card">
    @csrf
    @method('PUT')
    @include('partials.report-form')
</form>

@endsection
