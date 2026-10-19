@extends('layouts.front')

@section('title', 'HeatAlert - Emergency information')

@section('content')

<section class="page-section">
    <div class="container profile-page">

        <div class="section-heading">
            <span>Emergency</span>
            <h2>Emergency information</h2>
            <p>If someone shows signs of heat stroke, act immediately.</p>
        </div>

        <div class="form-card profile-card">
            <h3>Emergency numbers (Tunisia)</h3>
            <dl class="detail-grid">
                <div><dt>Ambulance (SAMU)</dt><dd>190</dd></div>
                <div><dt>Civil protection</dt><dd>198</dd></div>
                <div><dt>Police</dt><dd>197</dd></div>
                <div><dt>National guard</dt><dd>193</dd></div>
            </dl>
        </div>

        <div class="form-card profile-card">
            <h3>Signs of heat stroke</h3>
            <ul class="tips-list">
                <li>Very high body temperature, hot and dry (or damp) skin.</li>
                <li>Confusion, dizziness, severe headache, nausea or fainting.</li>
                <li>Fast pulse and rapid breathing.</li>
            </ul>
            <h3 style="margin-top:1.2rem">What to do while waiting for help</h3>
            <ul class="tips-list">
                <li>Move the person to a cool, shaded place and remove extra clothing.</li>
                <li>Cool the body with damp cloths, fans or a lukewarm shower.</li>
                <li>Give small sips of water if the person is fully conscious. Never force fluids.</li>
            </ul>
        </div>

        <p class="back-link">
            <a href="{{ route('front.alerts.index') }}" class="btn btn-primary">See current heat alerts</a>
        </p>

    </div>
</section>

@endsection
