@extends('layouts.guest') {{-- or remove this line if not using guest layout --}}

@section('content')

<div class="container d-flex align-items-center justify-content-center vh-100">

    <div class="card bg-black text-white border-warning shadow-lg" style="width: 400px;">
        <div class="card-body p-4">

            <div class="text-center">
                <img src="{{ asset('images/logo.png') }}"
                    alt="GameMGT Logo"
                    style="max-width:180px;">
            </div>
            <h3 class="text-warning mb-4">Login</h3>

            {{-- Session Status --}}
            @if (session('status'))
            <div class="alert alert-success">
                {{ session('status') }}
            </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                {{-- Username --}}
                <div class="mb-3">
                    <label for="username" class="form-label text-warning">
                        Username
                    </label>
                    <input type="text"
                        name="username"
                        id="username"
                        class="form-control bg-dark text-white border-warning @error('username') is-invalid @enderror"
                        value="{{ old('username') }}"
                        required
                        autofocus>

                    @error('username')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                    @enderror
                </div>

                {{-- Password --}}
                <div class="mb-3">
                    <label for="password" class="form-label text-warning">
                        Password
                    </label>
                    <input type="password"
                        name="password"
                        id="password"
                        class="form-control bg-dark text-white border-warning @error('password') is-invalid @enderror"
                        required>

                    @error('password')
                    <div class="invalid-feedback">
                        {{ $message }}
                    </div>
                    @enderror
                </div>

                {{-- Remember --}}
                <div class="form-check mb-3">
                    <input class="form-check-input"
                        type="checkbox"
                        name="remember"
                        id="remember">
                    <label class="form-check-label text-light" for="remember">
                        Remember me
                    </label>
                </div>

                {{-- Forgot Password --}}
                @if (Route::has('password.request'))
                <div class="mb-3 text-end">
                    <a href="{{ route('password.request') }}" class="text-warning small">
                        Forgot your password?
                    </a>
                </div>
                @endif

                {{-- Submit --}}
                <div class="d-grid">
                    <button type="submit" class="btn btn-warning fw-bold">
                        Log In
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

@endsection