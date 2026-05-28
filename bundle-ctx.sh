#!/bin/bash
set -e

OUTPUT="project_context.txt"

echo "=== ESTRUCTURA DEL PROYECTO ===" > $OUTPUT
find . -not -path '*/vendor/*' -not -path '*/node_modules/*' -not -path '*/storage/*' -not -path '*/public/*' -not -path '*/bootstrap/cache/*' -not -path '*/.git/*' -maxdepth 4 | sort >> $OUTPUT

echo -e "\n\n=== CONFIGURACIÓN CORE ===" >> $OUTPUT
for f in \
    .env.example \
    config/app.php \
    config/database.php \
    config/permission.php \
    config/industry-defaults.php \
    phpunit.xml; do
    if [ -f "$f" ]; then
        echo -e "\n--- $f ---" >> $OUTPUT
        cat "$f" >> $OUTPUT
    fi
done

echo -e "\n\n=== PANELES FILAMENT ===" >> $OUTPUT
for f in app/Providers/Filament/*.php; do
    echo -e "\n--- $f ---" >> $OUTPUT
    cat "$f" >> $OUTPUT
done

echo -e "\n\n=== MODELOS ===" >> $OUTPUT
for f in app/Models/*.php; do
    echo -e "\n--- $f ---" >> $OUTPUT
    cat "$f" >> $OUTPUT
done

echo -e "\n\n=== TRAITS ===" >> $OUTPUT
for f in app/Models/Concerns/*.php; do
    echo -e "\n--- $f ---" >> $OUTPUT
    cat "$f" >> $OUTPUT
done

echo -e "\n\n=== MIDDLEWARES PERSONALIZADOS ===" >> $OUTPUT
for f in app/Http/Middleware/*.php; do
    echo -e "\n--- $f ---" >> $OUTPUT
    cat "$f" >> $OUTPUT
done

echo -e "\n\n=== SERVICIOS CORE ===" >> $OUTPUT
for f in app/Services/*.php app/Services/**/*.php; do
    if [ -f "$f" ]; then
        echo -e "\n--- $f ---" >> $OUTPUT
        cat "$f" >> $OUTPUT
    fi
done

echo -e "\n\n=== FILAMENT RESOURCES (ADMIN) ===" >> $OUTPUT
for f in app/Filament/Resources/*.php; do
    echo -e "\n--- $f ---" >> $OUTPUT
    cat "$f" >> $OUTPUT
done

echo -e "\n\n=== FILAMENT RESOURCES (SUPERADMIN) ===" >> $OUTPUT
for f in app/Filament/Superadmin/Resources/*.php; do
    echo -e "\n--- $f ---" >> $OUTPUT
    cat "$f" >> $OUTPUT
done

echo -e "\n\n=== FILAMENT WIDGETS ===" >> $OUTPUT
for f in app/Filament/Widgets/*.php; do
    echo -e "\n--- $f ---" >> $OUTPUT
    cat "$f" >> $OUTPUT
done

echo -e "\n\n=== COMANDOS ARTISAN ===" >> $OUTPUT
for f in app/Console/Commands/*.php; do
    echo -e "\n--- $f ---" >> $OUTPUT
    cat "$f" >> $OUTPUT
done

echo -e "\n\n=== MIGRACIONES (SOLO NOMBRES) ===" >> $OUTPUT
ls -1 database/migrations/ >> $OUTPUT

echo -e "\n\n=== FACTORIES (SOLO NOMBRES) ===" >> $OUTPUT
ls -1 database/factories/ >> $OUTPUT

echo -e "\n\n=== DOCUMENTACIÓN CORE ===" >> $OUTPUT
for f in \
    docs/PROJECT_STATE.md \
    AGENTS.md \
    CLAUDE.md; do
    if [ -f "$f" ]; then
        echo -e "\n--- $f ---" >> $OUTPUT
        cat "$f" >> $OUTPUT
    fi
done

echo "Contexto empaquetado con éxito en $OUTPUT"
wc -l "$OUTPUT"
