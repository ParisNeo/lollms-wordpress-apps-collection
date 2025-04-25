<?php
/**
 * Plugin Name:       LoLLMs Apps Collection
 * Plugin URI:        https://lollms.com/
 * Description:       Hosts and manages various LoLLMs web applications within WordPress.
 * Version:           1.0.0
 * Author:            ParisNeo
 * Author URI:        https://github.com/ParisNeo/
 * License:           Apache-2.0
 * License URI:       http://www.apache.org/licenses/LICENSE-2.0
 * Text Domain:       lollms-apps-collection
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

define('LOLLMS_APPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('LOLLMS_APPS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('LOLLMS_APPS_DATA_TABLE', 'lollms_app_data'); // Use the same table name

// Activation Hook (reuse the table creation logic)
register_activation_hook(__FILE__, 'lollms_apps_activate');
function lollms_apps_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . LOLLMS_APPS_DATA_TABLE;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        app_id varchar(100) NOT NULL,
        data longtext NOT NULL,
        last_updated datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_app (user_id, app_id),
        KEY user_id (user_id),
        KEY app_id (app_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    // We might not need the registered apps option if apps are defined by folders/code
}

// --- Add Admin Menu ---
add_action('admin_menu', 'lollms_apps_admin_menu');
function lollms_apps_admin_menu() {
    add_menu_page(
        'LoLLMs Apps',          // Page Title
        'LoLLMs Apps',          // Menu Title
        'read',                 // Capability (allow logged-in users to see menu)
        'lollms-apps-main',     // Menu Slug
        'lollms_apps_main_page', // Function to display page content
        'dashicons-grid-view',  // Icon
        75                      // Position
    );

    // Add Submenu for Baby Caring App
    add_submenu_page(
        'lollms-apps-main',          // Parent Slug
        'Baby Caring App',           // Page Title
        'Baby Care',                 // Menu Title
        'read',                      // Capability
        'lollms-baby-app',           // Menu Slug
        'lollms_apps_render_baby_app' // Function to display Baby App
    );

    // Add Submenu for other apps similarly...
    // add_submenu_page(...)
}

// --- Page Rendering Functions ---

// Main Apps Dashboard (within WP Admin) - Could show icons linking to subpages
function lollms_apps_main_page() {
    if (!current_user_can('read')) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p>Welcome to your LoLLMs Apps Collection.</p>
        <p>Select an application from the menu on the left.</p>
        <!-- TODO: Maybe add icons/links here -->
    </div>
    <?php
}

// Function to render the Baby Caring App
function lollms_apps_render_baby_app() {
    if (!current_user_can('read')) {
        return;
    }
    // Check if user is logged in (should be if they can see admin page, but good practice)
    if (!is_user_logged_in()) {
        echo "<p>Please log in to use this application.</p>";
        // Optionally include wp_login_form();
        return;
    }

    // Enqueue necessary scripts and styles FOR THIS APP
    add_action('admin_enqueue_scripts', 'lollms_apps_enqueue_baby_scripts'); // Use admin_enqueue_scripts

    // Include the app's main view/template file
    // This file will contain the HTML structure and the Vue.js script
    // adapted to work within WordPress.
    include(LOLLMS_APPS_PLUGIN_DIR . 'apps/baby-caring-app/view.php');
}

// Enqueue Scripts/Styles specific to Baby App
function lollms_apps_enqueue_baby_scripts($hook_suffix) {
    // Only load on the specific baby app admin page
    // The hook_suffix for add_submenu_page is 'toplevel_page_lollms-apps-main_page' or 'lollms-apps_page_lollms-baby-app'
    // Check the exact hook suffix by echoing $hook_suffix on the page
    // error_log("Hook suffix: " . $hook_suffix); // Check debug.log if WP_DEBUG is on
    if ($hook_suffix != 'toplevel_page_lollms-apps-main' && !str_ends_with($hook_suffix, '_page_lollms-baby-app')) {
       return;
    }


    // Enqueue Vue.js (use WP's bundled version if available or CDN)
    // wp_enqueue_script('vuejs', 'https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js', array(), null, true);
    // Better: Check if Vue is registered, otherwise register and enqueue
     if (!wp_script_is('vue', 'registered')) {
        wp_register_script('vue', 'https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js', [], '2.6.14', true);
    }
    wp_enqueue_script('vue');


    // Enqueue Tailwind via CDN (or ideally, compile it and enqueue local CSS)
    wp_enqueue_style('tailwindcss', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css');

    // Enqueue Feather Icons
    wp_enqueue_script('feather-icons', 'https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.min.js', array(), null, true);

    // Enqueue Chart.js
     if (!wp_script_is('chartjs', 'registered')) {
        wp_register_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', [], '3.9.1', true);
    }
    wp_enqueue_script('chartjs');

    // Enqueue the Baby App's specific JS (we'll create/adapt this later)
    wp_enqueue_script(
        'lollms-baby-app-js',
        LOLLMS_APPS_PLUGIN_URL . 'apps/baby-caring-app/app.js',
        array('vue', 'chartjs'), // Dependencies
        '1.0.0', // Version
        true // Load in footer
    );

    // Pass data from PHP to JavaScript (like user ID, nonce for AJAX)
    wp_localize_script('lollms-baby-app-js', 'lollms_baby_app_data', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('lollms_baby_app_nonce'), // Create a nonce for AJAX
        'user_id' => get_current_user_id(), // Pass user ID if needed client-side
        'app_id' => 'baby_caring_app',
        // Pass initial data loaded from DB if desired (reduces initial AJAX call)
        'initial_data' => lollms_apps_load_data_php('baby_caring_app', get_current_user_id())
    ));

    // You might need to enqueue the baby app's specific CSS too
    wp_enqueue_style(
        'lollms-baby-app-css',
         LOLLMS_APPS_PLUGIN_URL . 'apps/baby-caring-app/style.css', // Assuming you create this
         array(),
        '1.0.0'
    );
}
// Hook the enqueue function correctly for admin pages
add_action('admin_enqueue_scripts', 'lollms_apps_enqueue_baby_scripts');


// --- AJAX Handlers (for saving/loading within the WP Admin context) ---

// Action for saving data
add_action('wp_ajax_lollms_save_baby_data', 'lollms_apps_ajax_save_data');
// Action for loading data (if not passing initially via wp_localize_script)
// add_action('wp_ajax_lollms_load_baby_data', 'lollms_apps_ajax_load_data');

function lollms_apps_ajax_save_data() {
    // 1. Verify Nonce
    check_ajax_referer('lollms_baby_app_nonce', 'nonce');

    // 2. Check User Permissions/Login (redundant in wp_ajax, but good practice)
    if (!is_user_logged_in()) {
        wp_send_json_error('User not logged in.', 401);
        return;
    }

    // 3. Get Data from POST request
    $app_id = isset($_POST['app_id']) ? sanitize_key($_POST['app_id']) : null;
    $data_json = isset($_POST['data']) ? wp_unslash($_POST['data']) : null; // Use wp_unslash

     if ($app_id !== 'baby_caring_app') { // Basic check
        wp_send_json_error('Invalid app ID.', 400);
        return;
    }

    // 4. Validate JSON
     if ($data_json === null || json_decode($data_json) === null && json_last_error() !== JSON_ERROR_NONE) {
         wp_send_json_error('Invalid data format provided.', 400);
         return;
     }

    // 5. Save to Database (using a helper function)
    $saved = lollms_apps_save_data_php($app_id, get_current_user_id(), $data_json);

    if ($saved) {
        wp_send_json_success('Data saved successfully.');
    } else {
        wp_send_json_error('Failed to save data.', 500);
    }
}

// --- Helper functions for DB interaction ---
function lollms_apps_save_data_php($app_id, $user_id, $data_json) {
    global $wpdb;
    $table_name = $wpdb->prefix . LOLLMS_APPS_DATA_TABLE;

    $result = $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO $table_name (user_id, app_id, data) VALUES (%d, %s, %s)
             ON DUPLICATE KEY UPDATE data = %s",
            $user_id, $app_id, $data_json, $data_json
        )
    );
    return ($result !== false);
}

function lollms_apps_load_data_php($app_id, $user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . LOLLMS_APPS_DATA_TABLE;

    $data_json = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT data FROM $table_name WHERE user_id = %d AND app_id = %s",
            $user_id, $app_id
        )
    );

    if ($data_json === null) {
        return null; // No data found
    }

    // Return the raw JSON string, let JS parse it
    // Or parse here if needed: return json_decode($data_json, true);
    return $data_json;
}


?>
