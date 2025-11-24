# Reglas – SEO
- Generar slugs limpios por idioma con `Slug::generate()` y permitir overrides manuales.
- Cada página/producto/tienda debe tener `meta_title`, `meta_description`, `canonical` y opcional `og:*` por idioma.
- `sitemap.xml` se compone dinámicamente desde BD y respeta idiomas en URLs.
- `robots.txt` debe vivir en `public/` y evitar exponer `/storage` y `/config`.
- Evitar parámetros en las URLs públicas; usar rutas limpias por módulo.
- Incluir etiquetas `link rel="alternate" hreflang` cuando haya contenido multilingüe.
