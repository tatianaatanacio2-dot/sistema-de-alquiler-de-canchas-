#!/usr/bin/env bash
set -euo pipefail
url=${CANCHAS_HEALTH_URL:?Configura CANCHAS_HEALTH_URL=https://TU_DOMINIO/up}
[[ "$url" == https://* ]] || { echo 'La comprobación requiere HTTPS.' >&2; exit 1; }
curl --fail --silent --show-error --max-time 15 --retry 2 "$url" >/dev/null
