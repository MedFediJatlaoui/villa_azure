<?php
/**
 * Villa Azur — Reservation pricing module.
 *
 * A self-contained, production booking experience layered on top of the existing
 * Fluent Forms #4 / Popup Maker #3241 pipeline (kept as the submission backend so
 * staff entries, email notifications and the spam-guard keep working untouched —
 * see villa-azur-fix.php).
 *
 * Separation of concerns:
 *   • CONFIG    — VAZ_Reservation::config(): the SINGLE source of truth for every
 *                 price, date range, discount, tax and limit. Change prices here
 *                 and nowhere else; the same array is handed to the browser so the
 *                 JS engine can never drift from the PHP one.
 *   • ENGINE    — VAZ_Reservation::quote(): pure pricing function (dates + rooms →
 *                 itemised breakdown). Mirrored 1:1 by the JS engine. Used on the
 *                 server to RE-COMPUTE and validate every submission — the client
 *                 total is displayed for the guest but never trusted for the record.
 *   • UI        — assets/vaz-reservation.js / .css (room repeater + live total).
 *   • BACKEND   — Fluent Forms #4 (entries + notifications).
 *
 * All figures come from `fiche de prix.md` (TND, per person, per night, taxes
 * included except the séjour tax which is itemised separately).
 */

if (!defined('ABSPATH')) {
    exit;
}

final class VAZ_Reservation {

    /** Fluent Forms form id this module drives. */
    const FORM_ID = 4;

    /** Popup Maker popup id that hosts the form. */
    const POPUP_ID = 3241;

    /**
     * THE single source of truth. Every price, rule and limit lives here.
     *
     * Seasons carry inclusive [start, end] date ranges (Y-m-d). A stay is priced
     * night-by-night, so a booking that straddles two seasons is charged correctly
     * for each night. Rates are per person, per night, in TND.
     */
    public static function config($lang = null) {
        $lang = $lang ?: VAZ_I18N::current();

        static $configs = [];
        if (isset($configs[$lang])) {
            return $configs[$lang];
        }

        $config = [
            'currency'   => 'TND',
            'form_id'    => self::FORM_ID,
            'popup_id'   => self::POPUP_ID,

            // Booking limits — configurable, referenced everywhere via this array
            // (never hardcoded elsewhere).
            'limits' => [
                'max_rooms'          => 6,
                'min_adults_room'    => 1,   // a room must have at least one adult
                'max_nights'         => 30,  // sanity ceiling for a single request
            ],

            // Per-season rates (TND / person / night) + single-occupancy supplement.
            // Season labels are internal (admin/config only, never shown to the
            // guest), so they stay French regardless of $lang.
            'seasons' => [
                'basse' => [
                    'label'       => 'Basse saison',
                    'bb'          => 99,   // logement + petit déjeuner
                    'hb'          => 133,  // demi-pension
                    'single_supp' => 16,
                    'ranges'      => [
                        ['2025-11-01', '2025-12-13'],
                        ['2026-01-04', '2026-03-28'],
                        ['2026-11-08', '2026-12-19'],
                    ],
                ],
                'moyenne' => [
                    'label'       => 'Moyenne saison',
                    'bb'          => 178,
                    'hb'          => 210,
                    'single_supp' => 21,
                    'ranges'      => [
                        ['2025-12-14', '2026-01-03'],
                        ['2026-03-29', '2026-07-04'],
                        ['2026-09-20', '2026-11-07'],
                        ['2026-12-20', '2026-12-31'],
                    ],
                ],
                'haute' => [
                    'label'       => 'Haute saison',
                    'bb'          => 222,
                    'hb'          => 254,
                    'single_supp' => 31,
                    'ranges'      => [
                        ['2026-07-05', '2026-09-19'],
                    ],
                ],
            ],

            // Fallback season for any night not covered by a defined range (kept so
            // the engine never divides by an undefined rate). The datepicker is
            // bounded to the covered window, so this is a safety net, not a path
            // a normal guest hits.
            'default_season' => 'moyenne',

            // Board options offered per room.
            'boards' => [
                'bb' => ['label' => VAZ_I18N::t($lang, 'board.bb.label'), 'sub' => VAZ_I18N::t($lang, 'board.bb.sub')],
                'hb' => ['label' => VAZ_I18N::t($lang, 'board.hb.label'), 'sub' => VAZ_I18N::t($lang, 'board.hb.sub')],
            ],

            // Occupancy caps per room type (client return, 2026-08-02): a "single"
            // sleeps exactly one adult, no additions. A "double" sleeps 2 adults,
            // plus ONE extra person who is either an adult (→ 3 adults) or a child
            // (→ 2 adults + 1 child) — never both, so max_occupants is the hard cap
            // that rules out 2 adults + 1 extra adult + 1 child.
            'room_types' => [
                'simple' => [
                    'label'         => VAZ_I18N::t($lang, 'room_type.simple.label'),
                    'sub'           => VAZ_I18N::t($lang, 'room_type.simple.sub'),
                    'max_adults'    => 1,
                    'max_children'  => 0,
                    'max_occupants' => 1,
                ],
                'double' => [
                    'label'         => VAZ_I18N::t($lang, 'room_type.double.label'),
                    'sub'           => VAZ_I18N::t($lang, 'room_type.double.sub'),
                    'max_adults'    => 3,
                    'max_children'  => 1,
                    'max_occupants' => 3,
                ],
            ],

            // Discounts (fiche de prix.md), applied automatically by the engine.
            'discounts' => [
                // 1 enfant < 12 ans dans la chambre des parents = -50% toute l'année.
                'child_under_12' => [
                    'rate'  => 0.50,
                    'label' => VAZ_I18N::t($lang, 'discount.child_under_12.label'),
                ],
                // 3ème personne adulte dans la même chambre = -30% toute l'année.
                // Applied to every adult beyond the second.
                'third_adult' => [
                    'rate'  => 0.30,
                    'label' => VAZ_I18N::t($lang, 'discount.third_adult.label'),
                ],
            ],

            // Taxe de séjour — Loi de finances 2024: 8 TND / nuitée / personne,
            // plafonnée à 10 nuitées.
            'tourist_tax' => [
                'per_person_per_night' => 8,
                'max_nights'           => 10,
                'applies_to_children'  => true,
                'label'                => VAZ_I18N::t($lang, 'tourist_tax.label'),
            ],
        ];

        /**
         * Escape hatch for future price changes without editing this file
         * (e.g. a small admin plugin or another mu-plugin).
         */
        $config = apply_filters('vaz_reservation_config', $config, $lang);
        $configs[$lang] = $config;
        return $config;
    }

