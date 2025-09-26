# Documentación Técnica: Sistema de Formularios Dinámicos y Catálogo

**Versión:** 1.0
**Fecha:** 13 de septiembre de 2025
**Proyecto:** Refactorización del Historial Clínico - Plan 2 (Fases 1, 2 y 4)

## 1. Introducción

Este documento detalla la arquitectura y la implementación del nuevo sistema de **Formularios Dinámicos para el Historial Clínico** y el módulo de **Catálogo de Productos**. El objetivo principal de esta funcionalidad es reemplazar el registro de historial estático por un sistema flexible y extensible, donde cada tipo de visita (consulta, vacunación, cirugía, etc.) presenta un formulario con campos personalizados.

La implementación abarca desde la creación de nuevas tablas en la base de datos hasta la integración completa en la interfaz del profesional, permitiendo una gestión de datos clínicos más rica y estructurada.

## 2. Arquitectura General

La solución se ha implementado siguiendo un flujo de datos desacoplado, utilizando la API REST de WordPress como intermediario entre el backend (lógica y base de datos) y el frontend (interfaz de usuario en JavaScript).

1.  **Backend (PHP - WordPress):**
    *   **Base de Datos:** Se ha extendido el esquema de la base de datos para albergar la definición de los tipos de entrada, la estructura de los formularios, los datos personalizados (metadata) y el catálogo de productos.
    *   **API REST:** Se han creado nuevos endpoints para exponer de forma segura los tipos de entrada, las estructuras de los formularios y la gestión del catálogo de productos.
    *   **Lógica de Negocio:** Se ha actualizado la lógica existente para manejar la creación y consulta de historiales clínicos enriquecidos.

2.  **Frontend (JavaScript Vanilla):**
    *   **Módulos de UI:** Se han modificado los módulos existentes (`Agenda`, `Servicios`, `Pacientes`) y se ha creado uno nuevo (`Catálogo`) para interactuar con los nuevos endpoints de la API.
    *   **Renderizado Dinámico:** La interfaz ahora construye los formularios en tiempo de ejecución basándose en la estructura devuelta por la API, permitiendo una gran flexibilidad sin necesidad de modificar el código del frontend para cambiar un formulario.

---

## 3. Implementación del Backend

### 3.1. Esquema de la Base de Datos

Se han añadido **cinco nuevas tablas** y se han modificado **tres tablas existentes**.

#### Nuevas Tablas:

-   `wp_va_entry_types`: Actúa como catálogo central para los tipos de entradas del historial (ej. Consulta, Vacunación).
    -   `entry_type_id`, `name`, `slug`, `icon`, `is_active`, etc.
-   `wp_va_form_fields`: Define la estructura de cada formulario, asociando campos a un `entry_type_id`.
    -   `field_id`, `entry_type_id`, `field_key`, `field_label`, `field_type` (`text`, `textarea`, `product_selector`, etc.), `is_required`.
-   `wp_va_pet_log_meta`: Almacena los datos introducidos en los formularios dinámicos. Es una tabla de metadatos clásica.
    -   `meta_id`, `log_id`, `meta_key`, `meta_value`.
-   `wp_va_products`: Catálogo de productos/inventario de cada profesional.
    -   `product_id`, `professional_id`, `product_name`, `product_type`, etc.
-   `wp_va_pet_log_products`: Tabla de relación que vincula un producto del catálogo a una entrada del historial.
    -   `log_product_id`, `log_id`, `product_id`, `lot_number`, `expiration_date`.

#### Tablas Modificadas:

-   `wp_va_services`: Se añadió `entry_type_id` para vincular cada servicio a un tipo de formulario.
-   `wp_va_template_services`: Se añadió `entry_type_id` para las plantillas.
-   `wp_va_pet_logs`: Se reemplazó el campo `entry_type` (VARCHAR) por `entry_type_id` (BIGINT) para normalizar los datos.

### 3.2. Clases PHP Modificadas

-   **`includes/class-crm-database.php`**:
    -   Se añadieron las definiciones y métodos de creación para las 5 nuevas tablas.
    -   Se implementó `maybe_populate_entry_types()` y `maybe_populate_form_fields()` para insertar los datos iniciales por defecto.
    -   Nuevos métodos públicos para la lógica de negocio:
        -   `get_entry_types()`: Obtiene los tipos de entrada activos.
        -   `get_form_fields_by_entry_type()`: Obtiene la estructura de un formulario.
        -   `get_products_by_professional()`, `save_product()`, `delete_product()`: CRUD para el catálogo de productos.
        -   `add_pet_log_meta()`, `add_pet_log_product()`: Guardan los datos enriquecidos.
        -   `get_pet_logs_full()`: Obtiene el historial completo, incluyendo metadatos y productos.

-   **`includes/class-rest-api-handler.php`**:
    -   Nuevos endpoints registrados:
        -   `GET /entry-types`: Devuelve la lista de tipos de entrada.
        -   `GET /forms/entry-type/{id}`: Devuelve la estructura de un formulario.
        -   `GET /products/professional/{id}`: Devuelve el catálogo de un profesional.
        -   `POST`, `PUT`, `DELETE /products/{id}`: Endpoints para el CRUD de productos.
    -   Métodos `handle_` correspondientes para cada nueva ruta.
    -   Se modificó `handle_create_pet_log()` para aceptar y procesar los arrays `meta` y `products`.
    -   Se actualizó `handle_get_pet_logs()` para que llame al nuevo método `get_pet_logs_full()`.

