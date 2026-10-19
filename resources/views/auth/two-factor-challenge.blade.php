@extends('layouts.front')

@section('title', 'HeatAlert - Two-factor authentication')
@section('overlay', '1')

@section('content')

<section class="auth-page">
    <div class="auth-card">
        <div class="logo"><span class="logo-icon"><x-icon name="sun" /></span> Heat<span class="logo-accent">Alert</span></div>
        <h1>Two-factor authentication</h1>
        <p class="auth-sub" id="code-help">Enter the 6-digit code from your authenticator app.</p>

        <x-flash-messages />

        <form action="{{ route('two-factor.login') }}" method="POST">
            @csrf
            <div class="form-group" id="code-field">
                <label for="code">Authentication code</label>
                <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus>
            </div>
            <div class="form-group" id="recovery-field" style="display:none">
                <label for="recovery_code">Recovery code</label>
                <input type="text" id="recovery_code" name="recovery_code" autocomplete="one-time-code">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Verify</button>
        </form>

        <p class="auth-foot"><a href="#" id="toggle-recovery">Use a recovery code instead</a></p>
    </div>
</section>

<script>
    (function () {
        const toggle = document.getElementById('toggle-recovery');
        let recovery = false;
        toggle.addEventListener('click', function (event) {
            event.preventDefault();
            recovery = !recovery;
            document.getElementById('code-field').style.display = recovery ? 'none' : '';
            document.getElementById('recovery-field').style.display = recovery ? '' : 'none';
            document.getElementById('code-help').textContent = recovery ? 'Enter one of your emergency recovery codes.' : 'Enter the 6-digit code from your authenticator app.';
            toggle.textContent = recovery ? 'Use an authentication code instead' : 'Use a recovery code instead';
            document.getElementById(recovery ? 'recovery_code' : 'code').focus();
        });
    })();
</script>

@endsection
