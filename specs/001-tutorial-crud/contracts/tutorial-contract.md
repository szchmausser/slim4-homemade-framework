# Contrato del recorrido

Lo que `tutorial-crud.md` tiene que cumplir para considerarse válido. `tasks.md` deriva de acá, y
`analyze` puede verificar cobertura contra la spec usando este archivo.

## Contrato del artefacto

| Requisito | Regla verificable |
|---|---|
| Ubicación | Archivo único `tutorial-crud.md` en la raíz del repositorio |
| Alcance del gate | No está en `docs/`, ni en `.ai/`, ni es el README; no agrega scripts ni dependencias |
| Prerrequisitos | Bloque al inicio: qué necesita el lector (PHP, Composer, cliente HTTP, navegador) y qué **no** se enseña acá |
| Orden | Los pasos son secuenciales y cada uno asume el estado que dejó el anterior |
| Arranque | Incluye el tramo hasta una sesión iniciada contra una ruta protegida |
| Progresión | Respuesta simple verificable por petición directa **antes** de introducir vistas |
| Cierre | Enseña los tests que reemplazan las comprobaciones manuales y los deja corriendo |
| Intocables | No modifica la guía, ni los demos, ni el `DESIGN.md`; el README suma una sola línea |
| Idioma | Prosa en español; código, rutas e identificadores en inglés |

## Contrato de paso

Todo paso del recorrido declara, en este orden:

1. **Objetivo** — la acción concreta, en imperativo.
2. **Estado de partida** — qué tiene que estar hecho; si falta, cómo volver.
3. **Acción** — el archivo y el código que el lector escribe o el comando que ejecuta.
4. **Comprobación** — instrucción copiable y resultado esperado literal, escritos antes de ejecutar.
5. **Qué no prueba** — el límite de la comprobación, explícito.
6. **Pieza presentada** (opcional) — nombre, qué resuelve, cuándo usarla, qué evita, alternativa manual.

Un paso sin comprobación, o con comprobación sin resultado esperado, no cumple el contrato.

## Contrato de comprobación

| Método | Forma esperada |
|---|---|
| `http` | Método y URL; cuerpo esperado literal; código de estado |
| `navegador` | URL a abrir; qué debe verse en pantalla; qué NO debe aparecer |
| `comando` | Comando copiable; salida esperada |
| `test` | Nombre del test; resultado esperado; qué caso cubre |

Ninguna comprobación puede depender de que el lector «vea que funciona». Si el resultado no se puede
escribir de antemano, la comprobación está mal diseñada.

## Contrato de pieza reutilizable

Una pieza se presenta cuando el lector la **necesita**, no antes. Debe quedar claro qué se haría sin
ella: sin esa comparación, la pieza parece obligatoria y el lector no aprende cuándo usarla.
