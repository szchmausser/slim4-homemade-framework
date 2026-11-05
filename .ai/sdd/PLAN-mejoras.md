# Plan: mejoras del esqueleto (post-MVP)

> Status: plan:approved
> Ámbito: calidad del esqueleto, no features nuevas (F03 API JSON queda fuera)
> Última actualización: 2026-11-04
> Referencia de evidencia: revisión de 3 subagentes sobre el repo + verificación manual

## Propósito

El proyecto se concibe como **esqueleto PHP para Termux (ARM 32-bit)** que se instala sin
toolchain y cuya señal de calidad son los tests. Este plan cierra las brechas que hoy
rompen esa promesa: instalación imposible en PHP < 8.4.1, tests que no ejecutan las
migraciones reales, FK inertes, un leak de timing en login, backups inconsistentes y
documentación que afirma cosas falsas.

## Reglas de ejecución

- **Agrupar por relación lógica**: se agrupan fixes que comparten mecanismo, archivos o
  riesgo. No se agrupa por prisa ni se mezcla lo que no se toca entre sí.
- **Un commit por cambio lógico**: una unidad puede tener 2 commits independientes
  (p. ej. `u3a` timing, `u3b` throttle) para poder revertir uno sin el otro.
- **Cada commit se sostiene solo**: mensaje con `PLAN-NN`, body con qué/por qué y la
  salida de `composer test` de ese commit. Un commit que rompe tests no se apila.
- **Relacionado ≠ oportunista**: un problema no relacionado se reporta y queda como
  backlog; no se arregla en el momento.
- **Verificación citada**: la implementación termina con `composer test` verde y la salida pegada en el resumen; sin "creo que anda".
- **Una unidad por ciclo de plan-mode**: `/plan` → `plan_mode_complete` → *Start fresh and implement*
  (o *Implement here*) con **Plan reinjection = Through first implementation run**. Goal no participa
  de este flujo (su coexistencia con plan-mode es solo exclusión mutua, no un handoff).
- **Supervisión**: el plan aprobado se ejecuta en la sesión de implementación; al volver, se
  verifica `git diff --stat`, `composer test` y que no haya archivos fuera de alcance, y se mergea.

## Unidades

Orden: **U1 → U2 → U3 → U4 → U5 → U6 → U7**

| Unidad | Fixes | Commits | Tests de regresión |
|---|---|---|---|
| **U1** | Piso real de PHP (>= 8.4.1) en manifiesto y docs | 1 | `tests/Support/RuntimeRequirementsTest.php` |
| **U2** | Migraciones reales en tests + `foreign_keys=ON` (u2a) · eliminar `tasks.done` con backfill (u2b) | 2 | `tests/Support/SchemaIntegrityTest.php`, migración fresh |
| **U3** | Hash dummy válido/Cost 12 (u3a) · throttling de login (u3b) | 2 | `AuthTest` ampliado, `tests/Http/LoginThrottleTest.php` |
| **U4** | Backup con `VACUUM INTO` + retención (u4a) · rotación de logs (u4b) | 2 | `tests/Support/BackupTest.php`, `tests/Support/LoggingTest.php` |
| **U5** | Cookies `Secure` + HSTS por entorno | 1 | `tests/Http/SecurityHeadersTest.php` |
| **U6** | CI + PHPStan + `composer verify` | 1 | `composer verify` local + CI |
| **U7** | Sincronizar documentación (cifras, "64-bit", comentario CDN) | 1 | `scripts/docs-check.php` dentro de `composer verify` |

### Estado

| Unidad | Estado | Evidencia |
|---|---|---|
| U1 | ✅ hecho | `c5016cc` (merge `2e4f6e1`) — 72 tests |
| U2 | ✅ hecho | `d75eaff` + `75b766e` (merge `02e8fb7`) — 77 tests |
| U3 | ✅ hecho | `cc4f0ee` + `08db5ed` (merge `88650d0`) — 82 tests |
| U4 | ✅ hecho | `3ff032b` + `e57ec71` (merge `7e7ff63`) — 87 tests |
| U5 | ✅ hecho | `0d2740c` (merge `d79de81`) — 90 tests |
| U6 | ✅ hecho | `ac96134` (merge `c38c9df`) — 90 tests |
| U7 | ✅ hecho | `4e9c61d` (merge `a023526`) — 90 tests |

### U1 — Piso real de PHP

- `composer.json`: `"php": ">=8.4.1"` (hoy dice `>=8.2`, pero `symfony/clock`,
  `symfony/translation` y `phpunit` exigen `>=8.4.1`, e `illuminate/database ^13` exige `^8.3`).
- Refrescar `composer.lock` (`composer update --lock --no-install`) para que
  `composer validate` no quede desactualizado.
- Docs: `README.md` (requisitos), `.ai/steering/tech-stack.md`, y las afirmaciones
  de piso en `docs/guia-stack-php-termux.md` (cap. 4).
- Test: `RuntimeRequirementsTest` verifica `PHP_VERSION_ID >= 80401` y que el
  constraint declarado en `composer.json` sea exactamente `>=8.4.1`.

### U2 — Integridad de esquema

- **u2a**: `config/container.php` agrega `foreign_key_constraints => true`;
  `tests/Support/AppFactory.php` deja de duplicar el esquema y migra con Phinx a un
  SQLite temporal; `phpunit.xml`/`tests/bootstrap.php` apuntan a ese archivo.
  Test nuevo: FK en `foreign_key_list`, `PRAGMA foreign_keys = 1`, borrar usuario
  borra sus `remember_tokens` en cascada.
