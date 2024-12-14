<?php
/*
Plugin Name: Libro de Recetas
Plugin URI: https://github.com/elgatosaez/recetario
Description: Libro de Recetas para Centro Médico Maktub.
Version: 0.1
Author: Sebastián Sáez M.
Author URI: https://gentes.cl
License: Revista Gentes License Agreement
*/

// Prevent direct access to the file
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Import necessary files for dbDelta function
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// Define the current database version
define('LIBRO_DE_RECETAS_DB_VERSION', '1.0');

// Basic plugin functionality
function libro_de_recetas_init() {
    // Plugin initialization code here
    add_action('admin_menu', 'libro_de_recetas_add_admin_menu');
}

// Show admin notice only on plugin activation
function libro_de_recetas_activation_notice() {
    set_transient('libro_de_recetas_admin_notice', true, 5);
}
register_activation_hook(__FILE__, 'libro_de_recetas_activation_notice');

function libro_de_recetas_admin_notice() {
    // Check if the transient is set
    if (get_transient('libro_de_recetas_admin_notice')) {
        echo '<div class="notice notice-success is-dismissible">
                <p>Libro de Recetas is successfully activated!</p>
             </div>';
        // Delete the transient so the notice is only shown once
        delete_transient('libro_de_recetas_admin_notice');
    }
}
add_action('admin_notices', 'libro_de_recetas_admin_notice');

// Add a new page to the WordPress dashboard menu
function libro_de_recetas_add_admin_menu() {
    add_menu_page(
        'Libro de Recetas',          // Page title
        'Libro de Recetas',          // Menu title
        'manage_options',            // Capability
        'libro_de_recetas_lista',    // Menu slug
        'libro_de_recetas_lista_html', // Callback function to display page content
        'dashicons-book',            // Icon for the menu
        6                            // Position in the menu
    );

    // Add submenu items
    add_submenu_page(
        'libro_de_recetas_lista',    // Parent slug
        'Nueva receta',              // Page title
        'Nueva receta',              // Submenu title
        'manage_options',            // Capability
        'libro_de_recetas_nueva',    // Menu slug
        'libro_de_recetas_nueva_html' // Callback function to display page content
    );
}

