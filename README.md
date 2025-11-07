# Sagicc Forms Manager

WordPress plugin that lets marketing teams create multiple custom forms which submit to the Sagicc API while keeping the token on the server. Each form can include its own HTML, CSS, JavaScript and an optional math captcha.

## Features
- Admin UI to create, edit and delete multiple Sagicc-bound forms.
- Secure server-side storage of Sagicc token and endpoint per form.
- Customizable HTML/CSS/JS snippets injected when rendering the shortcode.
- Optional captcha por formulario (ninguno, nativo de Sagicc, aritmético simple o Google reCAPTCHA v3) y rate limiting básico contra spam.
- Warns editors to keep mandatory Sagicc fields (`nombre`, `apellido`, `email`, `telefono`) in every form.

## Usage
1. Upload/activate the plugin in WordPress.
2. Navigate to **Sagicc Forms** in the admin menu.
3. Create a form by entering the ID, token, endpoint, and any custom markup.
4. Embed it on a page or post with `[sagicc_form id="tu_formulario"]`.