    /**
     * Classify a single night (Y-m-d) into a season key.
     */
    public static function season_for_date($date) {
        $cfg = self::config();
        foreach ($cfg['seasons'] as $key => $season) {
            foreach ($season['ranges'] as $range) {
                if ($date >= $range[0] && $date <= $range[1]) {
                    return $key;
                }
            }
        }
        return $cfg['default_season'];
    }

    /**
     * Build the inclusive list of night dates for a stay: check-in counts,
     * check-out does not. Returns [] on invalid input.
     *
     * @param string $checkin  Y-m-d
     * @param string $checkout Y-m-d
     * @return string[] list of Y-m-d night dates
     */
    public static function nights_between($checkin, $checkout) {
        $in  = DateTime::createFromFormat('Y-m-d', $checkin);
        $out = DateTime::createFromFormat('Y-m-d', $checkout);
        if (!$in || !$out || $out <= $in) {
            return [];
        }
        $in->setTime(0, 0, 0);
        $out->setTime(0, 0, 0);
        $nights = [];
        $cursor = clone $in;
        $guard  = 0;
        while ($cursor < $out && $guard < 400) {
            $nights[] = $cursor->format('Y-m-d');
            $cursor->modify('+1 day');
            $guard++;
        }
        return $nights;
    }

    /**
     * THE pricing engine (server authority). Pure: same inputs → same breakdown.
     * Mirrors assets/vaz-reservation.js quote().
     *
     * @param string $checkin  Y-m-d
     * @param string $checkout Y-m-d
     * @param array  $rooms     each: ['type'=>simple|double,'board'=>bb|hb,'adults'=>int,'children'=>int]
     * @return array itemised breakdown
     */
    public static function quote($checkin, $checkout, array $rooms, $lang = null) {
        $cfg    = self::config($lang);
        $nights = self::nights_between($checkin, $checkout);
        $n      = count($nights);

        $result = [
            'currency'        => $cfg['currency'],
            'nights'          => $n,
            'valid'           => $n > 0 && !empty($rooms),
            'accommodation'   => 0.0, // full-rate accommodation, all guests, board included
            'child_discount'  => 0.0, // positive number = amount subtracted
            'adult_discount'  => 0.0,
            'single_supp'     => 0.0,
            'tourist_tax'     => 0.0,
            'total'           => 0.0,
            'total_guests'    => 0,
            'rooms'           => [],
            'discount_notes'  => [], // human labels, for the "why" chips
        ];

        if ($n === 0) {
            return $result;
        }

        $childRate = $cfg['discounts']['child_under_12']['rate'];
        $adultRate = $cfg['discounts']['third_adult']['rate'];

        foreach ($rooms as $i => $room) {
            $type    = in_array($room['type'] ?? '', ['simple', 'double'], true) ? $room['type'] : 'double';
            $board   = in_array($room['board'] ?? '', ['bb', 'hb'], true) ? $room['board'] : 'bb';
            $adults  = max(0, (int) ($room['adults'] ?? 0));
            $children = max(0, (int) ($room['children'] ?? 0));

            $roomAccommodation = 0.0;
            $roomChildDisc     = 0.0;
            $roomAdultDisc     = 0.0;
            $roomSingle        = 0.0;

            foreach ($nights as $date) {
                $season = self::season_for_date($date);
                $rate   = (float) $cfg['seasons'][$season][$board];
                $supp   = (float) $cfg['seasons'][$season]['single_supp'];

                // Full-rate accommodation for every guest (board is baked into rate).
                $roomAccommodation += ($adults + $children) * $rate;
                // Children < 12: -50% each.
                $roomChildDisc     += $children * $rate * $childRate;
                // Every adult beyond the 2nd: -30%.
                $roomAdultDisc     += max(0, $adults - 2) * $rate * $adultRate;
                // Single occupancy (exactly one adult): supplement per night.
                if ($adults === 1) {
                    $roomSingle += $supp;
                }
            }

            $roomTotal = $roomAccommodation - $roomChildDisc - $roomAdultDisc + $roomSingle;

            $result['accommodation']  += $roomAccommodation;
            $result['child_discount'] += $roomChildDisc;
            $result['adult_discount'] += $roomAdultDisc;
            $result['single_supp']    += $roomSingle;
            $result['total_guests']   += $adults + $children;

            $result['rooms'][] = [
                'index'          => $i + 1,
                'type'           => $type,
                'board'          => $board,
                'adults'         => $adults,
                'children'       => $children,
                'child_discount' => round($roomChildDisc, 2),
                'adult_discount' => round($roomAdultDisc, 2),
                'single_supp'    => round($roomSingle, 2),
                'subtotal'       => round($roomTotal, 2),
            ];
        }

        // Taxe de séjour: per person, per night, capped at max_nights.
        $tax     = $cfg['tourist_tax'];
        $taxNights = min($n, (int) $tax['max_nights']);
        $taxable   = $result['total_guests']; // children counted per LoF text
        if (!$tax['applies_to_children']) {
            $taxable = array_reduce($result['rooms'], function ($c, $r) {
                return $c + $r['adults'];
            }, 0);
        }
        $result['tourist_tax'] = $taxable * $taxNights * (float) $tax['per_person_per_night'];

        $result['total'] = $result['accommodation']
            - $result['child_discount']
            - $result['adult_discount']
            + $result['single_supp']
            + $result['tourist_tax'];

        // "Why" notes for transparency.
        if ($result['child_discount'] > 0) {
            $result['discount_notes'][] = $cfg['discounts']['child_under_12']['label'];
        }
        if ($result['adult_discount'] > 0) {
            $result['discount_notes'][] = $cfg['discounts']['third_adult']['label'];
        }

        // Round money for output.
        foreach (['accommodation', 'child_discount', 'adult_discount', 'single_supp', 'tourist_tax', 'total'] as $k) {
            $result[$k] = round($result[$k], 2);
        }

        return $result;
    }