// Callback function to display "Nueva Receta" page content
function libro_de_recetas_nueva_html() {
    if (!current_user_can('manage_options')) {
        return;
    }

    global $wpdb;
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $staff_options = '';
    $customer_options = '';
    $is_admin = false;

    // Check if the user is administrator by looking into usermeta
    $capabilities = get_user_meta($user_id, $wpdb->prefix . 'capabilities', true);
    if (is_array($capabilities) && isset($capabilities['administrator'])) {
        $is_admin = true;
    }

    // Populate professional options for administrator
    if ($is_admin) {
        $staff_table = $wpdb->prefix . 'bookly_staff';
        $staff_results = $wpdb->get_results("SELECT id, full_name FROM $staff_table", ARRAY_A);
        foreach ($staff_results as $staff) {
            $staff_options .= '<option value="' . esc_attr($staff['id']) . '">' . esc_html($staff['full_name']) . ' (' . esc_html($staff['id']) . ')</option>';
        }
    } else {
        // Find the staff_id for non-admin user
        $staff_table = $wpdb->prefix . 'bookly_staff';
        $staff_result = $wpdb->get_row($wpdb->prepare("SELECT id, full_name FROM $staff_table WHERE wp_user_id = %d", $user_id), ARRAY_A);
        if ($staff_result) {
            $staff_options = '<option value="' . esc_attr($staff_result['id']) . '" selected>' . esc_html($staff_result['full_name']) . ' (' . esc_html($staff_result['id']) . ')</option>';
        } else {
            $staff_options = '<option value="">Ningún funcionario disponible</option>';
            $staff_warning = '<p style="color: red;">Ningún funcionario anclado a esta cuenta de WordPress</p>';
        }
    }

    // Populate customer options
    $customer_table = $wpdb->prefix . 'bookly_customers';
    $customer_results = $wpdb->get_results("SELECT id, full_name FROM $customer_table", ARRAY_A);
    foreach ($customer_results as $customer) {
        $customer_options .= '<option value="' . esc_attr($customer['id']) . '">' . esc_html($customer['full_name']) . ' (' . esc_html($customer['id']) . ')</option>';
    }

    // Form HTML
    echo '<div class="wrap">
            <h1>Nueva Receta</h1>
            <form method="post" action="">
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Profesional que emite</th>
                        <td>
                            <select name="staff_id">
                                ' . $staff_options . '
                            </select>
                            ' . ($is_admin ? '' : (isset($staff_warning) ? $staff_warning : '')) . '
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Cliente</th>
                        <td>
                            <select name="customer_id">
                                ' . $customer_options . '
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">RUT</th>
                        <td><input type="text" name="rut" value="" required /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Domicilio</th>
                        <td><input type="text" name="domicilio" value="" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Texto de la Receta</th>
                        <td><textarea name="texto_receta" rows="5" cols="50"></textarea></td>
                    </tr>
                </table>
                <p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="Emitir Receta"></p>
            </form>
          </div>';

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit'])) {
        $customer_id = sanitize_text_field($_POST['customer_id']);
        $staff_id = sanitize_text_field($_POST['staff_id']);
        $rut = sanitize_text_field($_POST['rut']);
        $domicilio = sanitize_text_field($_POST['domicilio']);
        $texto_receta = sanitize_textarea_field($_POST['texto_receta']);
        $fecha_emision = current_time('Y-m-d');
        $hora_emision = current_time('H:i:s');

        $table_name = $wpdb->prefix . 'recetario_entry';

        $wpdb->insert(
            $table_name,
            [
                'customer_id' => $customer_id,
                'appointment_id' => null, // Can be updated later if needed
                'rut' => $rut,
                'fecha_emision' => $fecha_emision,
                'hora_emision' => $hora_emision,
                'staff_id' => $staff_id,
                'domicilio' => $domicilio,
                'texto_receta' => $texto_receta,
            ]
        );

        echo '<div class="notice notice-success is-dismissible">
                <p>Receta emitida exitosamente.</p>
              </div>';
    }
}

// Callback function to display "Recetas" page content
function libro_de_recetas_lista_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    echo '<div class="wrap">
            <h1>Recetas</h1>
            <p>Listado de todas las recetas existentes.</p>
          </div>';
}

// Function to create the database table for recipes
function libro_de_recetas_create_table() {
    global $wpdb;

    // Define the table name with the appropriate prefix
    $table_name = $wpdb->prefix . 'recetario_entry';

    // Define the charset collate
    $charset_collate = $wpdb->get_charset_collate();

    // SQL statement to create the table
    $sql = "CREATE TABLE $table_name (
        id INT NOT NULL AUTO_INCREMENT,
        customer_id INT NOT NULL,
        appointment_id INT NULL,
        rut VARCHAR(20) NOT NULL,
        fecha_emision DATE NOT NULL,
        hora_emision TIME NOT NULL,
        staff_id INT NOT NULL,
        domicilio TEXT,
        texto_receta TEXT NOT NULL,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Execute the SQL statement to create the table
    dbDelta($sql);

    // Update the database version option
    add_option('libro_de_recetas_db_version', LIBRO_DE_RECETAS_DB_VERSION);
    update_option('libro_de_recetas_db_version', LIBRO_DE_RECETAS_DB_VERSION);
}

// Function to check if the database needs updating
function libro_de_recetas_update_db_check() {
    if (get_option('libro_de_recetas_db_version') != LIBRO_DE_RECETAS_DB_VERSION) {
        libro_de_recetas_create_table();
    }
}
add_action('plugins_loaded', 'libro_de_recetas_update_db_check');

// Hook to create the table only once when the plugin is activated
register_activation_hook(__FILE__, 'libro_de_recetas_create_table');

// Hook to initialize plugin
add_action('init', 'libro_de_recetas_init');