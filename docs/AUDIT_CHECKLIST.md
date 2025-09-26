# Checklist de Refactorizacion — Veterinalia Appointment (includes/)

Guia practica para implementar, rastrear y verificar los refactors sencillos detectados en la auditoria. Marque cada tarea cuando este completa e incluya notas y fecha si aplica.

Estado sugerido por item: [ ] Pendiente · [/] En progreso · [x] Hecho

## Progreso
- [x] Admin: boton Guardar con i18n en `includes/class-admin-settings.php` (submit_button)
- [x] Tipos de retorno (`: void` / `: self`) en metodos clave:
  - `includes/class-admin-settings.php`: `get_instance(): self`, `init(): void`, `add_admin_menu_page(): void`, `register_settings(): void`, `render_*(): void`
  - `includes/class-ajax-handler.php`: `get_instance(): self`, `init(): void`
  - `includes/class-rest-api-handler.php`: `get_instance(): self`, `init(): void`, `register_routes(): void`
  - `includes/class-appointment-database.php`: `create_tables(): void`, `drop_tables(): void`, `create_table_*(): void`
- [ ] UTF-8: pendiente limpieza de mojibake en comentarios/strings (cambios sin impacto funcional)

## 1) Codificacion UTF-8 (mojibake)
- [/] Corregir caracteres rotos en comentarios (archivo admin settings).
- [ ] Corregir caracteres rotos en comentarios/logs de `class-ajax-handler.php`, `class-rest-api-handler.php`, `class-appointment-database.php`.
  - Archivos: `includes/class-admin-settings.php`, `includes/class-ajax-handler.php`, `includes/class-appointment-database.php`, `includes/class-rest-api-handler.php`
  - Criterio de aceptacion: No quedan secuencias como "A�", "A3" u otras en comentarios/strings visibles. Archivos guardados en UTF-8 sin BOM.

## 2) Consistencia patron Singleton
- [ ] Marcar clases como `final` donde aplique (evitar herencia accidental).
- [ ] `@var self|null` para `$instance` y `@return self` en `get_instance()` (phpDoc).
- [ ] `__construct()` privado y uso consistente de `get_instance()`.
  - Archivos: todos en `includes/` que usan singleton.

## 3) Tipado y phpDoc minimos
- [ ] Anadir tipos de retorno (`: void`, `: array`, etc.) a metodos que no retornan valor o retornan coleccion.
- [ ] Completar phpDoc de parametros/retornos complejos (arrays asociativos, estructuras).
  - Archivos: todos en `includes/`.

## 4) Hooks e inicializacion
- [ ] Normalizar firma `init(): void`.
- [ ] Agrupar `add_action`/`add_filter` por dominio/logica.
- [ ] Extraer `register_hooks()` si la lista crece.
  - Archivos: `includes/class-admin-settings.php`, `includes/class-ajax-handler.php`, `includes/class-rest-api-handler.php`.

## 5) AJAX Handler
- [ ] Registrar acciones desde un mapa (`action` → `metodo`) en bucle para reducir repeticion.
- [ ] Mantener handlers concretos en metodos privados bien nombrados por dominio (Schedule, Booking, Availability).
- [ ] Estandarizar respuestas JSON (`wp_send_json_success`/`wp_send_json_error`) con estructura `{ success, data, error }`.
- [ ] Verificar nonces y permisos donde aplique.
  - Archivo: `includes/class-ajax-handler.php`.

## 6) Appointment Manager
- [ ] Extraer validaciones y normalizacion de entrada a helpers privados.
- [ ] Reducir longitud de metodos publicos y responsabilidades por metodo.
- [ ] Anadir phpDoc y tipos de retorno/argumentos.
  - Archivo: `includes/class-appointment-manager.php`.

## 7) Base de datos — Appointment
- [ ] Asegurar `prepare()` en consultas manuales y `format` explicito en `insert`/`update`.
- [ ] Centralizar nombres de tablas en constantes o getters (`get_table_names()`), y usarlas de forma uniforme.
- [ ] Anadir `DB_VERSION` y rutina `maybe_upgrade()` (opcional si falta).
  - Archivo: `includes/class-appointment-database.php`.

## 8) Base de datos — CRM (si aplica)
- [ ] Verificar que `$wpdb->get_charset_collate()` se inicializa en constructor y no se repite.
- [ ] Revisar indices y claves usuales para consultas (anadir `INDEX` en `dbDelta` si falta).
  - Archivo: `includes/class-crm-database.php`.

## 9) Base de datos — Templates
- [ ] Declarar `create_table_*(): void` en todos los creadores de tablas.
- [ ] Anadir indices a claves foraneas/lookup comunes (por ejemplo: `service_id`, `category_id`, etc.) en `dbDelta`.
- [ ] Verificar compatibilidad `dbDelta` con cambios (nombres y tipos exactos).
  - Archivo: `includes/class-templates-database.php`.

## 10) REST API
- [ ] Documentar `register_routes(): void` con phpDoc detallando endpoints, parametros, ejemplos y codigos de respuesta.
- [ ] Definir `args` con `sanitize_callback` y `validate_callback` al registrar rutas.
- [ ] Estandarizar respuestas con `WP_REST_Response` o `wp_send_json_*` (estructura uniforme).
  - Archivo: `includes/class-rest-api-handler.php`.

## 11) Admin Settings
- [ ] Anadir `sanitize_callback` por cada `register_setting`.
- [ ] Incluir/verificar `nonce` en formularios y su validacion.
- [ ] Extraer `render_*_field()` a metodos privados o plantillas si el HTML crece.
  - Archivo: `includes/class-admin-settings.php`.

## 12) Nombres y consistencia
- [ ] Prefijar hooks, opciones y acciones con `va_` o `veterinalia_` de forma uniforme.
- [ ] Usar snake_case para hooks y camelCase para metodos/propiedades PHP.
- [ ] Revisar y uniformar nombres de metodos y constantes.
  - Archivos: todos en `includes/`.

## 13) QA rapida (smoke tests)
- [ ] Crear/editar cita desde frontend (flujo completo) sin errores JS/PHP.
- [ ] Consultar disponibilidad y guardar horario profesional via AJAX.
- [ ] CRUD de servicios y plantillas guardando en BD.
- [ ] Probar endpoints REST: respuestas 200 y errores 4xx con validacion.

## 14) Documentacion y tooling (opcional pero recomendado)
- [ ] Actualizar README/docs con pautas de contribucion y convenciones (tipado, hooks, respuestas JSON, BD).
- [ ] Configurar PHPCS (WordPress-Extra) y ejecutar sobre `includes/` corrigiendo easy-fixes.

---

Notas:
- Prioridad sugerida inicial: (1) Codificacion UTF-8 → (2) Tipos/`init(): void` → (3) Mapa de hooks AJAX → (4) Sanitizacion/validacion (Admin/REST) → (5) Acceso BD (`prepare`/`format`).
- Anotar bajo cada item el commit/fecha y responsable si aplica.
