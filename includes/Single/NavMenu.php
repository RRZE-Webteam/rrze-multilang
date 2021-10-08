<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Locale;

class NavMenu
{
    public function __construct()
    {
        add_filter('wp_get_nav_menu_items', [$this, 'getNavMenuItems'], 10, 3);
        add_filter('wp_setup_nav_menu_item', [$this, 'setupNavMenuItem'], 10, 1);
        add_action('wp_nav_menu_item_custom_fields', [$this, 'customFields'], 10, 2);
        add_action('wp_update_nav_menu_item', [$this, 'updateNavMenuItem'], 10, 2);
    }

    public function getNavMenuItems($items, $menu, $args)
    {
        if (is_admin()) {
            return $items;
        }

        $locale = get_locale();

        foreach ($items as $key => $item) {
            if (!in_array($locale, $item->rrze_multilang_locales)) {
                unset($items[$key]);
            }
        }

        return $items;
    }

    public function setupNavMenuItem($menuItem)
    {
        if (isset($menuItem->rrze_multilang_locales)) {
            return $menuItem;
        }

        $menuItem->rrze_multilang_locales = [];

        if (
            isset($menuItem->post_type)
            && 'nav_menu_item' == $menuItem->post_type
        ) {
            $menuItem->rrze_multilang_locales = get_post_meta($menuItem->ID, '_rrze_multilang_single_locale');
        }

        if ($menuItem->rrze_multilang_locales) {
            $menuItem->rrze_multilang_locales = Locale::filterLocales($menuItem->rrze_multilang_locales);
        } else {
            $menuItem->rrze_multilang_locales = Locale::availableLocales();
        }

        return $menuItem;
    }

    public function customFields($menuItemId, $menuItem)
    {
        wp_nonce_field('rrze_multilang_custom_nav_fields_nonce', '_rrze_multilang_custom_nav_fields_nonce_name');
        $availableLanguages = Locale::getAvailableLanguages([
            'orderby' => 'value',
        ]);

        if (!$availableLanguages) {
            return '';
        }

        $output = '';

        $output .= '<fieldset class="field-rrze-multilang-language description rrze-multilang-locale-options">';

        $output .= sprintf(
            '<legend>%s</legend>',
            /* translators: followed by available languages list */
            esc_html(__('Displayed on pages in', 'rrze-multilang'))
        );

        $nameAttr = sprintf(
            'rrze-multilang-menu-item-locale[%s][]',
            $menuItemId
        );

        $dummy = sprintf(
            '<input type="hidden" name="%1$s" value="%2$s" />',
            esc_attr($nameAttr),
            'zxx' // special code in ISO 639-2
        );

        $output .= $dummy;

        foreach ($availableLanguages as $locale => $language) {
            $selected = in_array($locale, (array) $menuItem->rrze_multilang_locales);

            $idAttr = sprintf(
                'rrze-multilang-edit-menu-item-locale-%1$s-%2$s',
                $menuItemId,
                $locale
            );

            $input = sprintf(
                '<input type="checkbox" id="%1$s" name="%2$s" value="%3$s"%4$s />',
                esc_attr($idAttr),
                esc_attr($nameAttr),
                esc_attr($locale),
                $selected ? ' checked="checked"' : ''
            );

            $label = sprintf(
                '<label for="%1$s" class="rrze-multilang-locale-option%2$s">%3$s %4$s</label>',
                esc_attr($idAttr),
                $selected ? ' checked' : '',
                $input,
                esc_html($language)
            );

            $output .= $label . '<br>';
        }

        $output .= '</fieldset>';

        echo $output;
    }

    public function updateNavMenuItem($menuId, $menuItemId)
    {
        if (
            !isset($_POST['_rrze_multilang_custom_nav_fields_nonce_name'])
            || !wp_verify_nonce($_POST['_rrze_multilang_custom_nav_fields_nonce_name'], 'rrze_multilang_custom_nav_fields_nonce')
        ) {
            return;
        }

        if (!isset($_POST['rrze-multilang-menu-item-locale'][$menuItemId])) {
            return;
        }

        $requestedLocales = (array) $_POST['rrze-multilang-menu-item-locale'][$menuItemId];
        $currentLocales = (array) get_post_meta($menuItemId, '_rrze_multilang_single_locale');

        foreach ((array) Locale::availableLocales() as $locale) {
            if (
                in_array($locale, $currentLocales)
                && !in_array($locale, $requestedLocales)
            ) {
                delete_post_meta($menuItemId, '_rrze_multilang_single_locale', $locale);
            }

            if (
                !in_array($locale, $currentLocales)
                && in_array($locale, $requestedLocales)
            ) {
                add_post_meta($menuItemId, '_rrze_multilang_single_locale', $locale);
            }
        }

        if (!metadata_exists('post', $menuItemId, '_rrze_multilang_single_locale')) {
            add_post_meta($menuItemId, '_rrze_multilang_single_locale', 'zxx'); // special code in ISO 639-2
        }
    }
}
