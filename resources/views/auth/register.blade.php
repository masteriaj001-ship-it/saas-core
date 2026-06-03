<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="fi">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }} — Registro</title>
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
                Crear cuenta
            </h1>
            <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-6">
                Completa los datos para registrar tu taller
            </p>

            @if ($errors->any())
                <div class="mb-4 p-3 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg text-red-700 dark:text-red-400 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="space-y-5">
                @csrf

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre completo</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required autofocus
                        class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Correo electrónico</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                        class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>

                <div>
                    <label for="industry" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Industria</label>
                    <select name="industry" id="industry"
                        class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 shadow-sm focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                        <option value="general" {{ old('industry') === 'general' ? 'selected' : '' }}>Retail / General</option>
                        <option value="mechanic" {{ old('industry') === 'mechanic' ? 'selected' : '' }}>Taller mecánico</option>
                        <option value="restaurant" {{ old('industry') === 'restaurant' ? 'selected' : '' }}>Restaurante</option>
                        <option value="construction" {{ old('industry') === 'construction' ? 'selected' : '' }}>Constructora</option>
                        <option value="clinic" {{ old('industry') === 'clinic' ? 'selected' : '' }}>Clínica</option>
                    </select>
                </div>

                <div>
                    <label for="business_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nombre del negocio</label>
                    <input type="text" name="business_name" id="business_name" value="{{ old('business_name') }}" required
                        class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contraseña</label>
                    <input type="password" name="password" id="password" required
                        class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Mínimo 8 caracteres, una mayúscula, un número y un carácter especial.</p>
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirmar contraseña</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required
                        class="mt-1 block w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 shadow-sm placeholder:text-gray-400 focus:border-amber-500 focus:ring-1 focus:ring-amber-500">
                </div>

                <button type="submit"
                    class="w-full rounded-lg bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-amber-500 focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition">
                    Crear cuenta
                </button>
            </form>

            <p class="mt-6 text-sm text-center text-gray-500 dark:text-gray-400">
                ¿Ya tienes cuenta?
                <a href="{{ url('/admin/login') }}" class="font-medium text-amber-600 hover:text-amber-500 dark:text-amber-400 dark:hover:text-amber-300">Inicia sesión</a>
            </p>
        </div>
    </div>

    @filamentScripts
</body>
</html>
