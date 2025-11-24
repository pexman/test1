# Reglas – Core
- Mantener `public/` como único directorio expuesto; todo el core debe residir fuera de `public`.
- No usar frameworks pesados; sólo PHP nativo + PDO.
- Prohibido envolver `require`/`include` en bloques `try/catch`.
- Router debe resolver idioma por primer segmento y despachar sólo rutas declaradas en `config/routes.php`.
- El motor de plantillas debe soportar `{{variable}}`, `{{loop:name}}...{{endloop}}`, `{{include:file}}`, `{{t:key}}` y `{{content}}` en el layout.
- Controladores retornan HTML via `View`; la lógica de datos queda en modelos.
- Configuración se lee desde `/config`; no hardcodear rutas absolutas en el core.
- Toda conexión PDO debe usar `ERRMODE_EXCEPTION` y `UTF-8`.
- Preparar hooks para SEO/IA sin bloquear la ejecución cuando no estén configurados.
