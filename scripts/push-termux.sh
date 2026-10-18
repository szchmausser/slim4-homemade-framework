#!/usr/bin/env bash
#
# Push a origin desde Termux usando un ssh-agent de socket de ruta fija.
#
# Problema: los procesos sin TTY (pi, scripts, CI local) no comparten el
# ssh-agent de la sesion interactiva, y la clave tiene passphrase.
# Solucion: agente con socket fijo (~/.ssh/agent/pi.sock) + clave cargada.
#
# Uso:
#   bash scripts/push-termux.sh            # rama actual
#   bash scripts/push-termux.sh main       # rama explicita
#   SSH_KEY=~/.ssh/otra_key bash scripts/push-termux.sh
#
set -euo pipefail

AGENT_DIR="$HOME/.ssh/agent"
SOCK="$AGENT_DIR/pi.sock"
KEY="${SSH_KEY:-$HOME/.ssh/id_ed25519}"
BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"
REMOTE="${REMOTE:-origin}"

mkdir -p "$AGENT_DIR" "$(dirname "$KEY")"

# 1) Asegurar agente vivo en el socket fijo.
#    ssh-add -l: 0 = con identidades, 1 = agente vivo sin identidades,
#    2 = no se pudo conectar al agente.
rc=0
SSH_AUTH_SOCK="$SOCK" ssh-add -l >/dev/null 2>&1 || rc=$?
if [ "$rc" -eq 2 ]; then
  rm -f "$SOCK"
  ssh-agent -a "$SOCK" > "$AGENT_DIR/pi.env"
  echo "Agente iniciado en $SOCK"
fi

# 2) Cargar la clave si el agente no tiene identidades.
rc=0
SSH_AUTH_SOCK="$SOCK" ssh-add -l >/dev/null 2>&1 || rc=$?
if [ "$rc" -eq 1 ]; then
  echo "Cargando clave $KEY (te va a pedir la passphrase)..."
  SSH_AUTH_SOCK="$SOCK" ssh-add "$KEY"
fi

# 3) Push.
export SSH_AUTH_SOCK="$SOCK"
echo "Push de '$BRANCH' a '$REMOTE'..."
git push "$REMOTE" "$BRANCH"
