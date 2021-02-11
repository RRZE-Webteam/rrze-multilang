<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Functions;

class Terms
{
    public function __construct()
    {
        add_filter('bloginfo', [__CLASS__, 'bloginfoFilter'], 10, 2);
        add_filter('get_term', [__CLASS__, 'getTermFilter'], 10, 2);
        add_action('load-edit-tags.php', [__CLASS__, 'removeGetTermFilter'], 10, 0);
    }

    public static function bloginfoFilter($output, $show)
    {
        if (!Translation::isReady()) {
            return $output;
        }

        if ('name' == $show) {
            $output = Functions::translate('blogname', 'blogname', $output);
        } elseif ('description' == $show) {
            $output = Functions::translate('blogdescription', 'blogdescription', $output);
        }

        return $output;
    }

    public static function getTermFilter($term, $taxonomy)
    {
        if (!Translation::isReady()) {
            return $term;
        }

        if ($term instanceof \WP_Term) {
            $term = Functions::translateTerm($term);
        }

        return $term;
    }

    public static function removeGetTermFilter()
    {
        remove_filter('get_term', [__CLASS__, 'getTermFilter']);
    }
}
