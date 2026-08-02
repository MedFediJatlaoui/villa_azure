<?php

defined('ABSPATH') or die("you do not have access to this page!");

add_filter('cmplz_known_script_tags', function ($tags) {
    $tags[] = [
        'name'     => 'independent-analytics',
        'category' => 'statistics',
        'urls'     => [],
    ];

    return $tags;
});
