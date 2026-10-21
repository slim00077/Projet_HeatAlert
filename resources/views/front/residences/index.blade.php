@extends('layouts.front')

@section('title', 'HeatAlert - My Residences')

@section('content')

<section class="page-section">
    <div class="container intel-container">
        @include('partials.residences-hub')
    </div>
</section>

@endsection
