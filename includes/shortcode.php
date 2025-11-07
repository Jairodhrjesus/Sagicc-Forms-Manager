<?php
/**
 * Shortcode that renders Sagicc forms on the front-end.
 *
 * @package SagiccFormsManager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode [sagicc_form id="contacto_web"].
 *
 * @param array $atts Shortcode attributes.
 *
 * @return string
 */
function sagicc_form_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'id' => '',
        ),
        $atts,
        'sagicc_form'
    );

    $form_id = sanitize_key( $atts['id'] );

    if ( empty( $form_id ) ) {
        return '<p><strong>Sagicc Forms:</strong> Debes especificar un ID en el shortcode, por ejemplo <code>[sagicc_form id="contacto_web"]</code>.</p>';
    }

    $forms = sagicc_forms_get_all();

    if ( ! isset( $forms[ $form_id ] ) ) {
        return '<p><strong>Sagicc Forms:</strong> Formulario no encontrado para el ID <code>' . esc_html( $form_id ) . '</code>.</p>';
    }

    $config               = $forms[ $form_id ];
    $html                 = isset( $config['html'] ) ? $config['html'] : '';
    $css                  = isset( $config['css'] ) ? $config['css'] : '';
    $js                   = isset( $config['js'] ) ? $config['js'] : '';
    $captcha_type         = sagicc_forms_resolve_captcha_type( $config );
    $recaptcha_site_key   = $config['recaptcha_site_key'] ?? '';
    $nonce                = wp_create_nonce( 'sagicc_form_nonce_' . $form_id );
    $form_dom             = 'sagicc-form-' . $form_id;
    $msg_dom              = 'sagicc-form-msg-' . $form_id;
    $captcha_dom          = 'sagicc-form-captcha-' . $form_id;
    $recaptcha_input_dom  = 'sagicc-form-recaptcha-' . $form_id;
    $security_endpoint    = esc_url_raw(
        add_query_arg(
            'id',
            $form_id,
            rest_url( 'sagicc/v1/form-security' )
        )
    );
    $captcha_hash         = '';
    $captcha_a            = 0;
    $captcha_b            = 0;

    if ( 'arithmetic' === $captcha_type ) {
        $captcha_data = sagicc_forms_generate_arithmetic_captcha( $nonce );
        $captcha_a    = $captcha_data['a'];
        $captcha_b    = $captcha_data['b'];
        $captcha_hash = $captcha_data['hash'];
    }

    ob_start();
    ?>
    <?php if ( ! empty( $css ) ) : ?>
        <style id="sagicc-form-style-<?php echo esc_attr( $form_id ); ?>">
            <?php echo $css; ?>
        </style>
    <?php endif; ?>
    <form id="<?php echo esc_attr( $form_dom ); ?>"
          class="sagicc-form"
          data-sagicc-form-id="<?php echo esc_attr( $form_id ); ?>"
          enctype="multipart/form-data">

        <input type="hidden" name="_sagicc_form_id" value="<?php echo esc_attr( $form_id ); ?>">
        <input type="hidden" name="_sagicc_wp_nonce" value="<?php echo esc_attr( $nonce ); ?>">

        <?php
        // HTML que el usuario pego (interno del formulario).
        echo $html; // ya fue sanitizado al guardar.
        ?>
        <?php if ( 'arithmetic' === $captcha_type ) : ?>
            <div class="sagicc-form-captcha" data-sagicc-captcha-type="arithmetic">
                <label for="<?php echo esc_attr( $captcha_dom ); ?>" class="sagicc-captcha-label">
                    <span data-sagicc-captcha-question>Captcha: &iquest;Cuanto es <?php echo esc_html( $captcha_a ); ?> + <?php echo esc_html( $captcha_b ); ?>?</span>
                </label>
                <input type="number"
                       name="_sagicc_captcha"
                       id="<?php echo esc_attr( $captcha_dom ); ?>"
                       inputmode="numeric"
                       min="0"
                       step="1"
                       required>
                <input type="hidden" name="_sagicc_captcha_hash" id="<?php echo esc_attr( $captcha_dom ); ?>-hash" value="<?php echo esc_attr( $captcha_hash ); ?>">
            </div>
        <?php endif; ?>
        <?php if ( 'recaptcha_v3' === $captcha_type && ! empty( $recaptcha_site_key ) ) : ?>
            <input type="hidden" name="_sagicc_recaptcha_token" id="<?php echo esc_attr( $recaptcha_input_dom ); ?>" value="">
        <?php endif; ?>
    </form>

    <div id="<?php echo esc_attr( $msg_dom ); ?>" class="sagicc-form-message" data-sagicc-form-id="<?php echo esc_attr( $form_id ); ?>"></div>

    <script>
    (function(){
        const form   = document.getElementById('<?php echo esc_js( $form_dom ); ?>');
        const msgBox = document.getElementById('<?php echo esc_js( $msg_dom ); ?>');
        const captchaType = '<?php echo esc_js( $captcha_type ); ?>';
        const recaptchaSiteKey = '<?php echo esc_js( $recaptcha_site_key ); ?>';
        const recaptchaInput = document.getElementById('<?php echo esc_js( $recaptcha_input_dom ); ?>');
        const nonceInput = form ? form.querySelector('input[name="_sagicc_wp_nonce"]') : null;
        const captchaHashInput = document.getElementById('<?php echo esc_js( $captcha_dom ); ?>-hash');
        const captchaQuestionNode = form ? form.querySelector('[data-sagicc-captcha-question]') : null;
        const securityEndpoint = '<?php echo esc_url_raw( $security_endpoint ); ?>';

        if (!form) return;

        function setMessage(text, ok) {
            if (!msgBox) return;
            msgBox.textContent = text;
            msgBox.style.color = ok ? 'green' : 'red';
        }

        function applySecurityPayload(payload) {
            if (payload && payload.nonce && nonceInput) {
                nonceInput.value = payload.nonce;
            }

            if (
                captchaType === 'arithmetic' &&
                payload &&
                payload.captcha &&
                payload.captcha.hash &&
                captchaHashInput
            ) {
                captchaHashInput.value = payload.captcha.hash;
                if (captchaQuestionNode && typeof payload.captcha.a !== 'undefined') {
                    captchaQuestionNode.textContent = 'Captcha: \\u00BFCuanto es ' + payload.captcha.a + ' + ' + payload.captcha.b + '?';
                }
            }
        }

        function refreshSecurityFields() {
            if (!securityEndpoint) {
                return Promise.resolve();
            }

            return fetch(securityEndpoint, {
                method: 'GET',
                credentials: 'same-origin'
            })
            .then(function(response){
                if (!response.ok) {
                    throw new Error('security_fetch_failed');
                }
                return response.json();
            })
            .then(function(data){
                applySecurityPayload(data || {});
            })
            .catch(function(){
                // Ignore errors silently; fallback to existing values.
            });
        }

        refreshSecurityFields();

        function sendForm() {
            const fd = new FormData(form);

            setMessage('Enviando...', true);

            fetch('<?php echo esc_url( rest_url( 'sagicc/v1/submit' ) ); ?>', {
                method: 'POST',
                credentials: 'same-origin',
                body: fd
            })
            .then(r => r.json())
            .then(data => {
                if (data && data.success) {
                    setMessage('Mensaje enviado correctamente.', true);
                    form.reset();
                } else {
                    const err = data && (data.message || data.data) ? (data.message || data.data) : 'Algo salio mal.';
                    setMessage('Error: ' + err, false);
                }
            })
            .catch(err => {
                setMessage('Error: ' + err, false);
            });
        }

        form.addEventListener('submit', function(e){
            e.preventDefault();

            refreshSecurityFields().then(function(){
                if (captchaType === 'recaptcha_v3' && recaptchaSiteKey) {
                    if (typeof grecaptcha === 'undefined' || !grecaptcha.execute) {
                        setMessage('Error: reCAPTCHA no está disponible.', false);
                        return;
                    }
                    grecaptcha.ready(function(){
                        grecaptcha.execute(recaptchaSiteKey, {action: 'sagicc_form'}).then(function(token){
                            if (recaptchaInput) {
                                recaptchaInput.value = token;
                            }
                            sendForm();
                        }).catch(function(err){
                            setMessage('Error reCAPTCHA: ' + err, false);
                        });
                    });
                    return;
                }

                sendForm();
            });
        });
    })();
    </script>
    <?php if ( 'recaptcha_v3' === $captcha_type && ! empty( $recaptcha_site_key ) ) : ?>
        <script src="https://www.google.com/recaptcha/api.js?render=<?php echo esc_attr( $recaptcha_site_key ); ?>" async defer></script>
    <?php endif; ?>
    <?php if ( ! empty( $js ) ) : ?>
        <script id="sagicc-form-js-<?php echo esc_attr( $form_id ); ?>">
            <?php echo $js; ?>
        </script>
    <?php endif; ?>
    <?php

    return ob_get_clean();
}
add_shortcode( 'sagicc_form', 'sagicc_form_shortcode' );

