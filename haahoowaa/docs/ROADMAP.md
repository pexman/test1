# Roadmap de implementación

## STEP 1 – Core + Template Engine + estructura base
- Preparar estructura de directorios segura (hecho en esta entrega).
- Bootstrap + Router + View + Controller base + Model base.
- Motor de plantillas con variables, includes, loops y traducciones.
- Configuración inicial (config, rutas, i18n) y assets base.

## STEP 2 – Router + módulos
- Conectar Router a controladores de módulos `pages`, `products`, `shops`.
- Definir rutas dinámicas por idioma (slugs) y validaciones.
- Cargar modelos para persistencia real.

## STEP 3 – Multilingüe completo
- Persistir idiomas y traducciones en BD.
- Middleware de selección de idioma (cookie + preferencia navegador).
- Formularios backend para CRUD de traducciones y slugs por idioma.

## STEP 4 – SEO completo
- Campos meta (title, description, canonical, og) en BD por idioma.
- Generación de `sitemap.xml` dinámico y `robots.txt` configurable.
- Helper de slugs con reglas por idioma y desambiguación.

## STEP 5 – Importación de templates externos
- Wizard en backend para subir HTML y mapear placeholders.
- Validación de assets (CSS/JS) y namespaces de módulos.
- Previsualización por idioma/tema.

## STEP 6 – Integración IA opcional
- Configurar API keys (OpenAI) por entorno.
- Servicios para: generación de descripciones, meta title/description, sugerencia de slugs, traducción asistida.
- Hooks en backend para lanzar IA bajo demanda (no blocking).

## STEP 7 – Endurecimiento y QA
- Roles/ACL backend, CSRF, rate limiting de APIs públicas.
- Batería de tests unitarios para router, template engine e i18n.
- Pipelines CI/CD (lint PHP, revisar dependencias, publicar assets).
