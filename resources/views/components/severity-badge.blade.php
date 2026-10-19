@props(['severity'])

<span class="severity-badge soft-{{ $severity->tone() }}">{{ $severity->label() }}</span>
