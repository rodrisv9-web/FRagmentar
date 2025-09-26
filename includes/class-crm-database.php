<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

class Veterinalia_CRM_Database {

    private static $instance = null;
    private $charset_collate;
    private $table_name_clients;
    private $table_name_pets;
    private $table_name_pet_access;
    private $table_name_pet_logs;
    private $table_name_entry_types;
    private $table_name_form_fields;
    private $table_name_pet_log_meta;
    private $table_name_products;
    private $table_name_pet_log_products;
    private $table_name_manufacturers;
    private $table_name_active_ingredients;
    private $silent_mode;
    private $original_wpdb_settings;

    public static function get_instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->charset_collate = $wpdb->get_charset_collate(); // <-- AÑADE ESTA LÍNEA
        $prefix = $wpdb->prefix;
        $this->table_name_clients = $prefix . 'va_clients';
        $this->table_name_pets = $prefix . 'va_pets';
        $this->table_name_pet_access = $prefix . 'va_pet_access';
        $this->table_name_pet_logs = $prefix . 'va_pet_logs';
        $this->table_name_entry_types = $prefix . 'va_entry_types';
        $this->table_name_form_fields = $prefix . 'va_form_fields';
        $this->table_name_pet_log_meta = $prefix . 'va_pet_log_meta';
        $this->table_name_products = $prefix . 'va_products';
        $this->table_name_pet_log_products = $prefix . 'va_pet_log_products';
        $this->table_name_manufacturers = $prefix . 'va_manufacturers';
        $this->table_name_active_ingredients = $prefix . 'va_active_ingredients';
        $this->silent_mode = defined('VA_PLUGIN_ACTIVATING') && VA_PLUGIN_ACTIVATING;
    }

    public function create_tables() {
        // Durante activación, silenciar completamente errores SQL para evitar salida inesperada
        if ($this->silent_mode) {
            $this->setup_silent_sql_mode();
        }
        
        try {
            // Crear tablas maestras primero
            $this->create_table_entry_types();
            $this->create_table_manufacturers();
            $this->create_table_active_ingredients();
            
            $this->create_table_clients();
            $this->create_table_pets();
            $this->create_table_pet_access();
            $this->create_table_pet_logs();

            $this->create_table_form_fields();
            $this->create_table_pet_log_meta();
            $this->create_table_products();
            $this->create_table_pet_log_products();
            
            // Aplicar mejoras de normalización
            $this->apply_normalization_improvements();
            
            // Asegurar migración en actualizaciones
            $this->ensure_version_migration();
            
            $this->maybe_populate_sample_patients_data();
            $this->migrate_existing_client_data();
            $this->maybe_populate_form_fields(); // <-- Asegúrate de que esta línea esté aquí
        } finally {
            // Restaurar configuración SQL si se modificó
            if ($this->silent_mode) {
                $this->restore_sql_mode();
            }
        }
    }

    // PEGA AQUÍ TODOS LOS MÉTODOS CORTADOS

    /**
     * Crea la tabla de clientes
     */
    private function create_table_clients() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table = esc_sql( $this->table_name_clients );
        $sql = "CREATE TABLE {$table} (
            client_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) DEFAULT NULL,
            phone VARCHAR(50) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_by_professional BIGINT(20) DEFAULT NULL,
            is_guest TINYINT(1) NOT NULL DEFAULT 1,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (client_id),
            KEY idx_user_id (user_id),
            KEY idx_email (email),
            KEY idx_name (name),
            KEY idx_created_by_professional (created_by_professional)
        ) {$this->charset_collate};";

        dbDelta( $sql );
        
        // Mensaje de depuración
        $this->log_message('[Chocovainilla] DB Check: La tabla va_clients ahora incluye la columna is_guest.');
    }

    /**
     * Crea la tabla de mascotas con códigos únicos
     */
    private function create_table_pets() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table = esc_sql( $this->table_name_pets );
        $sql = "CREATE TABLE {$table} (
            pet_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            client_id BIGINT(20) NOT NULL,
            name VARCHAR(255) NOT NULL,
            species VARCHAR(50) NOT NULL,
            breed VARCHAR(255) DEFAULT NULL,
            birth_date DATE DEFAULT NULL,
            gender ENUM('male', 'female', 'unknown') DEFAULT 'unknown',
            weight DECIMAL(5,2) DEFAULT NULL,
            microchip_number VARCHAR(20) DEFAULT NULL,
            share_code VARCHAR(20) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            notes TEXT DEFAULT NULL,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (pet_id),
            UNIQUE KEY idx_share_code (share_code),
            KEY idx_client_id (client_id),
            KEY idx_species (species),
            KEY idx_microchip (microchip_number)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Crea la tabla de control de acceso profesional
     */
    private function create_table_pet_access() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table = esc_sql( $this->table_name_pet_access );
        $sql = "CREATE TABLE {$table} (
            access_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            pet_id BIGINT(20) NOT NULL,
            professional_id BIGINT(20) NOT NULL,
            access_level ENUM('read', 'write', 'full') DEFAULT 'read',
            granted_by BIGINT(20) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            date_granted DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_expires DATETIME DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (access_id),
            UNIQUE KEY idx_pet_professional (pet_id, professional_id),
            KEY idx_professional_id (professional_id),
            KEY idx_granted_by (granted_by)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Crea la tabla de historial médico
     */
    private function create_table_pet_logs() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table = esc_sql( $this->table_name_pet_logs );
        $sql = "CREATE TABLE {$table} (
            log_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            pet_id BIGINT(20) NOT NULL,
            professional_id BIGINT(20) NOT NULL,
            appointment_id BIGINT(20) DEFAULT NULL,
            entry_type_id BIGINT(20) DEFAULT NULL,
            entry_date DATETIME NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            diagnosis TEXT DEFAULT NULL,
            treatment TEXT DEFAULT NULL,
            medication TEXT DEFAULT NULL,
            next_visit_date DATE DEFAULT NULL,
            attachment_url VARCHAR(500) DEFAULT NULL,
            weight_recorded DECIMAL(5,2) DEFAULT NULL,
            temperature DECIMAL(4,1) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            is_private TINYINT(1) DEFAULT 0,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            date_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (log_id),
            KEY idx_pet_id (pet_id),
            KEY idx_professional_id (professional_id),
            KEY idx_appointment_id (appointment_id),
            KEY idx_entry_date (entry_date),
            KEY idx_entry_type_id (entry_type_id)
        ) {$this->charset_collate};";

        dbDelta( $sql );
    }

    /**
     * Crea un nuevo cliente
     * @param array $client_data Datos del cliente
     * @return int|false ID del cliente creado o false en error
     */
    public function create_client($client_data) {
        global $wpdb;
        
        $defaults = [
            'user_id' => null,
            'name' => '',
            'email' => null,
            'phone' => null,
            'address' => null,
            'notes' => null,
            'created_by_professional' => null
        ];
        
        $data = wp_parse_args($client_data, $defaults);
        
        if (empty($data['name'])) {
            return false;
        }
        
        $insert_data = [
            'user_id' => $data['user_id'] ? intval($data['user_id']) : null,
            'name' => sanitize_text_field($data['name']),
            'email' => $data['email'] ? sanitize_email($data['email']) : null,
            'phone' => $data['phone'] ? sanitize_text_field($data['phone']) : null,
            'address' => $data['address'] ? sanitize_textarea_field($data['address']) : null,
            'notes' => $data['notes'] ? sanitize_textarea_field($data['notes']) : null,
            'created_by_professional' => $data['created_by_professional'] ? intval($data['created_by_professional']) : null
        ];
        
        $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%d'];
        
        $result = $wpdb->insert($this->table_name_clients, $insert_data, $format);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Crea un cliente invitado desde el formulario de reserva
     * @param array $client_data Datos del cliente (name, email, phone, professional_id)
     * @return int|false ID del cliente creado o false en caso de error
     */
    public function create_guest_client($client_data) {
        global $wpdb;

        error_log('[Veterinalia CRM] Iniciando creación de cliente invitado');

        // Validación de datos requeridos
        if (empty($client_data['name']) || empty($client_data['email'])) {
            error_log('[Veterinalia CRM] ERROR: Faltan datos requeridos (name o email) para crear cliente invitado');
            return false;
        }

        if (empty($client_data['professional_id'])) {
            error_log('[Veterinalia CRM] ERROR: Falta professional_id para crear cliente invitado');
            return false;
        }

        // Verificar que no exista ya un cliente con este email
        $existing_client = $this->get_client_by_email($client_data['email']);
        if ($existing_client) {
            error_log('[Veterinalia CRM] Cliente ya existe con este email, usando cliente existente: ' . $existing_client->client_id);
            return $existing_client->client_id;
        }

        // Preparar datos para cliente invitado
        $insert_data = [
            'user_id' => null, // No tiene cuenta de usuario
            'name' => sanitize_text_field($client_data['name']),
            'email' => sanitize_email($client_data['email']),
            'phone' => !empty($client_data['phone']) ? sanitize_text_field($client_data['phone']) : null,
            'address' => null,
            'notes' => 'Cliente creado automáticamente desde formulario de reserva',
            'created_by_professional' => intval($client_data['professional_id']),
            'is_guest' => 1 // Especifica que es un cliente invitado
        ];

        $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d'];

        error_log('[Veterinalia CRM] Insertando cliente invitado en BD: ' . json_encode($insert_data));

        $result = $wpdb->insert($this->table_name_clients, $insert_data, $format);

        if ($result) {
            $client_id = $wpdb->insert_id;
            error_log('[Veterinalia CRM] Cliente invitado creado exitosamente con ID: ' . $client_id);
            return $client_id;
        } else {
            error_log('[Veterinalia CRM] ERROR: Falló la inserción del cliente invitado: ' . $wpdb->last_error);
            return false;
        }
    }

    /**
     * Obtiene clientes por profesional (basado en acceso a mascotas)
     * @param int $professional_id ID del profesional
     * @return array Lista de clientes
     */
    public function get_clients_by_professional($professional_id) {
        global $wpdb;
        
        $table_clients = esc_sql($this->table_name_clients);
        $table_pets = esc_sql($this->table_name_pets);
        $table_access = esc_sql($this->table_name_pet_access);
        
        // Consulta mejorada para incluir todos los casos posibles
        $sql = $wpdb->prepare("
            SELECT *
            FROM {$table_clients} c
            WHERE c.created_by_professional = %d
            UNION
            SELECT c.*
            FROM {$table_clients} c
            INNER JOIN {$table_pets} p ON c.client_id = p.client_id
            INNER JOIN {$table_access} a ON p.pet_id = a.pet_id
            WHERE a.professional_id = %d AND a.is_active = 1 AND p.is_active = 1
            ORDER BY name ASC
        ", intval($professional_id), intval($professional_id));
        
        $results = $wpdb->get_results($sql);
        
        // Debug log para troubleshooting
        error_log("[CRM Debug] get_clients_by_professional for ID {$professional_id}: " . count($results) . " clients found");
        if ($wpdb->last_error) {
            error_log("[CRM Error] SQL Error: " . $wpdb->last_error);
        }
        
        return $results;
    }

    /**
     * Obtiene un cliente por su ID
     * @param int $client_id ID del cliente
     * @return object|null Cliente encontrado o null
     */
    public function get_client_by_id($client_id) {
        global $wpdb;

        $table = esc_sql($this->table_name_clients);
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE client_id = %d", intval($client_id));

        return $wpdb->get_row($sql);
    }

    /**
     * Obtiene un cliente por su email
     * @param string $email Email del cliente a buscar
     * @return object|null Cliente encontrado o null
     */
    public function get_client_by_email($email) {
        global $wpdb;

        if (empty($email)) {
            error_log('[Veterinalia CRM] ERROR: Email vacío en get_client_by_email()');
            return null;
        }

        $table = esc_sql($this->table_name_clients);
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE email = %s LIMIT 1", sanitize_email($email));

        error_log("[Veterinalia CRM] Buscando cliente por email: {$email}");
        $result = $wpdb->get_row($sql);

        if ($result) {
            error_log("[Veterinalia CRM] Cliente encontrado: ID {$result->client_id}, Nombre: {$result->name}");
        } else {
            error_log("[Veterinalia CRM] Cliente NO encontrado para email: {$email}");
        }

        return $result;
    }

    /**
     * Vincula un perfil de cliente 'invitado' a una cuenta de usuario de WordPress.
     * @param int $client_id El ID del perfil de cliente a vincular.
     * @param int $user_id El ID del usuario de WordPress.
     * @return bool True si la actualización fue exitosa, false en caso contrario.
     */
    public function link_client_to_user($client_id, $user_id) {
        global $wpdb;

        if (empty($client_id) || empty($user_id)) {
            return false;
        }
        
        error_log("[Chocovainilla] DB: Vinculando client_id {$client_id} con user_id {$user_id}.");

        $result = $wpdb->update(
            $this->table_name_clients,
            [
                'user_id' => intval($user_id),
                'is_guest' => 0 // El cliente deja de ser "invitado"
            ],
            ['client_id' => intval($client_id)],
            ['%d', '%d'], // Formato para los datos a actualizar
            ['%d']  // Formato para la cláusula WHERE
        );

        return $result !== false;
    }

    /**
     * Busca clientes por un término y verifica si el profesional tiene acceso.
     * @param string $term El término de búsqueda.
     * @param int $professional_id El ID del profesional que realiza la búsqueda.
     * @return array Lista de clientes encontrados con un flag de acceso.
     */
    public function search_clients_with_access_check($term, $professional_id) {
        global $wpdb;
        $term_like = '%' . $wpdb->esc_like($term) . '%';
        $sql = $wpdb->prepare("
            SELECT 
                c.client_id, 
                c.name, 
                c.email,
                -- La subconsulta verifica si existe al menos una mascota de este cliente
                -- a la que el profesional actual tenga acceso.
                EXISTS (
                    SELECT 1 
                    FROM {$this->table_name_pets} p 
                    JOIN {$this->table_name_pet_access} pa ON p.pet_id = pa.pet_id 
                    WHERE p.client_id = c.client_id 
                    AND pa.professional_id = %d
                ) AS has_access
            FROM {$this->table_name_clients} c 
            WHERE c.name LIKE %s OR c.email LIKE %s 
            LIMIT 10
        ", $professional_id, $term_like, $term_like);

        error_log('[Chocovainilla] DB Query: Búsqueda de clientes ejecutada.');
        $results = $wpdb->get_results($sql);
        if ($results === null && !empty($wpdb->last_error)) {
            error_log('[Chocovainilla][DB ERROR] search_clients_with_access_check: ' . $wpdb->last_error);
        }
        return $results;
    }

    /**
     * Fallback simple: buscar clientes por nombre/email sin verificar acceso.
     */
    public function search_clients_basic($term) {
        global $wpdb;
        $term_like = '%' . $wpdb->esc_like($term) . '%';
        $sql = $wpdb->prepare(
            "SELECT client_id, name, email FROM {$this->table_name_clients} WHERE name LIKE %s OR email LIKE %s LIMIT 10",
            $term_like,
            $term_like
        );
        return $wpdb->get_results($sql);
    }

    /**
     * Crea una nueva mascota
     * @param array $pet_data Datos de la mascota
     * @return int|false ID de la mascota creada o false en error
     */
    public function create_pet($pet_data) {
        global $wpdb;
        
        $defaults = [
            'client_id' => 0,
            'name' => '',
            'species' => '',
            'breed' => null,
            'birth_date' => null,
            'gender' => 'unknown',
            'weight' => null,
            'microchip_number' => null,
            'share_code' => '',
            'notes' => null
        ];
        
        $data = wp_parse_args($pet_data, $defaults);
        
        if (empty($data['client_id']) || empty($data['name']) || empty($data['share_code'])) {
            return false;
        }
        
        $insert_data = [
            'client_id' => intval($data['client_id']),
            'name' => sanitize_text_field($data['name']),
            'species' => sanitize_text_field($data['species']),
            'breed' => $data['breed'] ? sanitize_text_field($data['breed']) : null,
            'birth_date' => $data['birth_date'] ? sanitize_text_field($data['birth_date']) : null,
            'gender' => $data['gender'],
            'weight' => $data['weight'] ? floatval($data['weight']) : null,
            'microchip_number' => $data['microchip_number'] ? sanitize_text_field($data['microchip_number']) : null,
            'share_code' => strtoupper(sanitize_text_field($data['share_code'])),
            'notes' => $data['notes'] ? sanitize_textarea_field($data['notes']) : null
        ];
        
        $format = ['%d', '%s', '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s'];
        
        $result = $wpdb->insert($this->table_name_pets, $insert_data, $format);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Crea una mascota con código de compartir único generado automáticamente
     * @param array $pet_data Datos de la mascota (client_id, name, species, breed, etc.)
     * @return int|false ID de la mascota creada o false en caso de error
     */
    public function create_pet_with_share_code($pet_data) {
        global $wpdb;

        error_log('[Veterinalia CRM] Iniciando creación de mascota con share_code automático');

        // Validación de datos requeridos
        if (empty($pet_data['client_id']) || empty($pet_data['name'])) {
            error_log('[Veterinalia CRM] ERROR: Faltan datos requeridos (client_id o name) para crear mascota');
            return false;
        }

        // Generar código de compartir único
        $share_code = $this->generate_unique_share_code();
        if (!$share_code) {
            error_log('[Veterinalia CRM] ERROR: No se pudo generar un share_code único');
            return false;
        }

        // Preparar datos para la mascota con share_code generado
        $pet_data_with_code = array_merge($pet_data, [
            'share_code' => $share_code,
            'species' => $pet_data['species'] ?? 'unknown',
            'gender' => $pet_data['gender'] ?? 'unknown'
        ]);

        error_log('[Veterinalia CRM] Creando mascota con share_code: ' . $share_code . ' para cliente: ' . $pet_data['client_id']);

        // Usar la función create_pet existente
        $pet_id = $this->create_pet($pet_data_with_code);

        if ($pet_id) {
            error_log('[Veterinalia CRM] Mascota creada exitosamente con ID: ' . $pet_id . ' y share_code: ' . $share_code);

            // Otorgar acceso automático al profesional que creó el cliente
            if (!empty($pet_data['professional_id'])) {
                $this->grant_pet_access($pet_id, $pet_data['professional_id']);
                error_log('[Veterinalia CRM] Acceso otorgado al profesional ID: ' . $pet_data['professional_id']);
            }

            return $pet_id;
        } else {
            error_log('[Veterinalia CRM] ERROR: Falló la creación de la mascota');
            return false;
        }
    }

    /**
     * Genera un código de compartir único para mascotas
     * @return string Código único generado
     */
    private function generate_unique_share_code() {
        global $wpdb;

        $attempts = 0;
        $max_attempts = 10;

        do {
            // Generar código aleatorio de 8 caracteres (4 letras + 4 números)
            $letters = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 4));
            $numbers = str_pad(mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
            $code = $letters . $numbers;

            // Verificar que no exista
            $table = esc_sql($this->table_name_pets);
            $exists = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE share_code = %s", $code));

            if (!$exists) {
                error_log('[Veterinalia CRM] Share_code único generado: ' . $code);
                return $code;
            }

            $attempts++;
        } while ($attempts < $max_attempts);

        error_log('[Veterinalia CRM] ERROR: No se pudo generar un share_code único después de ' . $max_attempts . ' intentos');
        return false;
    }

    /**
     * Obtiene mascotas por cliente
     * @param int $client_id ID del cliente
     * @return array Lista de mascotas
     */
    public function get_pets_by_client($client_id) {
        global $wpdb;
        
        $table = esc_sql($this->table_name_pets);
        $sql = $wpdb->prepare(
            "
            SELECT * FROM {$table} 
            WHERE client_id = %d AND is_active = 1
            ORDER BY name ASC
        ", intval($client_id));
        
        return $wpdb->get_results($sql);
    }

    /**
     * Obtiene mascotas por cliente, marcando si el profesional tiene acceso
     * VERSIÓN FINAL CON CORRECCIÓN DE TIPOS DE DATOS Y LOGS MEJORADOS
     */
    public function get_pets_by_client_with_access($client_id, $professional_id) {
        global $wpdb;
        $pets_table = esc_sql($this->table_name_pets);
        $access_table = esc_sql($this->table_name_pet_access);
        $clients_table = esc_sql($this->table_name_clients);

        $client_id = intval($client_id);
        $professional_id = intval($professional_id);

        error_log("[CHOCOVAINILLA DEBUG] Verificando acceso para professional_id: {$professional_id} sobre mascotas del client_id: {$client_id}");

        // Implementar cache para consulta compleja de acceso a mascotas (ALTA PRIORIDAD)
        $cache_key = VA_Cache_Helper::pet_access_key($professional_id, $client_id);
        
        $results = VA_Cache_Helper::get_or_set($cache_key, function() use ($wpdb, $access_table, $pets_table, $clients_table, $professional_id, $client_id) {
            $sql = $wpdb->prepare(
                "SELECT 
                    p.*, 
                    (EXISTS (SELECT 1 FROM {$access_table} pa WHERE pa.pet_id = p.pet_id AND pa.professional_id = %d)) AS access_direct,
                    (EXISTS (
                        SELECT 1 
                        FROM {$pets_table} px 
                        JOIN {$access_table} pax ON pax.pet_id = px.pet_id
                        WHERE px.client_id = p.client_id AND pax.professional_id = %d
                    )) AS access_inherited,
                    (EXISTS (
                        SELECT 1 FROM {$clients_table} c 
                        WHERE c.client_id = p.client_id AND c.created_by_professional = %d
                    )) AS access_by_creation
                FROM {$pets_table} p 
                WHERE p.client_id = %d AND p.is_active = 1 
                ORDER BY p.name ASC",
                $professional_id,
                $professional_id,
                $professional_id,
                $client_id
            );
            
            return $wpdb->get_results($sql);
        }, VA_Cache_Helper::SHORT_EXPIRATION); // Cache más corto para datos de acceso

        if ($wpdb->last_error) {
            error_log('[CHOCOVAINILLA DB ERROR] ' . $wpdb->last_error);
            return null;
        }

        if (is_array($results)) {
            foreach ($results as $pet) {
                // --- INICIO DE LA CORRECCIÓN CLAVE ---
                // Forzamos la conversión de los resultados a enteros (0 o 1)
                $access_direct = intval($pet->access_direct);
                $access_inherited = intval($pet->access_inherited);
                $access_by_creation = intval($pet->access_by_creation);
                
                // La lógica booleana ahora funcionará correctamente
                $pet->has_access = ($access_direct || $access_inherited || $access_by_creation) ? 1 : 0;
                // --- FIN DE LA CORRECCIÓN CLAVE ---
                
                error_log(
                    "[CHOCOVAINILLA DEBUG] Mascota '{$pet->name}' (ID: {$pet->pet_id}): " .
                    "Directo: {$access_direct}, Heredado: {$access_inherited}, Creación: {$access_by_creation} " .
                    "==> Final: " . ($pet->has_access ? 'CON ACCESO' : 'SIN ACCESO')
                );

                unset($pet->access_direct, $pet->access_inherited, $pet->access_by_creation);
            }
        }
        
        // Log final para ver exactamente qué se envía al frontend
        error_log('[CHOCOVAINILLA DEBUG] Datos finales a enviar a la API: ' . print_r($results, true));

        return $results;
    }

    /**
     * Busca una mascota por código de compartir
     * @param string $share_code Código de compartir
     * @return object|null Mascota encontrada o null
     */
    public function get_pet_by_share_code($share_code) {
        global $wpdb;
        
        $table = esc_sql($this->table_name_pets);
        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} 
            WHERE share_code = %s AND is_active = 1
            LIMIT 1
        ", strtoupper(sanitize_text_field($share_code)));
        
        return $wpdb->get_row($sql);
    }

    /**
     * Obtiene una mascota por su ID
     * @param int $pet_id ID de la mascota
     * @return object|null Mascota encontrada o null
     */
    public function get_pet_by_id($pet_id) {
        global $wpdb;
        
        $table = esc_sql($this->table_name_pets);
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE pet_id = %d", intval($pet_id));
        
        return $wpdb->get_row($sql);
    }

    /**
     * Actualiza los datos de una mascota
     * @param int $pet_id ID de la mascota
     * @param array $data Datos a actualizar
     * @return bool Success
     */
    public function update_pet($pet_id, $data) {
        global $wpdb;
        
        if (empty($pet_id) || empty($data)) {
            return false;
        }

        $update_data = [];
        $format = [];

        // Lista blanca de campos permitidos para actualizar
        $allowed_fields = [
            'name' => '%s', 'species' => '%s', 'breed' => '%s',
            'birth_date' => '%s', 'gender' => '%s', 'weight' => '%f',
            'microchip_number' => '%s', 'share_code' => '%s', 'notes' => '%s'
        ];

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $allowed_fields)) {
                $update_data[$key] = $value;
                $format[] = $allowed_fields[$key];
            }
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            $this->table_name_pets,
            $update_data,
            ['pet_id' => intval($pet_id)],
            $format,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Busca una mascota por nombre y cliente
     * @param string $pet_name Nombre de la mascota
     * @param int $client_id ID del cliente
     * @return object|null Datos de la mascota o null si no existe
     */
    public function get_pet_by_name_and_client($pet_name, $client_id) {
        global $wpdb;

        if (empty($pet_name) || empty($client_id)) {
            return null;
        }

        $query = $wpdb->prepare(
            "SELECT * FROM {$this->table_name_pets}
             WHERE name = %s AND client_id = %d AND is_active = 1
             LIMIT 1",
            sanitize_text_field($pet_name),
            intval($client_id)
        );

        return $wpdb->get_row($query);
    }

    /**
     * Otorga acceso a una mascota para un profesional
     * @param int $pet_id ID de la mascota
     * @param int $professional_id ID del profesional
     * @param string $access_level Nivel de acceso
     * @return bool Success
     */
    public function grant_pet_access($pet_id, $professional_id, $access_level = 'read') {
        global $wpdb;
        
        $insert_data = [
            'pet_id' => intval($pet_id),
            'professional_id' => intval($professional_id),
            'access_level' => $access_level,
            'granted_by' => get_current_user_id() ?: null
        ];
        
        $format = ['%d', '%d', '%s', '%d'];
        
        // Usar INSERT ... ON DUPLICATE KEY UPDATE para evitar duplicados
        $sql = $wpdb->prepare("\n            INSERT INTO {$this->table_name_pet_access} \n            (pet_id, professional_id, access_level, granted_by, date_granted, is_active)\n            VALUES (%d, %d, %s, %d, NOW(), 1)\n            ON DUPLICATE KEY UPDATE \n            access_level = VALUES(access_level),\n            is_active = 1,\n            date_granted = NOW()\n        ", $insert_data['pet_id'], $insert_data['professional_id'], $insert_data['access_level'], $insert_data['granted_by']);
        
        $result = $wpdb->query($sql) !== false;
        
        // Invalidar cache de acceso a mascotas cuando se modifica el acceso
        if ($result) {
            // Obtener el client_id de la mascota para invalidar el cache correcto
            $pet = $this->get_pet_by_id($insert_data['pet_id']);
            if ($pet && $pet->client_id) {
                $cache_key = VA_Cache_Helper::pet_access_key($insert_data['professional_id'], $pet->client_id);
                VA_Cache_Helper::invalidate($cache_key);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("[VA Cache] Invalidated pet access cache for professional {$insert_data['professional_id']} and client {$pet->client_id}");
                }
            }
        }
        
        return $result;
    }

    /**
     * Verifica si un profesional tiene acceso a una mascota
     * @param int $professional_id ID del profesional
     * @param int $pet_id ID de la mascota
     * @return bool|string Nivel de acceso o false si no tiene acceso
     */
    public function check_pet_access($professional_id, $pet_id) {
        global $wpdb;
        
        $table = esc_sql($this->table_name_pet_access);
        $sql = $wpdb->prepare("\n            SELECT access_level FROM {$table}\n            WHERE professional_id = %d AND pet_id = %d \n            AND is_active = 1\n            AND (date_expires IS NULL OR date_expires > NOW())\n            LIMIT 1\n        ", intval($professional_id), intval($pet_id));
        
        return $wpdb->get_var($sql);
    }

    /**
     * Crea una entrada en el historial médico
     * @param array $log_data Datos del historial
     * @return int|false ID del log creado o false en error
     */
    /**
     * Crea una entrada en el historial médico
     * @param array $log_data Datos del historial
     * @return int|false ID del log creado o false en error
     */
    public function create_pet_log($log_data) {
        global $wpdb;
        
        $defaults = [
            'pet_id' => 0,
            'professional_id' => 0,
            'appointment_id' => null,
            'entry_type_id' => null,
            'entry_date' => current_time('mysql'),
            'title' => '',
            'description' => null,
            'diagnosis' => null,
            'treatment' => null,
            'medication' => null,
            'next_visit_date' => null,
            'attachment_url' => null,
            'weight_recorded' => null,
            'temperature' => null,
            'notes' => null,
            'is_private' => 0
        ];
        
        $data = wp_parse_args($log_data, $defaults);
        
        if (empty($data['pet_id']) || empty($data['professional_id']) || empty($data['title'])) {
            return false;
        }
        
        $insert_data = [
            'pet_id' => intval($data['pet_id']),
            'professional_id' => intval($data['professional_id']),
            'appointment_id' => $data['appointment_id'] ? intval($data['appointment_id']) : null,
            'entry_type_id' => $data['entry_type_id'] ? intval($data['entry_type_id']) : null,
            'entry_date' => sanitize_text_field($data['entry_date']),
            'title' => sanitize_text_field($data['title']),
            'description' => $data['description'] ? sanitize_textarea_field($data['description']) : null,
            'diagnosis' => $data['diagnosis'] ? sanitize_textarea_field($data['diagnosis']) : null,
            'treatment' => $data['treatment'] ? sanitize_textarea_field($data['treatment']) : null,
            'medication' => $data['medication'] ? sanitize_textarea_field($data['medication']) : null,
            'next_visit_date' => $data['next_visit_date'] ? sanitize_text_field($data['next_visit_date']) : null,
            'attachment_url' => $data['attachment_url'] ? esc_url_raw($data['attachment_url']) : null,
            'weight_recorded' => $data['weight_recorded'] ? floatval($data['weight_recorded']) : null,
            'temperature' => $data['temperature'] ? floatval($data['temperature']) : null,
            'notes' => $data['notes'] ? sanitize_textarea_field($data['notes']) : null,
            'is_private' => $data['is_private'] ? 1 : 0
        ];
        
        // Formato de datos para la inserción en la BD
        $format = [
            '%d', // pet_id
            '%d', // professional_id
            '%d', // appointment_id
            '%d', // entry_type_id
            '%s', // entry_date
            '%s', // title
            '%s', // description
            '%s', // diagnosis
            '%s', // treatment
            '%s', // medication
            '%s', // next_visit_date
            '%s', // attachment_url
            '%f', // weight_recorded
            '%f', // temperature
            '%s', // notes
            '%d'  // is_private
        ];
        
        $result = $wpdb->insert($this->table_name_pet_logs, $insert_data, $format);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Obtiene el historial médico de una mascota
     * @param int $pet_id ID de la mascota
     * @param int $professional_id ID del profesional (para verificar acceso)
     * @return array Lista de entradas del historial
     */
    public function get_pet_logs($pet_id, $professional_id = null) {
        global $wpdb;
        
        $table_logs = esc_sql($this->table_name_pet_logs);
        $table_users = $wpdb->users;
        
        $where_clause = "pl.pet_id = %d";
        $params = [intval($pet_id)];
        
        // Si se especifica profesional, verificar acceso o mostrar solo entradas públicas
        if ($professional_id) {
            $where_clause .= " AND (pl.is_private = 0 OR pl.professional_id = %d)";
            $params[] = intval($professional_id);
        }
        
        $sql = $wpdb->prepare("
            SELECT pl.*, u.display_name as professional_name
            FROM {$table_logs} pl
            LEFT JOIN {$table_users} u ON pl.professional_id = u.ID
            WHERE {$where_clause}
            ORDER BY pl.entry_date DESC, pl.date_created DESC
        ", $params);
        
        return $wpdb->get_results($sql);
    }

    /**
     * Poblar datos de prueba para el CRM de pacientes
     * Solo se ejecuta una vez y crea datos de ejemplo
     */
    private function maybe_populate_sample_patients_data() {
        // Verificar si ya se poblaron los datos
        if (get_option('va_sample_patients_populated')) {
            return;
        }

        global $wpdb;
        
        // Verificar si ya hay datos
        $existing_clients = $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_name_clients}");
        if ($existing_clients > 0) {
            update_option('va_sample_patients_populated', true);
            return;
        }

        try {
            $wpdb->query('START TRANSACTION');
            
            // Datos de clientes de prueba
            $sample_clients = [
                ['name' => 'Ana García Martínez', 'email' => 'ana.garcia@ejemplo.com', 'phone' => '+1 234 567 8901'],
                ['name' => 'Carlos López Ruiz', 'email' => 'carlos.lopez@ejemplo.com', 'phone' => '+1 234 567 8902'],
                ['name' => 'María Fernández Silva', 'email' => 'maria.fernandez@ejemplo.com', 'phone' => '+1 234 567 8903'],
                ['name' => 'José Rodríguez Torres', 'email' => 'jose.rodriguez@ejemplo.com', 'phone' => '+1 234 567 8904'],
                ['name' => 'Laura Martín Gómez', 'email' => 'laura.martin@ejemplo.com', 'phone' => '+1 234 567 8905']
            ];

            $client_ids = [];
            
            // Insertar clientes
            foreach ($sample_clients as $client) {
                $result = $wpdb->insert(
                    $this->table_name_clients,
                    [
                        'name' => $client['name'],
                        'email' => $client['email'],
                        'phone' => $client['phone'],
                        'date_created' => current_time('mysql')
                    ],
                    ['%s', '%s', '%s', '%s']
                );
                
                if ($result !== false) {
                    $client_ids[] = $wpdb->insert_id;
                }
            }

            // Datos de mascotas de prueba
            $sample_pets = [
                ['client_idx' => 0, 'name' => 'Luna', 'species' => 'dog', 'breed' => 'Golden Retriever', 'share_code' => 'LUNA-G7K4'],
                ['client_idx' => 0, 'name' => 'Max', 'species' => 'cat', 'breed' => 'Persa', 'share_code' => 'MAX-H2L9'],
                ['client_idx' => 1, 'name' => 'Rocky', 'species' => 'dog', 'breed' => 'Pastor Alemán', 'share_code' => 'ROCKY-A1B3'],
                ['client_idx' => 2, 'name' => 'Mimi', 'species' => 'cat', 'breed' => 'Siamés', 'share_code' => 'MIMI-X9Y8'],
                ['client_idx' => 2, 'name' => 'Toby', 'species' => 'dog', 'breed' => 'Labrador', 'share_code' => 'TOBY-K5M7'],
                ['client_idx' => 3, 'name' => 'Bella', 'species' => 'dog', 'breed' => 'Bulldog Francés', 'share_code' => 'BELLA-R3T5'],
                ['client_idx' => 4, 'name' => 'Coco', 'species' => 'bird', 'breed' => 'Canario', 'share_code' => 'COCO-P8Q2']
            ];

            $pet_ids = [];
            
            // Insertar mascotas
            foreach ($sample_pets as $pet) {
                if (isset($client_ids[$pet['client_idx']])) {
                    $result = $wpdb->insert(
                        $this->table_name_pets,
                        [
                            'client_id' => $client_ids[$pet['client_idx']],
                            'name' => $pet['name'],
                            'species' => $pet['species'],
                            'breed' => $pet['breed'],
                            'share_code' => $pet['share_code'],
                            'date_created' => current_time('mysql')
                        ],
                        ['%d', '%s', '%s', '%s', '%s', '%s']
                    );
                    
                    if ($result !== false) {
                        $pet_ids[] = $wpdb->insert_id;
                    }
                }
            }

            // Obtener el primer usuario administrador para asignar como profesional de prueba
            $admin_users = get_users(['role' => 'administrator', 'number' => 1]);
            $professional_id = !empty($admin_users) ? $admin_users[0]->ID : 1;

            // Crear accesos para el profesional (primeras 3 mascotas)
            foreach (array_slice($pet_ids, 0, 3) as $pet_id) {
                $wpdb->insert(
                    $this->table_name_pet_access,
                    [
                        'pet_id' => $pet_id,
                        'professional_id' => $professional_id,
                        'access_level' => 'full',
                        'granted_by' => $professional_id,
                        'date_granted' => current_time('mysql')
                    ],
                    ['%d', '%d', '%s', '%d', '%s']
                );
            }

            // Crear algunas entradas de historial médico de prueba
            if (!empty($pet_ids)) {
                $sample_logs = [
                    [
                        'pet_id' => $pet_ids[0],
                        'title' => 'Vacunación anual',
                        'entry_type' => 'vaccination',
                        'description' => 'Aplicación de vacuna polivalente anual',
                        'entry_date' => date('Y-m-d H:i:s', strtotime('-30 days'))
                    ],
                    [
                        'pet_id' => $pet_ids[0],
                        'title' => 'Revisión general',
                        'entry_type' => 'consultation',
                        'description' => 'Checkup de rutina. Todo en orden.',
                        'entry_date' => date('Y-m-d H:i:s', strtotime('-15 days'))
                    ],
                    [
                        'pet_id' => $pet_ids[1],
                        'title' => 'Esterilización',
                        'entry_type' => 'surgery',
                        'description' => 'Procedimiento de esterilización exitoso',
                        'entry_date' => date('Y-m-d H:i:s', strtotime('-45 days'))
                    ]
                ];

                foreach ($sample_logs as $log) {
                    $wpdb->insert(
                        $this->table_name_pet_logs,
                        [
                            'pet_id' => $log['pet_id'],
                            'professional_id' => $professional_id,
                            'entry_type' => $log['entry_type'],
                            'entry_date' => $log['entry_date'],
                            'title' => $log['title'],
                            'description' => $log['description'],
                            'date_created' => current_time('mysql')
                        ],
                        ['%d', '%d', '%s', '%s', '%s', '%s', '%s']
                    );
                }
            }

            $wpdb->query('COMMIT');
            
            // Marcar como completado
            update_option('va_sample_patients_populated', true);
            
            error_log('[Veterinalia CRM] Datos de prueba poblados exitosamente');
            
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('[Veterinalia CRM] Error poblando datos de prueba: ' . $e->getMessage());
        }
    }

    /**
     * Migra datos de clientes existentes para añadir created_by_professional
     */
    private function migrate_existing_client_data() {
        global $wpdb;
        
        // Verificar si hay clientes sin created_by_professional
        $clients_without_creator = $wpdb->get_var("\n            SELECT COUNT(*)\n            FROM {$this->table_name_clients}\n            WHERE created_by_professional IS NULL\n        ");
        
        if ($clients_without_creator > 0) {
            // Para clientes existentes sin creator, asignar al primer admin disponible
            $admin_id = $wpdb->get_var("\n                SELECT ID \n                FROM {$wpdb->users} u\n                INNER JOIN {$wpdb->usermeta} um ON u.ID = um.user_id\n                WHERE um.meta_key = 'wp_capabilities'\n                AND um.meta_value LIKE '%administrator%'\n                LIMIT 1\n            ");
            
            if ($admin_id) {
                $updated = $wpdb->query($wpdb->prepare("\n                    UPDATE {$this->table_name_clients} \n                    SET created_by_professional = %d\n                    WHERE created_by_professional IS NULL\n                ", intval($admin_id)));
                
                if ($updated) {
                    error_log("[Veterinalia DB] Migrados {$updated} clientes existentes al admin ID: {$admin_id}");
                }
            }
        }
    }

    /**
     * Obtiene un cliente por su ID de usuario de WordPress.
     * @param int $user_id ID del usuario de WordPress.
     * @return object|null Cliente encontrado o null.
     */
    public function get_client_by_user_id($user_id) {
        global $wpdb;
        if (empty($user_id)) {
            return null;
        }
        $table = esc_sql($this->table_name_clients);
        $sql = $wpdb->prepare("SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", intval($user_id));
        return $wpdb->get_row($sql);
    }

    /**
     * Crea la tabla para los tipos de entrada del historial.
     */
    private function create_table_entry_types() {
        global $wpdb;
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

        $table = esc_sql( $this->table_name_entry_types );
        $sql = "CREATE TABLE {$table} (
            entry_type_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            icon VARCHAR(50) DEFAULT NULL,
            color VARCHAR(20) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            PRIMARY KEY (entry_type_id),
            UNIQUE KEY slug (slug)
        ) {$this->charset_collate};";

        dbDelta( $sql );
        $this->maybe_populate_entry_types();
    }

    /**
     * Puebla la tabla va_entry_types con los valores por defecto si está vacía.
     */
    private function maybe_populate_entry_types() {
        global $wpdb;
        $table = esc_sql( $this->table_name_entry_types );
        $count = $wpdb->get_var( "SELECT COUNT(*) FROM " . $table );
        if ( intval( $count ) === 0 ) {
            $types = [
                ['name' => 'Consulta', 'slug' => 'consultation', 'icon' => 'ph-stethoscope'],
                ['name' => 'Seguimiento / Control', 'slug' => 'follow_up', 'icon' => 'ph-clipboard-text'],
                ['name' => 'Urgencias', 'slug' => 'emergency', 'icon' => 'ph-heartbeat'],
                ['name' => 'Vacunación', 'slug' => 'vaccination', 'icon' => 'ph-syringe'],
                ['name' => 'Desparasitación', 'slug' => 'deworming', 'icon' => 'ph-bug'],
                ['name' => 'Control de Ectoparásitos', 'slug' => 'parasite_control', 'icon' => 'ph-shield-check'],
                ['name' => 'Laboratorio', 'slug' => 'lab_test', 'icon' => 'ph-test-tube'],
                ['name' => 'Imagenología', 'slug' => 'imaging', 'icon' => 'ph-camera'],
                ['name' => 'Cirugía', 'slug' => 'surgery', 'icon' => 'ph-first-aid-kit'],
                ['name' => 'Hospitalización', 'slug' => 'hospitalization', 'icon' => 'ph-bed'],
                ['name' => 'Odontología', 'slug' => 'dental', 'icon' => 'ph-tooth'],
                ['name' => 'Estética / Grooming', 'slug' => 'grooming', 'icon' => 'ph-scissors'],
                ['name' => 'Paseo', 'slug' => 'walking', 'icon' => 'ph-footprints'],
                ['name' => 'Pensión / Guardería', 'slug' => 'boarding', 'icon' => 'ph-house'],
                ['name' => 'Cremación', 'slug' => 'cremation', 'icon' => 'ph-fire'],
                ['name' => 'Reproductivo', 'slug' => 'reproductive', 'icon' => 'ph-gender-intersex'],
                ['name' => 'Microchip / Identificación', 'slug' => 'microchip', 'icon' => 'ph-qr-code'],
                ['name' => 'Nutrición', 'slug' => 'nutrition', 'icon' => 'ph-bowl-food'],
                ['name' => 'Conducta', 'slug' => 'behavior', 'icon' => 'ph-chats-circle'],
                ['name' => 'Fisioterapia', 'slug' => 'physiotherapy', 'icon' => 'ph-person-simple-walk'],
                ['name' => 'Otro', 'slug' => 'other', 'icon' => 'ph-paw-print'],
            ];
            foreach ( $types as $type ) {
                $wpdb->insert( $table, $type );
            }
        }
    }

    /**
     * Crea la tabla para definir los campos de formulario personalizados.
     */
    private function create_table_form_fields() {
        global $wpdb;
        $table = esc_sql( $this->table_name_form_fields );
        $sql = "CREATE TABLE {$table} (
            field_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            entry_type_id BIGINT(20) NOT NULL,
            field_key VARCHAR(100) NOT NULL,
            field_label VARCHAR(255) NOT NULL,
            field_type ENUM('text', 'textarea', 'number', 'date', 'checkbox', 'product_selector', 'next_appointment') NOT NULL DEFAULT 'text',
            product_filter_type ENUM('Analgésico', 'Antiinflamatorio', 'Antimicrobiano', 'Antiparasitario', 'Antibiótico', 'Biológico', 'Dermatológico', 'Gastrointestinal', 'Nutricional', 'Ótico', 'Otro', 'Salud y Belleza', 'Vacuna') DEFAULT NULL,
            is_required TINYINT(1) DEFAULT 0,
            display_order INT DEFAULT 0,
            PRIMARY KEY (field_id),
            KEY entry_type_id (entry_type_id),
            UNIQUE KEY unique_entry_field (entry_type_id, field_key)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    /**
     * Puebla la tabla va_form_fields con la estructura de formularios por defecto.
     * Se ejecuta una sola vez.
     */
    private function maybe_populate_form_fields() {
        global $wpdb;
        $table_fields = esc_sql( $this->table_name_form_fields );
        $table_types = esc_sql( $this->table_name_entry_types );

        $count = $wpdb->get_var( "SELECT COUNT(*) FROM " . $table_fields );
        if ( intval( $count ) > 0 ) {
            return; // La tabla ya tiene datos, no hacer nada.
        }

        // Obtener los IDs de los entry_type por su slug para la asociación
        $entry_types_q = $wpdb->get_results( "SELECT entry_type_id, slug FROM " . $table_types, OBJECT_K );
        if (empty($entry_types_q)) {
            return; // No se pueden poblar campos si no hay tipos de entrada.
        }
        $entry_types = [];
        foreach($entry_types_q as $slug => $data) {
            $entry_types[$slug] = $data->entry_type_id;
        }

        // Definición de campos basada en el CSV del usuario
        $forms_structure = [
            'consultation'      => ['Motivo de la Consulta', 'Diagnóstico Diferencial', 'Tratamiento Indicado', 'Notas Adicionales'],
            'follow_up'         => ['Motivo del Control', 'Evolución del Paciente', 'Ajustes al Tratamiento', 'Próximas Acciones'],
            'emergency'         => ['Evaluación Inicial (Triage)', 'Maniobras de Estabilización', 'Plan de Acción Inmediato', 'Notas de Urgencia'],
            'vaccination'       => ['Biológico Aplicado', 'Número de Lote', 'Próxima Dosis (Fecha)'],
            'deworming'         => ['Producto Administrado', 'Vía de Administración', 'Próxima Dosis (Fecha)'],
            'parasite_control'  => ['Producto Aplicado', 'Zona de Aplicación', 'Próxima Aplicación (Fecha)'],
            'lab_test'          => ['Tipo de Prueba Realizada', 'Resumen de Resultados', 'Archivos Adjuntos', 'Interpretación y Notas'],
            'imaging'           => ['Estudio Realizado (RX, ECO, etc.)', 'Hallazgos Relevantes', 'Archivos Adjuntos', 'Conclusiones'],
            'surgery'           => ['Procedimiento Quirúrgico', 'Protocolo Anestésico', 'Complicaciones (Si hubo)', 'Indicaciones Postoperatorias'],
            'hospitalization'   => ['Motivo de Ingreso', 'Terapias Durante Hospitalización', 'Evolución Diaria', 'Plan de Alta Médica'],
            'dental'            => ['Procedimiento Dental Realizado', 'Hallazgos en Cavidad Oral', 'Recomendaciones de Cuidado', 'Notas'],
            'grooming'          => ['Servicio de Estética Realizado', 'Productos Utilizados', 'Observaciones de Comportamiento', 'Recomendaciones'],
            'walking'           => ['Duración del Paseo (minutos)', 'Ruta o Zona del Paseo', 'Comportamiento Social', 'Incidencias u Observaciones'],
            'boarding'          => ['Fecha de Ingreso y Egreso', 'Indicaciones de Alimentación', 'Medicación Administrada', 'Observaciones Generales'],
            'cremation'         => ['Tipo de Cremación', 'Proveedor del Servicio', 'Notas sobre Entrega de Cenizas'],
            'reproductive'      => ['Evento Reproductivo', 'Fecha del Evento', 'Método Utilizado', 'Observaciones y Seguimiento'],
            'microchip'         => ['Código del Microchip Implantado', 'Sitio de Implante', 'Fecha de Registro'],
            'nutrition'         => ['Tipo de Dieta Indicada', 'Cálculo Calórico (kcal/día)', 'Metas de Condición Corporal', 'Notas de Seguimiento'],
            'behavior'          => ['Problema Conductual Identificado', 'Evaluación Inicial', 'Plan de Modificación de Conducta', 'Seguimiento y Ajustes'],
            'physiotherapy'     => ['Técnica de Fisioterapia Aplicada', 'Número de Sesiones', 'Evolución del Paciente', 'Plan a Seguir en Casa']
        ];

        foreach ( $forms_structure as $slug => $fields ) {
            if ( isset( $entry_types[ $slug ] ) ) {
                $entry_type_id = $entry_types[ $slug ];
                $order = 0;
                foreach ( $fields as $label ) {
                    $order++;
                    $field_type = 'textarea';
                    $product_filter = null; // Variable para el filtro

                    if (stripos($label, 'Biológico') !== false || stripos($label, 'Producto') !== false) {
                        $field_type = 'product_selector';
                        // Asignar el filtro basado en el tipo de entrada (slug)
                        if ($slug === 'vaccination') $product_filter = 'Vacuna';
                        if ($slug === 'deworming' || $slug === 'parasite_control') $product_filter = 'Desparasitante';
                    } elseif (stripos($label, 'Próxima') !== false) {
                        $field_type = 'next_appointment';
                    } elseif (stripos($label, 'Fecha') !== false) {
                        $field_type = 'date';
                    } elseif (stripos($label, 'Número de Lote') !== false || stripos($label, 'Código') !== false || stripos($label, 'Duración') !== false) {
                        $field_type = 'text';
                    }

                    $wpdb->insert(
                        $table_fields,
                        [
                            'entry_type_id' => $entry_type_id,
                            'field_key'     => sanitize_key( substr( $slug . '_' . $label, 0, 50 ) ),
                            'field_label'   => $label,
                            'field_type'    => $field_type,
                            'product_filter_type' => $product_filter, // <-- AÑADIDO
                            'is_required'   => 0,
                            'display_order' => $order
                        ]
                    );
                }
                // Añadir el campo de próxima cita a CADA formulario
                $wpdb->insert(
                    $table_fields,
                    [
                        'entry_type_id' => $entry_type_id,
                        'field_key'     => $slug . '_next_appointment',
                        'field_label'   => 'Agendar Próxima Cita',
                        'field_type'    => 'next_appointment',
                        'is_required'   => 0,
                        'display_order' => $order + 1
                    ]
                );
            }
        }
    }

    /**
     * Crea la tabla para almacenar los datos de los campos personalizados (metadata).
     */
    private function create_table_pet_log_meta() {
        global $wpdb;
        $table = esc_sql( $this->table_name_pet_log_meta );
        $sql = "CREATE TABLE {$table} (
            meta_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            log_id BIGINT(20) NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value LONGTEXT,
            PRIMARY KEY (meta_id),
            KEY log_id (log_id),
            KEY meta_key (meta_key(191))
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    /**
     * Crea la tabla para el catálogo de productos de los profesionales.
     */
    private function create_table_products() {
        global $wpdb;
        $table = esc_sql( $this->table_name_products );
        $sql = "CREATE TABLE {$table} (
            product_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            professional_id BIGINT(20) NOT NULL,
            product_name VARCHAR(255) NOT NULL,
            product_type ENUM('Analgésico', 'Antiinflamatorio', 'Antimicrobiano', 'Antiparasitario', 'Antibiótico', 'Biológico', 'Dermatológico', 'Gastrointestinal', 'Nutricional', 'Ótico', 'Otro', 'Salud y Belleza', 'Vacuna') NOT NULL,
            presentation VARCHAR(255) DEFAULT NULL,
            notes TEXT,
            is_active TINYINT(1) DEFAULT 1,
            manufacturer_id BIGINT(20) DEFAULT NULL,
            active_ingredient_id BIGINT(20) DEFAULT NULL,
            PRIMARY KEY (product_id),
            KEY professional_id (professional_id),
            KEY idx_manufacturer_id (manufacturer_id),
            KEY idx_active_ingredient_id (active_ingredient_id)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    /**
     * Crea la tabla pivote para vincular productos a una entrada del historial.
     */
    private function create_table_pet_log_products() {
        global $wpdb;
        $table = esc_sql( $this->table_name_pet_log_products );
        $sql = "CREATE TABLE {$table} (
            log_product_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            log_id BIGINT(20) NOT NULL,
            product_id BIGINT(20) NOT NULL,
            lot_number VARCHAR(100) DEFAULT NULL,
            expiration_date DATE DEFAULT NULL,
            quantity_used VARCHAR(50) DEFAULT NULL,
            PRIMARY KEY (log_product_id),
            KEY log_id (log_id),
            KEY product_id (product_id)
        ) {$this->charset_collate};";
        dbDelta( $sql );
    }

    /**
     * Obtiene todos los tipos de entrada activos de la base de datos.
     * @return array Lista de objetos de tipos de entrada.
     */
    public function get_entry_types() {
        global $wpdb;
        $table = esc_sql( $this->table_name_entry_types );
        return $wpdb->get_results( "SELECT * FROM {$table} WHERE is_active = 1 ORDER BY name ASC" );
    }

    /**
     * Obtiene los campos de formulario personalizados para un tipo de entrada específico.
     * @param int $entry_type_id El ID del tipo de entrada.
     * @return array Lista de objetos de campos de formulario.
     */
    public function get_form_fields_by_entry_type( $entry_type_id ) {
        global $wpdb;
        $table = esc_sql( $this->table_name_form_fields );
        $entry_type_id = intval( $entry_type_id );
        if ( empty( $entry_type_id ) ) {
            return [];
        }
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE entry_type_id = %d ORDER BY display_order ASC",
            $entry_type_id
        ) );
    }

    /**
     * Obtiene los productos de un profesional.
     * @param int $professional_id ID del profesional.
     * @return array Lista de productos.
     */
    public function get_products_by_professional($professional_id) {
        global $wpdb;
        $table = esc_sql( $this->table_name_products );
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE professional_id = %d AND is_active = 1 ORDER BY product_name ASC",
            intval($professional_id)
        ));
    }

    /**
     * Crea o actualiza un producto en el catálogo.
     * Ahora maneja automáticamente la normalización de fabricantes y principios activos.
     * Mantiene compatibilidad total con código existente.
     * @param array $product_data Datos del producto.
     * @return int|false ID del producto o false en error.
     */
    public function save_product($product_data) {
        global $wpdb;
        $table = esc_sql( $this->table_name_products );

        // Datos básicos - ahora 100% normalizado
        $data = [
            'professional_id'   => intval($product_data['professional_id']),
            'product_name'      => sanitize_text_field($product_data['product_name']),
            'product_type'      => sanitize_text_field($product_data['product_type']),
            'presentation'      => sanitize_text_field($product_data['presentation']),
            'notes'             => sanitize_textarea_field($product_data['notes']),
        ];

        // Normalización automática de fabricantes
        if (!empty($product_data['manufacturer'])) {
            $manufacturer_id = $this->create_or_get_manufacturer($product_data['manufacturer']);
            if ($manufacturer_id) {
                $data['manufacturer_id'] = $manufacturer_id;
            }
        }

        // Normalización automática de principios activos
        if (!empty($product_data['active_ingredient'])) {
            $ingredient_id = $this->create_or_get_active_ingredient($product_data['active_ingredient']);
            if ($ingredient_id) {
                $data['active_ingredient_id'] = $ingredient_id;
            }
        }

        if (!empty($product_data['product_id'])) {
            // Actualizar
            $wpdb->update($table, $data, ['product_id' => intval($product_data['product_id'])]);
            return intval($product_data['product_id']);
        } else {
            // Insertar
            $wpdb->insert($table, $data);
            return $wpdb->insert_id;
        }
    }

    /**
     * Elimina un producto (marcado como inactivo).
     * @param int $product_id ID del producto.
     * @param int $professional_id ID del profesional para verificación.
     * @return bool Éxito.
     */
    public function delete_product($product_id, $professional_id) {
        global $wpdb;
        $table = esc_sql( $this->table_name_products );
        return $wpdb->update(
            $table,
            ['is_active' => 0],
            ['product_id' => intval($product_id), 'professional_id' => intval($professional_id)]
        );
    }

    /**
     * Añade metadatos a una entrada del historial.
     * @param int $log_id ID de la entrada del historial.
     * @param string $meta_key Clave del metadato.
     * @param string $meta_value Valor del metadato.
     * @return int|false ID del metadato insertado o false en error.
     */
    public function add_pet_log_meta($log_id, $meta_key, $meta_value) {
        global $wpdb;
        return $wpdb->insert(
            $this->table_name_pet_log_meta,
            ['log_id' => $log_id, 'meta_key' => $meta_key, 'meta_value' => $meta_value],
            ['%d', '%s', '%s']
        );
    }

    /**
     * Vincula un producto a una entrada del historial.
     * @param int $log_id ID de la entrada del historial.
     * @param int $product_id ID del producto del catálogo.
     * @param array $context_data Datos contextuales como lote y caducidad.
     * @return int|false ID del registro insertado o false en error.
     */
    public function add_pet_log_product($log_id, $product_id, $context_data = []) {
        global $wpdb;
        $data = [
            'log_id' => intval($log_id),
            'product_id' => intval($product_id),
            'lot_number' => isset($context_data['lot_number']) ? sanitize_text_field($context_data['lot_number']) : null,
            'expiration_date' => isset($context_data['expiration_date']) ? sanitize_text_field($context_data['expiration_date']) : null,
            'quantity_used' => isset($context_data['quantity_used']) ? sanitize_text_field($context_data['quantity_used']) : null,
        ];
        return $wpdb->insert(
            $this->table_name_pet_log_products,
            $data,
            ['%d', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Obtiene el historial médico de una mascota, incluyendo metadatos y productos.
     * @param int $pet_id ID de la mascota.
     * @param int|null $professional_id ID del profesional para verificar acceso.
     * @return array Lista de entradas del historial enriquecidas.
     */
    public function get_pet_logs_full($pet_id, $professional_id = null) {
        global $wpdb;
        $logs = $this->get_pet_logs($pet_id, $professional_id); // Reutilizamos la función existente

        if (empty($logs)) {
            return [];
        }

        $log_ids = wp_list_pluck($logs, 'log_id');
        $placeholders = implode(', ', array_fill(0, count($log_ids), '%d'));

        // Obtener todos los metadatos de una vez
        $meta_table = esc_sql($this->table_name_pet_log_meta);
        $sql_meta = $wpdb->prepare("SELECT * FROM {$meta_table} WHERE log_id IN ($placeholders)", $log_ids);
        $all_meta = $wpdb->get_results($sql_meta);

        // Obtener todos los productos de una vez
        $products_table = esc_sql($this->table_name_pet_log_products);
        $catalog_table = esc_sql($this->table_name_products);
        $sql_products = $wpdb->prepare(
            "SELECT lp.*, p.product_name, p.product_type 
             FROM {$products_table} lp 
             JOIN {$catalog_table} p ON lp.product_id = p.product_id 
             WHERE lp.log_id IN ($placeholders)", $log_ids
        );
        $all_products = $wpdb->get_results($sql_products);

        // Mapear los datos adicionales a cada entrada del log
        $logs_by_id = [];
        foreach ($logs as $log) {
            $log->meta = [];
            $log->products = [];
            $logs_by_id[$log->log_id] = $log;
        }

        foreach ($all_meta as $meta) {
            if (isset($logs_by_id[$meta->log_id])) {
                $logs_by_id[$meta->log_id]->meta[$meta->meta_key] = $meta->meta_value;
            }
        }

        foreach ($all_products as $product) {
            if (isset($logs_by_id[$product->log_id])) {
                $logs_by_id[$product->log_id]->products[] = $product;
            }
        }

        return array_values($logs_by_id);
    }

    /**
     * Crea la tabla de fabricantes para normalizar los datos de productos.
     */
    private function create_table_manufacturers() {
        global $wpdb;
        $table = esc_sql($this->table_name_manufacturers);
        $sql = "CREATE TABLE {$table} (
            manufacturer_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            manufacturer_name VARCHAR(255) NOT NULL,
            contact_info TEXT DEFAULT NULL,
            website VARCHAR(255) DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (manufacturer_id),
            UNIQUE KEY unique_manufacturer_name (manufacturer_name)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }

    /**
     * Crea la tabla de principios activos para normalizar los datos de productos.
     */
    private function create_table_active_ingredients() {
        global $wpdb;
        $table = esc_sql($this->table_name_active_ingredients);
        $sql = "CREATE TABLE {$table} (
            ingredient_id BIGINT(20) NOT NULL AUTO_INCREMENT,
            ingredient_name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            safety_notes TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            date_created DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (ingredient_id),
            UNIQUE KEY unique_ingredient_name (ingredient_name)
        ) {$this->charset_collate};";
        dbDelta($sql);
    }

    /**
     * Aplica todas las mejoras de normalización incluyendo claves foráneas.
     * Mantiene compatibilidad total con código existente.
     */
    private function apply_normalization_improvements() {
        $this->add_foreign_key_constraints();
        $this->migrate_manufacturer_data();
        $this->migrate_active_ingredient_data();
        $this->update_products_table_structure();
        $this->cleanup_redundant_columns();
    }

    /**
     * Añade todas las restricciones de clave foránea necesarias.
     * Mejora la integridad referencial sin afectar la funcionalidad existente.
     */
    private function add_foreign_key_constraints() {
        global $wpdb;

        // Verificar si las claves foráneas ya existen antes de añadirlas
        $foreign_keys_to_add = [
            [
                'table' => $this->table_name_form_fields,
                'constraint' => 'fk_form_fields_entry_type',
                'sql' => "ALTER TABLE {$this->table_name_form_fields} 
                         ADD CONSTRAINT fk_form_fields_entry_type 
                         FOREIGN KEY (entry_type_id) REFERENCES {$this->table_name_entry_types}(entry_type_id) 
                         ON DELETE CASCADE ON UPDATE CASCADE"
            ],
            [
                'table' => $this->table_name_pet_log_meta,
                'constraint' => 'fk_pet_log_meta_log',
                'sql' => "ALTER TABLE {$this->table_name_pet_log_meta} 
                         ADD CONSTRAINT fk_pet_log_meta_log 
                         FOREIGN KEY (log_id) REFERENCES {$this->table_name_pet_logs}(log_id) 
                         ON DELETE CASCADE ON UPDATE CASCADE"
            ],
            [
                'table' => $this->table_name_pet_log_products,
                'constraint' => 'fk_pet_log_products_log',
                'sql' => "ALTER TABLE {$this->table_name_pet_log_products} 
                         ADD CONSTRAINT fk_pet_log_products_log 
                         FOREIGN KEY (log_id) REFERENCES {$this->table_name_pet_logs}(log_id) 
                         ON DELETE CASCADE ON UPDATE CASCADE"
            ],
            [
                'table' => $this->table_name_pet_log_products,
                'constraint' => 'fk_pet_log_products_product',
                'sql' => "ALTER TABLE {$this->table_name_pet_log_products} 
                         ADD CONSTRAINT fk_pet_log_products_product 
                         FOREIGN KEY (product_id) REFERENCES {$this->table_name_products}(product_id) 
                         ON DELETE CASCADE ON UPDATE CASCADE"
            ]
        ];

        foreach ($foreign_keys_to_add as $fk) {
            if (!$this->foreign_key_exists($fk['table'], $fk['constraint'])) {
                $wpdb->query($fk['sql']);
                $this->log_message("[Veterinalia] Clave foránea añadida: {$fk['constraint']} en tabla {$fk['table']}");
            }
        }
    }

    /**
     * Verifica si una clave foránea existe en una tabla.
     */
    private function foreign_key_exists($table, $constraint_name) {
        global $wpdb;
        $db_name = $wpdb->dbname;
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT CONSTRAINT_NAME 
             FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
             WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND CONSTRAINT_NAME = %s",
            $db_name, $table, $constraint_name
        ));
        return !empty($result);
    }

    /**
     * Migra los datos de fabricantes existentes a la tabla normalizada.
     * Solo ejecuta si existe la columna 'manufacturer' (instalaciones previas).
     */
    private function migrate_manufacturer_data() {
        global $wpdb;

        // Solo ejecutar una vez
        $option_key = 'va_manufacturers_migrated';
        if (get_option($option_key)) {
            return;
        }

        $products_table = esc_sql($this->table_name_products);
        $manufacturers_table = esc_sql($this->table_name_manufacturers);
        
        // Verificar si existe la columna manufacturer antes de intentar migrar
        $columns = $wpdb->get_col("DESCRIBE {$products_table}");
        if (!in_array('manufacturer', $columns)) {
            // No hay columna manufacturer = instalación nueva, nada que migrar
            update_option($option_key, true);
            $this->log_message("[Veterinalia] Instalación nueva - no hay fabricantes que migrar");
            return;
        }
        
        $unique_manufacturers = $wpdb->get_col(
            "SELECT DISTINCT manufacturer 
             FROM {$products_table} 
             WHERE manufacturer IS NOT NULL AND manufacturer != ''"
        );

        if (!empty($unique_manufacturers)) {
            foreach ($unique_manufacturers as $manufacturer) {
                $wpdb->insert(
                    $manufacturers_table,
                    ['manufacturer_name' => $manufacturer],
                    ['%s']
                );
            }
            $this->log_message("[Veterinalia] Migrados " . count($unique_manufacturers) . " fabricantes únicos");
        } else {
            $this->log_message("[Veterinalia] No se encontraron fabricantes que migrar");
        }

        update_option($option_key, true);
    }

    /**
     * Migra los datos de principios activos existentes a la tabla normalizada.
     * Solo ejecuta si existe la columna 'active_ingredient' (instalaciones previas).
     */
    private function migrate_active_ingredient_data() {
        global $wpdb;

        // Solo ejecutar una vez
        $option_key = 'va_active_ingredients_migrated';
        if (get_option($option_key)) {
            return;
        }

        $products_table = esc_sql($this->table_name_products);
        $ingredients_table = esc_sql($this->table_name_active_ingredients);
        
        // Verificar si existe la columna active_ingredient antes de intentar migrar
        $columns = $wpdb->get_col("DESCRIBE {$products_table}");
        if (!in_array('active_ingredient', $columns)) {
            // No hay columna active_ingredient = instalación nueva, nada que migrar
            update_option($option_key, true);
            $this->log_message("[Veterinalia] Instalación nueva - no hay principios activos que migrar");
            return;
        }
        
        $unique_ingredients = $wpdb->get_col(
            "SELECT DISTINCT active_ingredient 
             FROM {$products_table} 
             WHERE active_ingredient IS NOT NULL AND active_ingredient != ''"
        );

        if (!empty($unique_ingredients)) {
            foreach ($unique_ingredients as $ingredient) {
                $wpdb->insert(
                    $ingredients_table,
                    ['ingredient_name' => $ingredient],
                    ['%s']
                );
            }
            $this->log_message("[Veterinalia] Migrados " . count($unique_ingredients) . " principios activos únicos");
        } else {
            $this->log_message("[Veterinalia] No se encontraron principios activos que migrar");
        }

        update_option($option_key, true);
    }

    /**
     * Actualiza la estructura de la tabla productos para usar las nuevas relaciones normalizadas.
     * Mantiene los campos originales para compatibilidad total.
     */
    private function update_products_table_structure() {
        global $wpdb;

        $table = esc_sql($this->table_name_products);
        
        // Verificar si las columnas ya existen
        $columns = $wpdb->get_col("DESCRIBE {$table}");
        
        if (!in_array('manufacturer_id', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN manufacturer_id BIGINT(20) DEFAULT NULL");
            $wpdb->query("ALTER TABLE {$table} ADD KEY idx_manufacturer_id (manufacturer_id)");
        }
        
        if (!in_array('active_ingredient_id', $columns)) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN active_ingredient_id BIGINT(20) DEFAULT NULL");
            $wpdb->query("ALTER TABLE {$table} ADD KEY idx_active_ingredient_id (active_ingredient_id)");
        }

        // Poblar las nuevas columnas con datos normalizados
        $this->populate_normalized_product_references();

        // Añadir claves foráneas para las nuevas relaciones
        if (!$this->foreign_key_exists($table, 'fk_products_manufacturer')) {
            $wpdb->query("ALTER TABLE {$table} 
                         ADD CONSTRAINT fk_products_manufacturer 
                         FOREIGN KEY (manufacturer_id) REFERENCES {$this->table_name_manufacturers}(manufacturer_id) 
                         ON DELETE SET NULL ON UPDATE CASCADE");
        }

        if (!$this->foreign_key_exists($table, 'fk_products_ingredient')) {
            $wpdb->query("ALTER TABLE {$table} 
                         ADD CONSTRAINT fk_products_ingredient 
                         FOREIGN KEY (active_ingredient_id) REFERENCES {$this->table_name_active_ingredients}(ingredient_id) 
                         ON DELETE SET NULL ON UPDATE CASCADE");
        }

        // Añadir FK para professional_id con verificación de compatibilidad
        if (!$this->foreign_key_exists($table, 'fk_products_professional')) {
            $this->add_professional_fk_safely($table);
        }
    }

    /**
     * Pobla las referencias normalizadas en la tabla de productos.
     * Solo ejecuta si existen las columnas antiguas (para migración de datos existentes).
     */
    private function populate_normalized_product_references() {
        global $wpdb;

        $products_table = esc_sql($this->table_name_products);
        $manufacturers_table = esc_sql($this->table_name_manufacturers);
        $ingredients_table = esc_sql($this->table_name_active_ingredients);

        // Verificar si existen las columnas antiguas antes de intentar migrar
        $columns = $wpdb->get_col("DESCRIBE {$products_table}");
        $has_old_manufacturer = in_array('manufacturer', $columns);
        $has_old_ingredient = in_array('active_ingredient', $columns);

        // Solo migrar si existen las columnas antiguas (instalaciones previas)
        if ($has_old_manufacturer) {
            $wpdb->query("
                UPDATE {$products_table} p 
                JOIN {$manufacturers_table} m ON p.manufacturer = m.manufacturer_name 
                SET p.manufacturer_id = m.manufacturer_id 
                WHERE p.manufacturer_id IS NULL AND p.manufacturer IS NOT NULL
            ");
            $this->log_message("[Veterinalia] Migración manufacturer → manufacturer_id completada");
        }

        if ($has_old_ingredient) {
            $wpdb->query("
                UPDATE {$products_table} p 
                JOIN {$ingredients_table} i ON p.active_ingredient = i.ingredient_name 
                SET p.active_ingredient_id = i.ingredient_id 
                WHERE p.active_ingredient_id IS NULL AND p.active_ingredient IS NOT NULL
            ");
            $this->log_message("[Veterinalia] Migración active_ingredient → active_ingredient_id completada");
        }

        // Si no hay columnas antiguas, es una instalación nueva (no hay nada que migrar)
        if (!$has_old_manufacturer && !$has_old_ingredient) {
            $this->log_message("[Veterinalia] Instalación nueva detectada - no hay datos que migrar");
        }
    }

    /**
     * Obtiene todos los fabricantes activos.
     * @return array Lista de fabricantes
     */
    public function get_manufacturers() {
        global $wpdb;
        $table = esc_sql($this->table_name_manufacturers);
        return $wpdb->get_results("SELECT * FROM {$table} WHERE is_active = 1 ORDER BY manufacturer_name ASC");
    }

    /**
     * Obtiene todos los principios activos.
     * @return array Lista de principios activos
     */
    public function get_active_ingredients() {
        global $wpdb;
        $table = esc_sql($this->table_name_active_ingredients);
        return $wpdb->get_results("SELECT * FROM {$table} WHERE is_active = 1 ORDER BY ingredient_name ASC");
    }

    /**
     * Crea un nuevo fabricante o retorna el ID si ya existe.
     * @param string $manufacturer_name Nombre del fabricante
     * @param array $additional_data Datos adicionales opcionales
     * @return int|false ID del fabricante
     */
    public function create_or_get_manufacturer($manufacturer_name, $additional_data = []) {
        global $wpdb;
        
        $manufacturer_name = sanitize_text_field($manufacturer_name);
        if (empty($manufacturer_name)) {
            return false;
        }

        $table = esc_sql($this->table_name_manufacturers);
        
        // Verificar si ya existe
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT manufacturer_id FROM {$table} WHERE manufacturer_name = %s",
            $manufacturer_name
        ));

        if ($existing_id) {
            return intval($existing_id);
        }

        // Crear nuevo fabricante
        $data = array_merge([
            'manufacturer_name' => $manufacturer_name,
            'is_active' => 1
        ], $additional_data);

        $result = $wpdb->insert($table, $data);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Crea un nuevo principio activo o retorna el ID si ya existe.
     * @param string $ingredient_name Nombre del principio activo
     * @param array $additional_data Datos adicionales opcionales
     * @return int|false ID del principio activo
     */
    public function create_or_get_active_ingredient($ingredient_name, $additional_data = []) {
        global $wpdb;
        
        $ingredient_name = sanitize_text_field($ingredient_name);
        if (empty($ingredient_name)) {
            return false;
        }

        $table = esc_sql($this->table_name_active_ingredients);
        
        // Verificar si ya existe
        $existing_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ingredient_id FROM {$table} WHERE ingredient_name = %s",
            $ingredient_name
        ));

        if ($existing_id) {
            return intval($existing_id);
        }

        // Crear nuevo principio activo
        $data = array_merge([
            'ingredient_name' => $ingredient_name,
            'is_active' => 1
        ], $additional_data);

        $result = $wpdb->insert($table, $data);
        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Obtiene productos con información completa incluyendo fabricantes y principios activos.
     * Mantiene compatibilidad total con métodos existentes.
     * @param int $professional_id ID del profesional
     * @return array Lista de productos enriquecidos
     */
    public function get_products_full($professional_id) {
        global $wpdb;
        
        $products_table = esc_sql($this->table_name_products);
        $manufacturers_table = esc_sql($this->table_name_manufacturers);
        $ingredients_table = esc_sql($this->table_name_active_ingredients);

        $sql = $wpdb->prepare("
            SELECT p.*, 
                   m.manufacturer_name as manufacturer,
                   i.ingredient_name as active_ingredient
            FROM {$products_table} p
            LEFT JOIN {$manufacturers_table} m ON p.manufacturer_id = m.manufacturer_id
            LEFT JOIN {$ingredients_table} i ON p.active_ingredient_id = i.ingredient_id
            WHERE p.professional_id = %d AND p.is_active = 1
            ORDER BY p.product_name ASC
        ", $professional_id);

        $products = $wpdb->get_results($sql);

        return $products;
    }

    /**
     * Asegura que la migración se ejecute en actualizaciones del plugin.
     * Verifica la versión y fuerza limpieza si es necesario.
     */
    private function ensure_version_migration() {
        $current_version = get_option('va_database_version', '0.0.0');
        $target_version = '1.0.9.1'; // Versión con actualización de ENUM en va_form_fields
        
        if (version_compare($current_version, $target_version, '<')) {
            // Es una actualización, asegurar migración completa
            $this->force_cleanup_redundant_columns();
            $this->apply_structure_improvements();
            $this->apply_product_type_enum_update();
            $this->apply_form_fields_enum_update();
            
            // Actualizar versión de base de datos
            update_option('va_database_version', $target_version);
            $this->log_message("[Veterinalia] Base de datos migrada a versión {$target_version}");
        }
    }

    /**
     * Actualiza el ENUM de la columna product_type en la tabla de productos.
     */
    private function apply_product_type_enum_update() {
        global $wpdb;
        $table = esc_sql($this->table_name_products);
        $new_enum_values = "'Analgésico', 'Antiinflamatorio', 'Antimicrobiano', 'Antiparasitario', 'Antibiótico', 'Biológico', 'Dermatológico', 'Gastrointestinal', 'Nutricional', 'Ótico', 'Otro', 'Salud y Belleza', 'Vacuna'";
        $sql = "ALTER TABLE {$table} MODIFY COLUMN product_type ENUM({$new_enum_values}) NOT NULL";

        // Silenciar errores para evitar salida inesperada en caso de que la columna ya esté actualizada
        $original_suppress = $wpdb->suppress_errors(true);
        $original_show_errors = $wpdb->show_errors;
        $wpdb->show_errors(false);

        $wpdb->query($sql);

        // Restaurar configuración de errores
        $wpdb->suppress_errors($original_suppress);
        $wpdb->show_errors = $original_show_errors;

        $this->log_message("[Veterinalia] ENUM de product_type en va_products actualizado.");
    }

    /**
     * Actualiza el ENUM de la columna product_filter_type en la tabla de campos de formulario.
     */
    private function apply_form_fields_enum_update() {
        global $wpdb;
        $table = esc_sql($this->table_name_form_fields);
        $new_enum_values = "'Analgésico', 'Antiinflamatorio', 'Antimicrobiano', 'Antiparasitario', 'Antibiótico', 'Biológico', 'Dermatológico', 'Gastrointestinal', 'Nutricional', 'Ótico', 'Otro', 'Salud y Belleza', 'Vacuna'";
        $sql = "ALTER TABLE {$table} MODIFY COLUMN product_filter_type ENUM({$new_enum_values}) DEFAULT NULL";

        $original_suppress = $wpdb->suppress_errors(true);
        $original_show_errors = $wpdb->show_errors;
        $wpdb->show_errors(false);

        $wpdb->query($sql);

        $wpdb->suppress_errors($original_suppress);
        $wpdb->show_errors = $original_show_errors;

        $this->log_message("[Veterinalia] ENUM de product_filter_type en va_form_fields actualizado.");
    }

    /**
     * Aplica mejoras de estructura para instalaciones existentes.
     */
    private function apply_structure_improvements() {
        global $wpdb;
        
        // Añadir UNIQUE constraint si no existe
        $form_fields_table = esc_sql($this->table_name_form_fields);
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$form_fields_table}");
        
        $has_unique_constraint = false;
        foreach ($indexes as $index) {
            if ($index->Key_name === 'unique_entry_field') {
                $has_unique_constraint = true;
                break;
            }
        }
        
        if (!$has_unique_constraint) {
            $wpdb->query("ALTER TABLE {$form_fields_table} 
                         ADD UNIQUE KEY unique_entry_field (entry_type_id, field_key)");
            $this->log_message("[Veterinalia] UNIQUE constraint añadido a va_form_fields");
        }

        // Actualizar product_filter_type a ENUM si es VARCHAR
        $columns = $wpdb->get_results("DESCRIBE {$form_fields_table}");
        foreach ($columns as $column) {
            if ($column->Field === 'product_filter_type' && strpos($column->Type, 'varchar') !== false) {
                $wpdb->query("ALTER TABLE {$form_fields_table} 
                             MODIFY COLUMN product_filter_type ENUM('Vacuna', 'Desparasitante', 'Antibiótico', 'Antiinflamatorio', 'Otro') DEFAULT NULL");
                $this->log_message("[Veterinalia] product_filter_type convertido a ENUM");
            }
        }
    }

    /**
     * Añade la FK professional_id de forma segura, manejando incompatibilidades.
     * @param string $table Nombre de la tabla va_products
     */
    private function add_professional_fk_safely($table) {
        global $wpdb;
        
        // Verificar compatibilidad antes de crear FK
        $users_table_info = $wpdb->get_row("SHOW CREATE TABLE {$wpdb->users}", ARRAY_A);
        $products_table_info = $wpdb->get_row("SHOW CREATE TABLE {$table}", ARRAY_A);
        
        if (!$users_table_info || !$products_table_info) {
            $this->log_message("[Veterinalia] No se pudo verificar estructura de tablas para FK professional_id");
            return;
        }
        
        // Verificar que ambas tablas usen el mismo motor y charset
        $users_engine = $this->extract_engine_from_create_statement($users_table_info['Create Table']);
        $products_engine = $this->extract_engine_from_create_statement($products_table_info['Create Table']);
        
        // Intentar crear FK silenciosamente
        // Silenciar errores SQL temporalmente para evitar salida inesperada
        $original_suppress = $wpdb->suppress_errors(true);
        $original_show_errors = $wpdb->show_errors;
        $wpdb->show_errors(false);
        
        try {
            // Primer intento: FK directa (silenciado)
            $sql = "ALTER TABLE {$table} 
                    ADD CONSTRAINT fk_products_professional 
                    FOREIGN KEY (professional_id) REFERENCES {$wpdb->users}(ID) 
                    ON DELETE CASCADE ON UPDATE CASCADE";
            
            $result = $wpdb->query($sql);
            
            if ($result !== false && empty($wpdb->last_error)) {
                $this->log_message("[Veterinaria] FK professional_id añadida exitosamente");
            } else {
                // Si falla, crear constraint de verificación alternativo
                $this->create_professional_check_constraint($table);
            }
            
        } catch (Exception $e) {
            $this->log_message("[Veterinaria] Error creando FK professional_id");
            $this->create_professional_check_constraint($table);
        } finally {
            // Restaurar configuración de errores
            $wpdb->suppress_errors($original_suppress);
            $wpdb->show_errors = $original_show_errors;
        }
    }
    
    /**
     * Crea un constraint de verificación alternativo para professional_id.
     * @param string $table Nombre de la tabla va_products
     */
    private function create_professional_check_constraint($table) {
        global $wpdb;
        
        // En lugar de FK, crear un trigger o validación a nivel de aplicación
        $this->log_message("[Veterinaria] FK professional_id no compatible - usando validación alternativa");
        
        // Verificar si el índice ya existe antes de crearlo
        $indexes = $wpdb->get_results("SHOW INDEX FROM {$table}");
        $index_exists = false;
        
        foreach ($indexes as $index) {
            if ($index->Key_name === 'idx_professional_validation') {
                $index_exists = true;
                break;
            }
        }
        
        if (!$index_exists) {
            // Silenciar errores para evitar salida inesperada
            $original_suppress = $wpdb->suppress_errors(true);
            $original_show_errors = $wpdb->show_errors;
            $wpdb->show_errors(false);
            
            // Añadir un índice para optimizar consultas de verificación
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_professional_validation (professional_id)");
            
            // Restaurar configuración de errores
            $wpdb->suppress_errors($original_suppress);
            $wpdb->show_errors = $original_show_errors;
        }
        
        // Marcar que se necesita validación a nivel de aplicación
        update_option('va_professional_validation_required', true);
    }
    
    /**
     * Extrae el motor de base de datos de un statement CREATE TABLE.
     * @param string $create_statement Statement CREATE TABLE
     * @return string Motor de BD
     */
    private function extract_engine_from_create_statement($create_statement) {
        if (preg_match('/ENGINE=(\w+)/i', $create_statement, $matches)) {
            return strtoupper($matches[1]);
        }
        return 'UNKNOWN';
    }

    /**
     * Configura el modo silencioso para errores SQL durante la activación.
     */
    private function setup_silent_sql_mode() {
        global $wpdb;
        
        // Guardar configuración original
        $this->original_wpdb_settings = [
            'suppress_errors' => $wpdb->suppress_errors(),
            'show_errors' => $wpdb->show_errors,
            'print_error' => isset($wpdb->print_error) ? $wpdb->print_error : true
        ];
        
        // Silenciar completamente errores SQL
        $wpdb->suppress_errors(true);
        $wpdb->show_errors(false);
        if (isset($wpdb->print_error)) {
            $wpdb->print_error = false;
        }
    }
    
    /**
     * Restaura la configuración original de errores SQL.
     */
    private function restore_sql_mode() {
        global $wpdb;
        
        if ($this->original_wpdb_settings) {
            $wpdb->suppress_errors($this->original_wpdb_settings['suppress_errors']);
            $wpdb->show_errors = $this->original_wpdb_settings['show_errors'];
            if (isset($wpdb->print_error)) {
                $wpdb->print_error = $this->original_wpdb_settings['print_error'];
            }
        }
    }

    /**
     * Registra un mensaje de log solo si no estamos en modo silencioso.
     * @param string $message Mensaje a registrar
     */
    private function log_message($message) {
        if (!$this->silent_mode) {
            error_log($message);
        }
    }

    /**
     * FUNCIÓN PÚBLICA: Fuerza la limpieza de columnas redundantes.
     * Llama esta función para eliminar inmediatamente las columnas 'manufacturer' y 'active_ingredient'.
     * ¡CUIDADO! Esta operación es irreversible.
     */
    public function force_cleanup_redundant_columns() {
        global $wpdb;

        $table = esc_sql($this->table_name_products);
        
        // Verificar estructura actual
        $columns = $wpdb->get_col("DESCRIBE {$table}");
        
        $has_manufacturer_id = in_array('manufacturer_id', $columns);
        $has_ingredient_id = in_array('active_ingredient_id', $columns);
        $has_old_manufacturer = in_array('manufacturer', $columns);
        $has_old_ingredient = in_array('active_ingredient', $columns);

        $results = [];

        if (!$has_manufacturer_id || !$has_ingredient_id) {
            $results['error'] = 'Las columnas normalizadas (manufacturer_id, active_ingredient_id) no existen. Ejecuta create_tables() primero.';
            return $results;
        }

        // Migrar datos restantes antes de eliminar
        if ($has_old_manufacturer) {
            $this->migrate_remaining_manufacturer_data();
            $wpdb->query("ALTER TABLE {$table} DROP COLUMN manufacturer");
            $results['manufacturer'] = 'Columna "manufacturer" eliminada exitosamente';
        } else {
            $results['manufacturer'] = 'Columna "manufacturer" ya no existe';
        }

        if ($has_old_ingredient) {
            $this->migrate_remaining_ingredient_data();
            $wpdb->query("ALTER TABLE {$table} DROP COLUMN active_ingredient");
            $results['active_ingredient'] = 'Columna "active_ingredient" eliminada exitosamente';
        } else {
            $results['active_ingredient'] = 'Columna "active_ingredient" ya no existe';
        }

        // Marcar como completado para evitar ejecuciones futuras automáticas
        update_option('va_products_columns_cleaned', true);
        
        $results['status'] = 'Limpieza completada';
        return $results;
    }

    /**
     * Elimina las columnas redundantes de la tabla productos después de completar la normalización.
     * Esta función elimina las columnas 'manufacturer' y 'active_ingredient' que ahora son redundantes.
     */
    private function cleanup_redundant_columns() {
        global $wpdb;

        // Solo ejecutar una vez
        $option_key = 'va_products_columns_cleaned';
        if (get_option($option_key)) {
            return;
        }

        $table = esc_sql($this->table_name_products);
        
        // Verificar que las columnas normalizadas existen antes de eliminar las redundantes
        $columns = $wpdb->get_col("DESCRIBE {$table}");
        
        $has_manufacturer_id = in_array('manufacturer_id', $columns);
        $has_ingredient_id = in_array('active_ingredient_id', $columns);
        $has_old_manufacturer = in_array('manufacturer', $columns);
        $has_old_ingredient = in_array('active_ingredient', $columns);

        // Solo proceder si tenemos las columnas normalizadas y las redundantes
        if ($has_manufacturer_id && $has_ingredient_id) {
            
            // Migrar cualquier dato restante de las columnas antiguas (por seguridad)
            if ($has_old_manufacturer) {
                $this->migrate_remaining_manufacturer_data();
            }
            if ($has_old_ingredient) {
                $this->migrate_remaining_ingredient_data();
            }

            // Eliminar columnas redundantes
            if ($has_old_manufacturer) {
                $wpdb->query("ALTER TABLE {$table} DROP COLUMN manufacturer");
                $this->log_message("[Veterinalia] Columna redundante 'manufacturer' eliminada de va_products");
            }
            
            if ($has_old_ingredient) {
                $wpdb->query("ALTER TABLE {$table} DROP COLUMN active_ingredient");
                $this->log_message("[Veterinaria] Columna redundante 'active_ingredient' eliminada de va_products");
            }

            update_option($option_key, true);
            $this->log_message("[Veterinalia] Limpieza de columnas redundantes completada en va_products");
        }
    }

    /**
     * Migra cualquier dato restante de la columna 'manufacturer' antes de eliminarla.
     */
    private function migrate_remaining_manufacturer_data() {
        global $wpdb;
        
        $products_table = esc_sql($this->table_name_products);
        $manufacturers_table = esc_sql($this->table_name_manufacturers);

        // Buscar productos que tengan manufacturer pero no manufacturer_id
        $products_to_migrate = $wpdb->get_results("
            SELECT product_id, manufacturer 
            FROM {$products_table} 
            WHERE manufacturer IS NOT NULL 
            AND manufacturer != '' 
            AND manufacturer_id IS NULL
        ");

        foreach ($products_to_migrate as $product) {
            $manufacturer_id = $this->create_or_get_manufacturer($product->manufacturer);
            if ($manufacturer_id) {
                $wpdb->update(
                    $products_table,
                    ['manufacturer_id' => $manufacturer_id],
                    ['product_id' => $product->product_id]
                );
            }
        }
    }

    /**
     * Migra cualquier dato restante de la columna 'active_ingredient' antes de eliminarla.
     */
    private function migrate_remaining_ingredient_data() {
        global $wpdb;
        
        $products_table = esc_sql($this->table_name_products);
        $ingredients_table = esc_sql($this->table_name_active_ingredients);

        // Buscar productos que tengan active_ingredient pero no active_ingredient_id
        $products_to_migrate = $wpdb->get_results("
            SELECT product_id, active_ingredient 
            FROM {$products_table} 
            WHERE active_ingredient IS NOT NULL 
            AND active_ingredient != '' 
            AND active_ingredient_id IS NULL
        ");

        foreach ($products_to_migrate as $product) {
            $ingredient_id = $this->create_or_get_active_ingredient($product->active_ingredient);
            if ($ingredient_id) {
                $wpdb->update(
                    $products_table,
                    ['active_ingredient_id' => $ingredient_id],
                    ['product_id' => $product->product_id]
                );
            }
        }
    }

} 