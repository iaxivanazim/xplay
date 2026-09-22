<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'GameMGT') }}</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-white">

<div class="container vh-100 d-flex flex-column justify-content-center align-items-center">

    <h1 class="display-4 text-warning mb-4">
        🎰 {{ config('app.name', 'GameMGT') }}
    </h1>

    <p class="lead text-center mb-4">
        Casino Management System
    </p>

    <div class="d-flex gap-3">
        @auth
            <a href="{{ url('/dashboard') }}" class="btn btn-warning">
                Go to Dashboard
            </a>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline-warning">
                Log in
            </a>

            
        @endauth
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
