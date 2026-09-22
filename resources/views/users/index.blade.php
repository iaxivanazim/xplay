<x-app-layout>
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">User Management</h5>
            <div class="d-flex gap-2">
                {{-- Status Filter --}}
                <div class="btn-group" role="group">
                    <a href="{{ route('users.index', ['status' => 1, 'search' => request('search')]) }}" class="btn btn-sm {{ $status == 1 ? 'btn-warning' : 'btn-outline-warning' }}">
                        Active
                    </a>
                    <a href="{{ route('users.index', ['status' => 0, 'search' => request('search')]) }}" class="btn btn-sm {{ $status == 0 ? 'btn-warning' : 'btn-outline-warning' }}">
                        Inactive
                    </a>
                </div>
                <a href="{{ route('roles.index') }}" class="btn btn-warning btn-sm">Add Role</a>
                <a href="{{ route('users.create') }}" class="btn btn-success btn-sm">Add User</a>
            </div>
        </div>

        <div class="card-body">

            <form method="GET" class="mb-3">
                <input type="text" name="search" class="form-control" placeholder="Search by name or username..." value="{{ request('search') }}">
            </form>

            <table class="table table-dark table-striped align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Permissions</th>
                        <th>Status</th>
                        <th width="150">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->username }}</td>

                        <td>
                            {{ $user->role()->first()?->name ?? 'N/A' }}
                        </td>

                        <td>
                            @foreach($user->permissions() as $permission)
                            <span class="badge bg-secondary">{{ strtoupper($permission) }}</span>
                            @endforeach
                        </td>

                        <td>
                            @if($user->status)
                            <span class="badge bg-success">ACTIVE</span>
                            @else
                            <span class="badge bg-danger">INACTIVE</span>
                            @endif
                        </td>

                        <td>
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-primary">Edit</a>

                            @if($user->status == 1)
                            <form method="POST" action="{{ route('users.deactivate', $user) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-danger">Deactivate</button>
                            </form>
                            @else
                            <form method="POST" action="{{ route('users.restore', $user) }}" style="display:inline;">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">Restore</button>
                            </form>
                            @endif
                        </td>

                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $users->withQueryString()->links() }}

        </div>
    </div>
</x-app-layout>
