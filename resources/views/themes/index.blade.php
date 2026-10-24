<x-app-layout>
    <div class="container">

        <div class="row">

            {{-- Create Form --}}
            <div class="col-md-4">
                @if(auth()->user()->hasPermission('create-themes'))
                <div class="card shadow-sm mb-3">
                    <div class="card-header">
                        <h6>Add Theme</h6>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('themes.store') }}" method="POST">
                            @csrf

                            <div class="mb-2">
                                <input type="text" name="name" class="form-control"
                                    placeholder="Theme Name" required>
                            </div>

                            <div class="mb-2">
                                <input type="text" name="code" class="form-control"
                                    placeholder="Theme Code" required>
                            </div>

                            <div class="mb-2">
                                <input type="color" name="primary_color"
                                    class="form-control form-control-color">
                            </div>

                            <div class="mb-2">
                                <input type="color" name="secondary_color"
                                    class="form-control form-control-color">
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
                        <h6>Themes</h6>
                    </div>

                    <div class="card-body p-0">
                        <table class="table table-bordered mb-0">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Primary</th>
                                    <th>Secondary</th>
                                    <th width="120">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($themes as $theme)
                                <tr>
                                    <td>{{ $theme->name }}</td>
                                    <td>{{ $theme->code }}</td>
                                    <td>
                                        <div style="width:25px;height:25px;
                                        background:{{ $theme->primary_color }}">
                                        </div>
                                    </td>
                                    <td>
                                        <div style="width:25px;height:25px;
                                        background:{{ $theme->secondary_color }}">
                                        </div>
                                    </td>
                                    <td>
                                        @if(auth()->user()->hasPermission('delete-themes'))
                                        <button onclick="deleteTheme({{ $theme->id }})"
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