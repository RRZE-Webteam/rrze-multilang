<?php

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

class Options
{
    /**
     * Option name
     * @var string
     */
    protected static $optionName = 'rrze_multilang_postmeta';

    /**
     * Site Option Name
     * @var string
     */
    protected static $siteOptionName = 'rrze_multilang_sitemeta';

    /**
     * Default options
     * @return array
     */
    protected static function defaultOptions(): array
    {
        $options = [
            'multilang_mode' => 0,
            'connection_type' => 0,
            'post_types' => [
                'post',
                'page'
            ],
            'languages' => [],
            'lang_rewrite_regex' => '',
            'copy_post_meta' => [
                'content_images' => 1,
                'featured_image' => 1,
                'tags' => 0,
                'categories' => 0,
                'taxonomies' => 0
            ],
            'error_404_page' => 0
        ];

        return $options;
    }

    /**
     * Default site options
     * @return array
     */
    protected static function defaultSiteOptions(): array
    {
        $options = [
            'connections' => []
        ];

        return $options;
    }

    /**
     * Returns the default options.
     * @return object
     */
    public static function getDefaultOptions(): array
    {
        return self::defaultOptions();
    }

    /**
     * Returns the options.
     * @return object
     */
    public static function getOptions(): array
    {
        $defaults = self::defaultOptions();
        $options = (array) get_option(self::$optionName);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return $options;
    }

    /**
     * Returns the site options.
     * @return object
     */
    public static function getSiteOptions(): array
    {
        $defaults = self::defaultSiteOptions();
        $options = (array) get_site_option(self::$siteOptionName);
        $options = wp_parse_args($options, $defaults);
        $options = array_intersect_key($options, $defaults);

        return $options;
    }

    /**
     * Returns the name of the option.
     * @return string
     */
    public static function getOptionName(): string
    {
        return self::$optionName;
    }

    /**
     * Returns the name of the site option.
     * @return string
     */
    public static function getSiteOptionName(): string
    {
        return self::$siteOptionName;
    }

    public static function deleteOption(): bool
    {
        return delete_option(self::$optionName);
    }

    public static function deleteCurrentBlogConnections()
    {
        $currentBlogId = get_current_blog_id();
        $options = (object) get_site_option(self::$siteOptionName);
        if (isset($options->connections[$currentBlogId])) {
            unset($options->connections[$currentBlogId]);
            update_site_option(self::$siteOptionName, (array) $options);
        }
    }
}
