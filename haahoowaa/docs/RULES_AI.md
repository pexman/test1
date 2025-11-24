# Reglas – IA
- La integración es opcional; el CMS debe funcionar sin API keys.
- Encapsular todas las llamadas en `/ai/AI.php` con comprobación `isEnabled()`.
- No bloquear la respuesta HTTP si el proveedor de IA falla; manejar respuestas vacías.
- Registrar cada petición generada (futuro: `/storage/logs/ai.log`).
- Respetar límites de tokens y evitar enviar datos sensibles sin anonimizar.
