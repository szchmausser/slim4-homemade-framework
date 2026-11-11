# Verificación del recorrido Checklist: Recorrido de primera entidad

**Purpose**: Revisar la calidad de los requisitos de `spec.md` sobre el eje que decide esta feature: que cada paso sea verificable de verdad y que el alcance del material no se derive en silencio. No verifica la implementación.
**Created**: 2026-11-11
**Feature**: `specs/001-tutorial-crud/spec.md`

**Note**: Checklist generado por `/speckit.checklist` a partir del contexto de la feature.
**Review Ownership**: artefacto de revisión de requisitos, propiedad de quien revisa. Marcar `[x]` sólo cuando el criterio de calidad esté satisfecho.
**Marker Semantics**: `[x]` significa criterio revisado y satisfecho; no significa trabajo de implementación terminado.

**Supuestos del checklist** (no se preguntaron porque el pedido fue continuar): profundidad *standard*; audiencia *autor y revisor del PR*; focos elegidos: (1) verificabilidad por paso, (2) bordes de alcance. Quedaron fuera de foco rendimiento y accesibilidad, que no aplican a este artefacto.

## Completitud de requisitos

- [ ] CHK001 ¿Está definido qué debe contener el bloque de prerrequisitos, más allá de «declararlos»? [Gap]
- [ ] CHK002 ¿Está especificado qué debe hacer el lector si descubre a mitad del recorrido que no cumple un prerrequisito? [Gap]
- [ ] CHK003 ¿Está acotado qué tramos componen «el arranque hasta una sesión iniciada», o queda a criterio de la implementación? [Completeness, Spec §FR-002]
- [ ] CHK004 ¿Está definido el alcance de la entidad de ejemplo —campos y operaciones— en la spec, o sólo en el plan? [Completeness, Spec §FR-004]
- [ ] CHK005 ¿Está escrito qué pasa con los datos que el lector crea al terminar el recorrido? [Gap]

## Claridad y verificabilidad

- [ ] CHK006 ¿«Resultado esperado escrito antes de ejecutar» define el nivel de literalidad exigido: cadena exacta, cuerpo JSON, o descripción del efecto? [Clarity, Spec §FR-014]
- [ ] CHK007 ¿«Qué no prueba» tiene un criterio de suficiencia, o cualquier frase cumple el requisito? [Ambiguity, Spec §FR-014]
- [ ] CHK008 ¿Se distingue entre comprobación por petición HTTP y comprobación en navegador, o son intercambiables? [Clarity, Spec §FR-014]
- [ ] CHK009 ¿«Pieza reutilizable» está acotada a un conjunto identificable, o cualquier cosa del framework cuenta? [Ambiguity, Spec §FR-008]
- [ ] CHK010 ¿«Dejar el patrón explícito» para repetir con otra entidad tiene un criterio observable, o es un deseo? [Ambiguity, Spec §FR-010]
- [ ] CHK011 ¿«Usar el idioma vigente del framework» se traduce en comportamientos verificables concretos? [Clarity, Spec §FR-007]

## Consistencia entre requisitos

- [ ] CHK012 ¿FR-009 y FR-014 evitan que el lector escriba dos veces la misma verificación, una a mano y otra como test? [Consistency]
- [ ] CHK013 ¿FR-011 (fuera de los directorios escaneados) y FR-012 (una línea en el README) conviven sin contradicción? [Consistency]
- [ ] CHK014 ¿El límite entre FR-002 (arranque hasta sesión) y FR-013 (no enseñar a construir) está trazado de forma explícita? [Consistency]
- [ ] CHK015 ¿Las historias P1, P2 y P3 se pueden implementar y verificar por separado, sin solaparse? [Consistency]

## Criterios de aceptación y métricas

- [ ] CHK016 ¿SC-001 es medible sin instrumentación: quién la observa y sobre qué evidencia? [Measurability, Spec §SC-001]
- [ ] CHK017 ¿SC-003 define cuánta consulta del recorrido se considera todavía «sin releerlo completo»? [Measurability, Spec §SC-003]
- [ ] CHK018 ¿SC-005 se puede verificar en un clon limpio, sin depender de la máquina de quien escribe? [Measurability, Spec §SC-005]

## Cobertura de escenarios y casos de borde

- [ ] CHK019 ¿Está cubierto el lector que ya tiene una entidad propia y quiere adaptar el recorrido en lugar de seguirlo desde cero? [Coverage]
- [ ] CHK020 ¿Está definido qué se hace cuando el framework cambie y el recorrido quede desactualizado: procedimiento, no intención? [Gap]
- [ ] CHK021 ¿El caso de credenciales inválidas está cubierto más allá de «anticipar el rechazo»? [Coverage, Spec §Edge Cases]
- [ ] CHK022 ¿Está definido qué pasa si el lector instala o remueve un demo a mitad del recorrido? [Coverage]

## Bordes de alcance, dependencias y asunciones

- [ ] CHK023 ¿La excepción al principio V de la constitución tiene una mitigación verificable, o sólo declarada? [Assumption, Plan §Complexity Tracking]
- [ ] CHK024 ¿Está escrito con precisión qué archivos pueden cambiar, de modo que se pueda revisar en un diff? [Clarity, Spec §FR-012]
- [ ] CHK025 ¿La asunción de «archivo único en la raíz» declara cuándo deja de valer? [Assumption, Spec §Assumptions]

## Ambigüedades residuales

- [ ] CHK026 ¿Queda algún requisito cuyo cumplimiento dependa sólo de la calidad de la redacción y no de un resultado observable? [Ambiguity]

## Notes

- Marcar `[x]` sólo tras revisar que el criterio de calidad se cumple.
- Dejar sin marcar lo que todavía requiera aclaración o corrección.
- `/speckit.implement` lee el estado de los checkboxes como compuerta y no debe modificar los marcadores.
- Los ítems CHK007, CHK010 y CHK023 son los que más riesgo concentran: los tres pueden quedar «cumplidos» por redacción sin que nada cambie en la práctica.
