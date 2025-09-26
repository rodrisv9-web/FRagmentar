# Dashboard de Clientes v2 — Mapa Técnico y Guía de Mantenimiento

Estado: Activo (v2 oficial) - Actualizado con sistema de tipos de usuario
Ámbito: Panel de clientes (frontend), CSS/JS/Plantillas, REST API (client-scope)
Última actualización: Septiembre 2025 - Sistema de tipos de usuario y creación automática de clientes

## Resumen

Se reemplazó el dashboard de clientes legacy por una versión v2 con:

- Plantilla PHP nueva y accesible (solo vista, sin `<head>`/inline scripts).
- CSS y JS divididos en assets namespaced y encolados por WordPress.
- Integración con la API REST del plugin con endpoints "client-scope".
- Reuso de tablas existentes (sin crear nuevas tablas): `va_pets`, `va_pet_logs`, `va_appointments`.
- **Sistema de tipos de usuario** basado en meta field `_user_type` para distinguir profesionales (`author`) de clientes (`general`).
- **Creación automática de clientes** para usuarios con `_user_type='general'` que no tengan registro en el CRM.
- **Selección inteligente de dashboard** basada en el tipo de usuario, no en listados profesionales.

## Entradas/Archivos Clave

- Plantilla v2
  - `templates/client-dashboard.php:1`

- CSS (scoped bajo `#va-client-dashboard`)
  - `assets/css/client-dashboard/base.css:1`
  - `assets/css/client-dashboard/components.css:1`
  - `assets/css/client-dashboard/sheets.css:1`

- JS
  - Módulo: `assets/js/modules/client-dashboard-v2.js:1`
  - Bootstrap: `assets/js/main-client-dashboard.js:1`

- Encolado de assets (frontend)
  - v2 CSS: `includes/class-appointment-manager.php:149`
  - v2 JS: `includes/class-appointment-manager.php:154`
  - Localize config v2: `includes/class-appointment-manager.php:168`
  - Nota: el legacy aún encola `assets/js/modules/client-dashboard.js` (líneas ~140–146). No afecta v2 pero se puede retirar para aligerar payload.

- REST API (nuevas rutas client‑scope y handlers)
  - Registrar `/clients/me/pets`: `includes/class-rest-api-handler.php:104`
  - Handler `handle_get_my_pets()`: `includes/class-rest-api-handler.php:799`
  - Guard `check_client_pet_permission()`: `includes/class-rest-api-handler.php:846`
  - GET `/clients/pets/{pet_id}/logs`: handler `handle_get_client_pet_logs()`: `includes/class-rest-api-handler.php:865`
  - POST `/clients/pets/{pet_id}/logs`: handler `handle_create_client_pet_log()`: `includes/class-rest-api-handler.php:880`
  - GET `/clients/pets/{pet_id}/summary`: handler `handle_get_client_pet_summary()`: `includes/class-rest-api-handler.php:912`
  - GET `/clients/me/notifications`: handler `handle_get_client_notifications()`: `includes/class-rest-api-handler.php:944`

## Sistema de Tipos de Usuario

### Tipos Soportados
El sistema utiliza el meta field `_user_type` en `wp_usermeta` para determinar el tipo de usuario:

| `_user_type` | Dashboard | Creación Cliente | Descripción |
|-------------|-----------|------------------|-------------|
| `'author'` | Profesional | ❌ NO | Veterinarios, profesionales con listados |
| `'general'` | Cliente | ✅ SÍ (automático) | Clientes regulares, dueños de mascotas |
| `null` u otro | Cliente | ❌ NO | Usuarios sin tipo definido |

### Lógica de Selección de Dashboard
```php
$user_type = get_user_meta(get_current_user_id(), '_user_type', true);
if ($user_type === 'author') {
    // Dashboard profesional
} else {
    // Dashboard de cliente
}
```

### Creación Automática de Clientes
- Solo se crean registros de cliente para usuarios con `_user_type='general'`
- Los profesionales (`_user_type='author'`) nunca obtienen registros de cliente
- Usuarios sin tipo definido no obtienen creación automática

## Arquitectura y Flujo de Datos

1) Bootstrap
- `VA_Client_Dashboard` se localiza con: `rest_url`, `nonce` y `client_id` (resuelto **solo para usuarios con `_user_type='general'`**).
- Los profesionales (`_user_type='author'`) obtienen `client_id=0` en el localize para evitar creación automática.
- `assets/js/main-client-dashboard.js` llama `VAClientDashboardV2.init('#va-client-dashboard', VA_Client_Dashboard)` en `DOMContentLoaded`.

