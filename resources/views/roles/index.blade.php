<x-app-layout>

    <div class="card bg-dark text-white shadow">
        <div class="card-header d-flex justify-content-between">
            <h5>Roles</h5>
            <a href="{{ route('roles.create') }}" class="btn btn-success btn-sm">
                Add Role
            </a>
        </div>

        <div class="card-body">

            <table class="table table-dark table-striped">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Slug</th>
                        <th>Users</th>
                        <th width="150">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($roles as $role)
                    <tr>
                        <td>{{ strtoupper($role->name) }}</td>
                        <td>{{ $role->slug }}</td>
                        <td>{{ $role->users_count }}</td>
                        <td>
                            <a href="{{ route('roles.edit', $role) }}"
                                class="btn btn-primary btn-sm">Edit</a>

                            <form method="POST"
                                action="{{ route('roles.destroy', $role) }}"
                                class="d-inline delete-role">
                                @csrf
                                @method('DELETE')
                                <button type="button"
                                    class="btn btn-danger btn-sm btn-delete">
                                    Delete
                                </button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{ $roles->links() }}

        </div>
    </div>

</x-app-layout>