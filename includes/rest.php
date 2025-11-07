<?php
/**
 * REST API endpoint that forwards submissions to Sagicc.
 *
 * @package SagiccFormsManager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'rest_api_init', 'sagicc_forms_register_rest_routes' );

/**
 * Register REST routes for the plugin.
 *
 * @return void
 */
function sagicc_forms_register_rest_routes() {
    register_rest_route(
        'sagicc/v1',
        '/submit',
        array(
            'methods'             => 'POST',
            'callback'            => 'sagicc_forms_handle_submit',
            'permission_callback' => '__return_true',
        )
    );

    register_rest_route(
        'sagicc/v1',
        '/form-security',
        array(
            'methods'             => 'GET',
            'callback'            => 'sagicc_forms_get_form_security',
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_key',
                ),
            ),
        )
    );
}

/**
 * Handle the form submission coming from the front-end shortcode.
 *
 * @param WP_REST_Request $request REST request.
 *
 * @return WP_Error|WP_REST_Response
 */
function sagicc_forms_handle_submit( WP_REST_Request $request ) {
    $form_id = isset( $_POST['_sagicc_form_id'] ) ? sanitize_key( wp_unslash( $_POST['_sagicc_form_id'] ) ) : '';
    $nonce   = isset( $_POST['_sagicc_wp_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_sagicc_wp_nonce'] ) ) : '';

    if ( empty( $form_id ) ) {
        return new WP_Error( 'missing_form_id', 'Falta el ID del formulario.', array( 'status' => 400 ) );
    }

    if ( ! wp_verify_nonce( $nonce, 'sagicc_form_nonce_' . $form_id ) ) {
        return new WP_Error( 'invalid_nonce', 'Nonce invalido.', array( 'status' => 403 ) );
    }

    $forms = sagicc_forms_get_all();

    if ( ! isset( $forms[ $form_id ] ) ) {
        return new WP_Error( 'form_not_found', 'Formulario no configurado en el servidor.', array( 'status' => 404 ) );
    }

    $config               = $forms[ $form_id ];
    $token                = $config['token'];
    $endpoint             = isset( $config['endpoint'] ) ? esc_url_raw( $config['endpoint'] ) : '';
    $captcha_type         = sagicc_forms_resolve_captcha_type( $config );
    $recaptcha_secret_key = $config['recaptcha_secret_key'] ?? '';

    if ( empty( $endpoint ) ) {
        return new WP_Error( 'missing_endpoint', 'El endpoint no esta configurado para este formulario.', array( 'status' => 500 ) );
    }

    if ( 'arithmetic' === $captcha_type ) {
        $captcha_answer = isset( $_POST['_sagicc_captcha'] ) ? trim( wp_unslash( $_POST['_sagicc_captcha'] ) ) : '';
        $captcha_hash   = isset( $_POST['_sagicc_captcha_hash'] ) ? sanitize_text_field( wp_unslash( $_POST['_sagicc_captcha_hash'] ) ) : '';

        if ( '' === $captcha_answer || '' === $captcha_hash ) {
            return new WP_Error( 'captcha_required', 'Debes completar el captcha.', array( 'status' => 400 ) );
        }

        $expected_hash     = wp_hash( $captcha_answer . '|' . $nonce );
        $hashes_are_equal = function_exists( 'hash_equals' ) ? hash_equals( $captcha_hash, $expected_hash ) : ( $captcha_hash === $expected_hash );

        if ( ! $hashes_are_equal ) {
            return new WP_Error( 'captcha_failed', 'Captcha incorrecto.', array( 'status' => 400 ) );
        }
    } elseif ( 'recaptcha_v3' === $captcha_type ) {
        $recaptcha_token = isset( $_POST['_sagicc_recaptcha_token'] ) ? sanitize_text_field( wp_unslash( $_POST['_sagicc_recaptcha_token'] ) ) : '';

        if ( empty( $recaptcha_secret_key ) ) {
            return new WP_Error( 'recaptcha_not_configured', 'reCAPTCHA no esta configurado para este formulario.', array( 'status' => 500 ) );
        }

        if ( empty( $recaptcha_token ) ) {
            return new WP_Error( 'recaptcha_missing', 'No se recibio el token de reCAPTCHA.', array( 'status' => 400 ) );
        }

        $verify_response = wp_remote_post(
            'https://www.google.com/recaptcha/api/siteverify',
            array(
                'timeout' => 10,
                'body'    => array(
                    'secret'   => $recaptcha_secret_key,
                    'response' => $recaptcha_token,
                    'remoteip' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
                ),
            )
        );

        if ( is_wp_error( $verify_response ) ) {
            return new WP_Error( 'recaptcha_unreachable', 'No se pudo validar reCAPTCHA: ' . $verify_response->get_error_message(), array( 'status' => 500 ) );
        }

        $body = json_decode( wp_remote_retrieve_body( $verify_response ), true );
        if ( empty( $body['success'] ) ) {
            $error_codes = isset( $body['error-codes'] ) ? implode( ', ', (array) $body['error-codes'] ) : 'desconocido';
            return new WP_Error( 'recaptcha_failed', 'reCAPTCHA invalidado: ' . $error_codes, array( 'status' => 400 ) );
        }
    }

    $ip            = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
    $transient_key = 'sagicc_send_lock_' . md5( $ip . '_' . $form_id );

    if ( get_transient( $transient_key ) ) {
        return new WP_Error( 'too_many_requests', 'Demasiadas solicitudes. Intenta de nuevo en unos segundos.', array( 'status' => 429 ) );
    }
    set_transient( $transient_key, true, 10 );

    $post_fields = array(
        'sagicc_token' => $token,
    );

    foreach ( $_POST as $key => $value ) {
        if ( in_array( $key, array( '_sagicc_form_id', '_sagicc_wp_nonce', '_sagicc_captcha', '_sagicc_captcha_hash', '_sagicc_recaptcha_token' ), true ) ) {
            continue;
        }

        if ( is_array( $value ) ) {
            $post_fields[ $key ] = array_map( 'sanitize_text_field', wp_unslash( $value ) );
        } else {
            $post_fields[ $key ] = sanitize_text_field( wp_unslash( $value ) );
        }
    }

    $max_size_bytes = 5 * 1024 * 1024;

    if ( ! empty( $_FILES ) ) {
        foreach ( $_FILES as $field => $fileinfo ) {
            if ( is_array( $fileinfo['name'] ) ) {
                $count = count( $fileinfo['name'] );
                for ( $i = 0; $i < $count; $i++ ) {
                    if ( UPLOAD_ERR_OK === $fileinfo['error'][ $i ] ) {
                        $tmp_name = $fileinfo['tmp_name'][ $i ];
                        $name     = $fileinfo['name'][ $i ];
                        $type     = $fileinfo['type'][ $i ];
                        $size     = (int) $fileinfo['size'][ $i ];

                        if ( $size > $max_size_bytes ) {
                            return new WP_Error( 'file_too_large', 'Uno de los archivos excede el tamano maximo permitido (5 MB).', array( 'status' => 400 ) );
                        }

                        $post_fields[ $field . '[' . $i . ']' ] = new CURLFile( $tmp_name, $type, $name );
                    }
                }
            } else {
                if ( UPLOAD_ERR_OK === $fileinfo['error'] ) {
                    $tmp_name = $fileinfo['tmp_name'];
                    $name     = $fileinfo['name'];
                    $type     = $fileinfo['type'];
                    $size     = (int) $fileinfo['size'];

                    if ( $size > $max_size_bytes ) {
                        return new WP_Error( 'file_too_large', 'El archivo excede el tamano maximo permitido (5 MB).', array( 'status' => 400 ) );
                    }

                    $post_fields[ $field ] = new CURLFile( $tmp_name, $type, $name );
                }
            }
        }
    }

    $ch = curl_init();
    curl_setopt( $ch, CURLOPT_URL, $endpoint );
    curl_setopt( $ch, CURLOPT_POST, true );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $post_fields );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
    curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );

    $resp      = curl_exec( $ch );
    $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
    $curl_err  = curl_error( $ch );
    curl_close( $ch );

    if ( 200 === (int) $http_code ) {
        return rest_ensure_response(
            array(
                'success' => true,
                'message' => 'Enviado correctamente a Sagicc.',
                'data'    => $resp,
            )
        );
    }

    return new WP_Error(
        'sagicc_error',
        'Error al enviar al servidor externo: ' . ( $curl_err ? $curl_err : 'HTTP ' . $http_code ),
        array(
            'status'  => 500,
            'details' => $resp,
        )
    );
}

