<x-app-layout>

    <div class="card bg-dark text-white shadow">
        <div class="card-header">
            <h5>Edit User</h5>
        </div>

        <div class="card-body">
            <form method="POST" action="{{ route('users.update', $user) }}">
                @csrf
                @method('PUT')

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Name</label>
                        <input type="text" name="name"
                            value="{{ $user->name }}"
                            class="form-control bg-secondary text-white">
                    </div>

                    <div class="col-md-6">
                        <label>Username</label>
                        <input type="text" name="username"
                            value="{{ $user->username }}"
                            class="form-control bg-secondary text-white">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Role</label>
                        <select name="role_id"
                            class="form-select bg-secondary text-white">
                            @foreach($roles as $role)
                            <option value="{{ $role->id }}"
                                {{ $user->role_id == $role->id ? 'selected' : '' }}>
                                {{ strtoupper($role->name) }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label>Card ID</label>
                        <input type="text" name="card_id"
                            value="{{ $user->card_id }}"
                            class="form-control bg-secondary text-white"
                            onkeydown="if(event.keyCode == 13) { event.preventDefault(); return false; }">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>New Password (Optional)</label>
                        <input type="password" name="password"
                            class="form-control bg-secondary text-white">
                    </div>

                    <div class="col-md-6">
                        <label>Confirm Password</label>
                        <input type="password" name="password_confirmation"
                            class="form-control bg-secondary text-white">
                    </div>
                </div>

                <div class="mb-3">
                    <label>Status</label>
                    <select name="status"
                        class="form-select bg-secondary text-white">
                        <option value="1" {{ $user->status ? 'selected' : '' }}>
                            ACTIVE
                        </option>
                        <option value="0" {{ !$user->status ? 'selected' : '' }}>
                            INACTIVE
                        </option>
                    </select>
                </div>

                <button class="btn btn-primary">Update User</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
            </form>
        </div>
    </div>

</x-app-layout>