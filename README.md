# Sagicc Forms Manager

WordPress plugin that lets marketing teams create multiple custom forms that submit to the Sagicc API while keeping every token hidden on the server. Each form can have its own HTML, CSS, JavaScript, captcha configuration, UTM capture, and landing-page tracking.

## Features
- Admin UI to create, edit, and delete any number of Sagicc-bound forms.
- Two authoring modes: **Visual Designer** with curated templates (Base, PQRS, Marketing Lead) and **Advanced Editor** for raw HTML/CSS/JS.
- Secure server-side storage of Sagicc token and endpoint per form; the front-end never exposes the token.
- Optional captcha per form (none, native Sagicc snippet, arithmetic challenge, or Google reCAPTCHA v3) plus lightweight rate limiting.
- Automatic UTM + landing-page URL capture via hidden fields that are populated client-side before each submission.
- Live preview iframe that updates in real time as you edit HTML/CSS/JS inside the admin.

## Technical Overview
### Storage & Admin Flow
- All form definitions live inside the `sagicc_forms_config` option. Each entry stores: `name`, `token`, `endpoint`, `html`, `css`, `js`, `captcha_type`, reCAPTCHA keys, `capture_page_url`, `mode` (visual/advanced), and `template_id` when applicable.
- The admin page registers a tabbed interface. Selecting *Diseñador visual* shows the template gallery; choosing a template copies its HTML/CSS/JS into the advanced fields (which remain hidden until you switch to Advanced mode). Switching modes is instant because both code and preview share the same textareas.
- A preview iframe renders the combined HTML/CSS/JS code in isolation. JavaScript in the admin rewrites the iframe on every keystroke so users see the form as they edit it.

### Front-End Shortcode
- `[sagicc_form id="contacto_demo"]` outputs a `<form>` wrapper with hidden `_sagicc_form_id`, `_sagicc_wp_nonce`, UTM fields, and optionally `sagicc_page_url`.
- A front-end script:
  1. Calls `GET /wp-json/sagicc/v1/form-security?id={form_id}` to fetch a fresh nonce (and arithmetic captcha hash if enabled) whenever the page loads and right before every submit. This avoids issues with cached pages.
  2. Reads `window.location.search` with `URLSearchParams` to fill UTM fields (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `utm_id`) and, when enabled, copies `window.location.href` into `sagicc_page_url`.
  3. If the form requires reCAPTCHA v3, it calls `grecaptcha.execute()` to obtain a token and stores it in `_sagicc_recaptcha_token`.

### REST Proxy & Security
- `POST /wp-json/sagicc/v1/submit` verifies the nonce (`wp_verify_nonce`), validates the captcha mode (arithmetic hash or reCAPTCHA siteverify), applies a 10-second IP-based rate limit, injects the per-form Sagicc token, and forwards the sanitized payload via cURL to the configured endpoint.
- All hidden `_sagicc_*` fields are removed before relaying the data. Files (including multi-upload fields) are preserved using `CURLFile`.
- Because the Sagicc token and endpoint live only in WordPress, front-end users cannot spoof the submission or exfiltrate credentials.

## Usage
1. Upload/activate the plugin in WordPress.
2. Navigate to **Sagicc Forms** in the admin menu.
3. Create a form, choose Visual Designer or Advanced Editor, and configure token, endpoint, captcha, and styling as needed.
4. Place `[sagicc_form id="tu_formulario"]` on any page/post. The shortcode automatically injects the security fields, handles UTM capture, and forwards submissions through WordPress to Sagicc.
