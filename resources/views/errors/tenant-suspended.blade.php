<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Espacio de trabajo pausado · Jaosoft</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center px-4">

    <div class="max-w-md w-full text-center">

        {{-- Icono de estado --}}
        <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-amber-100">
            <svg class="h-10 w-10 text-amber-500" xmlns="http://www.w3.org/2000/svg"
                 fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71
                         c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898
                         0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
        </div>

        {{-- Logotipo / marca --}}
        <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-amber-600">
            Jaosoft · Smart Business OS
        </p>

        {{-- Título --}}
        <h1 class="mb-3 text-2xl font-bold text-gray-900">
            Espacio de trabajo pausado
        </h1>

        {{-- Mensaje --}}
        <p class="mb-8 text-base text-gray-600 leading-relaxed">
            El acceso a este espacio de trabajo ha sido pausado temporalmente.
            Por favor, ponte en contacto con el equipo de
            <span class="font-semibold text-gray-800">Jaosoft</span>
            para restablecer tu suscripción.
        </p>

        {{-- Separador --}}
        <div class="mb-8 border-t border-gray-200"></div>

        {{-- Contacto --}}
        <div class="rounded-lg bg-white border border-gray-200 px-6 py-4 text-sm text-gray-500">
            <p class="font-medium text-gray-700 mb-1">¿Necesitas ayuda?</p>
            <p>
                Escríbenos a
                <a href="mailto:soporte@jaosoft.com"
                   class="text-amber-600 hover:underline font-medium">
                    soporte@jaosoft.com
                </a>
            </p>
        </div>

        {{-- Volver al login --}}
        <div class="mt-6">
            <a href="{{ url('/admin/login') }}"
               class="text-sm text-gray-400 hover:text-gray-600 transition-colors">
                ← Volver al inicio de sesión
            </a>
        </div>

    </div>

</body>
</html>
