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

        /* Edit Nav Menu Walker */
        add_filter('wp_edit_nav_menu_walker', [$this, 'editNavMenuWalker'], 10, 2);
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

    public function editNavMenuWalker($class, $menuId)
    {
        return '\RRZE\Multilang\Single\NavMenuEdit';
    }

    public function updateNavMenuItem($menuId, $menuItemId)
    {
        if (!isset($_POST['menu-item-rrze-multilang-locale'][$menuItemId])) {
            return;
        }

        $requestedLocales = (array) $_POST['menu-item-rrze-multilang-locale'][$menuItemId];
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
