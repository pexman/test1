# haahoowaa CMS – Arquitectura

## Principios
- PHP clásico sin frameworks pesados, patrones MVC por módulo.
- Separación estricta entre recursos públicos (`/public`) y código/plantillas (`/core`, `/modules`, `/templates`).
- Motor de plantillas ligero con placeholders (`{{variable}}`, `{{loop:list}}`, `{{include:file}}`, `{{t:clave}}`).
- Multilingüe completo: detección de idioma por segmento inicial de URL y helper `t()`.
- Extensibilidad: cada módulo vive en `/modules/{nombre}` con `Controller.php`, `Model.php` y vistas propias en `/templates/{theme}/{module}/`.
- SEO integrado (slugs, meta, sitemap, robots) y soporte opcional de IA.

## Capas
- **Public**: único punto de entrada (`public/index.php`) y assets estáticos.
- **Core**: bootstrap, router, base controller/model/view y motor de plantillas.
- **Modules**: páginas, productos, tiendas; cada módulo controla lógica y renderiza vistas.
- **Templates**: temas intercambiables; `layout.html` + parciales + vistas por módulo.
- **i18n**: catálogo de idiomas y traducciones cargadas en memoria.
- **SEO**: generación de slug, meta y sitemap.
- **AI**: integración opcional para sugerir contenido/meta.
- **Config**: configuración general, base de datos y rutas declarativas.
- **Storage**: caché, logs y subidas fuera de público.

## Base de datos mínima
- **languages**: `id`, `code`, `name`, `is_default` (bool).
- **translations**: `id`, `lang_code`, `key_name`, `value`.
- **pages**: `id`, `slug`, `module`, `template`, `created_at`, `updated_at`.
- **pages_i18n**: `page_id`, `lang`, `title`, `content`, `meta_title`, `meta_description`.
- **products** / **products_i18n**: metadatos y contenidos por idioma.
- **shops** / **shops_i18n**: datos de tienda y traducciones.

## Flujo de petición
1. `.htaccess` dirige todo a `public/index.php`.
2. `index.php` carga core, i18n, SEO y AI, luego `Bootstrap`.
3. `Bootstrap` inicializa configuración, PDO y catálogos de idioma/traducción; registra rutas.
4. `Router` detecta idioma, matchea la ruta declarativa y despacha al controlador de módulo.
5. El controlador prepara datos y llama a `View`.
6. `View` resuelve `layout.html`, procesa includes/loops/variables/traducciones y entrega HTML.

## Seguridad
- Código sensible fuera de `public_html`.
- PDO con `ERRMODE_EXCEPTION` y `FETCH_ASSOC`.
- Tema y módulo validados por rutas declarativas.
- Fácil extensión de middlewares (futuros hooks en Router/Controller).

## Extensibilidad
- Agregar un módulo: crear carpeta en `/modules/{mod}` con controller/model y vistas en `/templates/{theme}/{mod}/`.
- Agregar un tema: duplicar carpeta en `/templates/{theme}` con los mismos nombres de vistas.
- IA opcional: la clase `AI` funciona sólo si se entrega `apiKey` en configuración futura.
