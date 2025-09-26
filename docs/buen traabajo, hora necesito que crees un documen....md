# **Documentación Técnica: Plantilla del Nuevo Dashboard de Mascotas (Proyecto Mandarina)**

## **1\. Resumen General**

Este documento describe la estructura y el funcionamiento de la plantilla new-professional-dashboard.php, diseñada para reemplazar el dashboard de clientes existente en el plugin "Veterinalia Appointment".

El objetivo de esta plantilla es ofrecer una interfaz de usuario moderna, reactiva y centrada en dispositivos móviles. Está construida como un componente de un solo archivo (Single-File Component) que contiene su propio HTML, CSS y JavaScript, facilitando así su integración en el entorno de WordPress a través de un shortcode.

**Tecnologías Utilizadas:**

* **HTML5:** Para la estructura semántica.  
* **CSS Puro (sin Tailwind):** Para el diseño visual, utilizando variables CSS (:root) para una fácil personalización.  
* **JavaScript (Vanilla JS):** Para toda la lógica de la interfaz, manipulación del DOM, gestión de estado y comunicación con el backend.

## **2\. Anatomía del Archivo de Plantilla**

El archivo new-professional-dashboard.php está dividido en tres secciones principales:

### **2.1. Estilos (\<style\>)**

* Contiene todo el CSS necesario para renderizar la interfaz.  
* No tiene dependencias externas (como Tailwind CSS).  
* Utiliza variables CSS para colores, sombras y radios de borde, lo que permite modificar el tema visual fácilmente desde un solo lugar.

### **2.2. Estructura HTML (\<body\>)**

El HTML se organiza en dos "pantallas" principales y una serie de "paneles modales" (Bottom Sheets) que se muestran bajo demanda.

