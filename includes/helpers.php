<?php
/**
 * Shared helper utilities for Sagicc Forms Manager.
 *
 * @package SagiccFormsManager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sanitize the HTML snippet that will live inside the user form.
 *
 * @param string $raw_html Raw HTML contents.
 *
 * @return string Sanitized HTML.
 */
function sagicc_forms_sanitize_form_html( $raw_html ) {
    if ( empty( $raw_html ) ) {
        return '';
    }

    $allowed_tags            = wp_kses_allowed_html( 'post' );
    $allowed_tags['style']   = array(
        'type'  => true,
        'media' => true,
    );
    $field_common_attributes = array(
        'class'            => true,
        'id'               => true,
        'style'            => true,
        'name'             => true,
        'value'            => true,
        'placeholder'      => true,
        'title'            => true,
        'required'         => true,
        'disabled'         => true,
        'readonly'         => true,
        'autocomplete'     => true,
        'maxlength'        => true,
        'minlength'        => true,
        'data-field'       => true,
        'data-label'       => true,
        'data-placeholder' => true,
        'aria-label'       => true,
        'aria-describedby' => true,
    );

    $allowed_tags['input'] = array_merge(
        $field_common_attributes,
        array(
            'type'     => true,
            'checked'  => true,
            'pattern'  => true,
            'min'      => true,
            'max'      => true,
            'step'     => true,
            'size'     => true,
            'multiple' => true,
        )
    );

    $allowed_tags['textarea'] = array_merge(
        $field_common_attributes,
        array(
            'rows' => true,
            'cols' => true,
        )
    );

    $allowed_tags['select'] = array_merge(
        $field_common_attributes,
        array(
            'multiple' => true,
            'size'     => true,
        )
    );

    $allowed_tags['option'] = array(
        'value'    => true,
        'selected' => true,
    );

    $allowed_tags['label'] = array(
        'for'   => true,
        'class' => true,
        'style' => true,
    );

    $allowed_tags['button'] = array_merge(
        $field_common_attributes,
        array(
            'type' => true,
        )
    );

    return wp_kses( $raw_html, $allowed_tags );
}

/**
 * Return the list of required Sagicc fields (name attribute => human label).
 *
 * @return array
 */
function sagicc_forms_required_fields() {
    return array(
        'nombre'   => 'Nombre',
        'apellido' => 'Apellido',
        'email'    => 'Correo',
        'telefono' => 'Telefono',
    );
}

/**
 * Find which required fields are missing from the provided HTML markup.
 *
 * @param string $html Sanitized HTML.
 *
 * @return array List of missing field labels.
 */
function sagicc_forms_find_missing_required_fields( $html ) {
    $missing   = array();
    $required  = sagicc_forms_required_fields();
    $normalized = strtolower( $html );

    foreach ( $required as $field_name => $label ) {
        $needle_double = 'name="' . strtolower( $field_name ) . '"';
        $needle_single = "name='" . strtolower( $field_name ) . "'";

        if ( false === strpos( $normalized, $needle_double ) && false === strpos( $normalized, $needle_single ) ) {
            $missing[] = $label;
        }
    }

    return $missing;
}

/**
 * Generate arithmetic captcha data tied to a nonce.
 *
 * @param string $nonce Nonce used to hash the expected result.
 *
 * @return array
 */
function sagicc_forms_generate_arithmetic_captcha( $nonce ) {
    $a = wp_rand( 1, 9 );
    $b = wp_rand( 1, 9 );

    return array(
        'a'    => $a,
        'b'    => $b,
        'hash' => wp_hash( ( $a + $b ) . '|' . $nonce ),
    );
}

/**
 * Return list of supported captcha options.
 *
 * @return array
 */
function sagicc_forms_captcha_options() {
    return array(
        'none'            => 'Sin captcha',
        'sagicc_default'  => 'Captcha nativo de Sagicc',
        'arithmetic'      => 'Captcha aritmético simple',
        'recaptcha_v3'    => 'Google reCAPTCHA v3',
    );
}

/**
 * Resolve the selected captcha type for a stored form (backward compatible).
 *
 * @param array $config Form configuration.
 *
 * @return string
 */
function sagicc_forms_resolve_captcha_type( $config ) {
    $options = array_keys( sagicc_forms_captcha_options() );

    if ( isset( $config['captcha_type'] ) && in_array( $config['captcha_type'], $options, true ) ) {
        return $config['captcha_type'];
    }

    if ( ! empty( $config['captcha'] ) ) {
        return 'arithmetic';
    }

    return 'none';
}
