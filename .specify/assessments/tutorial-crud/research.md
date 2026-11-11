# Idea Research: tutorial de CRUD paso a paso sobre este framework

- **Slug**: tutorial-crud
- **Created**: 2026-11-11
- **Evidence confidence (overall)**: medium

La evidencia sobre el estado del repo es medida y citable (alta). La evidencia sobre *demanda* es
casi toda suposición (baja). El conjunto queda en medium, y esa asimetría es la conclusión
metodológica de esta etapa.

## Users & Demand

- Persona objetivo declarada: «Desarrollador PHP: quiere un punto de partida rápido y mantenible, sin configuración compleja en Termux» — [source: `.ai/steering/product.md:11`] (confidence: high, cited)
- Persona secundaria: «Dueño de Proyecto: busca tests verdes, migraciones Phinx idempotentes y despliegues consistentes» — [source: `.ai/steering/product.md:13`] (confidence: high, cited). Su dolor declarado es operativo, no de aprendizaje.
- Señal de demanda observada: **ninguna**. El repo no tiene issues, tickets, telemetría ni encuestas; `origin` apunta a `github.com/szchmausser/slim4-homemade-framework` sin backlog consultable desde acá — [source: `git remote -v`] (confidence: high, sobre la ausencia de señal)
- Única señal de demanda disponible: el pedido del dueño del repo en esta sesión, n=1 — [source: `.specify/assessments/tutorial-crud/intake.md`] (confidence: high)
- Que quien clona el framework necesite un recorrido guiado para su primera entidad — [ASSUMPTION] (confidence: low). No hay fuente: ni entrevistas, ni soporte, ni métricas de uso.

## Prior Art

- El repo ya publica una guía paso a paso de 7179 líneas y 23 capítulos numerados — [source: `docs/guia-stack-php-termux.md`] (confidence: high)
- Dentro de esa guía hay **un solo** recorrido de punta a punta: el capítulo 16, «Ejemplo funcional de punta a punta (lista de tareas con HTMX)», entre las líneas 3548 y 4759 (~1212 líneas) y con **una sola** subsección (`### 16.1 Paginación reutilizable`) — [source: `docs/guia-stack-php-termux.md`] (confidence: high)
- El README declara que la guía trae «cada archivo con código copiable» — [source: `README.md:11`] (confidence: high)
- Existe una vía alternativa ya construida: dos demos instalables y removibles, `framework/scaffolds/tasks` (24 archivos, 2444 líneas) y `framework/scaffolds/contacts` (15 archivos, 1653 líneas), con comandos `demo:*:install` / `demo:*:remove` — [source: `framework/scaffolds/`, `composer.json`] (confidence: high)
- Esa vía está testeada: `tests/Support/DemoInstallerTest.php` es una guarda de regresión del removedor — [source: `tests/Support/DemoInstallerTest.php`] (confidence: high)
- `docs/tutorial-de-uso-spec-kit.md` (566 líneas) documenta el **proceso** de SDD, no una entidad CRUD: no se solapa con la idea — [source: `docs/tutorial-de-uso-spec-kit.md`] (confidence: high)
- Precedente de intento previo que envejeció: la primera medición de consistencia documental encontró «27 de 56 copias viejas» — [source: `scripts/docs-check.php:11`] (confidence: high). Es evidencia directa de que este repo ya pagó el costo de documentar copiando código.
- Precedente externo: el framework upstream (Slim) no incluye el recorrido paso a paso en su README (171 líneas) y lo delega a un sitio de documentación separado — [source: `https://github.com/slimphp/Slim`] (confidence: medium)

## Market & Context

- Alternativas que el usuario tiene hoy, todas dentro del repo: (a) `composer demo:tasks:install` y leer el código generado; (b) capítulo 16 de la guía; (c) leer `framework/scaffolds/` directamente; (d) entrar por SDD con `specify` si ya sabe qué quiere — [source: `composer.json`, `docs/guia-stack-php-termux.md`, `framework/scaffolds/`] (confidence: high)
- Costo de no hacer nada: el único recorrido end-to-end sigue siendo el capítulo 16, monolítico (una subsección en 1212 líneas) y atado a la entidad `tasks` — [source: `docs/guia-stack-php-termux.md`] (confidence: high)
- Que los frameworks de arranque compitan por la calidad de su «primer CRUD» — [ASSUMPTION] (confidence: low). No verificado con fuentes.

## Data & Constraints

- `composer docs:check` verifica **57 copias de archivo** y es parte de `composer verify` (`lint`, `docs:check`, `phpstan`, `test`, `migrate:fresh`): una copia desalineada bloquea el gate — [source: `composer.json:52-58`, `scripts/docs-check.php`] (confidence: high)
- `composer docs:sync` reescribe las copias y los fragmentos se marcan `excerpt` en el info string del fence — [source: `framework/Docs/CodeBlocks.php`] (confidence: high)
- Escala actual de la documentación: 3 documentos, 7838 líneas — [source: `docs/`] (confidence: high)
- Restricciones de plataforma que el artefacto tendría que respetar: PHP >= 8.4.1, Termux ARM 32-bit sin toolchain, sin bundler, assets con SRI y `data-theme` sin utilidades `dark:` — [source: `.specify/memory/constitution.md` v1.0.0, `.ai/steering/tech-stack.md`] (confidence: high)
- El repo sostiene **dos modelos de paginación incompatibles por diseño** (server-rendered vs Tabulator en modo remoto) y lo documenta explícitamente — [source: `DESIGN.md:106`] (confidence: high). Implicación: elegir entidad y modelo de paginación cambia el tutorial entero, no un párrafo.
- **Restricción del solicitante (2026-11-11):** el artefacto es referencia de uso, no documentación viva; su código es demostrativo y **no debe pasar por `docs:check`**, y por lo tanto vive fuera de `docs/`, `.ai/` y el README (que son los tres alcances escaneados), sin modificar la guía existente** — [source: decisiones del solicitante, 2026-11-11; `intake.md`] (confidence: high, como requerimiento)
- Alcances verificados de los tres gates, medidos en el repo: `docs:check` escanea `README.md`, `.ai/**/*.md` y `docs/**/*.md`, sin opt-out por archivo; `composer lint` cubre `app/`, `framework/` y `tests/`; PHPStan cubre `app`, `bootstrap`, `config`, `framework/{Auth,Docs,Http,Validation}`, `routes` y `scripts` — [source: `scripts/docs-check.php`, `scripts/lint.php:5-8`, `phpstan.neon`] (confidence: high). Consecuencia directa: cualquier ubicación fuera de esos alcances deja el código del tutorial **sin lint, sin análisis y sin comparación de copias**.

