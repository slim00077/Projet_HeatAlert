@extends('layouts.front')

@section('title', 'HeatAlert - ' . $case->title)

@section('content')

<section class="page-section">
    <div class="container intel-container">
        @include('partials.command-center.room')
    </div>
</section>

@endsection
