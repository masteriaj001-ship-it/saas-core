#!/usr/bin/env bash
set -euo pipefail

BACKUP_ROOT="${BACKUP_ROOT:-$HOME/backups/dotfiles}"
KEEP=${KEEP:-5}

STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="${BACKUP_ROOT}/${STAMP}"

mkdir -p "${DEST}"

copy_if_exists() {
    local src="$1"
    if [[ -e "${src}" ]]; then
        cp -a "${src}" "${DEST}/"
        echo "  ✓ ${src}"
    else
        echo "  - skip ${src}"
    fi
}

echo "Backup de dotfiles → ${DEST}"

copy_if_exists "${HOME}/.bashrc"
copy_if_exists "${HOME}/.bash_profile"
copy_if_exists "${HOME}/.profile"
copy_if_exists "${HOME}/.config/opencode"

EXCLUDES=(
    --exclude='node_modules'
    --exclude='.git'
)

TARBALL="${DEST}.tar.gz"
tar -czf "${TARBALL}" -C "${DEST}" "${EXCLUDES[@]}" .
echo "✓ Comprimido: ${TARBALL}"

rm -rf "${DEST}"

mapfile -t old < <(ls -1t "${BACKUP_ROOT}"/*.tar.gz 2>/dev/null | tail -n +$((KEEP + 1)))
for f in "${old[@]}"; do
    rm -f "${f}"
    echo "  ✗ retención: eliminado ${f}"
done

echo "✓ Backup completo (retención: últimos ${KEEP})"