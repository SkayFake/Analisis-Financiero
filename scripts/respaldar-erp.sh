#!/usr/bin/env bash
# Linux. Crea un respaldo local cifrado; no envía datos a terceros ni elimina copias.
set -euo pipefail
umask 077
ERP_PROJECT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"
: "${PGDATABASE:?Configure PGDATABASE y las credenciales PostgreSQL mediante PGPASSFILE}"
: "${ERP_BACKUP_DIR:?Seleccione un directorio privado fuera de public/}"
: "${ERP_BACKUP_RECIPIENT:?Indique el destinatario público age; conserve su clave privada por separado}"
command -v pg_dump >/dev/null
command -v age >/dev/null
if [[ ! -f "$ERP_PROJECT_DIR/storage/framework/down" ]]; then
  echo 'Detenga las escrituras y los workers, y active mantenimiento antes del respaldo coherente.' >&2
  exit 1
fi
mkdir -p -- "$ERP_BACKUP_DIR"
ERP_BACKUP_DIR="$(realpath -- "$ERP_BACKUP_DIR")"
case "$ERP_BACKUP_DIR/" in
  "$ERP_PROJECT_DIR/public/"*) echo 'El respaldo no puede ubicarse en public/.' >&2; exit 1 ;;
esac
ERP_BACKUP_STAGE="$(mktemp -d)"
trap 'rm -rf -- "$ERP_BACKUP_STAGE"' EXIT
pg_dump --format=custom --no-owner --file="$ERP_BACKUP_STAGE/database.dump"
if [[ -d "$ERP_PROJECT_DIR/storage/app/expedientes" ]]; then
  if [[ -n "$(find "$ERP_PROJECT_DIR/storage/app/expedientes" -type l -print -quit)" ]]; then
    echo 'Revise los enlaces simbólicos del almacén documental antes de respaldar.' >&2
    exit 1
  fi
  cp -a -- "$ERP_PROJECT_DIR/storage/app/expedientes" "$ERP_BACKUP_STAGE/expedientes"
fi
(
  cd -- "$ERP_BACKUP_STAGE"
  find . -type f ! -name SHA256SUMS -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS
)
ERP_BACKUP_NAME="erp-$(date -u +%Y%m%dT%H%M%SZ)-$(basename -- "$ERP_BACKUP_STAGE").tar.age"
tar -C "$ERP_BACKUP_STAGE" -cf - . | age -r "$ERP_BACKUP_RECIPIENT" -o "$ERP_BACKUP_DIR/$ERP_BACKUP_NAME"
echo "Respaldo cifrado creado: $ERP_BACKUP_DIR/$ERP_BACKUP_NAME"
