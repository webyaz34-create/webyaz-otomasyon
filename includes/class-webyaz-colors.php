<?php
if (!defined('ABSPATH')) exit;

class Webyaz_Colors {

    public static function get_theme_colors() {
        $primary = get_theme_mod('color_primary', '#446084');
        $secondary = get_theme_mod('color_secondary', '#d26e4b');

        if (function_exists('get_flatsome_opt')) {
            $p = get_flatsome_opt('color_primary');
            $s = get_flatsome_opt('color_secondary');
            if ($p) $primary = $p;
            if ($s) $secondary = $s;
        }

        return [
            'primary' => sanitize_hex_color($primary) ?: '#446084',
            'secondary' => sanitize_hex_color($secondary) ?: '#d26e4b',
        ];
    }
}
