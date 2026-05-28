@component('mail::message')
# Recuperación de contraseña

Has solicitado restablecer tu contraseña. Haz clic en el siguiente botón para crear una nueva contraseña:

@component('mail::button', ['url' => $url])
Restablecer contraseña
@endcomponent

Este enlace expirará en 30 minutos.

Si no solicitaste este cambio, ignora este mensaje.

Saludos,<br>
{{ config('app.name') }}
@endcomponent
