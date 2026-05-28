<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Registro</title>
    <!-- Include app.css, Filament assets, etc. via your stack -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen flex flex-col items-center justify-center bg-gray-50">
        <div class="w-full max-w-md bg-white rounded-lg shadow-md p-8">
            <h1 class="text-2xl font-bold text-center mb-6">Crear cuenta</h1>

            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded text-red-700 text-sm">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Nombre completo</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus
                           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700">Correo electrónico</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="industry" class="block text-sm font-medium text-gray-700">Industria</label>
                    <select name="industry" id="industry"
                            class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="general" {{ old('industry') === 'general' ? 'selected' : '' }}>Retail / General</option>
                        <option value="mechanic" {{ old('industry') === 'mechanic' ? 'selected' : '' }}>Taller mecánico</option>
                        <option value="restaurant" {{ old('industry') === 'restaurant' ? 'selected' : '' }}>Restaurante</option>
                        <option value="construction" {{ old('industry') === 'construction' ? 'selected' : '' }}>Constructora</option>
                        <option value="clinic" {{ old('industry') === 'clinic' ? 'selected' : '' }}>Clínica</option>
                    </select>
                </div>

                <div>
                    <label for="business_name" class="block text-sm font-medium text-gray-700">Nombre del negocio</label>
                    <input type="text" name="business_name" id="business_name" value="{{ old('business_name') }}" required
                           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                    <input type="password" name="password" id="password" required
                           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-500">Mínimo 8 caracteres, una mayúscula, un número y un carácter especial.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                           class="mt-1 block w-full rounded border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>

                <button type="submit"
                        class="w-full py-2 px-4 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded shadow-sm transition">
                    Crear cuenta
                </button>
            </form>

            <p class="mt-4 text-sm text-center text-gray-600">
                ¿Ya tienes cuenta?
                <a href="{{ url('/admin/login') }}" class="text-indigo-600 hover:underline">Inicia sesión</a>
            </p>
        </div>
    </div>
</body>
</html>
