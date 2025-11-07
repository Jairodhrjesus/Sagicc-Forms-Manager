<?php
/**
 * Persistence helpers for Sagicc Forms Manager.
 *
 * @package SagiccFormsManager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retrieve all stored forms from WordPress options.
 *
 * @return array
 */
function sagicc_forms_get_all() {
    $forms = get_option( 'sagicc_forms_config', array() );

    if ( ! is_array( $forms ) ) {
        $forms = array();
    }

    return $forms;
}

/**
 * Persist the entire forms configuration array.
 *
 * @param array $forms Forms configuration.
 *
 * @return void
 */
function sagicc_forms_save_all( $forms ) {
    if ( ! is_array( $forms ) ) {
        $forms = array();
    }

    update_option( 'sagicc_forms_config', $forms );
}
