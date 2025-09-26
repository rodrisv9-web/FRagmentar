

---

## **Documentación Técnica: Módulo de Historial Clínico Avanzado**

Versión: 1.0  
Autor: Asistente de Programación (Gemini)  
Proyecto: Veterinalia Appointment \- Plan 2

### **1\. Resumen General**

Este documento describe la arquitectura y el funcionamiento del sistema de historial clínico avanzado para el plugin "Veterinalia Appointment". La finalidad de esta implementación es permitir la captura de datos estructurados a través de **formularios dinámicos** que se presentan al profesional al completar una cita, y la gestión de un **catálogo de productos** personal para cada profesional.

La arquitectura está diseñada para ser modular, escalable y mantenible, separando la definición de los datos (base de datos), la lógica de negocio (API) y la presentación (frontend).

### **2\. Diagrama de Arquitectura**

Este diagrama ilustra cómo se relacionan los componentes principales del sistema:

Fragmento de código

graph TD  
    subgraph "Configuración (Profesional)"  
        A1\[Servicios\] \--\>|asigna 1| B(Tipo de Entrada);  
        A2\[Catálogo de Productos\] \--\>|usa| C(Formulario Dinámico);  
    end

    subgraph "Base de Datos"  
        B \-- define \--\> D\[Campos de Formulario\];  
        F\[Historial (Logs)\] \-- tiene muchos \--\> G\[Meta (Datos Formulario)\];  
        F \-- tiene muchos \--\> H\[Productos Usados\];  
        A2 \-- guarda en \--\> I\[Tabla de Productos\];  
    end

    subgraph "Flujo de Cita (Agenda)"  
        J\[Cita\] \-- tiene 1 \--\> A1;  
        K\[Click en "Completar Cita"\] \--\> L{Obtener Tipo de Entrada del Servicio};  
        L \--\> M\[API: Solicitar Campos\];  
        M \--\> N\[Renderizar Formulario Dinámico\];  
        N \-- guarda en \--\> F;  
    end

    subgraph "Consulta (Pacientes)"  
        O\[Ver Historial\] \--\> P\[API: Solicitar Historial Completo\];  
        P \--\> Q\[Mostrar Historial con Meta y Productos\];  
    end

    style B fill:\#EBF8FF,stroke:\#3182CE  
    style D fill:\#EBF8FF,stroke:\#3182CE  
    style I fill:\#EBF8FF,stroke:\#3182CE

---

### **3\. Esquema de la Base de Datos**

Se han añadido 5 nuevas tablas y se han modificado 3 tablas existentes.

#### **Nuevas Tablas**

* **wp\_va\_entry\_types**: Catálogo central de los tipos de entradas clínicas.  
  * entry\_type\_id (PK): ID único.  
  * name, slug, icon: Para la interfaz de usuario.  
* **wp\_va\_form\_fields**: Define la estructura de cada formulario.  
  * field\_id (PK): ID único del campo.  
  * entry\_type\_id (FK): Vincula el campo a un tipo de entrada.  
  * field\_label, field\_key, field\_type, is\_required: Propiedades del campo.  
* **wp\_va\_pet\_log\_meta**: Almacena los datos introducidos en los campos personalizados.  
  * meta\_id (PK): ID único.  
  * log\_id (FK): Vincula el dato a una entrada del historial.  
  * meta\_key, meta\_value: El dato guardado.  
* **wp\_va\_products**: Catálogo de productos personal de cada profesional.  
  * product\_id (PK): ID único.  
  * professional\_id (FK): A qué profesional pertenece el producto.  
  * product\_name, product\_type, etc.: Detalles del producto.  
* **wp\_va\_pet\_log\_products**: Tabla pivote que registra el uso de un producto en una consulta.  
  * log\_product\_id (PK): ID único.  
  * log\_id (FK): A qué entrada del historial pertenece.  
  * product\_id (FK): Qué producto se usó.  
  * lot\_number, expiration\_date: Datos contextuales de esa aplicación.

#### **Tablas Modificadas**

