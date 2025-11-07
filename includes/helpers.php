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
 * Catalog of built-in visual templates for guided mode.
 *
 * @return array
 */
function sagicc_forms_get_templates() {
    return array(
        'base'      => array(
            'name'        => 'Formulario Base',
            'description' => 'Campos esenciales para contacto general.',
            'html'        => '<div class="field">
    <label for="base-nombre">Nombre*</label>
    <input type="text" id="base-nombre" name="nombre" required>
</div>
<div class="field">
    <label for="base-apellido">Apellido*</label>
    <input type="text" id="base-apellido" name="apellido" required>
</div>
<div class="field">
    <label for="base-email">Correo*</label>
    <input type="email" id="base-email" name="email" required>
</div>
<div class="field">
    <label for="base-telefono">Tel&eacute;fono*</label>
    <input type="text" id="base-telefono" name="telefono" required>
</div>
<div class="field">
    <label for="base-mensaje">Mensaje</label>
    <textarea id="base-mensaje" name="mensaje" rows="4"></textarea>
</div>
<button type="submit" class="sagicc-btn-primary">Enviar</button>',
            'css'         => 'body { font-family:\"Nunito Sans\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Arial, sans-serif; }
.field { margin-bottom: 16px; }
.field label { display:block; font-weight:600; margin-bottom:6px; color:#202124; }
.field input,
.field textarea { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:8px; font-size:15px; background:#fff; color:#202124; }
.field input:focus,
.field textarea:focus { outline:none; border-color:#5f6368; box-shadow:0 0 0 1px #5f6368; }
.sagicc-btn-primary { background:#5f6368; color:#fff; border:none; padding:12px 28px; border-radius:24px; cursor:pointer; font-weight:600; }',
            'js'          => '',
        ),
        'pqrs'      => array(
            'name'        => 'Formulario PQRS',
            'description' => 'Optimizado para peticiones, quejas, reclamos o sugerencias.',
            'html'        => '<div class="row">
    <div class="field">
        <label for="pqrs-nombre">Nombre*</label>
        <input type="text" id="pqrs-nombre" name="nombre" required>
    </div>
    <div class="field">
        <label for="pqrs-apellido">Apellido*</label>
        <input type="text" id="pqrs-apellido" name="apellido" required>
    </div>
</div>
<div class="field">
    <label for="pqrs-email">Correo*</label>
    <input type="email" id="pqrs-email" name="email" required>
</div>
<div class="field">
    <label for="pqrs-telefono">Tel&eacute;fono*</label>
    <input type="text" id="pqrs-telefono" name="telefono" required>
</div>
<div class="field">
    <label for="pqrs-tipo">Tipo de solicitud*</label>
    <select id="pqrs-tipo" name="tipo_solicitud" required>
        <option value="">Selecciona una opci&oacute;n</option>
        <option value="peticion">Petici&oacute;n</option>
        <option value="queja">Queja</option>
        <option value="reclamo">Reclamo</option>
        <option value="sugerencia">Sugerencia</option>
    </select>
</div>
<div class="field">
    <label for="pqrs-mensaje">Detalle*</label>
    <textarea id="pqrs-mensaje" name="mensaje" rows="5" required></textarea>
</div>
<button type="submit" class="sagicc-btn-primary">Enviar solicitud</button>',
            'css'         => 'body { font-family:\"Nunito Sans\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Arial, sans-serif; }
.row { display:flex; gap:12px; flex-wrap:wrap; }
.field { flex:1; min-width:220px; margin-bottom:14px; }
.field label { display:block; font-size:14px; font-weight:600; margin-bottom:4px; color:#202124; }
.field input,
.field textarea,
.field select { width:100%; padding:10px 12px; border:1px solid #d6dae0; border-radius:8px; background:#fff; color:#202124; }
.field select { background:#fff; }
.sagicc-btn-primary { background:#5f6368; color:#fff; border:none; padding:12px 24px; border-radius:24px; cursor:pointer; font-weight:600; }',
            'js'          => '',
        ),
        'marketing' => array(
            'name'        => 'Formulario Marketing Lead',
            'description' => 'Enfocado en captar leads de campa&ntilde;as digitales.',
            'html'        => '<div class="field">
    <label for="mkt-nombre">Nombre*</label>
    <input type="text" id="mkt-nombre" name="nombre" required>
</div>
<div class="field">
    <label for="mkt-apellido">Apellido*</label>
    <input type="text" id="mkt-apellido" name="apellido" required>
</div>
<div class="field">
    <label for="mkt-email">Correo corporativo*</label>
    <input type="email" id="mkt-email" name="email" required>
</div>
<div class="field">
    <label for="mkt-telefono">Tel&eacute;fono*</label>
    <input type="text" id="mkt-telefono" name="telefono" required>
</div>
<div class="field">
    <label for="mkt-empresa">Empresa</label>
    <input type="text" id="mkt-empresa" name="empresa">
</div>
<div class="field">
    <label for="mkt-servicio">Servicio de inter&eacute;s</label>
    <select id="mkt-servicio" name="servicio_interes">
        <option value="">Selecciona un servicio</option>
        <option value="implementacion">Implementaci&oacute;n</option>
        <option value="soporte">Soporte</option>
        <option value="consultoria">Consultor&iacute;a</option>
    </select>
</div>
<div class="field">
    <label class="consent">
        <input type="checkbox" name="acepta_marketing" value="si">
        Deseo recibir informaci&oacute;n comercial.
    </label>
</div>
<button type="submit" class="sagicc-btn-primary">Quiero m&aacute;s informaci&oacute;n</button>',
            'css'         => 'body { font-family:\"Nunito Sans\", -apple-system, BlinkMacSystemFont, \"Segoe UI\", Arial, sans-serif; }
.field { margin-bottom:15px; }
.field label { display:block; font-size:14px; margin-bottom:6px; font-weight:500; color:#202124; }
.field input,
.field textarea,
.field select { width:100%; padding:11px 13px; border:1px solid #cfd3d7; border-radius:8px; background:#fff; color:#202124; }
.consent { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:500; color:#202124; }
.sagicc-btn-primary { width:100%; background:#5f6368; color:#fff; border:none; padding:14px; border-radius:30px; font-size:15px; font-weight:600; cursor:pointer; letter-spacing:.5px; }',
            'js'          => '',
        ),
    );
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