    /**
     * Validate a decoded rooms payload against the configured limits. Returns a
     * list of friendly, specific French error strings (empty = valid).
     */
    public static function validate($checkin, $checkout, $rooms, $lang = null) {
        $lang   = $lang ?: VAZ_I18N::current();
        $cfg    = self::config($lang);
        $lim    = $cfg['limits'];
        $errors = [];

        $nights = self::nights_between($checkin, $checkout);
        if (empty($nights)) {
            $errors[] = VAZ_I18N::t($lang, 'val.datesInvalid');
        } elseif (count($nights) > $lim['max_nights']) {
            $errors[] = sprintf(VAZ_I18N::t($lang, 'val.maxNights'), $lim['max_nights']);
        }

        if (!is_array($rooms) || count($rooms) < 1) {
            $errors[] = VAZ_I18N::t($lang, 'val.noRooms');
            return $errors;
        }
        if (count($rooms) > $lim['max_rooms']) {
            $errors[] = sprintf(VAZ_I18N::t($lang, 'val.maxRooms'), $lim['max_rooms']);
        }

        foreach ($rooms as $i => $room) {
            $label    = VAZ_I18N::t($lang, 'ui.room') . ' ' . ($i + 1);
            $type     = in_array($room['type'] ?? '', ['simple', 'double'], true) ? $room['type'] : 'double';
            $typeCfg  = $cfg['room_types'][$type];
            $adults   = (int) ($room['adults'] ?? 0);
            $children = (int) ($room['children'] ?? 0);

            if ($adults < $lim['min_adults_room']) {
                $errors[] = sprintf(VAZ_I18N::t($lang, 'val.minAdult'), $label);
            }
            if ($adults > $typeCfg['max_adults']) {
                $errors[] = sprintf(VAZ_I18N::t($lang, 'val.maxAdults'), $label, $typeCfg['label'], $typeCfg['max_adults'], VAZ_I18N::word($lang, 'adult', $typeCfg['max_adults']));
            }
            if ($children > $typeCfg['max_children']) {
                $errors[] = sprintf(VAZ_I18N::t($lang, 'val.maxChildren'), $label, $typeCfg['label'], $typeCfg['max_children'], VAZ_I18N::word($lang, 'child', $typeCfg['max_children']));
            }
            if ($adults + $children > $typeCfg['max_occupants']) {
                $errors[] = sprintf(VAZ_I18N::t($lang, 'val.maxOccupants'), $label, $typeCfg['label'], $typeCfg['max_occupants'], VAZ_I18N::word($lang, 'person', $typeCfg['max_occupants']));
            }
        }

        return $errors;
    }

    /* ───────────────────────── Front-end wiring ──────────────────────────── */

    public static function init() {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue']);
        add_filter('fluentform/validation_errors', [__CLASS__, 'server_validate'], 20, 3);
        add_filter('fluentform/insert_response_data', [__CLASS__, 'authoritative_recompute'], 10, 3);
        add_filter('fluentform/response_render_input_hidden', [__CLASS__, 'render_hidden_field'], 10, 3);
    }

