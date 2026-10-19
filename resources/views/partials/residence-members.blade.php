<div class="panel members-panel">
    <div class="panel-head"><h2>Residents ({{ $residence->members->count() }})</h2></div>

    @if ($canManageMembers)
        <form action="{{ route('residences.members.store', $residence) }}" method="POST" class="toolbar" style="margin-bottom: 1rem">
            @csrf
            <input type="email" name="email" value="{{ old('email') }}" placeholder="Resident email" required class="inline-input">
            <button type="submit" class="btn btn-primary btn-sm"><x-icon name="plus" /> Add resident</button>
        </form>
        @error('email')<p class="field-error">{{ $message }}</p>@enderror
    @elseif ($residence->isPending())
        <p class="field-hint">You can add residents once an admin approves this residence.</p>
    @endif

    <ul class="recent-list">
        @forelse ($residence->members as $member)
            <li class="recent-item">
                <div class="stat-icon soft-blue"><x-icon name="users" /></div>
                <div class="recent-main">
                    <strong>{{ $member->name }}</strong>
                    <small>{{ $member->email }}</small>
                </div>
                @if ($canManageMembers)
                    <form action="{{ route('residences.members.destroy', [$residence, $member]) }}" method="POST" onsubmit="return confirm('Remove {{ $member->name }} from this residence?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="icon-action is-danger" title="Remove"><x-icon name="trash" /></button>
                    </form>
                @endif
            </li>
        @empty
            <li class="empty-state">No residents added yet.</li>
        @endforelse
    </ul>
</div>
