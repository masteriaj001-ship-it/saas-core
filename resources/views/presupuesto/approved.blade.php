<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Presupuesto Aprobado - {{ $workOrder->code }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: #f5f5f5; color: #1a1a1a; padding: 1rem; line-height: 1.5; }
        .card { max-width: 32rem; margin: 4rem auto; background: #fff; border-radius: 0.75rem; box-shadow: 0 1px 3px rgba(0,0,0,.1); padding: 2rem; text-align: center; }
        .icon { width: 4rem; height: 4rem; background: #dcfce7; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; font-size: 2rem; }
        h1 { font-size: 1.5rem; margin-bottom: 0.5rem; }
        p { color: #6b7280; margin-bottom: 0.25rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1>Presupuesto Aprobado</h1>
        <p>El presupuesto <strong>{{ $workOrder->code }}</strong> ha sido aprobado exitosamente.</p>
        <p style="margin-top: 0.5rem;">Nos pondremos en contacto pronto para coordinar los trabajos.</p>
    </div>
</body>
</html>