    /**
     * Make the vaz_* hidden fields readable for non-technical staff wherever
     * Fluent Forms displays a submission (entries list/detail, {all_data},
     * and any {inputs.xxx} smart tag — this filter runs for all of them).
     * The raw JSON payload is implementation detail, not something a hotel
     * receptionist needs to read, so it's hidden entirely; the human-written
     * summary/breakdown get their line breaks preserved as real <br> tags.
     */
    public static function render_hidden_field($value, $field, $formId) {
        $name = $field['attributes']['name'] ?? '';
        if (strpos($name, 'vaz_') !== 0) {
            return $value;
        }
        if ($name === 'vaz_rooms_json') {
            return ''; // technical payload — not meant for human eyes
        }
        if (in_array($name, ['vaz_email_details', 'vaz_email_contact', 'vaz_email_welcome'], true)) {
            return $value; // already HTML — do not escape/convert
        }
        if (in_array($name, ['vaz_rooms_summary', 'vaz_breakdown'], true)) {
            return nl2br(esc_html($value));
        }
        return $value;
    }

    /**
     * Ship the UI assets and hand the config to the browser so the JS engine
     * uses the exact same numbers as PHP (no duplicated values).
     */
    public static function enqueue() {
        if (is_admin()) {
            return;
        }
        $dir = plugin_dir_url(__FILE__) . 'assets/';
        $base = __DIR__ . '/assets/';
        $ver = static function ($f) use ($base) {
            return file_exists($base . $f) ? (string) filemtime($base . $f) : '1.0.0';
        };

        wp_enqueue_style('vaz-reservation', $dir . 'vaz-reservation.css', [], $ver('vaz-reservation.css'));
        wp_enqueue_script('vaz-reservation', $dir . 'vaz-reservation.js', ['jquery'], $ver('vaz-reservation.js'), true);

        // pll_current_language() is reliable here: this hook runs while rendering
        // the actual page, after Polylang has parsed the /en/, /de/... URL prefix.
        $lang = VAZ_I18N::current();

        wp_localize_script('vaz-reservation', 'VAZ_RESA', [
            'config'   => self::config($lang),
            'i18n'     => self::strings($lang),
            'lang'     => $lang,
            'calendar' => VAZ_I18N::calendar($lang),
            // Shared with JS so the live preview's plurals (adults/children/nights)
            // use the exact same word forms as the server — never a hand-kept
            // second copy that can drift out of sync.
            'words'    => VAZ_I18N::WORDS,
        ]);
    }

    /** UI copy in one place. */
    public static function strings($lang = null) {
        $lang = $lang ?: VAZ_I18N::current();
        return [
            'addRoom'        => VAZ_I18N::t($lang, 'ui.addRoom'),
            'removeRoom'     => VAZ_I18N::t($lang, 'ui.removeRoom'),
            'room'           => VAZ_I18N::t($lang, 'ui.room'),
            'roomType'       => VAZ_I18N::t($lang, 'ui.roomType'),
            'board'          => VAZ_I18N::t($lang, 'ui.board'),
            'adults'         => VAZ_I18N::t($lang, 'ui.adults'),
            'children'       => VAZ_I18N::t($lang, 'ui.children'),
            'occHintSimple'  => VAZ_I18N::t($lang, 'ui.occHintSimple'),
            'occHintDouble'  => VAZ_I18N::t($lang, 'ui.occHintDouble'),
            'summaryTitle'   => VAZ_I18N::t($lang, 'ui.summaryTitle'),
            'yourRooms'      => VAZ_I18N::t($lang, 'ui.yourRooms'),
            'stay'           => VAZ_I18N::t($lang, 'ui.stay'),
            'accommodation'  => VAZ_I18N::t($lang, 'ui.accommodation'),
            'childDiscount'  => VAZ_I18N::t($lang, 'ui.childDiscount'),
            'adultDiscount'  => VAZ_I18N::t($lang, 'ui.adultDiscount'),
            'singleSupp'     => VAZ_I18N::t($lang, 'ui.singleSupp'),
            'touristTax'     => VAZ_I18N::t($lang, 'ui.touristTax'),
            'total'          => VAZ_I18N::t($lang, 'ui.total'),
            'perNight'       => VAZ_I18N::word($lang, 'night', 1),
            'nights'         => VAZ_I18N::word($lang, 'night', 2),
            'night'          => VAZ_I18N::word($lang, 'night', 1),
            'estimateNote'   => VAZ_I18N::t($lang, 'ui.estimateNote'),
            'pickDates'      => VAZ_I18N::t($lang, 'ui.pickDates'),
            'maxRoomsHit'    => VAZ_I18N::t($lang, 'ui.maxRoomsHit'),
            'touristTaxNote' => VAZ_I18N::t($lang, 'details.touristTaxNote'),
        ];
    }

    /**
     * Server authority #1 — reject impossible reservations even if the client JS
     * was bypassed. Scoped to form 4; runs alongside the spam-guard already on
     * this filter in villa-azur-fix.php.
     */
    public static function server_validate($errors, $formData, $form) {
        if ((int) $form->id !== self::FORM_ID) {
            return $errors;
        }
        $lang = VAZ_I18N::current();
        list($checkin, $checkout, $rooms) = self::extract_submission();
        if ($rooms === null) {
            $errors['vaz_rooms_json'] = [VAZ_I18N::t($lang, 'val.missingConfig')];
            return $errors;
        }
        $problems = self::validate($checkin, $checkout, $rooms, $lang);
        if (!empty($problems)) {
            $errors['vaz_rooms_json'] = $problems;
        }
        return $errors;
    }