/**
 * Provide fresh nonce and captcha data for a given form.
 *
 * @param WP_REST_Request $request REST request.
 *
 * @return WP_Error|WP_REST_Response
 */
function sagicc_forms_get_form_security( WP_REST_Request $request ) {
    $form_id = sanitize_key( $request->get_param( 'id' ) );

    if ( empty( $form_id ) ) {
        return new WP_Error( 'missing_form_id', 'Falta el ID del formulario.', array( 'status' => 400 ) );
    }

    $forms = sagicc_forms_get_all();

    if ( ! isset( $forms[ $form_id ] ) ) {
        return new WP_Error( 'form_not_found', 'Formulario no configurado en el servidor.', array( 'status' => 404 ) );
    }

    $config       = $forms[ $form_id ];
    $nonce        = wp_create_nonce( 'sagicc_form_nonce_' . $form_id );
    $captcha_type = sagicc_forms_resolve_captcha_type( $config );
    $payload      = array(
        'nonce'   => $nonce,
        'captcha' => null,
    );

    if ( 'arithmetic' === $captcha_type ) {
        $captcha              = sagicc_forms_generate_arithmetic_captcha( $nonce );
        $payload['captcha']   = array(
            'type' => 'arithmetic',
            'a'    => $captcha['a'],
            'b'    => $captcha['b'],
            'hash' => $captcha['hash'],
        );
    } else {
        $payload['captcha'] = array(
            'type' => $captcha_type,
        );
    }

    return rest_ensure_response( $payload );
}
