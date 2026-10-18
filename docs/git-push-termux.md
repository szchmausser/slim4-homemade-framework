# Push a GitHub desde Termux

> Por qué hace falta este paso extra y cómo resolverlo en un comando.

## El problema

En Termux, `pi` (o cualquier proceso sin TTY) **no comparte el `ssh-agent` de tu sesión interactiva**. Por eso, aunque tu terminal pueda pushear, desde la herramienta el push falla con:

```text
git@github.com: Permission denied (publickey).
fatal: Could not read from remote repository.

Please make sure you have the correct access rights
and the repository exists.
```

La causa: la clave `~/.ssh/id_ed25519` está protegida con **passphrase**. Tu terminal la desbloquea al vuelo cuando hacés `git push` (te la pide por pantalla), pero un proceso sin TTY no puede escribirla. Y el socket de agente que arranca tu shell normalmente está vacío o muerto.

La solución es un `ssh-agent` con **socket de ruta fija** (`~/.ssh/agent/pi.sock`) al que cualquier proceso pueda apuntar.

## Solución rápida (recomendada)

```bash
composer push                # rama actual
bash scripts/push-termux.sh main   # rama explícita
```

El script arranca el agente si hace falta, carga la clave (te pide la passphrase una sola vez) y pushea.

## Procedimiento manual

```bash
# 1. Arrancar un agente con socket de ruta fija (una vez por arranque de Termux)
ssh-agent -a ~/.ssh/agent/pi.sock > ~/.ssh/agent/pi.env

# 2. Cargar la clave (pide la passphrase)
SSH_AUTH_SOCK=~/.ssh/agent/pi.sock ssh-add ~/.ssh/id_ed25519

# 3. Pushear usando ese socket
SSH_AUTH_SOCK=~/.ssh/agent/pi.sock git push origin main
```

**Cualquier sesión de Termux sirve**: lo único que importa es que `SSH_AUTH_SOCK` apunte a `~/.ssh/agent/pi.sock`. No hace falta que sea la sesión donde arrancó el agente.

## Verificar que el agente tiene la clave

```bash
SSH_AUTH_SOCK=~/.ssh/agent/pi.sock ssh-add -l
# 256 SHA256:XPxXOjqZ9IW3RIya+MJCRCGKN6Z0eerjZJd3XEEuRME szchmausser@gmail.com (ED25519)
```

Si responde `The agent has no identities.`, repetí el paso 2.

## Alternativas

### HTTPS con gh (solo si el token tiene escritura)

```bash
gh auth status
git -c credential.helper='!gh auth git-credential' \
  push https://github.com/szchmausser/slim4-homemade-framework.git main
```

Si devuelve **403**, el fine-grained PAT no tiene **Contents: Read and write** sobre el repo; en ese caso usá SSH o habilitá ese permiso en GitHub → *Settings → Developer settings → Fine-grained tokens*.

### Pushear manualmente

```bash
git push origin main   # tu terminal te pide la passphrase
```

## Troubleshooting

| Síntoma | Causa | Solución |
|---|---|---|
| `Permission denied (publickey)` | agente ausente o clave no cargada | pasos 1–3 |
| `Connection refused` al usar el socket | el agente murió y el socket quedó colgado | `rm -f ~/.ssh/agent/pi.sock` y rearrancar |
| `! [rejected] ... (fetch first)` | el remoto tiene commits que no tenés localmente | `git fetch origin && git rebase origin/main` (o `git pull`) y reintentar |
| HTTPS `error: 403` | PAT sin permiso de escritura | otorgar `Contents: Read and write` o usar SSH |
| `ssh-add` sale con código 1 pero la clave aparece en `ssh-add -l` | aviso inofensivo del prompt | ignorar; la clave quedó cargada |

## Seguridad / limpieza

El agente mantiene la clave desbloqueada en memoria. Para bloquearla de nuevo:

```bash
SSH_AUTH_SOCK=~/.ssh/agent/pi.sock ssh-add -D          # quita las identidades
pkill -f 'ssh-agent -a .*pi.sock'                      # opcional: mata el agente
```

---

*Documentado el 2026-10-18 durante la unificación del workspace SDD.*