## Evidence Against the Idea

- **Nada verifica el código del tutorial.** El artefacto queda fuera de `docs:check`, de `composer lint` y de PHPStan; un error de sintaxis, un tipo mal puesto o una API que cambió no rompen ningún gate, y el primer aviso es el lector nuevo. No es un riesgo teórico: este repo ya midió que el material copiado envejece solo cuando no hay verificación (27 de 56 copias viejas en la primera medición) — [source: `scripts/docs-check.php:11`, `scripts/lint.php:5-8`, `phpstan.neon`]
- **Compite contra material que ya existe, es ejecutable y está verificado.** Los demos entregan un CRUD funcionando, testeado y removible, y el repositorio ya mantiene 7179 líneas de guía más 4097 líneas de scaffolds bajo gate; un tutorial que repita ese código crea una tercera copia de la misma verdad, esta vez sin verificación — [source: `framework/scaffolds/`, `tests/Support/DemoInstallerTest.php`, `docs/guia-stack-php-termux.md`, `composer.json:52-58`]
- **Se solapa con el capítulo 16.** Ya existe un recorrido de tareas con HTMX y paginación; sin una diferencia explícita (entidad nueva, desde cero, otro público) el artefacto nuevo es una reescritura del mismo material — [source: `docs/guia-stack-php-termux.md:3548-4759`]
- **La demanda no está observada.** No hay issues, tickets ni métricas; la única señal es un pedido en sesión (n=1) — [source: `intake.md`, ausencia de docs de soporte]
- **El alcance cambia el artefacto por completo.** La entidad y el modelo de paginación (server-rendered vs Tabulator remoto) son decisiones que reescriben el tutorial entero; uno «genérico» terminaría explicando el framework otra vez — [source: `DESIGN.md:106`]
- **Suposición débil a favor de lo contrario:** un tutorial largo puede rendir menos que leer código real y ejecutable — [ASSUMPTION] (confidence: low). No tengo evidencia de qué prefiere el usuario.

## Gaps & Open Questions

- [NEEDS CLARIFICATION: ¿Cuál es el punto de partida del lector: clona el repo o ya tiene la app corriendo?]
- [NEEDS CLARIFICATION: ¿Entidad nueva (`notas`) o existente (`tasks`/`contacts`)? Cambia si el artefacto copia archivos del repo o los inventa.]
- [NEEDS CLARIFICATION: ¿Desde cero a mano o sobre el demo instalable como andamio? Lo segundo es más barato y compite con lo que ya existe.]
- [NEEDS CLARIFICATION: ¿HTMX completo (swap, flash, paginación, CSRF en dos transportes) o CRUD server-rendered clásico?]
- [NEEDS CLARIFICATION: ¿Reemplaza al capítulo 16, lo parte en varios, o convive con él?]
- [NEEDS CLARIFICATION: ¿Qué reemplaza a `docs:check` para que el código demostrativo no envejezca sin aviso? La decisión de excluirlo del gate está tomada; esto es el extremo opuesto, no una revisión de esa decisión.]
- [NEEDS CLARIFICATION: ¿Los tests y `composer verify` son parte de los pasos o van aparte?]
- [NEEDS CLARIFICATION: ¿El tutorial es independiente o se apoya en `docs/tutorial-de-uso-spec-kit.md`?]
- [NEEDS CLARIFICATION: ¿Existe demanda externa medible? Con lo disponible no se puede medir, y sin eso la evidencia de valor queda en suposición.]

## Sources

- `.ai/steering/product.md` — personas y propuesta de valor (repo local, sin URL)
- `docs/guia-stack-php-termux.md`, `docs/tutorial-de-uso-spec-kit.md`, `README.md` — documentación existente (repo local)
- `framework/scaffolds/tasks/`, `framework/scaffolds/contacts/`, `tests/Support/DemoInstallerTest.php` — prior art ejecutable (repo local)
- `scripts/docs-check.php`, `framework/Docs/CodeBlocks.php`, `composer.json` — gate de consistencia documental y su costo (repo local)
- `DESIGN.md`, `.specify/memory/constitution.md` — restricciones vigentes (repo local)
- `https://github.com/slimphp/Slim` (host: `github.com`, policy: `allowlisted`) — precedente externo
  - Nota de política: el contenido del README se leyó por el endpoint `raw.githubusercontent.com` del mismo repositorio. Ese host no figura en la allowlist, así que se registra como desvío de política y no como fuente adicional.

Sin URLs adicionales, sin credenciales y sin contenido embebido de terceros.
