<?php
/**
 * Admin screen and form management.
 *
 * @package SagiccFormsManager
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the top-level admin menu entry.
 *
 * @return void
 */
function sagicc_forms_register_admin_menu() {
    add_menu_page(
        'Sagicc Forms',
        'Sagicc Forms',
        'manage_options',
        'sagicc-forms',
        'sagicc_forms_admin_page',
        'dashicons-feedback',
        65
    );
}
add_action( 'admin_menu', 'sagicc_forms_register_admin_menu' );

/**
 * Render the administration page where forms are created/edited.
 *
 * @return void
 */
function sagicc_forms_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $forms       = sagicc_forms_get_all();
    $action      = isset( $_GET['form_action'] ) ? sanitize_key( wp_unslash( $_GET['form_action'] ) ) : 'list';
    $current_id  = isset( $_GET['form_id'] ) ? sanitize_key( wp_unslash( $_GET['form_id'] ) ) : '';
    $search_term = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
    $editing     = ( 'edit' === $action && $current_id && isset( $forms[ $current_id ] ) ) ? $forms[ $current_id ] : null;
    $notices     = array();
    $missing_required_fields = array();

    if ( isset( $_GET['sagicc_forms_action'] ) && 'delete_form' === $_GET['sagicc_forms_action'] ) {
        check_admin_referer( 'sagicc_forms_delete_form' );
        $delete_id = isset( $_GET['delete_form_id'] ) ? sanitize_key( wp_unslash( $_GET['delete_form_id'] ) ) : '';

        if ( $delete_id && isset( $forms[ $delete_id ] ) ) {
            unset( $forms[ $delete_id ] );
            sagicc_forms_save_all( $forms );
            $redirect_url = add_query_arg(
                array(
                    'page'    => 'sagicc-forms',
                    'deleted' => 1,
                ),
                admin_url( 'admin.php' )
            );
        } else {
            $redirect_url = add_query_arg(
                array(
                    'page' => 'sagicc-forms',
                ),
                admin_url( 'admin.php' )
            );
        }

        wp_safe_redirect( $redirect_url );
        exit;
    }

    if ( isset( $_POST['sagicc_forms_action'] ) ) {
        $task = sanitize_text_field( wp_unslash( $_POST['sagicc_forms_action'] ) );

        if ( 'save_form' === $task ) {
            check_admin_referer( 'sagicc_forms_save_form' );

            $form_id   = isset( $_POST['form_id'] ) ? sanitize_key( $_POST['form_id'] ) : '';
            $form_name = isset( $_POST['form_name'] ) ? sanitize_text_field( wp_unslash( $_POST['form_name'] ) ) : '';
            $token     = isset( $_POST['form_token'] ) ? trim( wp_unslash( $_POST['form_token'] ) ) : '';
            $endpoint  = isset( $_POST['form_endpoint'] ) ? esc_url_raw( wp_unslash( $_POST['form_endpoint'] ) ) : '';
            $html      = isset( $_POST['form_html'] ) ? sagicc_forms_sanitize_form_html( wp_unslash( $_POST['form_html'] ) ) : '';
            $css       = isset( $_POST['form_css'] ) ? sanitize_textarea_field( wp_unslash( $_POST['form_css'] ) ) : '';
            $js        = isset( $_POST['form_js'] ) ? sanitize_textarea_field( wp_unslash( $_POST['form_js'] ) ) : '';
            $captcha_options = sagicc_forms_captcha_options();
            $captcha_type = isset( $_POST['form_captcha_type'] ) ? sanitize_key( $_POST['form_captcha_type'] ) : 'none';
            if ( ! isset( $captcha_options[ $captcha_type ] ) ) {
                $captcha_type = 'none';
            }
            $recaptcha_site_key   = isset( $_POST['form_recaptcha_site_key'] ) ? sanitize_text_field( wp_unslash( $_POST['form_recaptcha_site_key'] ) ) : '';
            $recaptcha_secret_key = isset( $_POST['form_recaptcha_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['form_recaptcha_secret_key'] ) ) : '';

            if ( empty( $form_id ) ) {
                $notices[] = array(
                    'type'    => 'error',
                    'message' => 'Debes especificar un ID de formulario (solo letras, numeros y guiones bajos).',
                );
            } elseif ( empty( $token ) ) {
                $notices[] = array(
                    'type'    => 'error',
                    'message' => 'Debes especificar un token de Sagicc.',
                );
            } elseif ( empty( $endpoint ) ) {
                $notices[] = array(
                    'type'    => 'error',
                    'message' => 'Debes especificar el endpoint de Sagicc.',
                );
            } elseif ( 'recaptcha_v3' === $captcha_type && ( empty( $recaptcha_site_key ) || empty( $recaptcha_secret_key ) ) ) {
                $notices[] = array(
                    'type'    => 'error',
                    'message' => 'Para usar Google reCAPTCHA v3 debes configurar el Site Key y Secret Key.',
                );
            } else {
                $forms[ $form_id ] = array(
                    'name'     => $form_name ? $form_name : $form_id,
                    'token'    => $token,
                    'endpoint' => $endpoint,
                    'html'     => $html,
                    'css'      => $css,
                    'js'       => $js,
                    'captcha'  => 'arithmetic' === $captcha_type ? 1 : 0, // backward compatibility.
                    'captcha_type'          => $captcha_type,
                    'recaptcha_site_key'    => 'recaptcha_v3' === $captcha_type ? $recaptcha_site_key : '',
                    'recaptcha_secret_key'  => 'recaptcha_v3' === $captcha_type ? $recaptcha_secret_key : '',
                );

                sagicc_forms_save_all( $forms );

                $notices[] = array(
                    'type'    => 'success',
                    'message' => 'Formulario guardado correctamente.',
                );

                $current_id = $form_id;
                $editing    = $forms[ $form_id ];
                $action     = 'edit';

                $missing_required_fields = sagicc_forms_find_missing_required_fields( $html );
                if ( ! empty( $missing_required_fields ) ) {
                    $notices[] = array(
                        'type'    => 'warning',
                        'message' => 'Faltan campos obligatorios en el HTML: ' . implode( ', ', $missing_required_fields ) . '.',
                    );
                }
            }
        }

        if ( 'delete_form' === $task ) {
            check_admin_referer( 'sagicc_forms_delete_form' );
            $delete_id = isset( $_POST['delete_form_id'] ) ? sanitize_key( $_POST['delete_form_id'] ) : '';

            if ( $delete_id && isset( $forms[ $delete_id ] ) ) {
                unset( $forms[ $delete_id ] );
                sagicc_forms_save_all( $forms );
                $notices[] = array(
                    'type'    => 'success',
                    'message' => 'Formulario eliminado.',
                );
            }

            $forms      = sagicc_forms_get_all();
            $current_id = '';
            $editing    = null;
            $action     = 'list';
        }

        if ( 'bulk_forms' === $task ) {
            check_admin_referer( 'sagicc_forms_bulk_action' );
            $bulk_action = isset( $_POST['bulk_action'] ) ? sanitize_key( $_POST['bulk_action'] ) : ( isset( $_POST['bulk_action_bottom'] ) ? sanitize_key( $_POST['bulk_action_bottom'] ) : '-1' );
            $selected    = isset( $_POST['form_ids'] ) ? array_map( 'sanitize_key', (array) $_POST['form_ids'] ) : array();

            if ( 'delete' === $bulk_action && ! empty( $selected ) ) {
                $deleted = 0;
                foreach ( $selected as $form_id ) {
                    if ( isset( $forms[ $form_id ] ) ) {
                        unset( $forms[ $form_id ] );
                        $deleted++;
                    }
                }

                if ( $deleted > 0 ) {
                    sagicc_forms_save_all( $forms );
                    $notices[] = array(
                        'type'    => 'success',
                        'message' => sprintf( 'Se eliminaron %d formularios.', $deleted ),
                    );
                } else {
                    $notices[] = array(
                        'type'    => 'info',
                        'message' => 'No se encontraron formularios para la acci&oacute;n seleccionada.',
                    );
                }
            } else {
                $notices[] = array(
                    'type'    => 'info',
                    'message' => 'Selecciona formularios y una acci&oacute;n v&aacute;lida para continuar.',
                );
            }
        }
    }

    // Refresh forms after any changes.
    $forms = sagicc_forms_get_all();
    $filtered_forms = sagicc_forms_filter_forms( $forms, $search_term );

    if ( 'edit' === $action && ( ! $current_id || ! isset( $forms[ $current_id ] ) ) ) {
        $action  = 'add';
        $editing = null;
    }

    if ( isset( $_GET['deleted'] ) && '1' === $_GET['deleted'] ) {
        $notices[] = array(
            'type'    => 'success',
            'message' => 'Formulario eliminado correctamente.',
        );
    }

    ?>
    <div class="wrap">
        <?php if ( 'list' === $action ) : ?>
            <h1 class="wp-heading-inline">Sagicc Forms</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sagicc-forms&form_action=add' ) ); ?>" class="page-title-action">Add Form</a>
            <hr class="wp-header-end">
        <?php else : ?>
            <h1 class="wp-heading-inline"><?php echo $editing ? 'Editar formulario' : 'Crear nuevo formulario'; ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sagicc-forms' ) ); ?>" class="page-title-action">Volver a la lista</a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sagicc-forms&form_action=add' ) ); ?>" class="page-title-action">Add Form</a>
            <hr class="wp-header-end">
        <?php endif; ?>

        <?php foreach ( $notices as $notice ) : ?>
            <div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible">
                <p><?php echo esc_html( $notice['message'] ); ?></p>
            </div>
        <?php endforeach; ?>

        <?php
        if ( 'list' === $action ) {
            sagicc_forms_render_list_table( $filtered_forms, $search_term );
        } else {
            sagicc_forms_render_form_editor( $current_id, $editing );
        }
        ?>
    </div>
    <script>
    (function(){
        document.addEventListener('click', function(event){
            const btn = event.target.closest('.sagicc-copy-shortcode');
            if (!btn) {
                return;
            }
            event.preventDefault();
            const shortcode = btn.getAttribute('data-shortcode');
            if (!shortcode) {
                return;
            }

            function markCopied(success) {
                const original = btn.textContent;
                btn.textContent = success ? 'Copiado' : 'Error';
                btn.disabled = true;
                setTimeout(function(){
                    btn.textContent = original;
                    btn.disabled = false;
                }, 2000);
            }

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(shortcode).then(function(){
                    markCopied(true);
                }).catch(function(){
                    markCopied(false);
                });
                return;
            }

            const temp = document.createElement('textarea');
            temp.value = shortcode;
            document.body.appendChild(temp);
            temp.select();
            try {
                document.execCommand('copy');
                markCopied(true);
            } catch (err) {
                markCopied(false);
            }
            document.body.removeChild(temp);
        });

        document.querySelectorAll('.sagicc-select-all').forEach(function(masterCheckbox){
            const targetSelector = masterCheckbox.getAttribute('data-target');
            masterCheckbox.addEventListener('change', function(){
                if (!targetSelector) {
                    return;
                }
                const targets = document.querySelectorAll(targetSelector);
                targets.forEach(function(checkbox){
                    checkbox.checked = masterCheckbox.checked;
                });
            });
        });

        const captchaSelect = document.getElementById('form_captcha_type');
        const recaptchaFields = document.getElementById('sagicc-recaptcha-fields');
        function toggleRecaptchaFields() {
            if (!recaptchaFields || !captchaSelect) {
                return;
            }
            recaptchaFields.style.display = captchaSelect.value === 'recaptcha_v3' ? 'block' : 'none';
        }
        if (captchaSelect) {
            captchaSelect.addEventListener('change', toggleRecaptchaFields);
            toggleRecaptchaFields();
        }
    })();
    </script>
    <?php
}

