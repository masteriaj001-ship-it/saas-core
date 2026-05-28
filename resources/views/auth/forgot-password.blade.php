<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Recuperar contraseña</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50">
        <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
            <h1 class="text-2xl font-bold text-center mb-2">Recuperar contraseña</h1>
            <p class="text-sm text-gray-600 text-center mb-6">Te enviaremos un enlace para restablecer tu contraseña.</p>

            @if (session('status'))
                <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded text-green-700 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required autofocus
                           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <button type="submit"
                        class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded shadow-sm transition">
                    Enviar enlace
                </button>
            </form>

            <p class="mt-4 text-sm text-center text-gray-600">
                <a href="{{ url('/admin/login') }}" class="text-indigo-600 hover:underline">Volver al inicio de sesión</a>
            </p>
        </div>
    </div>
</body>
</html>
