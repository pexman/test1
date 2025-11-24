# Reglas – i18n
- Lista de idiomas en `i18n/languages.php` y traducciones en `i18n/translations.php` hasta que se persista en BD.
- Primer segmento de URL define idioma (`/es`, `/en`, `/fr`); si falta, usar `is_default`.
- Todas las vistas deben consumir textos mediante `{{t:clave}}` o `t('clave')` en PHP.
- Slugs y rutas se definen por idioma; no forzar transliteración única.
- Evitar mezclar idiomas en la misma página; definir `lang` en `<html>` y atributos `hreflang` futuros.
- Mantener claves cortas, legibles y reutilizables.
