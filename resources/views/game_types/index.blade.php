<x-app-layout>
    <div class="container">

        <div class="row">

            {{-- Create Form --}}
            <div class="col-md-4">
                @if(auth()->user()->hasPermission('create-game_types'))
                <div class="card shadow-sm mb-3">
                    <div class="card-header">
                        <h6>Add Game Type</h6>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('game_types.store') }}" method="POST">
                            @csrf

                            <div class="mb-2">
                                <input type="text" name="name" class="form-control"
                                    placeholder="Game Name" required>
                            </div>

                            <div class="mb-2">
                                <input type="text" name="code" class="form-control"
                                    placeholder="Unique Code" required>
                            </div>

                            <div class="mb-2">
                                <textarea name="description" class="form-control"
                                    placeholder="Description"></textarea>
                            </div>

                            <button class="btn btn-primary w-100">
                                Save
                            </button>
                        </form>
                    </div>
                </div>
                @endif
            </div>

            {{-- Listing --}}
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h6>Game Types</h6>
                    </div>

                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Status</th>
                                    <th width="120">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($types as $type)
                                <tr>
                                    <td>{{ $type->name }}</td>
                                    <td>{{ $type->code }}</td>
                                    <td>
                                        <span class="badge bg-{{ $type->status ? 'success' : 'danger' }}">
                                            {{ $type->status ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if(auth()->user()->hasPermission('delete-game_types'))
                                        <button onclick="deleteType({{ $type->id }})"
                                            class="btn btn-sm btn-danger">
                                            Delete
                                        </button>
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>

        </div>

    </div>

</x-app-layout>