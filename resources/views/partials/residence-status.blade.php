@if (! $residence->isApproved())
    <div class="flash {{ $residence->isRejected() ? 'flash-error' : 'flash-success' }}" style="{{ $residence->isPending() ? 'background:var(--orange-soft);color:#92400e;border-color:#fde68a' : '' }}">
        @if ($residence->isPending())
            Waiting for admin approval. You can add residents once it is approved.
        @else
            Rejected{{ $residence->reviewer ? ' by '.$residence->reviewer->name : '' }}: {{ $residence->rejection_reason }}
            @can('update', $residence)
                - edit the residence and save to resubmit it.
            @endcan
        @endif
    </div>
@endif
