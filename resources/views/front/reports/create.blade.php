@extends('layouts.front')

@section('title', 'HeatAlert - Add Report')

@section('content')

<section class="fx-page page-section">
    <div class="container">
        <div class="section-heading">
            <h2>Add Report</h2>
        </div>

        <x-flash-messages />

        @if ($residences->isEmpty())
            <div class="flash flash-error">You need a residence before reporting an issue. <a href="{{ route('residences.create') }}">Add one now</a>.</div>
        @endif

        <form action="{{ route('resident-reports.store') }}" method="POST" class="form-card" enctype="multipart/form-data">
            @csrf
            @include('partials.report-form')
        </form>
    </div>
</section>

@endsection