    /**
     * Server authority #2 — overwrite the human-readable summary, breakdown and
     * grand total with values RE-COMPUTED on the server, so the entry and the
     * staff email can never carry a tampered or stale client total.
     */
    public static function authoritative_recompute($data, $formId, $inputConfigs = null) {
        if ((int) $formId !== self::FORM_ID) {
            return $data;
        }
        $lang = VAZ_I18N::current();
        list($checkin, $checkout, $rooms) = self::extract_submission();
        if ($rooms === null) {
            return $data;
        }
        $quote = self::quote($checkin, $checkout, $rooms, $lang);

        $contact = self::extract_contact_fields();

        $data['vaz_nights']          = (string) $quote['nights'];
        $data['vaz_grand_total']     = self::money($quote['total']) . ' ' . $quote['currency'];
        $data['vaz_rooms_summary']   = self::rooms_summary_text($quote, $lang);
        $data['vaz_breakdown']       = self::breakdown_text($quote, $lang);
        $data['vaz_email_details']   = self::email_details_html($quote, $checkin, $checkout, $lang);
        $data['vaz_email_contact']   = self::email_contact_recap_html(
            VAZ_I18N::t($lang, 'recap.heading'),
            $contact['first_name'], $contact['last_name'], $contact['email'], $contact['phone'],
            $checkin, $checkout, $contact['request'], $lang
        );
        $data['vaz_email_welcome']   = self::email_welcome_html($lang);

        // Client-facing email subject/heading/intro/footer, in the guest's
        // language — the Fluent Forms notification template pulls these via
        // {inputs.vaz_email_*} smart tags instead of hardcoding French.
        $firstName = trim((string) $contact['first_name']);
        $data['vaz_email_subject'] = VAZ_I18N::t($lang, 'email.client.subject');
        $data['vaz_email_heading'] = sprintf(VAZ_I18N::t($lang, 'email.client.heading'), $firstName);
        $data['vaz_email_intro']   = VAZ_I18N::t($lang, 'email.client.intro');
        $data['vaz_email_footer']  = VAZ_I18N::t($lang, 'email.client.footer');

        // Informational only, for hotel staff (who read the internal
        // notification in French regardless of the guest's language).
        $data['vaz_guest_lang'] = VAZ_I18N::native_name($lang) . ' (' . strtoupper($lang) . ')';

        return $data;
    }

