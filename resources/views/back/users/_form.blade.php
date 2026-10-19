<div class="form-group @error('name') has-error @enderror">
    <label for="name">Full name</label>
    <input type="text" id="name" name="name" value="{{ old('name', $user->name ?? '') }}" required>
    @error('name')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('email') has-error @enderror">
    <label for="email">Email address</label>
    <input type="email" id="email" name="email" value="{{ old('email', $user->email ?? '') }}" required>
    @error('email')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('role') has-error @enderror">
    <label for="role">Role</label>
    <select id="role" name="role" required>
        <option value="resident" @selected(old('role', $user->role ?? 'resident') === 'resident')>Resident - lives in a residence and reports issues</option>
        <option value="manager" @selected(old('role', $user->role ?? 'resident') === 'manager')>Residence Manager - creates residences and adds residents</option>
        <option value="admin" @selected(old('role', $user->role ?? 'resident') === 'admin')>Admin - full access</option>
    </select>
    @error('role')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('neighborhood_id') has-error @enderror" id="neighborhood-field">
    <label for="neighborhood_id">Neighborhood (managers only)</label>
    <select id="neighborhood_id" name="neighborhood_id">
        <option value="">-- Select a neighborhood --</option>
        @foreach ($neighborhoods as $neighborhood)
            <option value="{{ $neighborhood->id }}" @selected(old('neighborhood_id', $user->neighborhood_id ?? null) == $neighborhood->id)>{{ $neighborhood->name }}</option>
        @endforeach
    </select>
    <p class="field-hint">A manager can only create residences in this neighborhood.</p>
    @error('neighborhood_id')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group @error('password') has-error @enderror">
    <label for="password">Password @if ($user ?? null)<span class="field-hint">(leave blank to keep the current one)</span>@endif</label>
    <input type="password" id="password" name="password" autocomplete="new-password" @required(! ($user ?? null))>
    @error('password')<p class="field-error">{{ $message }}</p>@enderror
</div>

<div class="form-group">
    <label for="password_confirmation">Confirm password</label>
    <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password">
</div>

<div class="form-actions">
    <button type="submit" class="btn btn-primary">Save User</button>
    <a href="{{ route('admin.users.index') }}" class="btn btn-outline">Cancel</a>
</div>

<script>
    (function () {
        const role = document.getElementById('role');
        const field = document.getElementById('neighborhood-field');
        const sync = () => { field.style.display = role.value === 'manager' ? '' : 'none'; };
        role.addEventListener('change', sync);
        sync();
    })();
</script>
