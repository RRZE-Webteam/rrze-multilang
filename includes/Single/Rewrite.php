<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;

class Rewrite
{
    protected $optionName;

    protected $options;

    public function __construct()
    {
        $this->optionName = Options::getOptionName();
        $this->options = (object) Options::getOptions();

        add_action('init', [$this, 'addRewriteTags'], 10, 0);
        add_filter('root_rewrite_rules', [$this, 'rootRewriteRules'], 10, 1);
        add_filter('post_rewrite_rules', [$this, 'postRewriteRules'], 10, 1);
        add_filter('date_rewrite_rules', [$this, 'dateRewriteRules'], 10, 1);
        add_filter('comments_rewrite_rules', [$this, 'commentsRewriteRules'], 10, 1);
        add_filter('search_rewrite_rules', [$this, 'searchRewriteRules'], 10, 1);
        add_filter('author_rewrite_rules', [$this, 'authorRewriteRules'], 10, 1);
        add_filter('page_rewrite_rules', [$this, 'pageRewriteRules'], 10, 1);
        add_filter('category_rewrite_rules', [$this, 'categoryRewriteRules'], 10, 1);
        add_filter('post_tag_rewrite_rules', [$this, 'postTagRewriteRules'], 10, 1);
        add_filter('post_format_rewrite_rules', [$this, 'postFormatRewriteRules'], 10, 1);
        add_filter('rewrite_rules_array', [$this, 'rewriteRulesArray'], 10, 1);
    }

    public function addRewriteTags()
    {
        $regex = Locale::getLangRegex();

        if (empty($regex)) {
            return;
        }

        add_rewrite_tag('%lang%', $regex, 'lang=');

        $oldRegex = $this->options->lang_rewrite_regex;

        if ($regex != $oldRegex) {
            $this->options->lang_rewrite_regex = $regex;
            update_option($this->optionName, (array) $this->options);
            flush_rewrite_rules();
        }
    }

    public function rootRewriteRules($rootRewrite)
    {
        global $wp_rewrite;

        $permastruct = trailingslashit($wp_rewrite->root) . '%lang%/';

        $extra = $this->generateRewriteRules($permastruct, [
            'ep_mask' => EP_ROOT,
        ]);

        return array_merge($extra, $rootRewrite);
    }

    public function postRewriteRules($postRewrite)
    {
        global $wp_rewrite;

        $permastruct = $wp_rewrite->permalink_structure;

        // wp-admin/includes/misc.php
        $got_rewrite = apply_filters(
            'got_rewrite',
            apache_mod_loaded('mod_rewrite', true)
        );

        $got_url_rewrite = apply_filters(
            'got_url_rewrite',
            $got_rewrite || $GLOBALS['is_nginx'] || iis7_supports_permalinks()
        );

        if (!$got_url_rewrite) {
            $permastruct = preg_replace(
                '#^/index\.php#',
                '/index.php/%lang%',
                $permastruct
            );
        } elseif (
            is_multisite()
            && !is_subdomain_install()
            && is_main_site()
        ) {
            $permastruct = preg_replace(
                '#^/blog#',
                '/%lang%/blog',
                $permastruct
            );
        } else {
            $permastruct = preg_replace(
                '#^/#',
                '/%lang%/',
                $permastruct
            );
        }

        $extra = $this->generateRewriteRules($permastruct, [
            'ep_mask' => EP_PERMALINK,
            'paged' => false,
        ]);

        return array_merge($extra, $postRewrite);
    }

    public function dateRewriteRules($dateRewrite)
    {
        global $wp_rewrite;

        $permastruct = $wp_rewrite->get_date_permastruct();

        $permastruct = preg_replace(
            '#^' . $wp_rewrite->front . '#',
            '/%lang%' . $wp_rewrite->front,
            $permastruct
        );

        $extra = $this->generateRewriteRules($permastruct, [
            'ep_mask' => EP_DATE,
        ]);

        return array_merge($extra, $dateRewrite);
    }

