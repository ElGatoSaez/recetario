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
    echo '<div class="wrap">
            <h1>Nueva Receta</h1>
            <p>Aquí puedes agregar una nueva receta.</p>
          </div>';
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