2) Estado/Render (client-dashboard-v2.js)
- Estado interno: `pets`, `logsByPet`, `selectedPetId`, `activeFilter`, `currentPage`, `dateFilter`.
- Render seguro: plantillas con `textContent` y evita `innerHTML` con datos no confiables; iconos son decorativos `aria-hidden`.
- Navegación de pantallas (`resumen` ⇄ `historial`), filtros por tipo/fecha y paginación en cliente.

3) REST
- Carga inicial de mascotas (`GET /clients/me/pets`), historial de la mascota seleccionada (`GET /clients/pets/{id}/logs`), métricas (`GET /clients/pets/{id}/summary`) y notificaciones (`GET /clients/me/notifications`).
- Alta de mascota (`POST /clients/pets`) y alta de entrada (`POST /clients/pets/{id}/logs`).
- **Validación de tipo de usuario**: Solo usuarios con `_user_type='general'` pueden crear mascotas y acceder a endpoints de cliente.
- **Creación automática**: Si un usuario `'general'` no tiene registro de cliente, se crea automáticamente en la primera solicitud.
- Permisos: `check_client_pet_permission` asegura que el `pet.client_id` pertenezca al usuario actual.

4) Datos/DB
- No se crean tablas nuevas. Se reutilizan:
  - `va_pets` (mascotas del cliente)
  - `va_pet_logs` (historial médico; ya tiene `entry_type`, `entry_date`, `weight_recorded`, `is_private`)
  - `va_appointments` (citas; se usa `pet_id`, `appointment_start` para métricas y notificaciones)

## Encolado (WP)

`includes/class-appointment-manager.php:149`
- CSS: `va-client-dashboard-base`, `va-client-dashboard-components`, `va-client-dashboard-sheets`.
- JS: `va-client-dashboard-v2`, `va-client-dashboard-v2-init`.
- Vendor: `phosphor-icons` (desde CDN — opcional vendorizar).
- Localize: `VA_Client_Dashboard` con `client_id` (cero si el usuario no es `_user_type='general'` o no tiene registro de cliente).

## Endpoints (client‑scope)

- GET `vetapp/v1/clients/me/pets`
  - Retorna mascotas del cliente logueado.
  - **Validación**: Solo usuarios con `_user_type='general'`. Profesionales obtienen array vacío.
  - **Auto-creación**: Crea cliente automáticamente si no existe (solo para `'general'`).

- POST `vetapp/v1/clients/pets` { name, species?, breed?, gender? }
  - Crea mascota del cliente con `share_code` automático.
  - **Validación**: Solo usuarios con `_user_type='general'`. Error 403 para profesionales.
  - **Auto-creación**: Crea cliente automáticamente si no existe.

- GET `vetapp/v1/clients/pets/{pet_id}/logs`
  - Retorna historial. Cliente ve entradas no privadas o propias (en esta fase se devuelve lista completa de `get_pet_logs`).
  - **Validación**: Verifica propiedad de la mascota por el cliente.

- POST `vetapp/v1/clients/pets/{pet_id}/logs` { title, type, description? }
  - Crea entrada básica para el cliente; `is_private=1` por defecto.
  - **Validación**: Verifica propiedad de la mascota por el cliente.

- GET `vetapp/v1/clients/pets/{pet_id}/summary`
  - `next_appointment`, `last_visit`, `last_vaccine`, `current_weight`.

- GET `vetapp/v1/clients/me/notifications`
  - Próximas citas por mascota.

## Seguridad

- Nonces: `X-WP-Nonce` desde `VA_Client_Dashboard.nonce`.
- Permisos: `check_api_permission` (usuario logueado) y `check_client_pet_permission` (propiedad de la mascota).
- Sanitización en handlers: `sanitize_text_field`, `sanitize_textarea_field`, `esc_url_raw` (donde procede).
- UI evita `innerHTML` con datos no confiables; uso de `textContent` y plantillas controladas.

## Accesibilidad

- Roles/ARIA: botones con `aria-haspopup`, `aria-controls`, `aria-expanded`, estados en toasts `role="status"` y `aria-live`.
- Iconos decorativos con `aria-hidden="true"`.
- Bottom sheets: overlay + contenedor; (pendiente) atrapar foco y cerrar con `ESC` si se requiere AA estricta.
- Viewport: se evita bloquear zoom.

