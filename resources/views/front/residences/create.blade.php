@extends('layouts.front')

@section('title', 'HeatAlert - Add Residence')

@section('content')

<section class="fx-page page-section">
    <div class="container">
        <div class="section-heading">
            <h2>Add Residence</h2>
        </div>

        <x-flash-messages />

        <form action="{{ route('residences.store') }}" method="POST" enctype="multipart/form-data" class="form-card">
            @csrf
            @include('partials.residence-form')
        </form>
    </div>
</section>

@endsection
