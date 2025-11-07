<?php
/**
 * Plugin Name: Sagicc Forms Manager
 * Description: Gestiona multiples formularios que envian datos a Sagicc, ocultando el token en el servidor.
 * Version: 1.1.0
 * Author: Jairo Hurtado - Marketing Team
 *
 * @package SagiccFormsManager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SAGICC_FORMS_MANAGER_VERSION', '1.1.0' );

if ( ! defined( 'SAGICC_FORMS_MANAGER_DIR' ) ) {
    define( 'SAGICC_FORMS_MANAGER_DIR', plugin_dir_path( __FILE__ ) );
}

require_once SAGICC_FORMS_MANAGER_DIR . 'includes/helpers.php';
require_once SAGICC_FORMS_MANAGER_DIR . 'includes/storage.php';
require_once SAGICC_FORMS_MANAGER_DIR . 'includes/admin-page.php';
require_once SAGICC_FORMS_MANAGER_DIR . 'includes/shortcode.php';
require_once SAGICC_FORMS_MANAGER_DIR . 'includes/rest.php';
