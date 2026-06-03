<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fi">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Recuperar contraseña</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @filamentStyles
</head>
<body class="fi-body font-sans antialiased bg-gray-50 dark:bg-gray-950 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <div class="bg-white dark:bg-gray-800 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl p-8">
            <div class="flex justify-center mb-6">
                <div class="text-xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ config('app.name') }}
                </div>
            </div>

            <h1 class="text-center text-lg font-semibold text-gray-950 dark:text-white mb-1">
                Recuperar contraseña
            </h1>
            <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-6">
                Te enviaremos un enlace para restablecer tu contraseña.
            </p>

            @if (session('status'))
                <div class="mb-4 p-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg text-green-700 dark:text-green-400 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-400 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo electrónico</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                        class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>

                <button type="submit"
                    class="w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
                    Enviar enlace
                </button>
            </form>

            <p class="mt-6 text-sm text-center text-gray-500 dark:text-gray-400">
                <a href="{{ url('/admin/login') }}" class="font-medium text-amber-600 hover:text-amber-500 dark:text-amber-400 dark:hover:text-amber-300">Volver al inicio de sesión</a>
            </p>
        </div>
    </div>

    @filamentScripts
</body>
</html>
