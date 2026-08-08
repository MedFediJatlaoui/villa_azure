<?php
// Rebuild Fluent Forms 3 & 4 with correct structure
// Run via: wp eval-file rebuild_forms.php

$ts = round(microtime(true) * 1000);

// =============================================
// FORM 3 — CONTACT
// =============================================
$contact_fields = [
    "fields" => [
        [
            "index" => 0,
            "element" => "input_text",
            "attributes" => ["type" => "text", "name" => "nom_complet", "value" => "", "id" => "", "class" => "", "placeholder" => "Votre nom complet"],
            "settings" => [
                "label" => "Nom complet",
                "placeholder" => "Votre nom complet",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Nom complet",
                "conditional_logics" => [],
                "validation_rules" => ["required" => ["value" => true, "message" => "Ce champ est obligatoire"]]
            ],
            "editor_options" => ["title" => "Nom complet", "element" => "input_text", "icon_class" => "ff-edit-input", "template" => "inputText"],
            "uniqElKey" => "el_" . ($ts + 1)
        ],
        [
            "index" => 1,
            "element" => "input_email",
            "attributes" => ["type" => "email", "name" => "email", "value" => "", "id" => "", "class" => "", "placeholder" => "votre@email.com"],
            "settings" => [
                "label" => "Adresse email",
                "placeholder" => "votre@email.com",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Email",
                "conditional_logics" => [],
                "validation_rules" => [
                    "required" => ["value" => true, "message" => "Ce champ est obligatoire"],
                    "email" => ["value" => true, "message" => "Veuillez entrer un email valide"]
                ]
            ],
            "editor_options" => ["title" => "Email", "element" => "input_email", "icon_class" => "ff-edit-email", "template" => "inputEmail"],
            "uniqElKey" => "el_" . ($ts + 2)
        ],
        [
            "index" => 2,
            "element" => "input_text",
            "attributes" => ["type" => "text", "name" => "telephone", "value" => "", "id" => "", "class" => "", "placeholder" => "+216 XX XXX XXX"],
            "settings" => [
                "label" => "Telephone",
                "placeholder" => "+216 XX XXX XXX",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Telephone",
                "conditional_logics" => [],
                "validation_rules" => []
            ],
            "editor_options" => ["title" => "Telephone", "element" => "input_text", "icon_class" => "ff-edit-input", "template" => "inputText"],
            "uniqElKey" => "el_" . ($ts + 3)
        ],
        [
            "index" => 3,
            "element" => "select",
            "attributes" => ["name" => "type_de_demande", "id" => "", "class" => "", "value" => ""],
            "settings" => [
                "label" => "Type de demande",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Type de demande",
                "placeholder" => "Selectionner le type de demande",
                "conditional_logics" => [],
                "validation_rules" => ["required" => ["value" => true, "message" => "Ce champ est obligatoire"]],
                "options" => [
                    ["label" => "Demande d information generale", "value" => "information"],
                    ["label" => "Demande de reservation", "value" => "reservation"],
                    ["label" => "Annulation ou modification de reservation", "value" => "annulation_modification"],
                    ["label" => "Evenement prive (mariage, anniversaire, seminaire)", "value" => "evenement_prive"],
                    ["label" => "Demande de devis groupe", "value" => "devis_groupe"],
                    ["label" => "Reclamation", "value" => "reclamation"],
                    ["label" => "Suggestion", "value" => "suggestion"],
                    ["label" => "Partenariat et B2B", "value" => "partenariat"],
                    ["label" => "Presse et medias", "value" => "presse_medias"],
                    ["label" => "Autre", "value" => "autre"]
                ]
            ],
            "editor_options" => ["title" => "Type de demande", "element" => "select", "icon_class" => "ff-edit-select", "template" => "select"],
            "uniqElKey" => "el_" . ($ts + 4)
        ],
        [
            "index" => 4,
            "element" => "textarea",
            "attributes" => ["name" => "message", "id" => "", "class" => "", "placeholder" => "Votre message...", "rows" => 5, "cols" => ""],
            "settings" => [
                "label" => "Message",
                "placeholder" => "Votre message (20 caracteres minimum)...",
                "help_message" => "Minimum 20 caracteres",
                "container_class" => "",
                "admin_field_label" => "Message",
                "conditional_logics" => [],
                "validation_rules" => [
                    "required" => ["value" => true, "message" => "Ce champ est obligatoire"],
                    "minLength" => ["value" => 20, "message" => "Minimum 20 caracteres requis"]
                ]
            ],
            "editor_options" => ["title" => "Message", "element" => "textarea", "icon_class" => "ff-edit-textarea", "template" => "textarea"],
            "uniqElKey" => "el_" . ($ts + 5)
        ]
    ],
    "submitButton" => [
        "element" => "button",
        "attributes" => ["type" => "submit", "class" => ""],
        "settings" => [
            "align" => "center",
            "button_style" => "default",
            "container_class" => "",
            "help_message" => "",
            "button_size" => "md",
            "button_ui" => ["type" => "default", "text" => "Envoyer le message", "img_url" => ""]
        ],
        "editor_options" => ["title" => "Submit Button"],
        "uniqElKey" => "el_" . ($ts + 6)
    ]
];

