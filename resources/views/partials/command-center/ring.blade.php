{{-- Circular progress gauge. Expects $percent (0-100 or null), $label (accessible text); optional $tone and $size (px). --}}
@php
    $size = $size ?? 96;
    $value = max(0, min(100, (int) ($percent ?? 0)));
    $circumference = 2 * M_PI * 52;
@endphp
<div class="score-ring cc-ring" style="--size: {{ $size }}px" role="img" aria-label="{{ $label }}">
    <svg viewBox="0 0 120 120" aria-hidden="true">
        <circle class="ring-track" cx="60" cy="60" r="52" />
        <circle class="ring-value" cx="60" cy="60" r="52" style="stroke: var(--{{ $tone ?? 'green' }}); stroke-dasharray: {{ round($circumference, 2) }}; stroke-dashoffset: {{ round($circumference * (1 - $value / 100), 2) }}" />
    </svg>
    <div class="score-ring-label">
        <strong>{{ $percent === null ? '–' : $value.'%' }}</strong>
        <small>{{ $caption ?? 'progress' }}</small>
    </div>
</div>
