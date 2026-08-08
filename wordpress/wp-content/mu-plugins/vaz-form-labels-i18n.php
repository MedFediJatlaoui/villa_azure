<?php
/**
 * Villa Azur — Fluent Forms field labels/placeholders, localized.
 *
 * Polylang has no Fluent Forms integration, so field labels/placeholders/
 * options stay whatever's stored in the form builder (French) regardless of
 * the page's language. `fluentform/rendering_field_data_{element}` fires at
 * PAGE RENDER time (not the later AJAX submit), so pll_current_language() is
 * reliable here — same reasoning as VAZ_Reservation::enqueue().
 *
 * Scoped to form 3 (contact) and form 4 (reservation) only.
 */

if (!defined('ABSPATH')) {
    exit;
}

function vaz_form_i18n_lang() {
    return function_exists('pll_current_language') ? (pll_current_language() ?: 'fr') : 'fr';
}

// ── Form 4: names (input_name → nested first_name/last_name) ────────────────
add_filter('fluentform/rendering_field_data_input_name', function ($data, $form) {
    if ((int) $form->id !== 4 || empty($data['fields'])) {
        return $data;
    }
    $lang = vaz_form_i18n_lang();
    if (isset($data['fields']['first_name'])) {
        $data['fields']['first_name']['settings']['label']       = VAZ_I18N::t($lang, 'f4.first_name.label');
        $data['fields']['first_name']['attributes']['placeholder'] = VAZ_I18N::t($lang, 'f4.first_name.placeholder');
    }
    if (isset($data['fields']['last_name'])) {
        $data['fields']['last_name']['settings']['label']       = VAZ_I18N::t($lang, 'f4.last_name.label');
        $data['fields']['last_name']['attributes']['placeholder'] = VAZ_I18N::t($lang, 'f4.last_name.placeholder');
    }
    return $data;
}, 10, 2);

// ── Form 4: phone ────────────────────────────────────────────────────────────
// NB: this field is an `input_number` element (despite holding a phone number),
// so it must be hooked on rendering_field_data_input_number. It was originally
// hooked on _input_text — inferred from the field NAME "numeric_field" — which
// silently never fired, leaving the French "Telephone ou Whatsapp" label
// visible in all four other languages. Always confirm the element type from
// the form schema rather than guessing from the field name.
add_filter('fluentform/rendering_field_data_input_number', function ($data, $form) {
    if ((int) $form->id !== 4 || ($data['attributes']['name'] ?? '') !== 'numeric_field') {
        return $data;
    }
    $lang = vaz_form_i18n_lang();
    $data['settings']['label']         = VAZ_I18N::t($lang, 'f4.phone.label');
    $data['attributes']['placeholder'] = VAZ_I18N::t($lang, 'f4.phone.placeholder');
    return $data;
}, 10, 2);

// ── Form 4: email (input_email email_1) ──────────────────────────────────────
add_filter('fluentform/rendering_field_data_input_email', function ($data, $form) {
    if ((int) $form->id !== 4) {
        return $data;
    }
    $lang = vaz_form_i18n_lang();
    $data['settings']['label']         = VAZ_I18N::t($lang, 'f4.email.label');
    $data['attributes']['placeholder'] = VAZ_I18N::t($lang, 'f4.email.placeholder');
    return $data;
}, 10, 2);

// ── Form 4: dates ─────────────────────────────────────────────────────────
add_filter('fluentform/rendering_field_data_input_date', function ($data, $form) {
    if ((int) $form->id !== 4) {
        return $data;
    }
    $lang = vaz_form_i18n_lang();
    $name = $data['attributes']['name'] ?? '';
    if ($name === 'date_arrivee') {
        $data['settings']['label'] = VAZ_I18N::t($lang, 'f4.checkin.label');
    } elseif ($name === 'date_depart') {
        $data['settings']['label'] = VAZ_I18N::t($lang, 'f4.checkout.label');
    } else {
        return $data;
    }
    $ph = VAZ_I18N::t($lang, 'f4.date.placeholder');
    $data['attributes']['placeholder'] = $ph;
    if (isset($data['fields'])) { // date-range sub-inputs, if any
        foreach ($data['fields'] as &$sub) {
            if (isset($sub['attributes']['placeholder'])) {
                $sub['attributes']['placeholder'] = $ph;
            }
        }
        unset($sub);
    }
    return $data;
}, 10, 2);