wpFluent()->table("fluentform_forms")->where("id", 3)->update([
    "form_fields" => json_encode($contact_fields),
    "updated_at" => current_time("mysql")
]);

$check = wpFluent()->table("fluentform_forms")->find(3);
$parsed = json_decode($check->form_fields, true);
echo "Form 3 rebuilt.\n";
echo "  Fields: " . count($parsed["fields"]) . "\n";
echo "  uniqElKey[0]: " . ($parsed["fields"][0]["uniqElKey"] ?? "MISSING") . "\n";
echo "  template[0]: " . ($parsed["fields"][0]["editor_options"]["template"] ?? "MISSING") . "\n";
echo "  Submit: " . ($parsed["submitButton"]["settings"]["button_ui"]["text"] ?? "MISSING") . "\n\n";

// =============================================
// FORM 4 — RESERVATION
// =============================================
$ts2 = $ts + 100;
$resa_fields = [
    "fields" => [
        [
            "index" => 0,
            "element" => "input_text",
            "attributes" => ["type" => "text", "name" => "nom_complet", "value" => "", "id" => "", "class" => "", "placeholder" => "Votre nom complet"],
            "settings" => [
                "label" => "Nom complet",
                "placeholder" => "Votre nom complet",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Nom complet",
                "conditional_logics" => [],
                "validation_rules" => ["required" => ["value" => true, "message" => "Ce champ est obligatoire"]]
            ],
            "editor_options" => ["title" => "Nom complet", "element" => "input_text", "icon_class" => "ff-edit-input", "template" => "inputText"],
            "uniqElKey" => "el_" . ($ts2 + 1)
        ],
        [
            "index" => 1,
            "element" => "input_email",
            "attributes" => ["type" => "email", "name" => "email", "value" => "", "id" => "", "class" => "", "placeholder" => "votre@email.com"],
            "settings" => [
                "label" => "Adresse email",
                "placeholder" => "votre@email.com",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Email",
                "conditional_logics" => [],
                "validation_rules" => [
                    "required" => ["value" => true, "message" => "Ce champ est obligatoire"],
                    "email" => ["value" => true, "message" => "Veuillez entrer un email valide"]
                ]
            ],
            "editor_options" => ["title" => "Email", "element" => "input_email", "icon_class" => "ff-edit-email", "template" => "inputEmail"],
            "uniqElKey" => "el_" . ($ts2 + 2)
        ],
        [
            "index" => 2,
            "element" => "input_text",
            "attributes" => ["type" => "text", "name" => "telephone_whatsapp", "value" => "", "id" => "", "class" => "", "placeholder" => "+216 XX XXX XXX"],
            "settings" => [
                "label" => "Telephone ou WhatsApp",
                "placeholder" => "+216 XX XXX XXX",
                "help_message" => "Nous confirmerons votre reservation par WhatsApp",
                "container_class" => "",
                "admin_field_label" => "Telephone WhatsApp",
                "conditional_logics" => [],
                "validation_rules" => ["required" => ["value" => true, "message" => "Ce champ est obligatoire"]]
            ],
            "editor_options" => ["title" => "Telephone WhatsApp", "element" => "input_text", "icon_class" => "ff-edit-input", "template" => "inputText"],
            "uniqElKey" => "el_" . ($ts2 + 3)
        ],
        [
            "index" => 3,
            "element" => "input_date",
            "attributes" => ["type" => "text", "name" => "date_arrivee", "value" => "", "id" => "", "class" => "", "placeholder" => "Date d arrivee"],
            "settings" => [
                "label" => "Date d arrivee",
                "placeholder" => "jj/mm/aaaa",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Date arrivee",
                "date_format" => "d/m/Y",
                "conditional_logics" => [],
                "validation_rules" => ["required" => ["value" => true, "message" => "Ce champ est obligatoire"]]
            ],
            "editor_options" => ["title" => "Date arrivee", "element" => "input_date", "icon_class" => "ff-edit-date", "template" => "inputDate"],
            "uniqElKey" => "el_" . ($ts2 + 4)
        ],
        [
            "index" => 4,
            "element" => "input_date",
            "attributes" => ["type" => "text", "name" => "date_depart", "value" => "", "id" => "", "class" => "", "placeholder" => "Date de depart"],
            "settings" => [
                "label" => "Date de depart",
                "placeholder" => "jj/mm/aaaa",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Date depart",
                "date_format" => "d/m/Y",
                "conditional_logics" => [],
                "validation_rules" => ["required" => ["value" => true, "message" => "Ce champ est obligatoire"]]
            ],
            "editor_options" => ["title" => "Date depart", "element" => "input_date", "icon_class" => "ff-edit-date", "template" => "inputDate"],
            "uniqElKey" => "el_" . ($ts2 + 5)
        ],
        [
            "index" => 5,
            "element" => "input_number",
            "attributes" => ["type" => "number", "name" => "nombre_adultes", "value" => "1", "id" => "", "class" => "", "placeholder" => "1", "min" => "1", "max" => "10"],
            "settings" => [
                "label" => "Nombre d adultes",
                "placeholder" => "1",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Adultes",
                "conditional_logics" => [],
                "validation_rules" => [
                    "required" => ["value" => true, "message" => "Ce champ est obligatoire"],
                    "min" => ["value" => 1, "message" => "Minimum 1 adulte"],
                    "max" => ["value" => 10, "message" => "Maximum 10 adultes"]
                ]
            ],
            "editor_options" => ["title" => "Adultes", "element" => "input_number", "icon_class" => "ff-edit-number", "template" => "inputNumber"],
            "uniqElKey" => "el_" . ($ts2 + 6)
        ],
        [
            "index" => 6,
            "element" => "input_number",
            "attributes" => ["type" => "number", "name" => "nombre_enfants", "value" => "0", "id" => "", "class" => "", "placeholder" => "0", "min" => "0", "max" => "6"],
            "settings" => [
                "label" => "Nombre d enfants",
                "placeholder" => "0",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Enfants",
                "conditional_logics" => [],
                "validation_rules" => [
                    "min" => ["value" => 0, "message" => "Minimum 0"],
                    "max" => ["value" => 6, "message" => "Maximum 6 enfants"]
                ]
            ],
            "editor_options" => ["title" => "Enfants", "element" => "input_number", "icon_class" => "ff-edit-number", "template" => "inputNumber"],
            "uniqElKey" => "el_" . ($ts2 + 7)
        ],
        [
            "index" => 7,
            "element" => "select",
            "attributes" => ["name" => "type_de_chambre", "id" => "", "class" => "", "value" => ""],
            "settings" => [
                "label" => "Type de chambre",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Type de chambre",
                "placeholder" => "Selectionnez le type de chambre",
                "conditional_logics" => [],
                "validation_rules" => ["required" => ["value" => true, "message" => "Ce champ est obligatoire"]],
                "options" => [
                    ["label" => "Chambre Deluxe - 35 a 40m2 - a partir de 120 USD/nuit", "value" => "deluxe"],
                    ["label" => "Villa Familiale - 90m2 - a partir de 280 USD/nuit", "value" => "familiale"],
                    ["label" => "Suite Presidentielle - 80m2 - a partir de 450 USD/nuit", "value" => "suite"],
                    ["label" => "Pas encore decide", "value" => "indecis"]
                ]
            ],
            "editor_options" => ["title" => "Type de chambre", "element" => "select", "icon_class" => "ff-edit-select", "template" => "select"],
            "uniqElKey" => "el_" . ($ts2 + 8)
        ],
        [
            "index" => 8,
            "element" => "select",
            "attributes" => ["name" => "occasion_speciale", "id" => "", "class" => "", "value" => ""],
            "settings" => [
                "label" => "Occasion speciale",
                "help_message" => "",
                "container_class" => "",
                "admin_field_label" => "Occasion speciale",
                "placeholder" => "Selectionnez si applicable",
                "conditional_logics" => [],
                "validation_rules" => [],
                "options" => [
                    ["label" => "Aucune", "value" => "aucune"],
                    ["label" => "Lune de miel", "value" => "lune_de_miel"],
                    ["label" => "Anniversaire", "value" => "anniversaire"],
                    ["label" => "Voyage affaires", "value" => "affaires"],
                    ["label" => "Autre", "value" => "autre"]
                ]
            ],
            "editor_options" => ["title" => "Occasion speciale", "element" => "select", "icon_class" => "ff-edit-select", "template" => "select"],
            "uniqElKey" => "el_" . ($ts2 + 9)
        ],
        [
            "index" => 9,
            "element" => "textarea",
            "attributes" => ["name" => "demandes_particulieres", "id" => "", "class" => "", "placeholder" => "Lit bebe, allergie, decoration anniversaire...", "rows" => 4, "cols" => ""],
            "settings" => [
                "label" => "Demandes particulieres",
                "placeholder" => "Lit bebe, allergie, regime alimentaire, decoration anniversaire...",
                "help_message" => "Optionnel",
                "container_class" => "",
                "admin_field_label" => "Demandes particulieres",
                "conditional_logics" => [],
                "validation_rules" => []
            ],
            "editor_options" => ["title" => "Demandes particulieres", "element" => "textarea", "icon_class" => "ff-edit-textarea", "template" => "textarea"],
            "uniqElKey" => "el_" . ($ts2 + 10)
        ]
    ],
    "submitButton" => [
        "element" => "button",
        "attributes" => ["type" => "submit", "class" => ""],
        "settings" => [
            "align" => "center",
            "button_style" => "default",
            "container_class" => "",
            "help_message" => "",
            "button_size" => "md",
            "button_ui" => ["type" => "default", "text" => "Envoyer la demande", "img_url" => ""]
        ],
        "editor_options" => ["title" => "Submit Button"],
        "uniqElKey" => "el_" . ($ts2 + 11)
    ]
];

wpFluent()->table("fluentform_forms")->where("id", 4)->update([
    "form_fields" => json_encode($resa_fields),
    "updated_at" => current_time("mysql")
]);

$check4 = wpFluent()->table("fluentform_forms")->find(4);
$parsed4 = json_decode($check4->form_fields, true);
echo "Form 4 rebuilt.\n";
echo "  Fields: " . count($parsed4["fields"]) . "\n";
echo "  uniqElKey[0]: " . ($parsed4["fields"][0]["uniqElKey"] ?? "MISSING") . "\n";
echo "  template[0]: " . ($parsed4["fields"][0]["editor_options"]["template"] ?? "MISSING") . "\n";
echo "  Submit: " . ($parsed4["submitButton"]["settings"]["button_ui"]["text"] ?? "MISSING") . "\n\n";

// Final shortcode test
$out3 = do_shortcode('[fluentform id="3"]');
$out4 = do_shortcode('[fluentform id="4"]');
echo "Form 3 shortcode output length: " . strlen($out3) . " chars\n";
echo "Form 4 shortcode output length: " . strlen($out4) . " chars\n";
