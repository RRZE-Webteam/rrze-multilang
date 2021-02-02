<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Locale;

require_once(ABSPATH . 'wp-admin/includes/class-walker-nav-menu-edit.php');

class NavMenuEdit extends \Walker_Nav_Menu_Edit
{
    public function start_el(&$output, $item, $depth = 0, $args = '', $id = 0)
    {
        $parallel_output = '';

        parent::start_el($parallel_output, $item, $depth);

        $parallel_output = preg_replace(
            '/<div class="menu-item-settings wp-clearfix" id="menu-item-settings-([0-9]+)">/',
            '<div class="menu-item-settings wp-clearfix has-rrze-multilang-settings" id="menu-item-settings-${1}">' . $this->language_settings($item),
            $parallel_output,
            1
        );

        $output .= $parallel_output;
    }

    private function language_settings($menu_item)
    {
        $available_languages = Locale::getAvailableLanguages([
            'orderby' => 'value',
        ]);

        if (!$available_languages) {
            return '';
        }

        $output = '';

        $output .= '<fieldset class="field-rrze-multilang-language description rrze-multilang-locale-options">';

        $output .= sprintf(
            '<legend>%s</legend>',
            /* translators: followed by available languages list */
            esc_html(__('Displayed on pages in', 'rrze-multilang'))
        );

        $name_attr = sprintf(
            'menu-item-rrze-multilang-locale[%s][]',
            $menu_item->ID
        );

        $dummy = sprintf(
            '<input type="hidden" name="%1$s" value="%2$s" />',
            esc_attr($name_attr),
            'zxx' // special code in ISO 639-2
        );

        $output .= $dummy;

        foreach ($available_languages as $locale => $language) {
            $selected = in_array($locale, (array) $menu_item->rrze_multilang_locales);

            $id_attr = sprintf(
                'edit-menu-item-rrze-multilang-locale-%1$s-%2$s',
                $menu_item->ID,
                $locale
            );

            $input = sprintf(
                '<input type="checkbox" id="%1$s" name="%2$s" value="%3$s"%4$s />',
                esc_attr($id_attr),
                esc_attr($name_attr),
                esc_attr($locale),
                $selected ? ' checked="checked"' : ''
            );

            $label = sprintf(
                '<label for="%1$s" class="rrze-multilang-locale-option%2$s">%3$s %4$s</label>',
                esc_attr($id_attr),
                $selected ? ' checked' : '',
                $input,
                esc_html($language)
            );

            $output .= $label;
        }

        $output .= '</fieldset>';

        return $output;
    }
}
