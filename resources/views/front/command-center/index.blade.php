@extends('layouts.front')

@section('title', 'HeatAlert - Incident Command Center')

@section('content')

<section class="page-section">
    <div class="container intel-container">
        @include('partials.command-center.board')
    </div>
</section>

@endsection