    /**
     * Pull the plain contact/request fields from the raw POST (same technique
     * as extract_submission).
     *
     * The name field is Fluent Forms' `input_name` element, which submits as
     * nested `names[first_name]`/`names[last_name]`, not flat top-level keys —
     * parse_str() turns that into $fields['names']['first_name']. Reading
     * $fields['first_name'] (flat) silently returned '' for every submission,
     * which is why every client email greeted the guest with "Thank you, !".
     */
    private static function extract_contact_fields() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- FF verifies its own nonce upstream before these filters run.
        $raw = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
        parse_str($raw, $fields);
        $names = $fields['names'] ?? [];
        return [
            'first_name' => $names['first_name'] ?? '',
            'last_name'  => $names['last_name'] ?? '',
            'email'      => $fields['email_1'] ?? '',
            'phone'      => $fields['numeric_field'] ?? '',
            'request'    => $fields['demandes_particulieres'] ?? '',
        ];
    }

    /**
     * The ONE reservation-details block shared by both emails (admin + client)
     * and by the admin entries view: stay dates, a card per room with its own
     * price and "why" notes, then the subtotal/tax/total. Inline styles only —
     * table-based layout — for compatibility with real-world email clients.
     */
    public static function email_details_html($quote, $checkin = '', $checkout = '', $lang = null) {
        $lang = $lang ?: VAZ_I18N::current();
        $cfg = self::config($lang);
        $c   = $quote['currency'];
        $ink = '#1F1F1F'; $muted = '#727272'; $gold = '#E9A668'; $line = '#E7E1D6'; $green = '#2E7D5B';

        $stay = ($checkin && $checkout)
            ? VAZ_I18N::format_date($checkin, $lang) . ' &rarr; ' . VAZ_I18N::format_date($checkout, $lang)
            : '';
        $stayLine = $stay
            ? '<p style="margin:0 0 18px;font-size:14px;color:' . $ink . ';font-weight:600;">' . esc_html($stay)
                . ' &middot; ' . $quote['nights'] . ' ' . VAZ_I18N::word($lang, 'night', $quote['nights']) . '</p>'
            : '';

        $roomsHtml = '';
        foreach ($quote['rooms'] as $r) {
            $type  = esc_html($cfg['room_types'][$r['type']]['label'] ?? $r['type']);
            $board = esc_html($cfg['boards'][$r['board']]['label'] ?? $r['board']);
            $occ   = $r['adults'] . ' ' . VAZ_I18N::word($lang, 'adult', $r['adults']);
            if ($r['children'] > 0) {
                $occ .= ', ' . $r['children'] . ' ' . VAZ_I18N::word($lang, 'child', $r['children']);
            }
            $notes = [];
            if ($r['child_discount'] > 0) {
                $notes[] = '&#10003; ' . VAZ_I18N::t($lang, 'discount.child_under_12.label') . ' &minus;' . round($cfg['discounts']['child_under_12']['rate'] * 100)
                    . '% &middot; &minus;' . self::money($r['child_discount']) . ' ' . $c;
            }
            if ($r['adult_discount'] > 0) {
                $notes[] = '&#10003; ' . VAZ_I18N::t($lang, 'discount.third_adult.label') . ' &minus;' . round($cfg['discounts']['third_adult']['rate'] * 100)
                    . '% &middot; &minus;' . self::money($r['adult_discount']) . ' ' . $c;
            }
            if ($r['single_supp'] > 0) {
                $notes[] = sprintf(VAZ_I18N::t($lang, 'details.singleSuppNote'), self::money($r['single_supp']), $c);
            }
            $notesHtml = $notes
                ? '<div style="margin-top:6px;font-size:12.5px;font-weight:600;color:' . $green . ';">' . implode('<br>', $notes) . '</div>'
                : '';

            $roomsHtml .= '
              <tr>
                <td style="padding:14px 16px;border:1px solid ' . $line . ';border-radius:10px;display:block;margin-bottom:10px;">
                  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                    <td style="font-size:14px;font-weight:700;color:' . $ink . ';">' . VAZ_I18N::t($lang, 'ui.room') . ' ' . $r['index'] . ' &middot; ' . $type . '</td>
                    <td style="font-size:14px;font-weight:700;color:' . $ink . ';text-align:right;white-space:nowrap;">' . self::money($r['subtotal']) . ' ' . $c . '</td>
                  </tr></table>
                  <div style="margin-top:3px;font-size:12.5px;color:' . $muted . ';">' . $board . ' &middot; ' . $occ . '</div>
                  ' . $notesHtml . '
                </td>
              </tr>
              <tr><td style="height:10px;line-height:10px;font-size:0;">&nbsp;</td></tr>';
        }

        $accSubtotal = round($quote['total'] - $quote['tourist_tax'], 2);
        $tx = $cfg['tourist_tax'];

        $totalsHtml = '
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:4px;">
            <tr><td style="padding:6px 0;font-size:13.5px;color:' . $ink . ';">' . VAZ_I18N::t($lang, 'ui.accommodation') . '</td>
                <td style="padding:6px 0;font-size:13.5px;color:' . $ink . ';text-align:right;">' . self::money($accSubtotal) . ' ' . $c . '</td></tr>
            <tr><td style="padding:6px 0;font-size:13.5px;color:' . $ink . ';">' . VAZ_I18N::t($lang, 'ui.touristTax') . '</td>
                <td style="padding:6px 0;font-size:13.5px;color:' . $ink . ';text-align:right;">' . self::money($quote['tourist_tax']) . ' ' . $c . '</td></tr>
          </table>
          <p style="margin:8px 0 0;font-size:11.5px;line-height:1.5;color:' . $muted . ';">
            ' . sprintf(VAZ_I18N::t($lang, 'details.touristTaxNote'), $tx['per_person_per_night'], $c, $tx['max_nights'], VAZ_I18N::word($lang, 'night', $tx['max_nights'])) . '
          </p>
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:18px;background:#FBF6EE;border-radius:12px;">
            <tr>
              <td style="padding:16px 20px;font-size:14px;font-weight:600;color:' . $ink . ';">' . VAZ_I18N::t($lang, 'details.stayTotal') . '</td>
              <td style="padding:16px 20px;font-size:24px;font-weight:800;color:' . $gold . ';text-align:right;">' . self::money($quote['total']) . ' ' . $c . '</td>
            </tr>
          </table>
          <p style="margin:10px 0 0;font-size:11.5px;line-height:1.5;color:' . $muted . ';">
            ' . VAZ_I18N::t($lang, 'details.bottomNote') . '
          </p>';

        return $stayLine
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">' . $roomsHtml . '</table>'
            . $totalsHtml;
    }

    /**
     * "Vos coordonnées" / "Coordonnées du client" recap — echoes every
     * relevant submitted field back verbatim, to client and staff alike, so
     * neither ever has to wonder what was actually recorded (name, contact,
     * stay dates, special request). $heading lets the two emails address the
     * same data as "yours" vs. "the client's".
     */
    public static function email_contact_recap_html($heading, $firstName, $lastName, $email, $phone, $checkin, $checkout, $request, $lang = null) {
        $lang = $lang ?: VAZ_I18N::current();
        $ink = '#1F1F1F'; $muted = '#727272'; $line = '#E7E1D6';
        $rows = [
            VAZ_I18N::t($lang, 'recap.name')  => trim($firstName . ' ' . $lastName),
            VAZ_I18N::t($lang, 'recap.email') => $email,
            VAZ_I18N::t($lang, 'recap.phone') => $phone,
            VAZ_I18N::t($lang, 'recap.stay')  => ($checkin && $checkout)
                ? VAZ_I18N::format_date($checkin, $lang) . ' &rarr; ' . VAZ_I18N::format_date($checkout, $lang)
                : '',
        ];
        $request = trim((string) $request);
        if ($request !== '') {
            $rows[VAZ_I18N::t($lang, 'recap.request')] = nl2br(esc_html($request));
        }

        $rowsHtml = '';
        foreach ($rows as $label => $value) {
            if ($value === '') { continue; }
            $rowsHtml .= '<tr>'
                . '<td style="padding:5px 0;font-size:12.5px;color:' . $muted . ';white-space:nowrap;vertical-align:top;">' . esc_html($label) . '</td>'
                . '<td style="padding:5px 0 5px 14px;font-size:13.5px;color:' . $ink . ';font-weight:600;word-break:break-word;overflow-wrap:break-word;">' . $value . '</td>'
                . '</tr>';
        }

        return '<p style="margin:0 0 8px;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:' . $muted . ';">' . esc_html($heading) . '</p>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:100%;margin-bottom:22px;padding:16px 18px;background:#F7F5F1;border-radius:10px;">'
            . $rowsHtml
            . '</table>';
    }

    /**
     * Client-only closing note. A reservation *request* has no payment behind
     * it, so nothing stops a guest's interest quietly fading before the team
     * calls back — this section's job is to keep that warmth alive between
     * submission and confirmation. Reuses the exact brand voice already on
     * the site's About page (villa-azur-fix.php's copy, "maison de famille",
     * "16 chambres", "Là où la mer rencontre la sérénité") rather than
     * inventing a new tone, so the email reads as continuous with the site.
     */
    public static function email_welcome_html($lang = null) {
        $lang = $lang ?: VAZ_I18N::current();
        $navy = '#0F2A48'; $gold = '#E9A668'; $ink = '#1F1F1F'; $muted = '#727272';
        return '
          <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:26px;background:#FBF6EE;border-left:3px solid ' . $gold . ';border-radius:0 10px 10px 0;">
            <tr><td style="padding:20px 22px;">
              <p style="margin:0 0 12px;font-family:Georgia,\'Times New Roman\',serif;font-style:italic;font-size:14.5px;line-height:1.75;color:' . $ink . ';">
                ' . VAZ_I18N::t($lang, 'email.welcome.p1') . '
              </p>
              <p style="margin:0;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:13.5px;line-height:1.7;color:' . $ink . ';">
                ' . VAZ_I18N::t($lang, 'email.welcome.p2') . '
              </p>
              <p style="margin:16px 0 0;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:12.5px;font-weight:700;letter-spacing:.04em;color:' . $navy . ';">
                ' . VAZ_I18N::t($lang, 'email.welcome.sig') . '
              </p>
            </td></tr>
          </table>';
    }

    /**
     * Full branded email skeleton — table-based, inline CSS only (no external
     * stylesheet, no flex/grid) for compatibility across real-world email
     * clients (Outlook/Gmail/Apple Mail). Shared by both the admin and the
     * client email; only heading/intro/footer differ between the two.
     *
     * Header is cream (not navy) with the logo centered: the logo's own
     * wordmark is navy-on-transparent, so a navy header band would render it
     * illegible — a light band keeps it readable and reads as formal
     * letterhead rather than an app banner.
     */
    public static function email_wrapper($heading, $intro, $bodyHtml, $footerHtml) {
        $navy = '#0F2A48'; $gold = '#E9A668'; $ink = '#1F1F1F'; $muted = '#727272'; $cream = '#FDF3E9';
        $logoUrl = function_exists('wp_get_attachment_image_url') ? wp_get_attachment_image_url(2070, 'full') : '';
        $logoImg = $logoUrl
            ? '<img src="' . esc_url($logoUrl) . '" alt="Villa Azur" width="72" height="60" style="display:block;height:60px;width:auto;">'
            : '<span style="font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:16px;font-weight:700;letter-spacing:.06em;color:' . $navy . ';">VILLA AZUR</span>';

        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<style>img{max-width:100%;} table{max-width:100%;} @media (max-width:600px){.vaz-email-card{width:100% !important;}}</style></head>'
        . '<body style="margin:0;padding:0;background:' . $cream . ';font-family:Georgia,\'Times New Roman\',serif;word-break:break-word;overflow-wrap:break-word;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:' . $cream . ';padding:32px 12px;">'
        . '<tr><td align="center">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="vaz-email-card" style="width:100%;max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 8px 30px rgba(15,42,72,.08);">'

        // Letterhead: centered logo, small letter-spaced tagline, thin gold rule.
        . '<tr><td align="center" style="background:#FBF6EE;padding:30px 32px 22px;">'
        . '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td align="center">' . $logoImg . '</td></tr>'
        . '<tr><td align="center" style="padding-top:10px;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:11px;letter-spacing:.14em;text-transform:uppercase;color:' . $muted . ';">Djerba &middot; Tunisie</td></tr></table>'
        . '</td></tr>'
        . '<tr><td style="height:3px;line-height:3px;font-size:0;background:' . $gold . ';">&nbsp;</td></tr>'

        . '<tr><td style="padding:34px 36px 8px;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;word-break:break-word;overflow-wrap:break-word;">'
        . '<h1 style="margin:0 0 12px;font-size:21px;font-weight:700;line-height:1.35;color:' . $navy . ';word-break:break-word;overflow-wrap:break-word;">' . $heading . '</h1>'
        . '<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:' . $ink . ';word-break:break-word;overflow-wrap:break-word;">' . $intro . '</p>'
        . '</td></tr>'
        . '<tr><td style="padding:0 36px 24px;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;word-break:break-word;overflow-wrap:break-word;">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:22px 36px 30px;border-top:1px solid #EFE9DE;font-family:\'Plus Jakarta Sans\',Arial,sans-serif;font-size:12px;line-height:1.8;color:' . $muted . ';word-break:break-word;overflow-wrap:break-word;">' . $footerHtml . '</td></tr>'
        . '</table>'
        . '</td></tr></table>'
        . '</body></html>';
    }

    /**
     * Pull check-in/out and rooms[] from the raw POST. Fluent Forms drops fields
     * it doesn't recognise from $formData (same reason the spam-guard reads
     * $_POST['data'] directly), so the JSON room payload is read from the raw
     * request too. Returns [checkin, checkout, rooms|null].
     */
    private static function extract_submission() {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- FF verifies its own nonce upstream before these filters run.
        $raw = isset($_POST['data']) ? wp_unslash($_POST['data']) : '';
        parse_str($raw, $fields);

        $checkin  = self::to_iso($fields['date_arrivee'] ?? '');
        $checkout = self::to_iso($fields['date_depart'] ?? '');

        $rooms = null;
        if (!empty($fields['vaz_rooms_json'])) {
            $decoded = json_decode($fields['vaz_rooms_json'], true);
            if (is_array($decoded)) {
                $rooms = $decoded;
            }
        }
        return [$checkin, $checkout, $rooms];
    }

    /** d/m/Y (or already-iso) → Y-m-d. */
    private static function to_iso($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('#^(\d{4})-(\d{2})-(\d{2})$#', $value)) {
            return $value;
        }
        $dt = DateTime::createFromFormat('d/m/Y', $value);
        return $dt ? $dt->format('Y-m-d') : '';
    }

    private static function money($n) {
        // Thousands with a thin space, no decimals unless needed.
        $rounded = round((float) $n, 2);
        $whole   = number_format($rounded, ($rounded == (int) $rounded) ? 0 : 2, ',', ' ');
        return $whole;
    }

    private static function rooms_summary_text($quote, $lang = null) {
        $lang  = $lang ?: VAZ_I18N::current();
        $cfg   = self::config($lang);
        $c     = $quote['currency'];
        $lines = [];
        foreach ($quote['rooms'] as $r) {
            $type  = $cfg['room_types'][$r['type']]['label'] ?? $r['type'];
            $board = $cfg['boards'][$r['board']]['label'] ?? $r['board'];
            $occ   = $r['adults'] . ' ' . VAZ_I18N::word($lang, 'adult', $r['adults']);
            if ($r['children'] > 0) {
                $occ .= ', ' . $r['children'] . ' ' . VAZ_I18N::word($lang, 'child', $r['children']);
            }
            $lines[] = sprintf(
                '%s %d — %s · %s · %s · %d %s — %s %s',
                VAZ_I18N::t($lang, 'ui.room'), $r['index'], $type, $board, $occ,
                $quote['nights'], VAZ_I18N::word($lang, 'night', $quote['nights']),
                self::money($r['subtotal']), $c
            );
            if ($r['child_discount'] > 0) {
                $lines[] = sprintf('   ✓ %s -%d%% : -%s %s', VAZ_I18N::t($lang, 'discount.child_under_12.label'),
                    round($cfg['discounts']['child_under_12']['rate'] * 100), self::money($r['child_discount']), $c);
            }
            if ($r['adult_discount'] > 0) {
                $lines[] = sprintf('   ✓ %s -%d%% : -%s %s', VAZ_I18N::t($lang, 'discount.third_adult.label'),
                    round($cfg['discounts']['third_adult']['rate'] * 100), self::money($r['adult_discount']), $c);
            }
            if ($r['single_supp'] > 0) {
                $lines[] = sprintf('   + %s : %s %s', VAZ_I18N::t($lang, 'ui.singleSupp'), self::money($r['single_supp']), $c);
            }
        }
        return implode("\n", $lines);
    }

    private static function breakdown_text($quote, $lang = null) {
        $lang = $lang ?: VAZ_I18N::current();
        $cfg = self::config($lang);
        $c   = $quote['currency'];
        $tx  = $cfg['tourist_tax'];
        $accSubtotal = round($quote['total'] - $quote['tourist_tax'], 2);
        $l = [];
        $l[] = sprintf('%s : %d %s', VAZ_I18N::t($lang, 'ui.stay'), $quote['nights'], VAZ_I18N::word($lang, 'night', $quote['nights']));
        $l[] = sprintf('%s : %s %s', VAZ_I18N::t($lang, 'ui.accommodation'), self::money($accSubtotal), $c);
        $l[] = sprintf(
            '%s (%d %s/%s/%s, max %d %s) : %s %s',
            VAZ_I18N::t($lang, 'ui.touristTax'), $tx['per_person_per_night'], $c,
            VAZ_I18N::word($lang, 'person', 1), VAZ_I18N::word($lang, 'night', 1),
            $tx['max_nights'], VAZ_I18N::word($lang, 'night', $tx['max_nights']),
            self::money($quote['tourist_tax']), $c
        );
        $l[] = sprintf('%s : %s %s', VAZ_I18N::t($lang, 'plain.totalEstimated'), self::money($quote['total']), $c);
        return implode("\n", $l);
    }
}

VAZ_Reservation::init();