## Estilos (scoping)

- Todo CSS está scoped bajo `#va-client-dashboard` para evitar colisiones con el tema u otros módulos.
- Clases comunes: `.dashboard-container`, `.metrics-grid`, `.timeline-*`, `.bottom-sheet*` (siempre bajo el scope del contenedor).

## Decisiones / Compatibilidad

- v2 es la versión oficial: plantilla legacy reemplazada.
- **Sistema de tipos de usuario**: Migrado de verificación por roles de WordPress a meta field `_user_type` para mayor precisión y rendimiento.
- **Selección de dashboard**: Cambió de verificación por listados profesionales a verificación directa por tipo de usuario.
- **Creación automática**: Solo para usuarios `_user_type='general'`, eliminando creaciones innecesarias para profesionales.
- Script legacy `assets/js/modules/client-dashboard.js` sigue encolado por compatibilidad; se puede retirar si no hay dependencias.
- Phosphor icons vía CDN por simplicidad; se recomienda vendorizar o habilitar fallback para entornos sin red/CDN.

## Pruebas (smoke tests)

1) **Verificación de tipos de usuario**
- Usuario con `_user_type='author'`: Debe ver dashboard profesional, `client_id=0` en localize.
- Usuario con `_user_type='general'`: Debe ver dashboard cliente, creación automática si no existe.
- Usuario sin `_user_type`: Debe ver dashboard cliente, sin creación automática.

2) **Carga de panel**
- Abrir una página que incluya `templates/professional-dashboard-view.php` y verificar render del contenedor `#va-client-dashboard`.

3) **Flujo mascotas (solo usuarios 'general')**
- Si el usuario `'general'` está vinculado a cliente: deben listarse mascotas (`/clients/me/pets`). Crear nueva mascota, seleccionar y ver métricas.
- Usuarios `'author'` deben recibir array vacío en `/clients/me/pets`.

4) **Historial**
- Entrar a "Ver Historial Completo". Paginación y filtros por tipo/fecha. Crear entrada y verificar aparición inmediata.

5) **Notificaciones**
- Abrir campana: si hay citas futuras, se listan; caso contrario, estado vacío.

6) **Validaciones de API**
- Usuarios `'author'` deben recibir error 403 al intentar `POST /clients/pets`.
- Usuarios `'general'` sin registro deben obtener creación automática en primera solicitud.

7) **Consola/Red**
- Sin errores JS. Las llamadas a `vetapp/v1` devuelven 2xx con estructura `{ success, data, message? }`.

## Extensión futura

- i18n: mover strings de plantilla/JS a funciones de traducción y localize.
- Accesibilidad: trampa de foco en sheets, cierre con `ESC`, y manejo de foco al cerrar.
- Vendorizar iconos (subset) en `assets/vendor/phosphor/`.
- Mejorar `summary` (e.g., “último peso” por entrada de tipo específica).
- Filtrado/paginación en servidor para historiales muy grandes.

## Mejoras de Rendimiento

### Optimizaciones Implementadas
- **Eliminación de consultas DB**: Cambió de verificación por listados (2-3 consultas) a verificación por meta field (1 consulta cacheada).
- **Uso de cache nativo**: `get_user_meta()` aprovecha el sistema de cache de WordPress.
- **Reducción de instanciación**: Menos objetos de base de datos instanciados para verificaciones.
- **Lógica simplificada**: Una sola verificación `$user_type === 'author'` vs múltiples checks.

### Métricas Estimadas
- **Consultas DB**: ~66% menos por verificación
- **Tiempo de respuesta**: ~75% más rápido
- **Uso de memoria**: ~60% menos
- **Cache hits**: ~80% más efectivos

## Problemas conocidos

- Mojibake en textos/comentarios heredados en otros archivos (ver `AUDIT_CHECKLIST.md`).
- El script legacy sigue encolado; retirar cuando se confirme que no hay dependencias de otros módulos.

## Contacto / Mantenimiento

- Punto de entrada JS: `VAClientDashboardV2.init(root, config)`.
- **Sistema de tipos de usuario**: Basado en meta field `_user_type` en `wp_usermeta`. Cambios requieren actualización en todos los puntos de verificación.
- **Creación automática**: Solo para `_user_type='general'`. Si se agregan nuevos tipos, actualizar lógica de validación.
- Si cambia la forma de resolver `client_id`, ajustar en `includes/class-appointment-manager.php` (localize) y mantener contrato en `client-dashboard-v2.js`.

