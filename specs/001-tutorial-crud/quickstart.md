# Quickstart de validación — Recorrido de primera entidad

Cómo se comprueba que el entregable hace lo que la spec promete. No contiene el recorrido: eso es
`tutorial-crud.md`. Esto es la guía para validarlo, incluida la parte que ningún gate cubre.

## Prerrequisitos

- PHP 8.4.1 o superior con `pdo_sqlite`, Composer y Phinx (los del proyecto).
- Un cliente HTTP (`curl`) y un navegador.
- Un clon del repositorio sin los demos instalados, para que el recorrido no dependa de ellos.

## 1. Verificar el estado de partida

```bash
composer install:termux && composer dump-autoload -o
ls tutorial-crud.md
```

Esperado: el archivo existe en la raíz. Si además corrés `composer demo:tasks:install` y después el
recorrido, debería funcionar igual: el material no puede depender del estado de los demos.

## 2. Seguir el recorrido

De arriba abajo, sin saltear pasos. En cada paso, comparar el resultado obtenido con el
**resultado esperado escrito**, y leer el apartado **qué no prueba**.

Criterio de fracaso del recorrido completo: llegar al final sin haber escrito ni ejecutado ninguna
comprobación, o llegar a una ruta que responde y no persiste nada.

## 3. Verificar el cierre

```bash
composer test
```

Esperado: los tres tests del cierre pasan, y el resto de la suite sigue en verde.

```bash
composer verify
```

Esperado: lint, docs-check, PHPStan, suite y migración fresh sin errores. El recorrido no puede
romper el gate aunque esté fuera de su alcance.

## 4. Verificar que no se tocó lo intocable

```bash
git status --short
```

Esperado: `tutorial-crud.md` nuevo y, como único cambio en archivos existentes, **una línea** en
`README.md`. Nada en `docs/`, nada en `framework/scaffolds/`, nada en `DESIGN.md`.

```bash
composer docs:check
```

Esperado: el mismo resultado que antes de agregar el artefacto (23 markdown, 44 fuentes, 57 copias).
Si el número de markdown subió, el archivo quedó en un lugar escaneado y hay que moverlo.

## 5. Verificar la transferencia

Sin volver a leer el recorrido completo, cambiar el nombre de la entidad (`notas` → otro) y uno de
sus campos, y repetir los pasos usando el recorrido sólo como consulta.

Esperado: la segunda entidad lista y persiste. Si hace falta releer todo, el cierre no dejó el patrón
explícito y la historia P3 no se cumplió.

## Qué no cubre esta validación

- Calidad de la redacción: ningún comando la mide.
- Vigencia futura: fuera del gate, el material envejece en silencio. La única defensa es que cada
  paso conserve su comprobación; si una deja de dar el resultado esperado, el paso está roto aunque
  el texto siga leyéndose bien.
