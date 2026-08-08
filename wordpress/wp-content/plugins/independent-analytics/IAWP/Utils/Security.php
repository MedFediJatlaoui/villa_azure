<?php

namespace IAWP\Utils;

/** @internal */
class Security
{
    public static function json_encode($object)
    {
        return \json_encode($object, \JSON_HEX_QUOT | \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS);
    }
    public static function string($string)
    {
        return \trim(\sanitize_text_field($string));
    }
    public static function array($array)
    {
        if (\is_string($array)) {
            return self::string($array);
        }
        if (\is_array($array)) {
            foreach ($array as $key => $value) {
                $sanitized_key = \is_string($key) ? self::string($key) : $key;
                // Sanitize key if it's a string
                $array[$sanitized_key] = self::array($value);
                // Recursively sanitize value
                if ($sanitized_key !== $key) {
                    unset($array[$key]);
                    // Remove old key if changed
                }
            }
        }
        return $array;
    }
    public static function form($html)
    {
        return \wp_kses($html, ['div' => ['class' => [], 'style' => []], 'select' => ['class' => []], 'option' => ['class' => [], 'value' => [], 'data-datatype' => []], 'input' => ['class' => [], 'type' => [], 'data-css' => [], 'data-dow' => [], 'data-format' => [], 'readonly'], 'button' => ['class' => []], 'span' => ['class' => [], 'style' => []]]);
    }
    public static function svg($html)
    {
        return \wp_kses($html, ['svg' => ['height' => [], 'width' => [], 'fill' => [], 'viewbox' => [], 'style' => []], 'path' => ['d' => []]]);
    }
    public static function table_cell_content($html)
    {
        return \wp_kses($html, ['span' => ['class' => []], 'a' => ['href' => [], 'target' => [], 'class' => []], 'img' => ['alt' => [], 'src' => [], 'class' => [], 'height' => [], 'width' => [], 'loading' => []], 'div' => ['class' => []]]);
    }
    public static function user_journey_event_contents($html)
    {
        return \wp_kses($html, ['p' => [], 'a' => ['href' => [], 'target' => []], 'span' => ['class' => []]]);
    }
    public static function strong_tags_only($html)
    {
        return \wp_kses($html, ['strong' => []]);
    }
    public static function span_tags_only($html)
    {
        return \wp_kses($html, ['span' => ['class' => []]]);
    }
}