    public function commentsRewriteRules($commentsRewrite)
    {
        global $wp_rewrite;

        $permastruct = trailingslashit($wp_rewrite->root)
            . '%lang%/' . $wp_rewrite->comments_base;

        $extra = $this->generateRewriteRules($permastruct, [
            'ep_mask' => EP_COMMENTS,
            'forcomments' => true,
            'walk_dirs' => false,
        ]);

        return array_merge($extra, $commentsRewrite);
    }

    public function searchRewriteRules($searchRewrite)
    {
        global $wp_rewrite;

        $permastruct = trailingslashit($wp_rewrite->root) . '%lang%/'
            . $wp_rewrite->search_base . '/%search%';

        $extra = $this->generateRewriteRules($permastruct, [
            'ep_mask' => EP_SEARCH,
        ]);

        return array_merge($extra, $searchRewrite);
    }

    public function authorRewriteRules($authorRewrite)
    {
        global $wp_rewrite;

        $permastruct = $wp_rewrite->get_author_permastruct();

        $permastruct = preg_replace(
            '#^' . $wp_rewrite->front . '#',
            '/%lang%' . $wp_rewrite->front,
            $permastruct
        );

        $extra = $this->generateRewriteRules($permastruct, [
            'ep_mask' => EP_AUTHORS,
        ]);

        return array_merge($extra, $authorRewrite);
    }

    public function pageRewriteRules($pageRewrite)
    {
        global $wp_rewrite;

        $wp_rewrite->add_rewrite_tag('%pagename%', '(.?.+?)', 'pagename=');
        $permastruct = trailingslashit($wp_rewrite->root) . '%lang%/%pagename%';

        $extra = $this->generateRewriteRules($permastruct, [
            'ep_mask' => EP_PAGES,
            'walk_dirs' => false,
        ]);

        return array_merge($extra, $pageRewrite);
    }

    public function categoryRewriteRules($categoryRewrite)
    {
        return $this->taxonomyRewriteRules(
            $categoryRewrite,
            'category',
            EP_CATEGORIES
        );
    }

    public function postTagRewriteRules($postTagRewrite)
    {
        return $this->taxonomyRewriteRules($postTagRewrite, 'post_tag', EP_TAGS);
    }

    public function postFormatRewriteRules($postFormatRewrite)
    {
        return $this->taxonomyRewriteRules($postFormatRewrite, 'post_format');
    }

