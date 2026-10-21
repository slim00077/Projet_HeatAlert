@extends('layouts.front')

@section('title', 'HeatAlert - Open a response case')

@section('content')

<section class="page-section">
    <div class="container intel-container">
        @include('partials.command-center.create-form')
    </div>
</section>

@endsection
