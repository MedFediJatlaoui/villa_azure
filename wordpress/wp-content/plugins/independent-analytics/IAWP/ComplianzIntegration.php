<?php

namespace IAWP;

/** @internal */
class ComplianzIntegration
{
    public static function initialize()
    {
        \add_filter('cmplz_integrations', function ($cmplz_integrations_list) {
            $cmplz_integrations_list['independent-analytics'] = ['constant_or_function' => 'IAWP_DB_VERSION', 'label' => 'Independent Analytics'];
            return $cmplz_integrations_list;
        });
        \add_filter('cmplz_integration_path', function ($path, $plugin) {
            if ($plugin === 'independent-analytics') {
                return \IAWPSCOPED\iawp_path_to('iawp-complianz.php');
            }
            return $path;
        }, 10, 2);
        \add_filter('default_option_complianz_options_integrations', function ($value) {
            $plugin_key = 'independent-analytics';
            // If the option isn't an array yet (not saved), initialize it
            if (!\is_array($value)) {
                $value = [];
            }
            // Only set the default if the user hasn't manually saved a preference yet
            if (!isset($value[$plugin_key])) {
                $value[$plugin_key] = \false;
            }
            return $value;
        });
        \add_filter('option_complianz_options_integrations', function ($value) {
            $plugin_key = 'independent-analytics';
            // If the option isn't an array yet (not saved), initialize it
            if (!\is_array($value)) {
                $value = [];
            }
            // Only set the default if the user hasn't manually saved a preference yet
            if (!isset($value[$plugin_key])) {
                $value[$plugin_key] = \false;
            }
            return $value;
        });
    }
}
