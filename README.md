# haahoowaa CMS (base)

Arquitectura modular en PHP (sin frameworks pesados) con soporte de plantillas, i18n y SEO listo para extender a backend completo.

## Estructura clave
- `haahoowaa/public/`: único punto público (`index.php`, `.htaccess`, assets en `css/`, `js/`, `images/`).
- `haahoowaa/core/`: bootstrap, router, MVC base y motor de plantillas.
- `haahoowaa/modules/`: módulos `pages`, `products`, `shops` con controladores/modelos.
- `haahoowaa/templates/default/`: tema base con layout, header/footer y vistas por módulo.
- `haahoowaa/i18n/`: idiomas y traducciones iniciales + helper `t()`.
- `haahoowaa/seo/` y `haahoowaa/ai/`: utilidades SEO y stub de IA opcional.
- `haahoowaa/storage/`: uploads, caché y logs fuera del alcance público.
- `haahoowaa/docs/`: reglas, TODOs y roadmap detallado.

## Roadmap resumido
1. Core + motor de plantillas (entregado).
2. Router + módulos conectados a datos.
3. Multilingüe completo (BD + CRUD traducciones).
4. SEO completo (meta, sitemap, robots, canonical/hreflang).
5. Importador de templates HTML.
6. Integración IA opcional para contenido/meta.

## Cómo iniciar
1. Configura `haahoowaa/config/database.php` con tus credenciales MySQL.
2. Apunta tu vhost/document root a `haahoowaa/public`.
3. Importa `haahoowaa/docs/DB_SCHEMA.sql` para crear las tablas mínimas (`languages`, `translations`, `pages`, `pages_i18n`, `products`, `products_i18n`, `shops`, `shops_i18n`).
4. Accede a `/` o `/es`, `/en`, `/fr` para probar detección de idioma y rutas de ejemplo.
