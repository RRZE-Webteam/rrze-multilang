<?php

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

use RRZE\Multilang\Single\Translation;

class Functions
{
    public static function getPostTypes()
    {
        $postTypeAry = [];
        $args = [
            'show_ui' => true,
            'public' => true
        ];
        $allPostTypes = get_post_types($args, 'objects');
        if (is_array($allPostTypes) && !empty($allPostTypes)) {
            foreach ($allPostTypes as $name => $postObj) {
                if ($name == 'attachment') {
                    continue;
                }
                $postTypeAry[$name] = !empty($postObj->labels->name) ? $postObj->labels->name : $name;
            }
        }
        return $postTypeAry;
    }

    public static function arrayOrderby()
    {
        $args = func_get_args();
        $data = array_shift($args);
        foreach ($args as $n => $field) {
            if (is_string($field)) {
                $tmp = [];
                foreach ($data as $key => $row) {
                    $tmp[$key] = $row[$field];
                }
                $args[$n] = $tmp;
            }
        }
        $args[] = &$data;
        call_user_func_array('array_multisort', $args);
        return array_pop($args);
    }

    public static function formatAtts($atts)
    {
        $html = '';

        $prioritizedAtts = ['type', 'name', 'value'];

        foreach ($prioritizedAtts as $att) {
            if (isset($atts[$att])) {
                $value = trim($atts[$att]);
                $html .= sprintf(' %s="%s"', $att, esc_attr($value));
                unset($atts[$att]);
            }
        }

        foreach ($atts as $key => $value) {
            $key = strtolower(trim($key));

            if (!preg_match('/^[a-z_:][a-z_:.0-9-]*$/', $key)) {
                continue;
            }

            $value = trim($value);

            if ('' !== $value) {
                $html .= sprintf(' %s="%s"', $key, esc_attr($value));
            }
        }

        $html = trim($html);

        return $html;
    }

    public static function translate($singular, $context = '', $default = '')
    {
        return Translation::translate($singular, $context, $default);
    }

    public static function translateTerm(\WP_Term $term)
    {
        $term->name = self::translate(
            sprintf('%s:%d', $term->taxonomy, $term->term_id),
            $term->taxonomy,
            $term->name
        );

        return $term;
    }

    public static function termsTranslation($locale_to_edit)
    {
        static $items = [];
        static $locale = null;

        if (
            !empty($items)
            && $locale === $locale_to_edit
        ) {
            return $items;
        }

        $locale = $locale_to_edit;

        if (!Translation::import($locale)) {
            Translation::reset();
        }

        if (!Locale::isAvailableLocale($locale)) {
            return $items;
        }

        $items[] = [
            'name' => 'blogname',
            'original' => get_option('blogname'),
            'translated' => Functions::translate(
                'blogname',
                'blogname',
                get_option('blogname')
            ),
            'context' => __('Site Title', 'rrze-multilang'),
            'cap' => 'manage_options',
        ];

        $items[] = [
            'name' => 'blogdescription',
            'original' => get_option('blogdescription'),
            'translated' => Functions::translate(
                'blogdescription',
                'blogdescription',
                get_option('blogdescription')
            ),
            'context' => __('Tagline', 'rrze-multilang'),
            'cap' => 'manage_options',
        ];

        remove_filter('get_term', ['RRZE\Multilang\Terms', 'removeGetTermFilter']);

        foreach ((array) get_taxonomies([], 'objects') as $taxonomy) {
            $tax_labels = get_taxonomy_labels($taxonomy);
            $terms = get_terms([
                'taxonomy' => $taxonomy->name,
                'orderby' => 'slug',
                'hide_empty' => false,
            ]);

            foreach ((array) $terms as $term) {
                $name = sprintf('%s:%d', $taxonomy->name, $term->term_id);
                $items[] = [
                    'name' => $name,
                    'original' => $term->name,
                    'translated' => Functions::translate(
                        $name,
                        $taxonomy->name,
                        $term->name
                    ),
                    'context' => $tax_labels->name,
                    'cap' => $taxonomy->cap->edit_terms,
                ];
            }
        }

        add_filter('get_term', ['RRZE\Multilang\Terms', 'removeGetTermFilter'], 10, 2);

        $items = apply_filters('rrze_multilang_terms_translation', $items, $locale);

        return $items;
    }

    public static function fixUrl($url)
    {
        $protocol = stripos($_SERVER['SERVER_PROTOCOL'], 'https') === true ? 'https://' : 'http://';
        $url = preg_replace("/(^\/\/)/", $protocol, $url);
        return $url;
    }

    public static function getTablename($blogid, $table = 'posts')
    {
        global $wpdb;

        $siteid = $blogid != 1 ? $blogid . "_" : '';
        $tablename = $wpdb->base_prefix . $siteid . $table;

        return $tablename;
    }

    public static function &hashObjBy($objArray = false, $key)
    {
        $res = [];
        if ($objArray && is_array($objArray)) {
            foreach ($objArray as &$obj) {
                $res[$obj->$key] = $obj;
            }
        }
        unset($obj);
        return $res;
    }

    public static function isBlogPublic(int $blogId): bool
    {
        return (bool) get_blog_status($blogId, 'public');
    }

    public static function flashAdminNotice($message, $class = '')
    {
        if (!($currentUser = get_current_user_id())) {
            return;
        }
        $defaultAllowedClasses = array('error', 'updated');
        $allowedClasses = apply_filters('admin_notices_allowed_classes', $defaultAllowedClasses);
        $defaultClass = apply_filters('admin_notices_default_class', 'updated');

        if (!in_array($class, $allowedClasses)) {
            $class = $defaultClass;
        }

        $transient = sprintf('rrze_multilang_flash_admin_notices_%s', $currentUser);
        $transientValue = get_transient($transient);
        $notices = maybe_unserialize($transientValue ? $transientValue : []);
        $notices[$class][] = $message;

        set_transient($transient, $notices, 60);
    }

    public static function showFlashAdminNotices()
    {
        if (!($currentUser = get_current_user_id())) {
            return;
        }
        $transient = sprintf('rrze_multilang_flash_admin_notices_%s', $currentUser);
        $transientValue = get_transient($transient);
        $notices = maybe_unserialize($transientValue ? $transientValue : '');

        if (is_array($notices)) {
            foreach ($notices as $class => $messages) {
                foreach ($messages as $message) {
                    printf('<div class="%1$s">%2$s', $class, PHP_EOL);
                    printf('<p>%1$s</p>%2$s', $message, PHP_EOL);
                    echo '</div>', PHP_EOL;
                }
            }
        }

        delete_transient($transient);
    }
}