    public function rewriteRulesArray($rules)
    {
        global $wp_rewrite;

        $lang_regex = Locale::getLangRegex();

        // REST rewrite rules
        if (function_exists('rest_get_url_prefix')) {
            $rest_url_prefix = rest_get_url_prefix();

            $extra_rules = [
                "^{$lang_regex}/{$rest_url_prefix}/?$"
                => 'index.php?lang=$matches[1]&rest_route=/',
                "^{$lang_regex}/{$rest_url_prefix}/(.*)?"
                => 'index.php?lang=$matches[1]&rest_route=/$matches[2]',
            ];

            $rules = $extra_rules + $rules;
        }

        $postTypes = array_diff(
            (array) Post::localizablePostTypes(),
            get_post_types(array('_builtin' => true))
        );

        if (empty($postTypes)) {
            return $rules;
        }

        foreach ($postTypes as $postType) {
            if (!$postType_obj = get_post_type_object($postType)) {
                continue;
            }

            if (false === $postType_obj->rewrite) {
                continue;
            }

            $permastruct = $wp_rewrite->get_extra_permastruct($postType);

            if ($postType_obj->rewrite['with_front']) {
                $permastruct = preg_replace(
                    '#^' . $wp_rewrite->front . '#',
                    '/%lang%' . $wp_rewrite->front,
                    $permastruct
                );
            } else {
                $permastruct = preg_replace(
                    '#^' . $wp_rewrite->root . '#',
                    '/%lang%/' . $wp_rewrite->root,
                    $permastruct
                );
            }

            $rules = array_merge(
                $this->generateRewriteRules($permastruct, $postType_obj->rewrite),
                $rules
            );

            if ($postType_obj->has_archive) {
                if ($postType_obj->has_archive === true) {
                    $archive_slug = $postType_obj->rewrite['slug'];
                } else {
                    $archive_slug = $postType_obj->has_archive;
                }

                if ($postType_obj->rewrite['with_front']) {
                    $archive_slug = substr($wp_rewrite->front, 1) . $archive_slug;
                } else {
                    $archive_slug = $wp_rewrite->root . $archive_slug;
                }

                $extra_rules = [
                    "{$lang_regex}/{$archive_slug}/?$"
                    => 'index.php?lang=$matches[1]&post_type=' . $postType,
                ];

                $rules = $extra_rules + $rules;

                if ($postType_obj->rewrite['feeds'] && $wp_rewrite->feeds) {
                    $feeds = '(' . trim(implode('|', $wp_rewrite->feeds)) . ')';

                    $extra_rules = [
                        "{$lang_regex}/{$archive_slug}/feed/$feeds/?$"
                        => 'index.php?lang=$matches[1]&post_type=' . $postType . '&feed=$matches[2]',
                        "{$lang_regex}/{$archive_slug}/$feeds/?$"
                        => 'index.php?lang=$matches[1]&post_type=' . $postType . '&feed=$matches[2]',
                    ];

                    $rules = $extra_rules + $rules;
                }

                if ($postType_obj->rewrite['pages']) {
                    $extra_rules = [
                        "{$lang_regex}/{$archive_slug}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$" => 'index.php?lang=$matches[1]&post_type=' . $postType . '&paged=$matches[2]',
                    ];

                    $rules = $extra_rules + $rules;
                }
            }

            foreach (get_object_taxonomies($postType) as $tax) {
                if (!$tax_obj = get_taxonomy($tax)) {
                    continue;
                }

                if (false === $tax_obj->rewrite) {
                    continue;
                }

                $permastruct = $wp_rewrite->get_extra_permastruct($tax);

                if ($tax_obj->rewrite['with_front']) {
                    $permastruct = preg_replace(
                        '#^' . $wp_rewrite->front . '#',
                        '/%lang%' . $wp_rewrite->front,
                        $permastruct
                    );
                } else {
                    $permastruct = preg_replace(
                        '#^' . $wp_rewrite->root . '#',
                        '/%lang%/' . $wp_rewrite->root,
                        $permastruct
                    );
                }

                $rules = array_merge(
                    $this->generateRewriteRules($permastruct, $tax_obj->rewrite),
                    $rules
                );
            }
        }

        return $rules;
    }

    public function taxonomyRewriteRules($taxonomy_rewrite, $taxonomy, $ep_mask = EP_NONE)
    {
        global $wp_rewrite;

        $permastruct = $wp_rewrite->get_extra_permastruct($taxonomy);

        $permastruct = preg_replace(
            '#^' . $wp_rewrite->front . '#',
            '/%lang%' . $wp_rewrite->front,
            $permastruct
        );

        $extra = $this->generateRewriteRules($permastruct, [
            'ep_mask' => $ep_mask,
        ]);

        return array_merge($extra, $taxonomy_rewrite);
    }