* **wp\_va\_services**: Se añadió entry\_type\_id para vincular cada servicio a un formulario.  
* **wp\_va\_template\_services**: Se añadió entry\_type\_id por la misma razón.  
* **wp\_va\_pet\_logs**: Se reemplazó la columna entry\_type por entry\_type\_id.

---

### **4\. Endpoints de la API REST (/wp-json/vetapp/v1/...)**

* **GET /entry-types**: Devuelve la lista de todos los tipos de entrada disponibles para poblar selectores.  
* **GET /forms/entry-type/{id}**: Devuelve la estructura (los campos) del formulario asociado a un entry\_type\_id.  
* **GET /products/professional/{id}**: Devuelve el catálogo de productos de un profesional específico.  
* **POST, PUT, DELETE /products/...**: Endpoints para que el profesional gestione (Cree, Actualice, Elimine) los productos de su catálogo.  
* **POST /pet-logs**: **Endpoint Modificado.** Ahora acepta un objeto meta (con los datos del formulario) y un array products (con los productos usados) en su cuerpo (body).  
* **GET /patients/pets/{id}/logs**: **Endpoint Modificado.** Ahora devuelve cada entrada del historial con dos nuevas propiedades anidadas: meta y products.

---

### **5\. Módulos Frontend Involucrados**

* **professional-services.js**: Modificado para incluir un selector de **"Tipo de Entrada"** en el modal de creación/edición de servicios. Es el punto de conexión clave.  
* **catalog-module.js (Nuevo):** Contiene toda la lógica para la nueva sección "Mi Catálogo", incluyendo la comunicación con la API para gestionar los productos.  
* **agenda-module.js**: Orquesta el flujo de "Completar Cita". Identifica el entry\_type del servicio, solicita el formulario a la API, lo construye dinámicamente en el modal y envía los datos completos para ser guardados.  
* **patients-module.js**: Modificado para leer las nuevas propiedades meta y products del historial y renderizarlas de forma clara y estructurada en la vista del historial de la mascota.  
* **api-client.js**: Actualizado con nuevos métodos para comunicarse con los endpoints de entry-types y products.

---

### **6\. Flujo de Trabajo Principal: "Completar Cita"**

1. **Configuración Previa:** Un profesional edita su servicio "Vacuna Anual" y le asigna el "Tipo de Entrada" \-\> **Vacunación**.  
2. **Acción:** En la agenda, el profesional hace clic en "Completar Cita" para una cita del servicio "Vacuna Anual".  
3. **Llamada a la API (Frontend):** El agenda-module.js identifica el entry\_type\_id del servicio y hace una llamada a GET /forms/entry-type/{id}.  
4. **Respuesta de la API (Backend):** La API consulta va\_form\_fields y devuelve un JSON con los campos definidos para "Vacunación" (ej: 'Biológico Aplicado' (selector de producto), 'Número de Lote' (texto)).  
5. **Renderizado (Frontend):** El agenda-module.js recibe el JSON y construye el formulario HTML dinámicamente dentro del modal. El selector de productos se puebla con los datos del catálogo del profesional.  
6. **Envío (Frontend):** El profesional llena el formulario y presiona "Guardar". El agenda-module.js recopila todos los datos y los envía a POST /pet-logs.  
7. **Guardado (Backend):** La API recibe la petición, guarda la entrada principal en va\_pet\_logs, los datos del formulario en va\_pet\_log\_meta, y los productos usados en va\_pet\_log\_products.  
8. **Visualización:** Cuando se consulta el historial desde el patients-module.js, la API devuelve toda esta información consolidada para ser mostrada al usuario.

### **7\. Mantenimiento y Actualizaciones Futuras**

* **Para añadir un nuevo formulario:**  
  1. Añade una nueva fila a la tabla va\_entry\_types.  
  2. Añade las filas correspondientes a va\_form\_fields, asociándolas con el nuevo entry\_type\_id.  
* **Para añadir un nuevo tipo de campo a los formularios:**  
  1. Añade la nueva opción al ENUM de la columna field\_type en va\_form\_fields.  
  2. Actualiza la función buildFormFromSchema en agenda-module.js para que sepa cómo renderizar este nuevo tipo de campo.