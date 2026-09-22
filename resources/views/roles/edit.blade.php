<x-app-layout>

    <div class="card bg-dark text-white shadow">
        <div class="card-header">
            <h5>Edit Role — {{ $role->name }}</h5>
        </div>

        <div class="card-body">

            <form method="POST" action="{{ route('roles.update', $role) }}">
                @csrf
                @method('PUT')

                <div class="row mb-3">
                    <div class="col-md-6">
                        <label>Name</label>
                        <input type="text" name="name"
                            value="{{ old('name', $role->name) }}"
                            class="form-control bg-secondary text-white @error('name') is-invalid @enderror">
                        @error('name')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label>Slug</label>
                        <input type="text" name="slug"
                            value="{{ old('slug', $role->slug) }}"
                            class="form-control bg-secondary text-white @error('slug') is-invalid @enderror">
                        @error('slug')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description"
                        class="form-control bg-secondary text-white @error('description') is-invalid @enderror">{{ old('description', $role->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <hr>

                <h6>Permissions</h6>

                @foreach($permissions as $module => $modulePermissions)
                <div class="mb-3">
                    <h6 class="text-warning">{{ strtoupper($module) }}</h6>

                    <div class="row">
                        @foreach($modulePermissions as $permission)
                        <div class="col-md-3">
                            <div class="form-check">
                                <input type="checkbox"
                                    name="permissions[]"
                                    value="{{ $permission->id }}"
                                    class="form-check-input"
                                    {{ in_array($permission->id, $selectedPermissions) ? 'checked' : '' }}>

                                <label class="form-check-label">
                                    {{ $permission->name }}
                                </label>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endforeach

                <button class="btn btn-primary">Update Role</button>
                <a href="{{ route('roles.index') }}"
                    class="btn btn-secondary">Cancel</a>

            </form>
        </div>
    </div>

</x-app-layout>