<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Clase refactorizada para manejar la base de datos del plugin Veterinalia Appointment.
 * - Mantiene compatibilidad con la interfaz pública existente.
 * - Divide create_tables en métodos más pequeños.
 * - Usa siempre las propiedades $this->table_name_*
 * - Valida nombres de tabla con esc_sql() donde es necesario.
 * - Corrige consultas peligrosas y formatos de $wpdb->insert()/update().
 * - Añade recomendaciones para migraciones e índices.
 */
class Veterinalia_Appointment_Database {

    private static $instance = null;

    private $charset_collate;
    private $table_name_availability;
    private $table_name_appointments;
    private $table_name_categories;
    private $table_name_services;
    // (Las tablas del CRM han sido movidas a Veterinalia_CRM_Database)

    /**
     * Obtiene la única instancia de la clase.
     *
     * @return Veterinalia_Appointment_Database
     */
    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;

        // Definir nombres de tablas una sola vez
        $this->table_name_availability         = $prefix . 'va_professional_availability';
        $this->table_name_appointments         = $prefix . 'va_appointments';
        $this->table_name_categories           = $prefix . 'va_service_categories';
        $this->table_name_services             = $prefix . 'va_services';
        // (Las tablas del CRM se inicializan en Veterinalia_CRM_Database)
    }

    /**
     * Crea todas las tablas (llama a métodos más pequeños, idempotente gracias a dbDelta).
     */
    public function create_tables(): void {
        $this->create_table_availability();
        $this->create_table_categories();
        $this->create_table_services();
        $this->create_table_appointments();
    }

    private function create_table_availability(): void {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table = esc_sql( $this->table_name_availability );
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            professional_id BIGINT(20) NOT NULL,
            dia_semana_id BIGINT(20) NOT NULL,
            start_time TIME NOT NULL,
            end_time TIME NOT NULL,
            slot_duration INT(11) NOT NULL,
            is_available TINYINT(1) DEFAULT 1,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY professional_id (professional_id),
            KEY dia_semana_id (dia_semana_id)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    private function create_table_categories(): void {
        global $wpdb;
        $table = esc_sql( $this->table_name_categories );
        $sql = "CREATE TABLE {$table} (
            category_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            professional_id BIGINT(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            display_order INT DEFAULT 0,
            PRIMARY KEY (category_id),
            KEY professional_id (professional_id)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    private function create_table_services(): void {
        global $wpdb;
        $table = esc_sql( $this->table_name_services );
        $sql = "CREATE TABLE {$table} (
            service_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            category_id BIGINT(20) NOT NULL,
            professional_id BIGINT(20) NOT NULL,
            entry_type_id BIGINT(20) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            price DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            duration INT NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (service_id),
            KEY category_id (category_id),
            KEY professional_id (professional_id),
            KEY idx_entry_type_id (entry_type_id)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    // <-- INICIO DEL CAMBIO: Proyecto Chocovainilla - Paso 0.1 -->
    private function create_table_appointments(): void {
        global $wpdb;
        $table = esc_sql( $this->table_name_appointments );
        $sql = "CREATE TABLE {$table} (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            professional_id BIGINT(20) NOT NULL,
            client_id BIGINT(20) DEFAULT NULL,
            service_id BIGINT(20) NOT NULL,
            pet_id BIGINT(20) DEFAULT NULL,
            appointment_start DATETIME NOT NULL,
            appointment_end DATETIME NOT NULL,
            status VARCHAR(50) DEFAULT 'pending',
            price_at_booking DECIMAL(10,2) NOT NULL DEFAULT '0.00',
            client_name VARCHAR(255) DEFAULT NULL,
            client_email VARCHAR(255) DEFAULT NULL,
            pet_name VARCHAR(255) DEFAULT NULL,
            client_phone VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_professional_start (professional_id, appointment_start),
            KEY idx_service (service_id),
            KEY idx_pet_id (pet_id),
            KEY idx_professional_status_start (professional_id, status, appointment_start)
        ) {$this->charset_collate};";
        
        // dbDelta es seguro, solo aplicará los cambios si la columna no existe.
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Mensaje de depuración (silenciado durante activación)
        if (!defined('VA_PLUGIN_ACTIVATING') || !VA_PLUGIN_ACTIVATING) {
            error_log('[Chocovainilla] DB Check: La tabla va_appointments ahora incluye la columna pet_id.');
        }
    }
    // <-- FIN DEL CAMBIO: Proyecto Chocovainilla - Paso 0.1 -->

    public function drop_tables(): void {
        global $wpdb;
        $tables = [
            $this->table_name_services,
            $this->table_name_categories,
            $this->table_name_appointments,
            $this->table_name_availability,
        ];

        foreach ( $tables as $t ) {
            $wpdb->query( "DROP TABLE IF EXISTS " . esc_sql( $t ) );
        }
    }

    // ----------------- Métodos CRUD y utilitarios (manteniendo firmas públicas) -----------------

    public function delete_professional_availability( $professional_id ) {
        global $wpdb;
        return $wpdb->delete( $this->table_name_availability, [ 'professional_id' => intval( $professional_id ) ], [ '%d' ] );
    }

    public function insert_professional_availability( $professional_id, $dia_semana_id, $start_time, $end_time, $slot_duration ) {
        global $wpdb;
        $data = [
            'professional_id' => intval( $professional_id ),
            'dia_semana_id'   => intval( $dia_semana_id ),
            'start_time'      => $start_time,
            'end_time'        => $end_time,
            'slot_duration'   => intval( $slot_duration ),
        ];
        $format = [ '%d', '%d', '%s', '%s', '%d' ];
        $result = $wpdb->insert( $this->table_name_availability, $data, $format );
        return $result ? $wpdb->insert_id : false;
    }

    public function get_professional_availability( $professional_id ) {
        global $wpdb;
        $table_av = esc_sql( $this->table_name_availability );
        $table_ds = esc_sql( $wpdb->prefix . 'va_dias_semana' );

        $sql = $wpdb->prepare(
            "SELECT pa.*, ds.nombre_dia
             FROM {$table_av} AS pa
             LEFT JOIN {$table_ds} AS ds ON pa.dia_semana_id = ds.dia_id
             WHERE pa.professional_id = %d
             ORDER BY pa.dia_semana_id, pa.start_time ASC",
            intval( $professional_id )
        );

        return $wpdb->get_results( $sql );
    }

    public function insert_appointment( $appointment_data ) {
        global $wpdb;
        // Mantener compatibilidad: aceptar array asociativo con llaves que coincidan con columnas.
        $defaults = [
            'professional_id'  => 0,
            'client_id'        => null,
            'service_id'       => 0,
            'pet_id'           => null, // Añadido pet_id a los valores predeterminados
            'appointment_start'=> null,
            'appointment_end'  => null,
            'status'           => 'pending',
            'price_at_booking' => '0.00',
            'client_name'      => null,
            'client_email'     => null,
            'pet_name'         => null,
            'client_phone'     => null,
            'notes'            => null,
        ];

        $data = wp_parse_args( $appointment_data, $defaults );

        // Formatos: usar '%s' para decimales para mayor compatibilidad con $wpdb
        $format = [ '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]; // Añadido %d para pet_id

        $insert_data = [
            'professional_id'  => intval( $data['professional_id'] ),
            'client_id'        => $data['client_id'] !== null ? intval( $data['client_id'] ) : null,
            'service_id'       => intval( $data['service_id'] ),
            'pet_id'           => $data['pet_id'] !== null ? intval( $data['pet_id'] ) : null, // Incluir pet_id
            'appointment_start'=> $data['appointment_start'],
            'appointment_end'  => $data['appointment_end'],
            'status'           => sanitize_text_field( $data['status'] ),
            'price_at_booking' => number_format( floatval( $data['price_at_booking'] ), 2, '.', '' ),
            'client_name'      => $data['client_name'],
            'client_email'     => $data['client_email'],
            'pet_name'         => $data['pet_name'],
            'client_phone'     => $data['client_phone'],
            'notes'            => $data['notes'],
        ];

        $result = $wpdb->insert( $this->table_name_appointments, $insert_data, $format );
        return $result ? $wpdb->insert_id : false;
    }

    public function get_appointments_by_professional_id( $professional_id, $args = [] ) {
        global $wpdb;
        error_log('[Veterinalia DB] Iniciando get_appointments_by_professional_id para profesional: ' . $professional_id);
        
        $table_app = esc_sql( $this->table_name_appointments );
        $table_ser = esc_sql( $this->table_name_services );
        $table_clients = esc_sql( $wpdb->prefix . 'va_clients' ); // CORREGIDO
        $table_pets = esc_sql( $wpdb->prefix . 'va_pets' );       // CORREGIDO
        
        error_log('[Veterinalia DB] Tabla appointments: ' . $table_app);
        error_log('[Veterinalia DB] Tabla services: ' . $table_ser);
        error_log('[Veterinalia DB] Tabla clients: ' . $table_clients); // Añadido
        error_log('[Veterinalia DB] Tabla pets: ' . $table_pets); // Añadido

        // Lista blanca de columnas permitidas para ORDER BY
        $allowed_orderby = [ 'app.appointment_start', 'app.appointment_end', 'app.date_created', 'app.status', 'ser.name', 'c.name', 'p.name' ]; // Actualizado

        $sql = "SELECT app.*, ser.name AS service_name, ser.entry_type_id AS entry_type_id,
                       c.name AS client_name_actual, c.email AS client_email_actual,
                       p.name AS pet_name_actual, p.species AS pet_species_actual, p.breed AS pet_breed_actual
                FROM {$table_app} AS app
                LEFT JOIN {$table_ser} AS ser ON app.service_id = ser.service_id
                LEFT JOIN {$table_clients} AS c ON app.client_id = c.client_id  -- Añadido JOIN con clients
                LEFT JOIN {$table_pets} AS p ON app.pet_id = p.pet_id          -- Añadido JOIN con pets
                WHERE app.professional_id = %d";

        $params = [ intval( $professional_id ) ];

        if ( ! empty( $args['status'] ) ) {
            $sql .= " AND app.status = %s";
            $params[] = sanitize_text_field( $args['status'] );
            error_log('[Veterinalia DB] Filtro por status: ' . $args['status']);
        }

        $orderby = isset( $args['orderby'] ) ? $args['orderby'] : 'app.appointment_start';
        if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
            $orderby = 'app.appointment_start';
        }

        $order = isset( $args['order'] ) && in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true ) ? strtoupper( $args['order'] ) : 'ASC';

        $sql .= " ORDER BY {$orderby} {$order}";
        
        error_log('[Veterinalia DB] SQL construido: ' . $sql);
        error_log('[Veterinalia DB] Parámetros: ' . print_r($params, true));
        error_log('[Veterinalia DB] Orderby: ' . $orderby . ', Order: ' . $order);

        $prepared = $wpdb->prepare( $sql, $params );
        error_log( '[Veterinalia DB Query] SQL Final Ejecutado: ' . $prepared );
        
        $results = $wpdb->get_results( $prepared );
        error_log( '[Veterinalia DB Query] Resultados Crudos de la BD: ' . print_r( $results, true ) );
        
        if ($wpdb->last_error) {
            error_log('[Veterinalia DB Query] Error en consulta: ' . $wpdb->last_error);
        }
        
        error_log('[Veterinalia DB] Número de resultados: ' . count($results));
        
        return $results;
    }

    public function update_appointment_status( $appointment_id, $new_status ) {
        global $wpdb;
        $result = $wpdb->update(
            $this->table_name_appointments,
            [ 'status' => sanitize_text_field( $new_status ) ],
            [ 'id' => intval( $appointment_id ) ],
            [ '%s' ],
            [ '%d' ]
        );
        return false !== $result;
    }

    public function get_listings_by_author_id( $user_id ) {
        if ( empty( $user_id ) ) return [];

        $args = [
            'post_type'      => defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'listing',
            'author'         => intval( $user_id ),
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'all',
        ];

        return get_posts( $args );
    }

    public function get_appointment_at_time( $professional_id, $appointment_date, $appointment_time ) {
        global $wpdb;
        // Convertir tiempos
        $formatted_time = date( 'H:i:s', strtotime( $appointment_time ) );
        $table = esc_sql( $this->table_name_appointments );

        $sql = $wpdb->prepare(
            "SELECT id FROM {$table} WHERE professional_id = %d AND DATE(appointment_start) = %s AND TIME(appointment_start) = %s AND status IN ('pending','confirmed')",
            intval( $professional_id ),
            $appointment_date,
            $formatted_time
        );

        return $wpdb->get_row( $sql );
    }

    public function get_appointments_for_date( $professional_id, $date ) {
        global $wpdb;
        $table = esc_sql( $this->table_name_appointments );
        $start_of_day = $date . ' 00:00:00';
        $end_of_day   = $date . ' 23:59:59';

        $sql = $wpdb->prepare(
            "SELECT appointment_start, appointment_end FROM {$table} WHERE professional_id = %d AND appointment_start BETWEEN %s AND %s AND status IN ('pending', 'confirmed') ORDER BY appointment_start ASC",
            intval( $professional_id ),
            $start_of_day,
            $end_of_day
        );

        return $wpdb->get_results( $sql );
    }

    public function is_slot_already_booked( $professional_id, $start_time, $end_time ) {
        global $wpdb;
        $table = esc_sql( $this->table_name_appointments );

        $sql = $wpdb->prepare(
            "SELECT id FROM {$table} 
             WHERE professional_id = %d 
             AND status IN ('pending','confirmed')
             AND (%s < appointment_end) AND (%s > appointment_start) LIMIT 1",
            intval( $professional_id ),
            $start_time,
            $end_time
        );

        $res = $wpdb->get_var( $sql );
        return ! empty( $res );
    }

    // ----------------- Categorías y Servicios -----------------

    public function save_category( $category_data ) {
        global $wpdb;
        $defaults = [ 'professional_id' => 0, 'name' => '', 'description' => '', 'display_order' => 0 ];
        $data = wp_parse_args( $category_data, $defaults );

        if ( empty( $data['professional_id'] ) || empty( $data['name'] ) ) return false;

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT category_id FROM {$this->table_name_categories} WHERE professional_id = %d AND name = %s", intval( $data['professional_id'] ), $data['name'] ) );
        if ( $exists ) return false;

        $result = $wpdb->insert( $this->table_name_categories, [
            'professional_id' => intval( $data['professional_id'] ),
            'name' => sanitize_text_field( $data['name'] ),
            'description' => sanitize_textarea_field( $data['description'] ),
            'display_order' => intval( $data['display_order'] ),
        ], [ '%d', '%s', '%s', '%d' ] );

        // Invalidar cache de categorías cuando se crea una nueva
        if ($result) {
            $professional_id = intval( $data['professional_id'] );
            VA_Cache_Helper::invalidate(VA_Cache_Helper::categories_key($professional_id));
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[VA Cache] Invalidated categories cache for professional {$professional_id}");
            }
        }

        return $result ? $wpdb->insert_id : false;
    }

    public function get_categories_by_professional( $professional_id ) {
        global $wpdb;
        if ( empty( $professional_id ) ) return [];
        
        // Implementar cache para categorías por profesional
        $cache_key = VA_Cache_Helper::categories_key($professional_id);
        
        return VA_Cache_Helper::get_or_set($cache_key, function() use ($wpdb, $professional_id) {
            $sql = $wpdb->prepare( "SELECT * FROM {$this->table_name_categories} WHERE professional_id = %d ORDER BY display_order ASC, name ASC", intval( $professional_id ) );
            return $wpdb->get_results( $sql );
        }, VA_Cache_Helper::DEFAULT_EXPIRATION);
    }

    public function get_category_by_id( $category_id ) {
        global $wpdb;
        if ( empty( $category_id ) ) return null;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name_categories} WHERE category_id = %d", intval( $category_id ) ) );
    }

    public function save_service( $service_data ) {
        global $wpdb;
        $defaults = [ 'professional_id' => 0, 'category_id' => 0, 'name' => '', 'description' => '', 'price' => '0.00', 'duration' => 30, 'is_active' => 1, 'entry_type_id' => 0 ];
        $data = wp_parse_args( $service_data, $defaults );

        if ( empty( $data['professional_id'] ) || empty( $data['category_id'] ) || empty( $data['name'] ) ) return false;

        $exists = $wpdb->get_var( $wpdb->prepare( "SELECT service_id FROM {$this->table_name_services} WHERE category_id = %d AND name = %s", intval( $data['category_id'] ), $data['name'] ) );
        if ( $exists ) return false;

        $result = $wpdb->insert( $this->table_name_services, [
            'professional_id' => intval( $data['professional_id'] ),
            'category_id'     => intval( $data['category_id'] ),
            'name'            => sanitize_text_field( $data['name'] ),
            'description'     => sanitize_textarea_field( $data['description'] ),
            'price'           => number_format( floatval( $data['price'] ), 2, '.', '' ),
            'duration'        => intval( $data['duration'] ),
            'is_active'       => $data['is_active'] ? 1 : 0,
            'entry_type_id'   => intval( $data['entry_type_id'] ),
        ], [ '%d', '%d', '%s', '%s', '%s', '%d', '%d', '%d' ] );

        // Invalidar cache relacionado cuando se crea un nuevo servicio
        if ($result) {
            $professional_id = intval( $data['professional_id'] );
            $category_id = intval( $data['category_id'] );
            
            VA_Cache_Helper::invalidate(VA_Cache_Helper::services_key($professional_id, true));
            VA_Cache_Helper::invalidate('va_services_cat_' . $category_id);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[VA Cache] Invalidated services cache for professional {$professional_id} and category {$category_id}");
            }
        }

        return $result ? $wpdb->insert_id : false;
    }

    public function get_services_by_category( $category_id ) {
        global $wpdb;
        if ( empty( $category_id ) ) return [];
        
        // Implementar cache para servicios por categoría
        $cache_key = 'va_services_cat_' . $category_id;
        
        return VA_Cache_Helper::get_or_set($cache_key, function() use ($wpdb, $category_id) {
            $sql = $wpdb->prepare( "SELECT * FROM {$this->table_name_services} WHERE category_id = %d AND is_active = 1 ORDER BY name ASC", intval( $category_id ) );
            return $wpdb->get_results( $sql );
        }, VA_Cache_Helper::DEFAULT_EXPIRATION);
    }
    
    /**
     * Obtiene todos los servicios activos para un profesional específico.
     *
     * @param int $professional_id ID del profesional.
     * @return array Array de objetos de servicio.
     */
    public function get_services_by_professional( $professional_id ) {
        global $wpdb;
        if ( empty( $professional_id ) ) return [];
        
        // Implementar cache para servicios por profesional (ALTA PRIORIDAD)
        $cache_key = VA_Cache_Helper::services_key($professional_id, true);
        
        return VA_Cache_Helper::get_or_set($cache_key, function() use ($wpdb, $professional_id) {
            $sql = $wpdb->prepare( "SELECT * FROM {$this->table_name_services} WHERE professional_id = %d AND is_active = 1 ORDER BY name ASC", intval( $professional_id ) );
            error_log('[Veterinalia DB] Consulta get_services_by_professional: ' . $sql);
            
            $results = $wpdb->get_results( $sql );
            error_log('[Veterinalia DB] Resultados get_services_by_professional: ' . print_r($results, true));
            
            return $results;
        }, VA_Cache_Helper::DEFAULT_EXPIRATION);
    }

    public function get_service_by_id( $service_id ) {
        global $wpdb;
        if ( empty( $service_id ) ) return null;
        
        $query = $wpdb->prepare( "SELECT * FROM {$this->table_name_services} WHERE service_id = %d", intval( $service_id ) );
        error_log('[Veterinalia DB] Consulta get_service_by_id: ' . $query);
        
        $result = $wpdb->get_row( $query );
        error_log('[Veterinalia DB] Resultado get_service_by_id: ' . print_r($result, true));
        
        return $result;
    }

    public function get_category_by_name_and_professional( $professional_id, $category_name ) {
        global $wpdb;
        if ( empty( $professional_id ) || empty( $category_name ) ) return null;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_name_categories} WHERE professional_id = %d AND name = %s LIMIT 1", intval( $professional_id ), $category_name ) );
    }

    public function update_category( $category_id, $data ) {
        global $wpdb;
        if ( empty( $category_id ) || empty( $data ) ) return false;
        $allowed = [ 'name', 'description', 'display_order' ];
        $update = [];
        $format = [];
        foreach ( $data as $k => $v ) {
            if ( in_array( $k, $allowed, true ) ) {
                $update[ $k ] = $v;
                $format[] = $k === 'display_order' ? '%d' : '%s';
            }
        }
        if ( empty( $update ) ) return false;
        $res = $wpdb->update( $this->table_name_categories, $update, [ 'category_id' => intval( $category_id ) ], $format, [ '%d' ] );
        return false !== $res;
    }

    public function delete_category( $category_id ) {
        global $wpdb;
        if ( empty( $category_id ) ) return false;
        // Eliminar servicios asociados
        $wpdb->delete( $this->table_name_services, [ 'category_id' => intval( $category_id ) ], [ '%d' ] );
        $res = $wpdb->delete( $this->table_name_categories, [ 'category_id' => intval( $category_id ) ], [ '%d' ] );
        return false !== $res;
    }

    public function update_service( $service_id, $data ) {
        global $wpdb;
        if ( empty( $service_id ) || empty( $data ) ) return false;
        $allowed = [ 'name', 'description', 'price', 'duration', 'is_active', 'entry_type_id' ];
        $update = [];
        $format = [];
        foreach ( $data as $k => $v ) {
            if ( in_array( $k, $allowed, true ) ) {
                $update[ $k ] = in_array( $k, [ 'price' ], true ) ? number_format( floatval( $v ), 2, '.', '' ) : ( in_array( $k, [ 'duration', 'is_active' ], true ) ? intval( $v ) : sanitize_text_field( $v ) );
                if ( in_array( $k, [ 'price' ], true ) ) {
                    $format[] = '%s';
                } elseif ( in_array( $k, [ 'duration', 'is_active' ], true ) ) {
                    $format[] = '%d';
                } else {
                    $format[] = '%s';
                }
            }
        }
        if ( empty( $update ) ) return false;
        $res = $wpdb->update( $this->table_name_services, $update, [ 'service_id' => intval( $service_id ) ], $format, [ '%d' ] );
        return false !== $res;
    }

    public function delete_service( $service_id ) {
        global $wpdb;
        if ( empty( $service_id ) ) return false;
        $res = $wpdb->delete( $this->table_name_services, [ 'service_id' => intval( $service_id ) ], [ '%d' ] );
        return false !== $res;
    }

}

// Fin de clase


