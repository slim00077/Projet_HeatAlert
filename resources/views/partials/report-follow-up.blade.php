{{-- Manager/admin follow-up of a report through the existing status workflow. Expects $report and $statuses. --}}
<form action="{{ route('managed-reports.update', $report) }}" method="POST" class="form-card" style="margin-top:1.5rem">
    @csrf
    @method('PATCH')

    <h3 style="margin-top:0">Follow-up</h3>

    <div class="form-group @error('status') has-error @enderror">
        <label for="follow-up-status">Status</label>
        <select id="follow-up-status" name="status">
            @foreach ($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $report->status) === $status)>{{ $status }}</option>
            @endforeach
        </select>
        @error('status')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    <div class="form-group @error('resolution_notes') has-error @enderror">
        <label for="follow-up-notes">Documented resolution</label>
        <textarea id="follow-up-notes" name="resolution_notes" rows="4" placeholder="What was done, by whom, and whether it was confirmed to work">{{ old('resolution_notes', $report->resolution_notes) }}</textarea>
        <p class="field-hint">Shared with the reporting resident. Record only what actually happened, without personal details.</p>
        @error('resolution_notes')<p class="field-error">{{ $message }}</p>@enderror
    </div>

    @if ($report->resolved_at)
        <p class="field-hint">Resolved on {{ $report->resolved_at->format('Y-m-d') }} after {{ $report->daysOpen() }} day(s).</p>
    @endif

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save follow-up</button>
    </div>
</form>
