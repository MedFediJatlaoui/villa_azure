<?php

namespace IAWP\Click_Tracking;

use IAWPSCOPED\Illuminate\Support\Str;
/** @internal */
class Site
{
    /**
     * A site will have a click id only if it's part of a multisite network.
     */
    public static function id() : ?string
    {
        if (!\is_multisite()) {
            return null;
        }
        $option_value = \get_option('iawp_click_tracking_id');
        if (!\is_string($option_value)) {
            \update_option('iawp_click_tracking_id', Str::random(8), \false);
            $option_value = \get_option('iawp_click_tracking_id');
        }
        return $option_value;
    }
    /**
     * A site will have a non-empty click file suffix only if it's part of a multisite network.
     */
    public static function file_suffix() : string
    {
        if (self::id() === null) {
            return '';
        }
        return '-' . self::id();
    }
}
