<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Functions;
use RRZE\Multilang\Locale;

class Tools
{
    protected $optionName;

    protected $options;

    protected $menuPage = 'rrze-multilang';

    protected $listTable;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'subMenu']);
        add_filter('set_screen_option_rrze_multilang_terms_per_page', [$this, 'setScreenOptions'], 10, 3);
    }

    public function subMenu()
    {
        $tools = add_submenu_page(
            'tools.php',
            __('Terms Translations', 'rrze-multilang'),
            __('Terms Translations', 'rrze-multilang'),
            'manage_options',
            $this->menuPage,
            [$this, 'subMenuPage']
        );

        add_action('load-' . $tools, [$this, 'loadToolsPage'], 10, 0);
        $this->listTable = new TermsListTable;
    }

    public function setScreenOptions($result, $option, $value)
    {
        $screens = [
            'rrze_multilang_terms_per_page',
        ];

        if (in_array($option, $screens)) {
            $result = $value;
        }

        return $result;
    }

    public function subMenuPage()
    {
        $this->listTable->prepare_items();

        echo '<div class="wrap">';

        echo '<h1 class="wp-heading-inline">';
        echo esc_html(__('Terms Translations', 'rrze-multilang'));
        echo '</h1>';

        if (!empty($_REQUEST['s'])) {
            printf(
                '<span class="subtitle">%s</span>',
                sprintf(
                    __('Search results for &#8220;%s&#8221;', 'rrze-multilang'),
                    esc_html($_REQUEST['s'])
                )
            );
        }

        echo '<hr class="wp-header-end">';

        //$this->adminNotice();

        echo '<form action="" method="get">';
        echo '<input type="hidden" name="page" value="', isset($_REQUEST['page']) ? esc_attr($_REQUEST['page']) : '', '">';
        echo '<input type="hidden" name="locale" value="', isset($_REQUEST['locale']) ? esc_attr($_REQUEST['locale']) : '', '">';

        $this->listTable->search_box(
            __('Search Translation', 'rrze-multilang'),
            'rrze-multilang-terms-translation'
        );

        echo '</form>';

        echo '<form action="" method="post" id="rrze-multilang-terms-translation">';
        echo '<input type="hidden" name="paged" value="', isset($_GET['paged']) ? absint($_GET['paged']) : '', '">';

        wp_nonce_field('rrze_multilang_edit_terms_translations');
        $this->listTable->display();

        echo '</form>';
        echo '</div>';
    }

    public function loadToolsPage()
    {
        if (isset($_POST['rrze_multilang_terms_translations_language'])) {
            $this->pageLanguage();
        } elseif (isset($_POST['rrze_multilang_terms_translations_save'])) {
            $this->pageSave();
        } else {
            $this->pageScreen();
        }
    }

    protected function pageLanguage()
    {
        check_admin_referer('rrze_multilang_edit_terms_translations');

        if (!current_user_can('rrze_multilang_edit_terms_translationss')) {
            wp_die(__('You are not allowed to edit terms translations.', 'rrze-multilang'));
        }

        $locale = isset($_POST['locale']) ? $_POST['locale'] : null;

        if (!Locale::isAvailableLocale($locale)) {
            return;
        }

        if (!current_user_can('rrze_multilang_access_locale', $locale)) {
            wp_die(__('You are not allowed to edit terms in this locale.', 'rrze-multilang'));
        }

        $redirectTo = add_query_arg(
            [
                'locale' => $locale,
                'paged' => isset($_POST['paged']) ? absint($_POST['paged']) : 1,
            ],
            menu_page_url($this->menuPage, false)
        );

        wp_safe_redirect($redirectTo);
        exit();
    }

    protected function pageSave()
    {
        check_admin_referer('rrze_multilang_edit_terms_translations');

        if (!current_user_can('rrze_multilang_edit_terms_translationss')) {
            wp_die(__('You are not allowed to edit terms translations.', 'rrze-multilang'));
        }

        $locale = isset($_POST['locale']) ? $_POST['locale'] : null;

        if (!Locale::isAvailableLocale($locale)) {
            return;
        }

        if (!current_user_can('rrze_multilang_access_locale', $locale)) {
            wp_die(__('You are not allowed to edit terms in this locale.', 'rrze-multilang'));
        }

        $entries = [];

        foreach ((array) Functions::termsTranslation($locale) as $item) {
            $translation = $item['translated'];

            $cap = isset($item['cap'])
                ? $item['cap']
                : 'rrze_multilang_edit_terms_translations';

            if (
                isset($_POST[$item['name']])
                && current_user_can($cap)
            ) {
                $translation = $_POST[$item['name']];
            }

            $entries[] = [
                'singular' => $item['name'],
                'translations' => [$translation],
                'context' => preg_replace('/:.*$/', '', $item['name']),
            ];
        }

        if (Translation::export($locale, $entries)) {
            $message = 'translation_saved';
        } else {
            $message = 'translation_failed';
        }

        $redirectTo = add_query_arg(
            [
                'locale' => $locale,
                'message' => $message,
                'paged' => isset($_POST['paged']) ? absint($_POST['paged']) : 1,
            ],
            menu_page_url($this->menuPage, false)
        );

        wp_safe_redirect($redirectTo);
        exit();
    }

    protected function pageScreen()
    {
        $current_screen = get_current_screen();

        add_filter('manage_' . $current_screen->id . '_columns', [$this->listTable, 'define_columns'], 10, 1);

        add_screen_option('per_page', [
            'default' => 20,
            'option' => 'rrze_multilang_terms_per_page',
        ]);
    }
}
