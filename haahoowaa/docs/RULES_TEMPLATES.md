# Reglas – Templates
- Cada tema vive en `/templates/{theme}` y debe incluir `layout.html`, `header.html`, `footer.html` y vistas por módulo.
- Layout debe exponer `{{content}}` y puede usar `{{include:*}}` para parciales.
- Los módulos renderizan a `templates/{theme}/{module}/{view}.html`.
- Usar placeholders `{{variable}}` para datos simples, `{{loop:collection}}...{{endloop}}` para listas, `{{t:clave}}` para traducciones y `{{include:archivo}}` para parciales.
- Evitar lógica PHP dentro de plantillas: sólo HTML + placeholders.
- Los assets propios de un tema deben referenciar `/public/assets/...` y no rutas absolutas externas.
- Mantener accesibilidad básica (etiquetas semánticas, `lang` en `<html>`).
