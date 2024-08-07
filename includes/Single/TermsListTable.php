<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

use RRZE\Multilang\Functions;
use RRZE\Multilang\Locale;

require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');

class TermsListTable extends \WP_List_Table
{
    private $localeToEdit = null;

    public function define_columns()
    {
        $columns = [
            'original' => __('Original', 'rrze-multilang'),
            'translation' => __('Translation', 'rrze-multilang'),
            'context' => __('Context', 'rrze-multilang')
        ];

        return $columns;
    }

    public function prepare_items()
    {
        $this->localeToEdit = trim($_GET['locale'] ?? '');

        if (!Locale::isAvailableLocale($this->localeToEdit)) {
            return;
        }

        $items = Functions::termsTranslation($this->localeToEdit);

        foreach ($items as $key => $item) {
            $cap = isset($item['cap'])
                ? $item['cap']
                : 'rrze_multilang_edit_terms_translations';

            if (!current_user_can($cap)) {
                unset($items[$key]);
            }
        }

        if (!empty($_REQUEST['s'])) {
            $keywords = preg_split('/[\s]+/', $_REQUEST['s']);

            foreach ($items as $key => $item) {
                $haystack = $item['original'] . ' ' . $item['translated'];

                foreach ($keywords as $needle) {
                    if (false === stripos($haystack, $needle)) {
                        unset($items[$key]);
                        break;
                    }
                }
            }
        }

        $items = array_filter($items);
        $items = array_values($items);

        $per_page = $this->get_items_per_page('rrze_multilang_terms_per_page');
        $offset = ($this->get_pagenum() - 1) * $per_page;

        $this->items = array_slice($items, $offset, $per_page);

        $total_items = count($items);
        $total_pages = ceil($total_items / $per_page);

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'total_pages' => $total_pages,
            'per_page' => $per_page,
        ));
    }

    public function get_columns()
    {
        return get_column_headers(get_current_screen());
    }

    public function column_original($item)
    {
        return esc_html($item['original']);
    }

    public function column_translation($item)
    {
        return sprintf(
            '<input name="%1$s" type="text" id="%1$s" value="%2$s" class="%3$s" />',
            esc_attr($item['name']),
            esc_attr($item['translated']),
            'large-text'
        );
    }

    public function column_context($item)
    {
        return esc_html($item['context']);
    }

    protected function display_tablenav($which)
    {
        printf('<div class="tablenav %1$s">', esc_attr($which));
        $this->extra_tablenav($which);
        $this->pagination($which);
        echo '<br class="clear" />';
        echo '</div>';
    }

    protected function extra_tablenav($which)
    {
        if ('top' == $which) {
            echo '<div class="alignleft actions">';
            echo '<select name="locale" id="select-locale">';
            printf(
                '<option value="">%1$s</option>',
                esc_html(__('&mdash; Select Language &mdash;', 'rrze-multilang'))
            );

            $available_locales = Locale::availableLocales([
                'current_user_can_access' => true,
            ]);

            foreach ($available_locales as $locale) {
                if (Locale::isDefaultLocale($locale)) {
                    continue;
                }

                printf(
                    '<option value="%1$s"%3$s>%2$s</option>',
                    esc_attr($locale),
                    esc_html(Locale::getLanguage($locale)),
                    $locale === $this->localeToEdit ? ' selected="selected"' : ''
                );
            }

            echo '</select>';
            submit_button(__('Filter'), 'secondary', 'rrze_multilang_terms_translations_language', false);
            echo '</div>';
        }

        if ('bottom' == $which) {
            echo '<div class="alignleft">';
            submit_button(__('Save Changes'), 'primary', 'rrze_multilang_terms_translations_save', false);
            echo '</div>';
        }
    }
}