* **Contenedor Principal (.dashboard-container):** Envuelve toda la aplicación para centrarla y darle un ancho máximo.  
* **Pantalla de Resumen (\#resumen-screen):** Es la vista principal que se muestra al cargar. Contiene el saludo, el selector de mascota, las métricas clave y los accesos rápidos.  
* **Pantalla de Historial (\#historial-screen):** Una vista secundaria que muestra la línea de tiempo del historial médico.  
* **Paneles Modales (Bottom Sheets):** Son \<div\> ocultos por defecto que se deslizan desde la parte inferior de la pantalla para realizar acciones específicas.

### **2.3. Lógica de la Interfaz (\<script\>)**

Esta sección es el "cerebro" de la plantilla. Un único script se encarga de:

* **Gestión de Estado:** Mantiene un registro de la mascota seleccionada, la página actual del historial, filtros activos, etc.  
* **Renderizado Dinámico:** Actualiza la interfaz con los datos correctos (ej., cambia el nombre de la mascota, renderiza la línea de tiempo).  
* **Manejo de Eventos:** Escucha los clics del usuario en botones, listas y enlaces para ejecutar las acciones correspondientes.  
* **Comunicación con el Backend:** (El objetivo final) Realizará llamadas fetch a un endpoint de la API REST de WordPress para obtener y enviar datos.

## **3\. Desglose de Componentes Clave (IDs y Clases)**

A continuación se detallan los IDs de los elementos HTML más importantes que el JavaScript manipula.

### **3.1. Pantalla de Resumen (\#resumen-screen)**

* \#user-name: \<span\> para mostrar el nombre del usuario.  
* \#notifications-button: Botón para abrir el panel de notificaciones.  
* \#add-pet-button: Botón para abrir el panel de añadir mascota.  
* \#pet-select-button: El botón principal para seleccionar una mascota.  
* \#current-pet-avatar, \#current-pet-name-btn, \#current-pet-species-btn: Elementos que muestran la información de la mascota activa.  
* \#next-appointment, \#last-visit, \#last-vaccine, \#current-weight: Párrafos (\<p\>) donde se deben inyectar los datos de las métricas.  
* \#quick-access-card: Enlace para navegar a la pantalla de historial.  
* \#add-history-quick-access: Enlace para abrir el panel de añadir nueva entrada al historial.

### **3.2. Pantalla de Historial (\#historial-screen)**

* \#back-to-resumen-button: Botón para volver a la pantalla de resumen.  
* \#date-filter-toggle y \#date-filter-popup: Elementos para el filtro de fechas.  
* \#filter-container: Contenedor para los botones de filtro por categoría (Consulta, Vacuna, etc.).  
* \#timeline-container: \<div\> donde el JavaScript renderiza la línea de tiempo del historial.  
* \#pagination-controls: Contenedor para los botones de "Anterior" y "Siguiente".

### **3.3. Paneles Modales (Bottom Sheets)**

* \#sheet-overlay: Fondo oscuro que se muestra detrás de cualquier panel abierto.  
* \#pet-select-sheet: Panel para mostrar la lista de mascotas (\#pet-list).  
* \#add-pet-sheet: Panel con pestañas para crear una mascota nueva (\#content-create-pet) o añadirla por código (\#content-add-by-code).  
* \#add-history-sheet: Panel con el formulario (\#add-history-form) para añadir una nueva entrada al historial médico.  
* \#notifications-sheet: Panel para mostrar la lista de notificaciones (\#notifications-list).

## **4\. El Motor JavaScript y el Flujo de Datos**

### **4.1. Objeto de Estado: appData**

El script se basa en un objeto JavaScript central llamado appData. **Este objeto es el contrato de datos entre el frontend y el backend.** El backend del plugin deberá generar un objeto JSON con esta estructura exacta para que la plantilla funcione.

**Estructura esperada:**

{  
  "pets": \[  
    {  
      "id": 1,  
      "name": "Luna",  
      "species": "Perro",  
      "icon": "ph-dog",  
      "nextAppointment": "22 Oct, 10:00",  
      "lastVisit": "08 Sep, 2025",  
      "lastVaccine": "Polivalente",  
      "weight": "25.6 kg"  
    }  
  \],  
  "shareablePets": {  
    "ROCKY123": {  
      "id": 4,  
      "name": "Rocky",  
      "species": "Perro"  
    }  
  },  
  "medicalHistory": {  
    "1": \[  
      {  
        "id": 1,  
        "date": "2025-09-08T10:15:00",  
        "type": "consultation",  
        "title": "Revisión por cojera",  
        "description": "Dueño reporta cojera...",  
        "professional": "Dr. Carlos Ruiz"  
      }  
    \]  
  }  
}

* pets: Un array de objetos, donde cada objeto es una mascota del usuario.  
* shareablePets: Un objeto donde las llaves son los códigos para compartir. Se usa para validar al "Añadir por Código".  
* medicalHistory: Un objeto donde cada llave es el id de una mascota, y su valor es un array de todas sus entradas de historial.

### **4.2. Funciones Principales**

* MapsTo(screenName): Cambia entre la pantalla de resumen y la de historial.  
* openSheet(sheetId): Muestra un panel modal (bottom sheet).  
* updateDashboard(): Rellena las métricas de la pantalla principal con los datos de la mascota seleccionada.  
* renderTimeline(): Filtra, pagina y renderiza el historial médico de la mascota seleccionada en \#timeline-container.  
* showToast(message): Muestra una notificación temporal en la parte inferior.

### **4.3. Flujo de Datos para la Implementación**

Actualmente, el objeto appData está codificado directamente en el script para fines de demostración. **El objetivo de la implementación es reemplazarlo.**

1. El JavaScript debe ser modificado para eliminar el objeto appData estático.  
2. En su lugar, debe realizar una llamada fetch() a un endpoint de la API REST de WordPress al cargar.  
3. Este endpoint de la API debe devolver un objeto JSON que coincida **exactamente** con la estructura de appData descrita anteriormente.  
4. El script entonces poblará la interfaz con los datos reales recibidos del backend.

## **5\. Guía de Implementación en el Plugin**

1. **Paso 1: Separar Archivos**  
   * Mover el contenido de la etiqueta \<style\> a un nuevo archivo CSS (ej: assets/css/new-dashboard.css).  
   * Mover el contenido de la etiqueta \<script\> a un nuevo archivo JS (ej: assets/js/new-dashboard.js).  
   * El archivo new-professional-dashboard.php solo debe contener la estructura HTML.  
2. **Paso 2: Crear el Endpoint de la API REST**  
   * En includes/class-rest-api-handler.php (o un archivo similar), registrar una nueva ruta (ej: GET /vetapp/v1/dashboard-data).  
   * La función de callback de esta ruta debe:  
     * Obtener el ID del profesional actual.  
     * Utilizar las clases Veterinalia\_CRM\_Database y Veterinalia\_Appointment\_Database para consultar la base de datos.  
     * Construir un array en PHP que coincida con la estructura de appData. Esto incluye realizar las consultas necesarias para calcular "Próxima Cita", "Última Visita", etc.  
     * Devolver este array como una WP\_REST\_Response.  
3. **Paso 3: Actualizar el Shortcode**  
   * En includes/class-appointment-shortcodes.php, modificar la función render\_professional\_dashboard.  
   * Esta función debe:  
     * Encolar el nuevo archivo CSS (wp\_enqueue\_style).  
     * Encolar el nuevo archivo JS (wp\_enqueue\_script).  
     * Usar wp\_localize\_script para pasar la URL del endpoint de la API y el nonce de seguridad al archivo JavaScript.  
     * Incluir (include) el archivo templates/new-professional-dashboard.php para renderizar el HTML.