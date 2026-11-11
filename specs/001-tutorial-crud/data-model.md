# Data Model — Recorrido de primera entidad

Este feature no agrega tablas al proyecto. Modela dos cosas distintas: **la estructura del
contenido** del recorrido y **la entidad de ejemplo** que el lector construye mientras lo sigue.

## Contenido del recorrido

### Paso

Unidad atómica del artefacto. Es el contrato que `tasks.md` va a usar como checklist.

| Campo | Tipo | Regla |
|---|---|---|
| `n` | entero | Secuencial, sin huecos; el lector lo cita al reportar problemas |
| `titulo` | texto | Acción concreta en imperativo («Agregá la ruta de listado»), no un tema |
| `estado_partida` | texto | Qué tiene que estar hecho antes; si falta, el paso indica cómo volver |
| `accion` | bloque de código + prosa | Qué escribe o ejecuta el lector, con el archivo donde va |
| `resultado_esperado` | texto literal | Lo que debe ver, escrito **antes** de que lo vea |
| `no_prueba` | texto | Qué queda sin verificar con esa comprobación (evita el falso positivo) |
| `pieza` | referencia o nulo | Pieza reutilizable que el paso presenta, si presenta alguna |

### Comprobación

| Campo | Tipo | Regla |
|---|---|---|
| `metodo` | `http` \| `navegador` \| `comando` \| `test` | Cómo la ejecuta el lector |
| `instruccion` | texto | Copiable tal cual (URL, comando) |
| `esperado` | texto literal | Cuerpo, elemento visible o salida de consola esperados |
| `falla_si` | texto | La señal concreta que indica que el paso no se hizo |

Regla transversal: toda comprobación declara `falla_si`. Una comprobación sin señal de falla no
cuenta como comprobación (FR-014).

### Pieza reutilizable

| Campo | Tipo | Regla |
|---|---|---|
| `nombre` | texto | El nombre que usa el proyecto, no un alias |
| `resuelve` | texto | El problema que evita resolver a mano |
| `cuando_usarla` | texto | Criterio de uso |
| `que_evita` | texto | El error concreto que se comete sin ella |
| `alternativa_manual` | texto | Cómo se haría sin la pieza, para que la elección sea visible |

## Entidad de ejemplo (`notas`)

La escribe el lector; el recorrido la usa como caso. No existe en el repositorio hasta que alguien
sigue el tutorial.

| Campo | Tipo | Regla |
|---|---|---|
| `id` | entero, autoincremental | Clave primaria |
| `titulo` | texto(120) | Requerido, entre 1 y 120 caracteres; se rechaza vacío o sólo espacios |
| `cuerpo` | texto, nulo permitido | Opcional, hasta 2000 caracteres |
| `created_at` / `updated_at` | timestamps | Los que el proyecto ya usa en sus modelos |

**Transiciones**: no hay estados. Se crea, se lista y se consulta una nota por identificador
—para poder demostrar el aviso ante un id inexistente—; editar y borrar quedan fuera del recorrido
(la entidad existe para enseñar el camino, no para cubrir un CRUD completo).

**Validaciones que el recorrido demuestra**: título vacío → rechazo con mensaje junto al campo;
título de más de 120 caracteres → rechazo; identificador inexistente → aviso legible, nunca 404 mudo
ni 500 silencioso.
