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

        add_action('init', [$this, 'addRewriteTags']);
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

    public function rewriteRulesArray($rules)
    {
        global $wp_rewrite;

        $langRegex = Locale::getLangRegex();

        $extraRules = [];

        if ($restUrlPrefix = rest_get_url_prefix()) {
            $extraRules += [
                "{$langRegex}/{$restUrlPrefix}/?$"
                => 'index.php?lang=$matches[1]&rest_route=/',
                "{$langRegex}/{$restUrlPrefix}/(.*)?"
                => 'index.php?lang=$matches[1]&rest_route=/$matches[2]',
                "{$wp_rewrite->index}/{$langRegex}/{$restUrlPrefix}/?$"
                => 'index.php?lang=$matches[1]&rest_route=/',
                "{$wp_rewrite->index}/{$langRegex}/{$restUrlPrefix}/(.*)?"
                => 'index.php?lang=$matches[1]&rest_route=/$matches[2]',
            ];
        }

        $localizablePostTypes = Post::localizablePostTypes();

        foreach ($localizablePostTypes as $postType) {
            if (
                !$postTypeObj = get_post_type_object($postType)
                or false === $postTypeObj->rewrite
            ) {
                continue;
            }

            if ($postTypeObj->has_archive) {
                if ($postTypeObj->has_archive === true) {
                    $archiveSlug = $postTypeObj->rewrite['slug'];
                } else {
                    $archiveSlug = $postTypeObj->has_archive;
                }

                if ($postTypeObj->rewrite['with_front']) {
                    $archiveSlug = substr($wp_rewrite->front, 1) . $archiveSlug;
                } else {
                    $archiveSlug = $wp_rewrite->root . $archiveSlug;
                }

                $extraRules += [
                    "{$langRegex}/{$archiveSlug}/?$"
                    => 'index.php?lang=$matches[1]&post_type=' . $postType,
                ];

                if ($postTypeObj->rewrite['feeds'] and $wp_rewrite->feeds) {
                    $feeds = '(' . trim(implode('|', $wp_rewrite->feeds)) . ')';

                    $extraRules += [
                        "{$langRegex}/{$archiveSlug}/feed/$feeds/?$"
                        => 'index.php?lang=$matches[1]&post_type=' . $postType . '&feed=$matches[2]',
                        "{$langRegex}/{$archiveSlug}/$feeds/?$"
                        => 'index.php?lang=$matches[1]&post_type=' . $postType . '&feed=$matches[2]',
                    ];
                }

                if ($postTypeObj->rewrite['pages']) {
                    $extraRules += [
                        "{$langRegex}/{$archiveSlug}/{$wp_rewrite->pagination_base}/([0-9]{1,})/?$"
                        => 'index.php?lang=$matches[1]&post_type=' . $postType . '&paged=$matches[2]',
                    ];
                }
            }

            $permastruct = $wp_rewrite->get_extra_permastruct($postType);
            $permastruct = $this->addLangToPermastruct($permastruct);

            $extraRules += $this->generateRewriteRules(
                $permastruct,
                $postTypeObj->rewrite
            );
        }

        $localizableTaxonomies = get_object_taxonomies(
            $localizablePostTypes,
            'objects'
        );

        foreach ($localizableTaxonomies as $taxonomy) {
            if (empty($taxonomy->rewrite)) {
                continue;
            }

            $permastruct = $wp_rewrite->get_extra_permastruct($taxonomy->name);
            $permastruct = $this->addLangToPermastruct($permastruct);

            $extraRules += $this->generateRewriteRules(
                $permastruct,
                $taxonomy->rewrite
            );
        }

        $rootRules = $this->generateRewriteRules(
            $this->addLangToPermastruct($wp_rewrite->root),
            ['ep_mask' => EP_ROOT]
        );

        $commentsRules = $this->generateRewriteRules(
            $this->addLangToPermastruct(
                $wp_rewrite->root . $wp_rewrite->comments_base
            ),
            [
                'ep_mask' => EP_COMMENTS,
                'forcomments' => true,
                'walk_dirs' => false,
            ]
        );

        $searchRules = $this->generateRewriteRules(
            $this->addLangToPermastruct($wp_rewrite->get_search_permastruct()),
            ['ep_mask' => EP_SEARCH]
        );

        $authorRules = $this->generateRewriteRules(
            $this->addLangToPermastruct($wp_rewrite->get_author_permastruct()),
            ['ep_mask' => EP_AUTHORS]
        );

        $dateRules = $this->generateRewriteRules(
            $this->addLangToPermastruct($wp_rewrite->get_date_permastruct()),
            ['ep_mask' => EP_DATE]
        );

        $postRules = $this->generateRewriteRules(
            $this->addLangToPermastruct($wp_rewrite->permalink_structure),
            [
                'ep_mask' => EP_PERMALINK,
                'paged' => false,
            ]
        );

        $wp_rewrite->add_rewrite_tag('%pagename%', '(.?.+?)', 'pagename=');

        $pageRules = $this->generateRewriteRules(
            $this->addLangToPermastruct($wp_rewrite->get_page_permastruct()),
            [
                'ep_mask' => EP_PAGES,
                'walk_dirs' => false,
            ]
        );

        if ($wp_rewrite->use_verbose_page_rules) {
            $rules = array_merge(
                $extraRules,
                $rootRules,
                $commentsRules,
                $searchRules,
                $authorRules,
                $dateRules,
                $pageRules,
                $postRules,
                $rules
            );
        } else {
            $rules = array_merge(
                $extraRules,
                $rootRules,
                $commentsRules,
                $searchRules,
                $authorRules,
                $dateRules,
                $postRules,
                $pageRules,
                $rules
            );
        }

        return $rules;
    }

    protected function addLangToPermastruct($permastruct)
    {
        global $wp_rewrite;

        $rootQuoted = preg_quote($wp_rewrite->root);

        $remains = preg_replace("#^{$rootQuoted}#", '', $permastruct);
        $remains = path_join('%lang%', ltrim($remains, '/'));

        return path_join($wp_rewrite->root, $remains);
    }

    protected function generateRewriteRules($permalinkStructure, $args = '')
    {
        global $wp_rewrite;

        $args = wp_parse_args($args, [
            'ep_mask' => EP_NONE,
            'paged' => true,
            'feed' => true,
            'forcomments' => false,
            'walk_dirs' => true,
            'endpoints' => true,
        ]);

        if (strpos($permalinkStructure, '%lang%') === false) {
            return [];
        }

        $feedregex2 = '(' . implode('|', $wp_rewrite->feeds) . ')/?$';
        $feedregex = $wp_rewrite->feed_base . '/' . $feedregex2;
        $trackbackregex = 'trackback/?$';
        $pageregex = $wp_rewrite->pagination_base . '/?([0-9]{1,})/?$';
        $commentregex = $wp_rewrite->comments_pagination_base . '-([0-9]{1,})/?$';
        $embedregex = 'embed/?$';

        if ($args['endpoints']) {
            $epQueryAppend = [];
            foreach ((array) $wp_rewrite->endpoints as $endpoint) {
                $epmatch = $endpoint[1] . '(/(.*))?/?$';
                $epquery = '&' . $endpoint[2] . '=';
                $epQueryAppend[$epmatch] = [$endpoint[0], $epquery];
            }
        }

        $front = substr($permalinkStructure, 0, strpos($permalinkStructure, '%'));

        preg_match_all('/%.+?%/', $permalinkStructure, $tokens);

        $queries = [];

        $index = $wp_rewrite->index;
        $feedindex = $index;
        $trackbackindex = $index;
        $embedindex = $index;

        for ($i = 0; $i < count($tokens[0]); ++$i) {
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

        $structure = $permalinkStructure;

        if ($front !== '/') {
            $structure = str_replace($front, '', $structure);
        }

        $structure = trim($structure, '/');

        $dirs = $args['walk_dirs'] ? explode('/', $structure) : [$structure];

        $front = preg_replace('|^/+|', '', $front);

        $postRewrite = [];
        $struct = $front;

        for ($j = 0; $j < count($dirs); ++$j) {
            $struct .= $dirs[$j] . '/';
            $struct = ltrim($struct, '/');
            $match = str_replace($wp_rewrite->rewritecode, $wp_rewrite->rewritereplace, $struct);
            $num_toks = preg_match_all('/%.+?%/', $struct, $toks);

            $query = (isset($queries) && is_array($queries) && !empty($num_toks))
                ? $queries[$num_toks - 1] : '';

            switch ($dirs[$j]) {
                case '%year%':
                    $epMaskSpecific = EP_YEAR;
                    break;
                case '%monthnum%':
                    $epMaskSpecific = EP_MONTH;
                    break;
                case '%day%':
                    $epMaskSpecific = EP_DAY;
                    break;
                default:
                    $epMaskSpecific = EP_NONE;
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

            $embedmatch = $match . $embedregex;
            $embedquery = $embedindex . '?' . $query . '&embed=true';

            if ($args['forcomments']) {
                $feedquery .= '&withcomments=1';
                $feedquery2 .= '&withcomments=1';
            }

            $rewrite = [];

            if ($args['feed']) {
                $rewrite = [
                    $feedmatch => $feedquery,
                    $feedmatch2 => $feedquery2,
                    $embedmatch => $embedquery
                ];
            }

            if ($args['paged']) {
                $rewrite = array_merge($rewrite, [$pagematch => $pagequery]);
            }

            if (
                EP_PAGES & $args['ep_mask']
                || EP_PERMALINK & $args['ep_mask']
            ) {
                $rewrite = array_merge($rewrite, [$commentmatch => $commentquery]);
            } elseif (
                EP_ROOT & $args['ep_mask']
                && get_option('page_on_front')
            ) {
                $rewrite = array_merge(
                    $rewrite,
                    [$rootcommentmatch => $rootcommentquery]
                );
            }

            if ($args['endpoints']) {
                foreach ((array) $epQueryAppend as $regex => $ep) {
                    if (
                        $ep[0] & $args['ep_mask']
                        || $ep[0] & $epMaskSpecific
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

                    if (!empty($args['endpoints'])) {
                        foreach ((array) $epQueryAppend as $regex => $ep) {
                            if ($ep[0] & EP_ATTACHMENT) {
                                $rewrite[$sub1 . $regex] =
                                    $subquery . $ep[1] . $wp_rewrite->preg_index(3);
                                $rewrite[$sub2 . $regex] =
                                    $subquery . $ep[1] . $wp_rewrite->preg_index(3);
                            }
                        }
                    }

                    $sub1 .= '?$';
                    $sub2 .= '?$';

                    $match = $match . '(?:/([0-9]+))?/?$';
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
                        $rewrite = array_merge(
                            $rewrite,
                            [
                                $sub1 => $subquery,
                                $sub1tb => $subtbquery,
                                $sub1feed => $subfeedquery,
                                $sub1feed2 => $subfeedquery,
                                $sub1comment => $subcommentquery,
                                $sub1embed => $subembedquery
                            ]
                        );
                    }

                    $rewrite = array_merge(
                        [
                            $sub2 => $subquery,
                            $sub2tb => $subtbquery,
                            $sub2feed => $subfeedquery,
                            $sub2feed2 => $subfeedquery,
                            $sub2comment => $subcommentquery,
                            $sub2embed => $subembedquery
                        ],
                        $rewrite
                    );
                }
            }

            $postRewrite = array_merge($rewrite, $postRewrite);
        }

        return $postRewrite;
    }
}
