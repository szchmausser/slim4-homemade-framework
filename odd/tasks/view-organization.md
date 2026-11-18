# Reorganizar `resources/views` por función

Un solo árbol para el shell, uno para los componentes reutilizables y uno para las pantallas, de modo
que «¿dónde va esto?» tenga una única respuesta. Incluye framework, demos y tutorial.

## Specs

- **S1**: «todo dentro de resources debería estar organizados según su función, las vistas en carpetas
  específicas de vistas, componentes reutilizables en carpetas de componentes, y cosas del layout en la
  carpeta layout».
- **S2**: eliminar el riesgo de «duplicación de componentes que harían lo mismo por no encontrarse en la
  carpeta correcta», y la ambigüedad de nombres como `partials/layout/header.twig` frente a
  `components/ui/page-header.twig`.
- **S3**: «ajustar los demos para que se ajusten a la nueva convención de directorios».
- **S4**: «asegurarte que el tutorial […] siga esa convención de directorios, para que el resultado de
  seguirlo manualmente sea el mismo que produciría la misma entidad desde el demo instalable».
- **S5**: «hagamos esto de forma metódica y ordenada […] con planificación ODD», incluyendo el codebase
  del framework primero.

## Árbol objetivo

```
resources/views/
├── layouts/                  esqueleto del documento
│   ├── app.twig
│   ├── auth.twig
│   └── parts/                fragmentos del esqueleto
│       ├── head.twig
│       ├── topbar.twig       (era partials/layout/header.twig)
│       ├── sidebar.twig
│       ├── footer.twig
│       ├── toasts.twig
│       └── ink-tokens.twig
├── components/               reutilizable con contrato propio
│   ├── ui/                   presentación genérica
│   │   ├── button.twig
│   │   ├── icon.twig         (era partials/_icon.twig)
│   │   ├── logo.twig         (era partials/_logo.twig)
│   │   ├── action-bar.twig
│   │   ├── modal.twig
│   │   ├── page-header.twig
│   │   └── page-header-title.twig
│   ├── form/
│   │   ├── field.twig
│   │   └── csrf.twig         (era partials/_csrf.twig)
│   ├── feedback/alert.twig
│   ├── list/simple.twig
│   ├── table/tabulator.twig
│   └── pagination/simple.twig
└── pages/                    pantallas
    ├── home.twig
    ├── auth/login.twig
    ├── errors/{error,_htmx}.twig
    └── <entidad>/{index,_panel,_form}.twig   (instalada por los demos)
```

Regla de fragmentos: lo interno de una pantalla o feature lleva `_nombre.twig` y vive en la carpeta de
su feature (`pages/notas/_panel.twig`). Los componentes nunca llevan guion bajo.

## Tasks

- **T1** — Ficha, inventario y reglas escritas (S1, S2, S5).
- **T2** — Mover el framework: shell, átomos, csrf, pantallas; renombrar `topbar`; actualizar includes,
  rutas, manejador de errores y tests de contrato (S1, S2).
- **T3** — Guardarraíl: test de estructura de vistas (S2).
- **T4** — Demos: vistas, controladores, rutas internas y mapeo del instalador (S3).
- **T5** — Tutorial: rutas de archivos, fragmentos de código y figura de capítulo 14 (S4).
- **T6** — `DESIGN.md`, `CHANGELOG`, docs copiadas y verificación completa (S1, S5).

## Log

- **L1**: «estoy revisando ya vamos bien, pero me parece que estamos haciendo un desastre de
  organizacion de componentes dentro de la ruta resources\views\components donde tambien tenemos cosas
  en resources\views\layouts y resources\views\partials parece que tenemos una organizacion poco
  eficiente de las cosas reutilizables, a nivel de componente individuales reutilizables, el layout de
  kla pagina, y demas fragmentos que usamos, introduciendo un riesgo de organizacion y posible
  duplicacion de componentes que harian lo mismo por no encontrarse en la carpeta correcta donde
  deberian de estar a nivel de jerarquia u organizacion... se supone que todo dentro de resources
  deberia estar organizados segun su funcion, las vistas en carpetas especificas de vistas, componentes
  reutilizables en carpetas de componentes, y cosas del layout en la carpeta layout, por favor haz una
  revision de como estan organizadas las cosas en resources y dime que podemos mejorar al respecto, todo
  tiene que tener una organizacion fundamentada y logica o a futuro tendremos un problema peor de
  organizacion»
- **L2**: «hagamos esto de forma metodica y ordenada […] planificacion odd, luego implementes los
  cambios, incluyendo el codebase del propio framework, luego ajustar los demos […] y despues
  asegurarte que el tutorial […] siga esa convencion».
- **L3**: inventario — 22 archivos en `resources/views`; `_icon` es la pieza más usada (29 includes);
  10 `class="btn` fuera de `components/` (home ×3, login, error, topbar ×2, sidebar, toasts).