- **u2b**: migración `005_drop_done_from_tasks.php` con backfill
  (`done=1 → status=completado, completed_at=updated_at`) y `DROP COLUMN`
  (SQLite 3.54 lo soporta); limpiar `Task::$fillable/$casts`, seeds, fixtures y las
  referencias en `.ai/sdd/specs/001-crud-tareas/*`.

### U3 — Endurecimiento del login

- **u3a**: reemplazar el hash dummy inválido de `AuthController::store` (50 chars)
  por un bcrypt válido de cost 12. Test: `password_get_info()` reporta bcrypt/cost 12
  y `password_verify` devuelve false.
- **u3b**: migración `006_login_attempts.php` + `App\Support\Throttle` + chequeo en
  `AuthController::store` (5 fallos / 15 min por email+IP, mensaje genérico, limpieza
  al autenticar). Test HTTP con ventana y limpieza.

### U4 — Operación del dispositivo

- **u4a**: `scripts/db-backup.php` usa `VACUUM INTO` sobre la DB y deja de copiar
  `-wal/-shm/-journal`; retención `KEEP` (default 10). Test: backup válido +
  `PRAGMA integrity_check` + poda del más viejo.
- **u4b**: `StreamHandler` → `RotatingFileHandler` (14 días) en `config/container.php`;
  `.env.example` documenta `LOG_MAX_FILES`. Test: el handler resuelto rota.

### U5 — Despliegue seguro

- `SESSION_SECURE=true` → cookie de sesión y remember-me con `Secure`, y HSTS en
  `SecurityHeadersMiddleware`. Por defecto sigue en `false` (dev local).
- Test HTTP: con la variable activa aparecen `Secure` y `Strict-Transport-Security`;
  por defecto no.

### U6 — Verificación automatizada

- `composer verify` = lint (`php -l` sobre app/tests) + `composer test` + migración
  fresh sobre DB temporal.
- PHPStan nivel 5 (dev-only, phar, sin compilación) y baseline vacía.
- GitHub Actions con PHP 8.4/8.5 ejecutando `composer verify`.

### U7 — Documentación sincronizada

- Eliminar cifras hardcodeadas de tests (65/66/69, 200/204/221) y decir que la cifra
  la da `composer test`.
- Corregir la afirmación "PHP 64-bit" en `.ai/sdd/specs/001-crud-tareas/review.md`
  (el runtime es 32-bit: `PHP_INT_SIZE=4`) y el comentario de
  `SecurityHeadersMiddleware` que menciona jsdelivr/unpkg (los assets son locales con SRI).
- `scripts/docs-check.php` (invocado desde `composer verify`) falla si reaparecen
  "65 tests", "66 tests", "jsdelivr", "unpkg" o "64-bit PHP".

## Backlog (fuera de los goals)

- **Advisories en dependencias dev** (detectados 2026-11-04 con `composer update --lock`):
  `symfony/config` GHSA-5jff-8h8q-r5qx (XXE en `XmlUtils::parse`) y
  `symfony/filesystem` GHSA-6prc-h956-hxjq (path escape), ambos traídos por Phinx.
  No son de runtime; evaluar bump cuando Phinx lo permita. Si U6 agrega
  `composer audit` a `composer verify`, resolverlos antes para no nacer en rojo.
- `Vary: HX-Request` (solo relevante con caché compartida).
- `hx-disabled-elt` / `hx-sync` (doble submit en red lenta).
- bcrypt del validator de remember-me (~1.45 s una vez por sesión; opción:
  `sha256` + `hash_equals`).
- `license` en `composer.json` (warning de `composer validate`).
- Y2038 en `remember_tokens.expires_at`.
- `odd/`: legado de la metodología anterior, se conserva como bitácora.

## Descartado en la segunda revisión (no hacer)

- `busy_timeout`: ya vale 60000 por defecto.
- "bcrypt en cada request": falso; solo al abrir sesión.
- Mutaciones no-HTMX devolviendo fragmento: inalcanzable desde la UI (sin JS no hay POST).
- CSP ausente y `persistentTokenMode`: decisiones documentadas, no defectos.
- 404 con mensaje de ruta, `mkdir 0777`, falta de progressive enhancement.

## Plantilla por unidad (plan-mode)

En la TUI:

```text
/plan U4 — Operación del dispositivo: backup consistente y rotación de logs
```

Después de aprobar el plan que devuelve el agente (`plan_mode_complete`), elegir
**Start fresh and implement** y verificar que **Plan reinjection** esté en
**Through first implementation run** (`/plan settings` si hace falta).

El plan de cada unidad debe incluir, como mínimo:

- alcance de archivos permitido y prohibiciones (deps, reformateos, DB real);
- los commits esperados, en orden, cada uno con `composer test` verde;
- tests de regresión concretos;
- comando de verificación y formato del resumen final;
- qué hacer ante un imprevisto no relacionado (reportarlo, no arreglarlo).

> Nota de método: el handoff prescrito por `pi-plan-mode` es *Implement here* o
> *Start fresh and implement*, con el plan exacto transferido y reinjectado. `pi-goal`
> queda para trabajo autónomo que no nace de un plan aprobado (su `workflow:mutex:v1`
> solo garantiza que no corran a la vez).
