<?php
/**
 * Villa Azur — Contact form (Fluent Forms #3): localized confirmation email.
 *
 * Same pattern as the reservation form's email localization: the notification
 * template (DB) pulls subject/heading/intro/labels/footer via {inputs.vaz_*}
 * smart tags instead of hardcoding French, and the guest's language is
 * captured into a hidden `vaz_lang` field at page-render time (when
 * pll_current_language() is accurate) because the AJAX submission itself
 * carries no /en/, /de/... URL prefix to detect it from.
 */

if (!defined('ABSPATH')) {
    exit;
}

const VAZ_CONTACT_FORM_ID = 3;

add_filter('fluentform/insert_response_data', function ($data, $formId, $inputConfigs = null) {
    if ((int) $formId !== VAZ_CONTACT_FORM_ID) {
        return $data;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- FF verifies its own nonce upstream before this filter runs.
    $raw = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
    parse_str($raw, $fields);
    $lang = (!empty($fields['vaz_lang']) && in_array($fields['vaz_lang'], VAZ_I18N::LANGS, true))
        ? $fields['vaz_lang']
        : VAZ_I18N::current();

    $data['vaz_contact_subject']  = VAZ_I18N::t($lang, 'f3.subject');
    $data['vaz_contact_heading']  = sprintf(VAZ_I18N::t($lang, 'email.client.heading'), trim((string) ($fields['nom_complet'] ?? '')));
    $data['vaz_contact_intro']    = VAZ_I18N::t($lang, 'f3.intro');
    $data['vaz_contact_l_name']   = VAZ_I18N::t($lang, 'f3.name.label');
    $data['vaz_contact_l_email']  = VAZ_I18N::t($lang, 'f3.email.label');
    $data['vaz_contact_l_phone']  = VAZ_I18N::t($lang, 'f3.phone.label');
    $data['vaz_contact_l_type']   = VAZ_I18N::t($lang, 'f3.type.label');
    $data['vaz_contact_l_message']= VAZ_I18N::t($lang, 'f3.message.label');
    $data['vaz_contact_footer']   = VAZ_I18N::t($lang, 'email.client.footer');

    // Informational only, for hotel staff (who read the internal
    // notification in French regardless of the visitor's language).
    $data['vaz_contact_guest_lang'] = VAZ_I18N::native_name($lang) . ' (' . strtoupper($lang) . ')';

    return $data;
}, 10, 3);

// Guarantee a hidden vaz_lang field exists on the Contact form and is kept
// current — mirrors ensureLangField() in vaz-reservation.js, but this form
// lives directly on the page (no popup), so a simple page-load pass is enough.
add_action('wp_footer', function () {
    if (!function_exists('pll_current_language')) {
        return;
    }
    ?>
    <script>
    (function () {
      if (typeof window.jQuery === 'undefined') { return; }
      var $ = window.jQuery;
      var $form = $('#fluentform_<?php echo (int) VAZ_CONTACT_FORM_ID; ?>');
      if (!$form.length) { return; }
      var lang = <?php echo wp_json_encode(VAZ_I18N::current()); ?>;
      if (!$form.find('input[name="vaz_lang"]').length) {
        $('<input>', { type: 'hidden', name: 'vaz_lang' }).appendTo($form);
      }
      $form.find('input[name="vaz_lang"]').val(lang);
    })();
    </script>
    <?php
}, 20);
