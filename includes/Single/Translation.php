<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use function RRZE\Multilang\plugin;
use RRZE\Multilang\Locale;

class Translation
{
    private static $mo;

    public static function translate($singular, $context = '', $default = '')
    {
        if (!self::$mo) {
            return '' !== $default ? $default : $singular;
        }

        $translated = self::$mo->translate($singular, $context);

        if (
            $translated == $singular
            && '' !== $default
        ) {
            return $default;
        } else {
            return $translated;
        }
    }

    public static function export($locale, $entries = [])
    {
        if (! Locale::isAvailableLocale($locale)) {
            return false;
        }

        $dir = self::dir();

        $revisionDate = new \DateTimeImmutable();

        $headers = [
            'PO-Revision-Date' => $revisionDate->format('Y-m-d H:i:s') . '+0000',
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
            'X-Generator' => sprintf('RRZE Multilang %s', plugin()->getVersion()),
            'Language' => $locale,
            'Project-Id-Version' =>
            sprintf('WordPress %s', get_bloginfo('version')),
        ];

        require_once ABSPATH . WPINC . '/pomo/po.php';
        $po = new \PO();
        $po->set_headers($headers);

        foreach ((array) $entries as $entry) {
            $entry = new \Translation_Entry($entry);
            $po->add_entry($entry);
        }

        $poFile = is_multisite()
            ? sprintf('%d-%s.po', get_current_blog_id(), $locale)
            : sprintf('%s.po', $locale);
        $poFile = path_join($dir, $poFile);
        $po->export_to_file($poFile);

        $mo = new \MO();
        $mo->set_headers($headers);

        foreach ((array) $entries as $entry) {
            $entry = new \Translation_Entry($entry);
            $mo->add_entry($entry);
        }

        $moFile = is_multisite()
            ? sprintf('%d-%s.mo', get_current_blog_id(), $locale)
            : sprintf('%s.mo', $locale);
        $moFile = path_join($dir, $moFile);
        return $mo->export_to_file($moFile);
    }

    public static function import($locale)
    {
        if (!Locale::isAvailableLocale($locale)) {
            return false;
        }

        $dir = self::dir();

        $moFile = is_multisite()
            ? sprintf('%d-%s.mo', get_current_blog_id(), $locale)
            : sprintf('%s.mo', $locale);
        $moFile = path_join($dir, $moFile);

        if (!is_readable($moFile)) {
            return false;
        }

        $mo = new \MO();

        if (!$mo->import_from_file($moFile)) {
            return false;
        }

        self::$mo = $mo;
        return true;
    }

    public static function reset()
    {
        self::$mo = null;
    }

    public static function isReady()
    {
        return (bool) self::$mo;
    }

    private static function dir()
    {
        $dir = path_join(WP_LANG_DIR, 'rrze-multilang');
        $dir = apply_filters('rrze_multilang_translation_dir', $dir);
        wp_mkdir_p($dir);
        return $dir;
    }
}
