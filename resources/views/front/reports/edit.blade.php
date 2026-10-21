@extends('layouts.front')

@section('title', 'HeatAlert - Edit Report')

@section('content')

<section class="page-section">
    <div class="container">
        <div class="section-heading">
            <h2>Edit Report</h2>
        </div>

        <x-flash-messages />

        <form action="{{ route('resident-reports.update', $report) }}" method="POST" class="form-card" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            @include('partials.report-form')
        </form>
    </div>
</section>

@endsection