-   **`includes/class-ajax-handler.php` y `includes/class-appointment-database.php`**:
    -   Se modificaron `handle_save_service` y `handle_edit_service` para incluir el `entry_type_id` al guardar un servicio.
    -   Se actualizó el array `$allowed` en `update_service` para permitir la modificación de este nuevo campo.

---

## 4. Implementación del Frontend

### 4.1. Lógica de la Interfaz (JavaScript)

-   **`assets/js/api-client.js`**:
    -   Se añadió el método `getEntryTypes()` para facilitar la llamada al nuevo endpoint de la API REST.

-   **`assets/js/modules/professional-services.js`**:
    -   El modal de creación/edición de servicios ahora es `async`.
    -   Obtiene la lista de tipos de entrada (`entry_types`) y renderiza un campo `<select>` obligatorio, permitiendo al profesional asignar un tipo de formulario a cada servicio.
    -   La función `saveService` ahora envía el `entry_type_id` seleccionado.

-   **`assets/js/modules/catalog-module.js` (Nuevo)**:
    -   Controla toda la funcionalidad del nuevo módulo "Mi Catálogo".
    -   Gestiona el estado (lista de productos, filtros).
    -   Renderiza la parrilla de productos y maneja la búsqueda y el filtrado.
    -   Controla el modal para crear y editar productos, interactuando con los endpoints CRUD de la API.

-   **`assets/js/modules/agenda-module.js`**:
    -   **`openLogbookModal`**: Ahora es `async`. Realiza dos llamadas en paralelo (`Promise.all`) para obtener la estructura del formulario y el catálogo de productos.
    -   **`buildFormFromSchema`**: Nueva función que recibe la estructura del formulario y la lista de productos, y genera el HTML dinámicamente. Es el núcleo del renderizado dinámico.
    -   **`handleLogbookSubmit`**: Recolecta los datos de todos los campos (incluyendo los dinámicos, el producto seleccionado con su lote/caducidad y la próxima cita) y los empaqueta en los objetos `meta`, `products` y `next_appointment` para enviarlos a la API.

-   **`assets/js/modules/patients-module.js`**:
    -   **`viewPetHistory`**: Ahora es `async` y llama a la API, que ya devuelve los datos enriquecidos.
    -   **`renderFullPetHistory`**: Nueva función que recibe los historiales con los datos completos y renderiza un timeline detallado, mostrando los campos personalizados y los productos utilizados en cada entrada.

### 4.2. Plantillas y Estilos

-   **`templates/modules/catalog-module.php` (Nuevo)**: Estructura HTML del módulo de catálogo, incluyendo la parrilla de productos y el modal de edición.
-   **`assets/css/modules/catalog-module.css` (Nuevo)**: Estilos específicos para dar un aspecto profesional al nuevo módulo de catálogo.
-   **`templates/professional-dashboard-view.php`**: Se añadió una nueva "Acción Rápida" para dar acceso al módulo de catálogo.
-   **`includes/class-appointment-manager.php` y `assets/js/dashboard-controller.js`**: Se actualizaron para encolar y ejecutar el nuevo script `catalog-module.js` cuando corresponde.

## 5. Flujo de Datos (Caso de Uso Típico)

1.  **Configuración**: Un profesional va a "Administrar Servicios", crea un nuevo servicio (ej. "Vacunación Anual") y le asigna el "Tipo de Entrada" `Vacunación`.
2.  **Cita**: Un cliente agenda una cita para ese servicio.
3.  **Completar Cita**: El profesional, desde la "Agenda", marca la cita como "Completar".
4.  **Formulario Dinámico**: El `agenda-module.js` se activa.
    -   Detecta que el servicio "Vacunación Anual" tiene un `entry_type_id`.
    -   Llama a la API para obtener el formulario de "Vacunación" y el catálogo de productos.
    -   Renderiza un modal con campos como "Biológico Aplicado", "Número de Lote" y un selector de productos.
5.  **Registro**: El profesional rellena el formulario, selecciona la vacuna desde su catálogo y guarda.
6.  **Guardado**: `agenda-module.js` envía todos los datos a la API (`/pet-logs`), que los procesa y guarda en `wp_va_pet_log_meta` y `wp_va_pet_log_products`.
7.  **Consulta**: Más tarde, el profesional va a "Mis Pacientes", busca a la mascota y abre su historial.
8.  **Visualización**: `patients-module.js` llama a la API, que devuelve el historial completo. La función `renderFullPetHistory` muestra una entrada clara que dice: "Vacunación Anual", y debajo, los detalles: "Biológico Aplicado: Nobivac", "Producto Utilizado: Nobivac KC (Lote: 123)".

## 6. Conclusión

La implementación ha sido un éxito, transformando una funcionalidad estática en un sistema dinámico y escalable. El profesional ahora tiene un control sin precedentes sobre la información que registra, mejorando la calidad de los datos del historial clínico y abriendo la puerta a futuras funcionalidades como la gestión de inventario y recordatorios automáticos.