    public function generateRewriteRules($permalink_structure, $args = '')
    {
        global $wp_rewrite;

        $defaults = [
            'ep_mask' => EP_NONE,
            'paged' => true,
            'feed' => true,
            'forcomments' => false,
            'walk_dirs' => true,
            'endpoints' => true,
        ];

        $args = wp_parse_args($args, $defaults);

        extract($args, EXTR_SKIP);

        $feedregex2 = '(' . implode('|', $wp_rewrite->feeds) . ')/?$';
        $feedregex = $wp_rewrite->feed_base . '/' . $feedregex2;
        $trackbackregex = 'trackback/?$';
        $pageregex = $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$';
        $commentregex = 'comment-page-([0-9]{1,})/?$';
        $embedregex = 'embed/?$';

        if ($endpoints) {
            $ep_query_append = [];

            foreach ((array) $wp_rewrite->endpoints as $endpoint) {
                $epmatch = $endpoint[1] . '(/(.*))?/?$';
                $epquery = '&' . $endpoint[1] . '=';
                $ep_query_append[$epmatch] = [$endpoint[0], $epquery];
            }
        }

        $front = substr($permalink_structure, 0, strpos($permalink_structure, '%'));
        preg_match_all('/%.+?%/', $permalink_structure, $tokens);
        $num_tokens = count($tokens[0]);
        $index = $wp_rewrite->index;
        $feedindex = $index;
        $trackbackindex = $index;
        $embedindex = $index;

        for ($i = 0; $i < $num_tokens; ++$i) {
            if (0 < $i) {
                $queries[$i] = $queries[$i - 1] . '&';
            } else {
                $queries[$i] = '';
            }

            $query_token =
                str_replace($wp_rewrite->rewritecode, $wp_rewrite->queryreplace, $tokens[0][$i])
                . $wp_rewrite->preg_index($i + 1);

            $queries[$i] .= $query_token;
        }

        $structure = $permalink_structure;

        if ($front != '/') {
            $structure = str_replace($front, '', $structure);
        }

        $structure = trim($structure, '/');

        $dirs = $walk_dirs ? explode('/', $structure) : [$structure];
        $num_dirs = count($dirs);

        $front = preg_replace('|^/+|', '', $front);

        $postRewrite = [];
        $struct = $front;

        for ($j = 0; $j < $num_dirs; ++$j) {
            $struct .= $dirs[$j] . '/';
            $struct = ltrim($struct, '/');
            $match = str_replace($wp_rewrite->rewritecode, $wp_rewrite->rewritereplace, $struct);
            $num_toks = preg_match_all('/%.+?%/', $struct, $toks);

            $query = (isset($queries) && is_array($queries) && !empty($num_toks))
                ? $queries[$num_toks - 1] : '';

            switch ($dirs[$j]) {
                case '%year%':
                    $ep_mask_specific = EP_YEAR;
                    break;
                case '%monthnum%':
                    $ep_mask_specific = EP_MONTH;
                    break;
                case '%day%':
                    $ep_mask_specific = EP_DAY;
                    break;
                default:
                    $ep_mask_specific = EP_NONE;
            }

            $pagematch = $match . $pageregex;
            $pagequery = $index . '?' . $query
                . '&paged=' . $wp_rewrite->preg_index($num_toks + 1);

            $commentmatch = $match . $commentregex;
            $commentquery = $index . '?' . $query
                . '&cpage=' . $wp_rewrite->preg_index($num_toks + 1);

            if (get_option('page_on_front')) {
                $rootcommentmatch = $match . $commentregex;
                $rootcommentquery = $index . '?' . $query
                    . '&page_id=' . get_option('page_on_front')
                    . '&cpage=' . $wp_rewrite->preg_index($num_toks + 1);
            }

            $feedmatch = $match . $feedregex;
            $feedquery = $feedindex . '?' . $query
                . '&feed=' . $wp_rewrite->preg_index($num_toks + 1);

            $feedmatch2 = $match . $feedregex2;
            $feedquery2 = $feedindex . '?' . $query
                . '&feed=' . $wp_rewrite->preg_index($num_toks + 1);

            if ($forcomments) {
                $feedquery .= '&withcomments=1';
                $feedquery2 .= '&withcomments=1';
            }

            $rewrite = [];

            if ($feed) {
                $rewrite = [
                    $feedmatch => $feedquery,
                    $feedmatch2 => $feedquery2
                ];
            }

            if ($paged) {
                $rewrite = array_merge($rewrite, [$pagematch => $pagequery]);
            }

            if (
                EP_PAGES & $ep_mask
                || EP_PERMALINK & $ep_mask
            ) {
                $rewrite = array_merge($rewrite, [$commentmatch => $commentquery]);
            } elseif (
                EP_ROOT & $ep_mask
                && get_option('page_on_front')
            ) {
                $rewrite = array_merge(
                    $rewrite,
                    [$rootcommentmatch => $rootcommentquery]
                );
            }

            if ($endpoints) {
                foreach ((array) $ep_query_append as $regex => $ep) {
                    if (
                        $ep[0] & $ep_mask
                        || $ep[0] & $ep_mask_specific
                    ) {
                        $rewrite[$match . $regex] = $index . '?' . $query
                            . $ep[1] . $wp_rewrite->preg_index($num_toks + 2);
                    }
                }
            }

            if ($num_toks) {
                $post = false;
                $page = false;

                if (
                    strpos($struct, '%postname%') !== false
                    || strpos($struct, '%post_id%') !== false
                    || strpos($struct, '%pagename%') !== false
                    || (strpos($struct, '%year%') !== false
                        && strpos($struct, '%monthnum%') !== false
                        && strpos($struct, '%day%') !== false
                        && strpos($struct, '%hour%') !== false
                        && strpos($struct, '%minute%') !== false
                        && strpos($struct, '%second%') !== false)
                ) {
                    $post = true;

                    if (strpos($struct, '%pagename%') !== false) {
                        $page = true;
                    }
                }

                if (!$post) {
                    foreach (get_post_types(array('_builtin' => false)) as $ptype) {
                        if (strpos($struct, "%$ptype%") !== false) {
                            $post = true;
                            $page = is_post_type_hierarchical($ptype);
                            break;
                        }
                    }
                }

                if ($post) {
                    $trackbackmatch = $match . $trackbackregex;
                    $trackbackquery = $trackbackindex . '?' . $query . '&tb=1';

                    $embedmatch = $match . $embedregex;
                    $embedquery = $embedindex . '?' . $query . '&embed=true';

                    $match = rtrim($match, '/');
                    $submatchbase = preg_replace('/\(([^?].+?)\)/', '(?:$1)', $match);

                    $sub1 = $submatchbase . '/([^/]+)/';
                    $sub1tb = $sub1 . $trackbackregex;
                    $sub1feed = $sub1 . $feedregex;
                    $sub1feed2 = $sub1 . $feedregex2;
                    $sub1comment = $sub1 . $commentregex;
                    $sub1embed = $sub1 . $embedregex;

                    $sub2 = $submatchbase . '/attachment/([^/]+)/';
                    $sub2tb = $sub2 . $trackbackregex;
                    $sub2feed = $sub2 . $feedregex;
                    $sub2feed2 = $sub2 . $feedregex2;
                    $sub2comment = $sub2 . $commentregex;
                    $sub2embed = $sub2 . $embedregex;

                    $subquery = $index . '?attachment=' . $wp_rewrite->preg_index(1);
                    $subtbquery = $subquery . '&tb=1';
                    $subfeedquery = $subquery . '&feed=' . $wp_rewrite->preg_index(2);
                    $subcommentquery = $subquery . '&cpage=' . $wp_rewrite->preg_index(2);
                    $subembedquery = $subquery . '&embed=true';

                    if (!empty($endpoints)) {
                        foreach ((array) $ep_query_append as $regex => $ep) {
                            if ($ep[0] & EP_ATTACHMENT) {
                                $rewrite[$sub1 . $regex] =
                                    $subquery . $ep[1] . $wp_rewrite->preg_index(2);
                                $rewrite[$sub2 . $regex] =
                                    $subquery . $ep[1] . $wp_rewrite->preg_index(2);
                            }
                        }
                    }

                    $sub1 .= '?$';
                    $sub2 .= '?$';

                    $match = $match . '(/[0-9]+)?/?$';
                    $query = $index . '?' . $query
                        . '&page=' . $wp_rewrite->preg_index($num_toks + 1);
                } else {
                    $match .= '?$';
                    $query = $index . '?' . $query;
                }

                $rewrite = array_merge($rewrite, [$match => $query]);

                if ($post) {
                    $rewrite = array_merge([$trackbackmatch => $trackbackquery], $rewrite);

                    $rewrite = array_merge([$embedmatch => $embedquery], $rewrite);

                    if (!$page) {
                        $rewrite = array_merge($rewrite, [
                            $sub1 => $subquery,
                            $sub1tb => $subtbquery,
                            $sub1feed => $subfeedquery,
                            $sub1feed2 => $subfeedquery,
                            $sub1comment => $subcommentquery,
                            $sub1embed => $subembedquery
                        ]);
                    }

                    $rewrite = array_merge([
                        $sub2 => $subquery,
                        $sub2tb => $subtbquery,
                        $sub2feed => $subfeedquery,
                        $sub2feed2 => $subfeedquery,
                        $sub2comment => $subcommentquery,
                        $sub2embed => $subembedquery
                    ], $rewrite);
                }
            }

            $postRewrite = array_merge($rewrite, $postRewrite);
        }

        return $postRewrite;
    }
}
