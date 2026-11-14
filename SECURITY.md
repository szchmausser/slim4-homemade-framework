# Security policy

## Versiones soportadas

| Versión | Soporte |
|---|---|
| `0.1.x` | Sí, hasta la próxima minor |

## Cómo reportar una vulnerabilidad

No abras un issue público para una vulnerabilidad. Usá **GitHub Security
Advisories** en la pestaña *Security* del repositorio:

https://github.com/szchmausser/slim4-homemade-framework/security/advisories/new

Incluí:

- versión/commit afectado,
- pasos reproducibles,
- impacto esperado,
- si existe, un parche sugerido.

Intentaré confirmar el reporte y coordinaremos la divulgación. No hay un plazo
de SLA formal para un proyecto mantenido por una persona.

## Alcance

- Código de `app/`, `framework/`, `bootstrap/`, `config/`, `routes/`, `public/`
  y `scripts/`.
- Los demos (`framework/scaffolds/*`) son ejemplos: si los usás en producción,
  revisá ownership multiusuario, datos y configuración del servidor.

## Prácticas del proyecto

- `.env` no se versiona; `.env.example` solo trae placeholders.
- `APP_DEBUG=false` por defecto. Whoops solo se monta con `APP_DEBUG=true` y
  `APP_ENV=local`.
- Cookies de sesión `HttpOnly`/`SameSite=Lax`; `SESSION_SECURE=true` detrás de
  HTTPS.
- CSRF global en métodos mutantes.
- Assets locales con SRI; sin CDNs en runtime.
- `composer audit` y `composer verify` antes de cada release.

## Secretos

Si encontrás una credencial commiteada, no la copies en el reporte: indicá la
ruta/commit y rotala. Los secretos reales viven fuera del repositorio.
