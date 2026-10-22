@extends('layouts.front')

@section('title', 'HeatAlert - Edit Residence')

@section('content')

<section class="fx-page page-section">
    <div class="container">
        <div class="section-heading">
            <h2>Edit Residence</h2>
        </div>

        <x-flash-messages />

        <form action="{{ route('residences.update', $residence) }}" method="POST" enctype="multipart/form-data" class="form-card">
            @csrf
            @method('PUT')
            @include('partials.residence-form')
        </form>
    </div>
</section>

@endsection
