<?php

namespace RRZE\Multilang\Multiple;

use RRZE\Multilang\Options;
use RRZE\Multilang\Locale;
use RRZE\Multilang\Functions;

defined('ABSPATH') || exit;

class Sites
{
    public static function getSecondary(string $postType = ''): array
    {
        $siteOptions = (object) Options::getSiteOptions();
        $currentBlogId = get_current_blog_id();

        $secondary = [];
        foreach ($siteOptions->connections[$currentBlogId] as $blogId) {
            if (!Functions::isBlogPublic($blogId)) {
                continue;
            }

            switch_to_blog($blogId);
            if (
                Post::isLocalizablePostType($postType)
                && isset($siteOptions->connections[$blogId])
                && in_array($currentBlogId, $siteOptions->connections[$blogId])
            ) {
                $secondary[$blogId] = [
                    'blog_id' => $blogId,
                    'name' => get_bloginfo('name'),
                    'url' => get_bloginfo('url'),
                    'language' => Locale::getDefaultLocale(),
                    'posts' => Post::getPosts($postType, ['publish'])
                ];
            }
            restore_current_blog();
        }
        return $secondary;
    }
}
