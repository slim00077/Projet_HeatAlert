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
        @foreach ([\App\Models\User::ROLE_RESIDENT, \App\Models\User::ROLE_ADMIN] as $role)
            <option value="{{ $role }}" @selected(old('role', $user->role ?? \App\Models\User::ROLE_RESIDENT) === $role)>{{ ucfirst($role) }}</option>
        @endforeach
    </select>
    @error('role')<p class="field-error">{{ $message }}</p>@enderror
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
