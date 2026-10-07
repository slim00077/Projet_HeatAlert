@extends('layouts.front')

@section('title', 'HeatAlert - Add Advice')

@section('content')

<section class="page-section">
    <div class="container">
        <div class="section-heading">
            <h2>Add Advice</h2>
        </div>

        <x-flash-messages />

        <form action="{{ route('advice.store') }}" method="POST" enctype="multipart/form-data" class="form-card">
            @csrf
            @include('partials.advice-form', ['cancelUrl' => route('advice.index')])
        </form>
    </div>
</section>

@endsection
