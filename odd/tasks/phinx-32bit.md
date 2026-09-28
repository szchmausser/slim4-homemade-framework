# Feature: phinx-32bit

## Objective
Desbloquear `composer install` en Termux 32-bit: Phinx 0.16 exige `php-64bit` (imposible en armv7l). Fijar la última línea compatible (0.13.x) y regenerar el lock.

## Problem
En Termux: `composer update` falla con `robmorgan/phinx 0.16.12 requires php-64bit >=8.1`. Verificado contra Packagist vía Composer: 0.14.0, 0.15.x y 0.16.x piden php-64bit; 0.13.4 pide `php >= 7.2` + cakephp/database ^4.0. API usada por el proyecto (`change()`, `table()->...->create()`, `migrate/rollback/status/create`) estable entre ambas.

## Why
Bug reportado desde Termux real. Sin esto, nadie instala en 32 bits.

## Scope
- `composer.json`: pin `robmorgan/phinx` a `^0.13` + regenerar `composer.lock` (scoped update acá en 64-bit, nunca en el teléfono).
- Verificar: lock sin `php-64bit` en ningún paquete; `phinx status/migrate` funciona; suite verde.
- Guía: cap 4 (pin + motivo), cap 11 (nota), troubleshooting con el error exacto.
- Fuera de alcance: reemplazar Phinx por otro migrador.

## Constraints
- No tocar API de migración ni `phinx.php` (compatibles).
- Revisar techo: si upstream quita el requisito, se puede re-evaluar 0.16+.
- Sin commits/push hasta que el usuario lo pida (preferencia vigente).

## Authorized scope
Investigar + implementar el fix + verificar. Commit/push solo si lo pide.

## Acceptance criteria
- [ ] `composer.lock` sin ninguna ocurrencia de `php-64bit`.
- [ ] `vendor/bin/phinx status` + `migrate` OK con 0.13.
- [ ] Suite phpunit verde.
- [ ] Guía actualizada en los 3 puntos.

## Applicable checks
- `php -r` sobre composer.lock buscando `php-64bit`.
- `vendor/bin/phinx status`, `composer test`, smoke `/tareas` 200.

## TDD resolution
- Mode: off/unknown. Runner: `vendor/bin/phpunit`.

## Delivery strategy
- `ask-on-risk`. Cambio chico. Sin slicing.

## Tasks
- [x] T1 — pin ^0.13 + scoped update + verificar lock limpio (phinx 0.13.4, cake 4.6.5, cero php-64bit en todo el set)
- [x] T2 — verificar phinx 0.13 funcional (status/migrate) + suite + smoke (38/94, /tareas y / 200). Bonus: `install:termux` sin `--no-dev` (sin dev no hay migrate en el teléfono)
- [x] T3 — guía (cap 4 pin+techo, cap 11 nota 0.13, cap 21.20 con el error literal)

## Progress
- 2026-09-28: T1-T3 done en working tree. PENDIENTE decisión del usuario: commit+push (lo necesita en Termux vía pull).