// ── Form 4: special-request textarea, Form 3: message textarea ─────────────
add_filter('fluentform/rendering_field_data_textarea', function ($data, $form) {
    $name = $data['attributes']['name'] ?? '';
    $lang = vaz_form_i18n_lang();
    if ((int) $form->id === 4 && $name === 'demandes_particulieres') {
        $data['settings']['label']         = VAZ_I18N::t($lang, 'f4.request.label');
        $data['attributes']['placeholder'] = VAZ_I18N::t($lang, 'f4.request.placeholder');
    } elseif ((int) $form->id === 3 && $name === 'message') {
        $data['settings']['label']         = VAZ_I18N::t($lang, 'f3.message.label');
        $data['attributes']['placeholder'] = VAZ_I18N::t($lang, 'f3.message.placeholder');
    }
    return $data;
}, 10, 2);

// ── Form 4 + 3 submit buttons ─────────────────────────────────────────────
add_filter('fluentform/rendering_field_data_button', function ($data, $form) {
    if ((int) $form->id === 4) {
        $data['settings']['button_ui']['text'] = VAZ_I18N::t(vaz_form_i18n_lang(), 'f4.submit');
    } elseif ((int) $form->id === 3) {
        $data['settings']['button_ui']['text'] = VAZ_I18N::t(vaz_form_i18n_lang(), 'f3.submit');
    }
    return $data;
}, 10, 2);

// ── Form 3: full name, email, phone ─────────────────────────────────────────
add_filter('fluentform/rendering_field_data_input_text', function ($data, $form) {
    if ((int) $form->id !== 3) {
        return $data;
    }
    $lang = vaz_form_i18n_lang();
    $name = $data['attributes']['name'] ?? '';
    if ($name === 'nom_complet') {
        $data['settings']['label']         = VAZ_I18N::t($lang, 'f3.name.label');
        $data['attributes']['placeholder'] = VAZ_I18N::t($lang, 'f3.name.placeholder');
    } elseif ($name === 'telephone') {
        $data['settings']['label']         = VAZ_I18N::t($lang, 'f3.phone.label');
        $data['attributes']['placeholder'] = VAZ_I18N::t($lang, 'f3.phone.placeholder');
    }
    return $data;
}, 9, 2); // priority 9: runs before the form-4 numeric_field handler above, both harmless no-ops on the other form

add_filter('fluentform/rendering_field_data_input_email', function ($data, $form) {
    if ((int) $form->id !== 3) {
        return $data;
    }
    $lang = vaz_form_i18n_lang();
    $data['settings']['label']         = VAZ_I18N::t($lang, 'f3.email.label');
    $data['attributes']['placeholder'] = VAZ_I18N::t($lang, 'f3.email.placeholder');
    return $data;
}, 9, 2);

// ── Form 3: "type de demande" select + its options ──────────────────────────
add_filter('fluentform/rendering_field_data_select', function ($data, $form) {
    if ((int) $form->id !== 3 || ($data['attributes']['name'] ?? '') !== 'type_de_demande') {
        return $data;
    }
    $lang = vaz_form_i18n_lang();
    $data['settings']['label'] = VAZ_I18N::t($lang, 'f3.type.label');
    if (!empty($data['settings']['advanced_options']) && is_array($data['settings']['advanced_options'])) {
        foreach ($data['settings']['advanced_options'] as &$opt) {
            $key = 'f3.type.opt.' . $opt['value'];
            $translated = VAZ_I18N::t($lang, $key);
            if ($translated !== $key) {
                $opt['label'] = $translated;
            }
        }
        unset($opt);
    }
    return $data;
}, 10, 2);