/**
 * Render the list of registered forms in a table similar to the Posts list.
 *
 * @param array $forms Stored forms.
 *
 * @return void
 */
/**
 * Filter forms by search keyword (matching ID or name).
 *
 * @param array  $forms       Forms array.
 * @param string $search_term Term to filter.
 *
 * @return array
 */
function sagicc_forms_filter_forms( $forms, $search_term ) {
    if ( '' === $search_term ) {
        return $forms;
    }

    $filtered = array();
    foreach ( $forms as $fid => $config ) {
        $name = $config['name'] ?? $fid;
        if ( false !== stripos( $fid, $search_term ) || false !== stripos( $name, $search_term ) ) {
            $filtered[ $fid ] = $config;
        }
    }

    return $filtered;
}

function sagicc_forms_render_list_table( $forms, $search_term ) {
    $count = count( $forms );
    ?>
    <form method="get">
        <input type="hidden" name="page" value="sagicc-forms">
        <input type="hidden" name="form_action" value="list">
        <p class="search-box">
            <label class="screen-reader-text" for="sagicc-form-search-input">Buscar formularios:</label>
            <input type="search" id="sagicc-form-search-input" name="s" value="<?php echo esc_attr( $search_term ); ?>" placeholder="Buscar por ID o nombre">
            <?php submit_button( 'Buscar formularios', 'secondary', '', false ); ?>
            <?php if ( ! empty( $search_term ) ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sagicc-forms' ) ); ?>" class="button-link">Limpiar b&uacute;squeda</a>
            <?php endif; ?>
        </p>
    </form>

    <form method="post">
        <?php wp_nonce_field( 'sagicc_forms_bulk_action' ); ?>
        <input type="hidden" name="sagicc_forms_action" value="bulk_forms">
        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-top" class="screen-reader-text">Seleccionar acci&oacute;n masiva</label>
                <select name="bulk_action" id="bulk-action-selector-top">
                    <option value="-1">Acciones en lote</option>
                    <option value="delete">Eliminar</option>
                </select>
                <?php submit_button( 'Aplicar', 'secondary', 'bulk_apply_top', false ); ?>
            </div>
            <div class="tablenav-pages one-page">
                <span class="displaying-num"><?php echo esc_html( $count ); ?> formularios</span>
            </div>
            <br class="clear">
        </div>

        <table class="wp-list-table widefat fixed striped table-view-list">
            <thead>
            <tr>
                <td id="cb" class="manage-column column-cb check-column">
                    <input type="checkbox" class="sagicc-select-all" data-target=".sagicc-form-checkbox">
                </td>
                <th scope="col" class="manage-column column-primary">Formulario</th>
                <th scope="col">ID</th>
                <th scope="col">Endpoint</th>
                <th scope="col">Shortcode</th>
                <th scope="col">Captcha</th>
            </tr>
            </thead>
            <tbody>
            <?php if ( empty( $forms ) ) : ?>
                <tr>
                    <td colspan="6">A&uacute;n no has creado formularios. Usa el bot&oacute;n "Add Form" para empezar.</td>
                </tr>
            <?php else : ?>
                <?php foreach ( $forms as $fid => $form_config ) : ?>
                    <tr>
                        <th scope="row" class="check-column">
                            <input type="checkbox" name="form_ids[]" value="<?php echo esc_attr( $fid ); ?>" class="sagicc-form-checkbox">
                        </th>
                        <td class="column-primary">
                            <strong>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=sagicc-forms&form_action=edit&form_id=' . $fid ) ); ?>">
                                    <?php echo esc_html( $form_config['name'] ?? $fid ); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="edit">
                                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=sagicc-forms&form_action=edit&form_id=' . $fid ) ); ?>">Editar</a> |
                                </span>
                                <span class="delete">
                                    <?php
                                    $delete_url = add_query_arg(
                                        array(
                                            'page'                 => 'sagicc-forms',
                                            'sagicc_forms_action'  => 'delete_form',
                                            'delete_form_id'       => $fid,
                                            'form_action'          => 'list',
                                        ),
                                        admin_url( 'admin.php' )
                                    );
                                    if ( ! empty( $search_term ) ) {
                                        $delete_url = add_query_arg( 's', $search_term, $delete_url );
                                    }
                                    ?>
                                    <a href="<?php echo esc_url( wp_nonce_url( $delete_url, 'sagicc_forms_delete_form' ) ); ?>" class="submitdelete" onclick="return confirm('Seguro que deseas eliminar este formulario?');">Eliminar</a>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html( $fid ); ?></td>
                        <td><?php echo ! empty( $form_config['endpoint'] ) ? esc_html( $form_config['endpoint'] ) : '&mdash;'; ?></td>
                        <td>
                            <code id="sagicc-shortcode-<?php echo esc_attr( $fid ); ?>">[sagicc_form id="<?php echo esc_html( $fid ); ?>"]</code>
                            <button type="button" class="button button-small sagicc-copy-shortcode" data-shortcode="[sagicc_form id='<?php echo esc_attr( $fid ); ?>']">Copiar</button>
                        </td>
                        <td>
                            <?php
                            $type    = sagicc_forms_resolve_captcha_type( $form_config );
                            $options = sagicc_forms_captcha_options();
                            $label   = isset( $options[ $type ] ) ? $options[ $type ] : ucfirst( $type );
                            echo esc_html( $label );
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="alignleft actions bulkactions">
                <label for="bulk-action-selector-bottom" class="screen-reader-text">Seleccionar acci&oacute;n masiva</label>
                <select name="bulk_action_bottom" id="bulk-action-selector-bottom">
                    <option value="-1">Acciones en lote</option>
                    <option value="delete">Eliminar</option>
                </select>
                <?php submit_button( 'Aplicar', 'secondary', 'bulk_apply_bottom', false ); ?>
            </div>
            <div class="tablenav-pages one-page">
                <span class="displaying-num"><?php echo esc_html( $count ); ?> formularios</span>
            </div>
            <br class="clear">
        </div>
    </form>
    <?php
}

/**
 * Render the add/edit form view.
 *
 * @param string $current_id Current form ID (empty when creating).
 * @param array  $editing    Existing configuration (null for new forms).
 *
 * @return void
 */
function sagicc_forms_render_form_editor( $current_id, $editing ) {
    $required_fields      = sagicc_forms_required_fields();
    $captcha_options      = sagicc_forms_captcha_options();
    $selected_captcha     = $editing ? sagicc_forms_resolve_captcha_type( $editing ) : 'none';
    if ( ! isset( $captcha_options[ $selected_captcha ] ) ) {
        $selected_captcha = 'none';
    }
    $recaptcha_site_key   = $editing['recaptcha_site_key'] ?? '';
    $recaptcha_secret_key = $editing['recaptcha_secret_key'] ?? '';
    $recaptcha_fields_style = 'recaptcha_v3' === $selected_captcha ? '' : 'display:none;';

    ?>
    <form method="post">
        <?php wp_nonce_field( 'sagicc_forms_save_form' ); ?>
        <input type="hidden" name="sagicc_forms_action" value="save_form">

        <table class="form-table">
            <tr>
                <th scope="row"><label for="form_id">ID del formulario</label></th>
                <td>
                    <input type="text" name="form_id" id="form_id" value="<?php echo esc_attr( $current_id ); ?>" <?php echo $editing ? 'readonly' : ''; ?> class="regular-text" required>
                    <p class="description">Usa un ID corto, por ejemplo <code>contacto_web</code>. Lo usar&aacute;s en el shortcode: <code>[sagicc_form id="contacto_web"]</code></p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="form_name">Nombre del formulario</label></th>
                <td>
                    <input type="text" name="form_name" id="form_name" value="<?php echo esc_attr( $editing['name'] ?? '' ); ?>" class="regular-text">
                    <p class="description">Solo para identificarlo en el panel. Opcional.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="form_token">Token de Sagicc</label></th>
                <td>
                    <input type="text" name="form_token" id="form_token" value="<?php echo isset( $editing['token'] ) ? esc_attr( $editing['token'] ) : ''; ?>" class="regular-text" required>
                    <p class="description">Tu token de Sagicc. Nunca se mostrar&aacute; en el HTML p&uacute;blico.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="form_endpoint">Endpoint de Sagicc</label></th>
                <td>
                    <input type="text" name="form_endpoint" id="form_endpoint" value="<?php echo isset( $editing['endpoint'] ) ? esc_attr( $editing['endpoint'] ) : ''; ?>" class="regular-text" required>
                    <p class="description">URL completa a la que se enviar&aacute; el formulario (obligatoria).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="form_html">HTML interno del formulario</label></th>
                <td>
                    <textarea name="form_html" id="form_html" rows="12" class="large-text code"><?php echo isset( $editing['html'] ) ? esc_textarea( $editing['html'] ) : ''; ?></textarea>
                    <p class="description">
                        Pega aqu&iacute; el contenido <strong>interno</strong> de tu formulario: campos, labels, botones, estilos inline, etc.<br>
                        <strong>No</strong> incluyas la etiqueta <code>&lt;form&gt;</code> ni el token. El plugin generar&aacute; el <code>&lt;form&gt;</code> y a&ntilde;adir&aacute; el token en el servidor.
                    </p>
                    <p class="description">
                        <strong>Campos obligatorios de Sagicc:</strong> <?php echo esc_html( implode( ', ', $required_fields ) ); ?>.<br>
                        Aseg&uacute;rate de incluir inputs con esos atributos <code>name</code>. Si faltan, Sagicc rechazar&aacute; el registro.
                    </p>
                    <details>
                        <summary>Ver ejemplo base sugerido</summary>
                        <pre><code>&lt;div class="field"&gt;
    &lt;label for="nombre"&gt;Nombre*&lt;/label&gt;
    &lt;input type="text" id="nombre" name="nombre" required&gt;
&lt;/div&gt;
&lt;div class="field"&gt;
    &lt;label for="apellido"&gt;Apellido*&lt;/label&gt;
    &lt;input type="text" id="apellido" name="apellido" required&gt;
&lt;/div&gt;
&lt;div class="field"&gt;
    &lt;label for="email"&gt;Correo*&lt;/label&gt;
    &lt;input type="email" id="email" name="email" required&gt;
&lt;/div&gt;
&lt;div class="field"&gt;
    &lt;label for="telefono"&gt;Tel&eacute;fono*&lt;/label&gt;
    &lt;input type="text" id="telefono" name="telefono" required&gt;
&lt;/div&gt;
&lt;div class="field"&gt;
    &lt;label for="mensaje"&gt;Mensaje&lt;/label&gt;
    &lt;textarea id="mensaje" name="mensaje" rows="4"&gt;&lt;/textarea&gt;
&lt;/div&gt;</code></pre>
                    </details>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="form_css">CSS personalizado</label></th>
                <td>
                    <textarea name="form_css" id="form_css" rows="5" class="large-text code"><?php echo isset( $editing['css'] ) ? esc_textarea( $editing['css'] ) : ''; ?></textarea>
                    <p class="description">Opcional. Se insertar&aacute; dentro de <code>&lt;style&gt;</code> antes del formulario.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="form_js">JavaScript personalizado</label></th>
                <td>
                    <textarea name="form_js" id="form_js" rows="5" class="large-text code"><?php echo isset( $editing['js'] ) ? esc_textarea( $editing['js'] ) : ''; ?></textarea>
                    <p class="description">Opcional. Se agregar&aacute; dentro de <code>&lt;script&gt;</code> despu&eacute;s del formulario.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="form_captcha_type">Captcha</label></th>
                <td>
                    <select name="form_captcha_type" id="form_captcha_type">
                        <?php foreach ( $captcha_options as $key => $label ) : ?>
                            <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $selected_captcha, $key ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        Elige el m&eacute;todo de protecci&oacute;n contra bots. El captcha nativo de Sagicc requiere que tu HTML incluya su snippet oficial.
                    </p>
                    <div id="sagicc-recaptcha-fields" style="margin-top:15px; <?php echo esc_attr( $recaptcha_fields_style ); ?>">
                        <label for="form_recaptcha_site_key">reCAPTCHA Site Key</label><br>
                        <input type="text" name="form_recaptcha_site_key" id="form_recaptcha_site_key" value="<?php echo esc_attr( $recaptcha_site_key ); ?>" class="regular-text">
                        <p class="description">Clave p&uacute;blica provista por Google reCAPTCHA v3.</p>
                        <label for="form_recaptcha_secret_key">reCAPTCHA Secret Key</label><br>
                        <input type="text" name="form_recaptcha_secret_key" id="form_recaptcha_secret_key" value="<?php echo esc_attr( $recaptcha_secret_key ); ?>" class="regular-text">
                        <p class="description">Clave privada usada para validar el token contra Google.</p>
                    </div>
                </td>
            </tr>
        </table>

        <?php submit_button( $editing ? 'Guardar cambios' : 'Crear formulario' ); ?>
    </form>
    <?php
}
