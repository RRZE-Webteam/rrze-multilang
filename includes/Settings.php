<?php

namespace RRZE\Multilang;

defined('ABSPATH') || exit;

class Settings
{
    protected $optionName;

    protected $siteOptionName;

    protected $options;

    protected $siteOptions;

    protected $currentBlogId;

    protected $menuPage = 'rrze-multilang';

    protected $copyPostMetaLabels;

    public function __construct()
    {
        $this->optionName = Options::getOptionName();
        $this->siteOptionName = Options::getSiteOptionName();
        $this->options = (object) Options::getOptions();
        $this->siteOptions = (object) Options::getSiteOptions();

        $this->currentBlogId = get_current_blog_id();

        $this->copyPostMetaLabels = [
            'content_images' => __('Content Images', 'rrze-multilang'),
            'featured_image' => __('Featured Image', 'rrze-multilang'),
            'tags' => __('Tags', 'rrze-multilang'),
            'categories' => __('Categories', 'rrze-multilang'),
            'taxonomies' => __('Taxonomies', 'rrze-multilang')
        ];

        add_action('admin_menu', [$this, 'adminMenu']);
        add_action('admin_init', [$this, 'adminInit']);
    }

    public function getMenuPage()
    {
        return $this->menuPage;
    }

    public function adminMenu()
    {
        add_options_page(__('Multilanguage', 'rrze-multilang'), __('Multilanguage', 'rrze-multilang'), 'manage_options', $this->menuPage, [$this, 'optionsPage']);
    }

    public function optionsPage()
    {
?>
        <div class="wrap">
            <h1><?php echo esc_html(__('Multilanguage Settings', 'rrze-multilang')); ?></h1>
            <form method="post" action="options.php">
                <?php do_settings_sections($this->menuPage); ?>
                <?php settings_fields($this->menuPage); ?>
                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }

    public function adminInit()
    {
        register_setting($this->menuPage, $this->optionName, [$this, 'optionsValidate']);

        add_settings_section('rrze_multilang_general_section', false, '__return_false', $this->menuPage);
        add_settings_field('multilang_mode', __('Multilanguage Mode', 'rrze-multilang'), [$this, 'multilangModeField'], $this->menuPage, 'rrze_multilang_general_section');

        if (!is_multisite() && $this->options->multilang_mode == 2) {
            $this->options->multilang_mode = 0;
            update_option($this->optionName, (array) $this->options);
        }

        if ($this->options->multilang_mode == 0) {
            $this->deleteMainConnection($this->currentBlogId);
        } elseif ($this->options->multilang_mode == 1) {
            $this->deleteMainConnection($this->currentBlogId);
            add_settings_field('post_types', __('Post Types', 'rrze-multilang'), [$this, 'postTypesField'], $this->menuPage, 'rrze_multilang_general_section');
            add_settings_field('default_page', __('Default Page', 'rrze-multilang'), [$this, 'defaultPageField'], $this->menuPage, 'rrze_multilang_general_section');
            add_settings_field('languages', __('Available languages', 'rrze-multilang'), [$this, 'languagesField'], $this->menuPage, 'rrze_multilang_general_section');
        } elseif (
            $this->options->multilang_mode == 2
            && !Functions::isCmsWorkflowPluginModuleActivated('network')
        ) {
            $this->addMainConnection($this->currentBlogId);
            add_settings_field('main_connection', __('Connection Type', 'rrze-multilang'), [$this, 'connectionTypeField'], $this->menuPage, 'rrze_multilang_general_section');
            if (in_array($this->options->connection_type, [1, 2])) {
                add_settings_field('post_types', __('Post Types', 'rrze-multilang'), [$this, 'postTypesField'], $this->menuPage, 'rrze_multilang_general_section');
                add_settings_field('default_page', __('Default Page', 'rrze-multilang'), [$this, 'defaultPageField'], $this->menuPage, 'rrze_multilang_general_section');
            }
            if ($this->options->connection_type == 1) {
                add_settings_field('copy_post_meta', __('Copy', 'rrze-multilang'), [$this, 'copyPostMetaField'], $this->menuPage, 'rrze_multilang_general_section');
                add_settings_field('connections', __('Available Websites', 'rrze-multilang'), [$this, 'connectionsField'], $this->menuPage, 'rrze_multilang_general_section');
            }
        }
    }

    public function multilangModeField()
    {
        echo '<fieldset>';
        echo '<legend class="screen-reader-text">', __('Multilanguage Mode', 'rrze-multilang'), '</legend>';
        echo '<label><input type="radio" name="', $this->optionName, '[multilang_mode]" id="rrze-multilang-multilang-mode" value="0" ', checked($this->options->multilang_mode, 0), '>', __('Disabled', 'rrze-multilang'), '</label>', '<br>';
        echo '<label><input type="radio" name="', $this->optionName, '[multilang_mode]" id="rrze-multilang-multilang-mode" value="1" ', checked($this->options->multilang_mode, 1), '>', __('Single Website', 'rrze-multilang'), '</label>', '<br>';
        if (is_multisite()) {
            echo '<label><input type="radio" name="', $this->optionName, '[multilang_mode]" id="rrze-multilang-multilang-mode" value="2" ', checked($this->options->multilang_mode, 2), '>', __('Multiple Websites', 'rrze-multilang'), '</label>';
        }
        echo '</fieldset>';

        if (Functions::isCmsWorkflowPluginModuleActivated('network')) {
            printf('<p>%s</p>', __('Warning: The Network module of the CMS Workflow plugin is activated! Multiple Websites mode cannot be used if this module is enabled.', 'rrze-multilang'));
        }
    }

    public function connectionTypeField()
    {
        echo '<fieldset>';
        echo '<legend class="screen-reader-text">', __('Connection Type', 'rrze-multilang'), '</legend>';
        echo '<select class="rrze-multilang-links" name="', $this->optionName, '[connection_type]">';
        if ($this->options->connection_type == 0) {
            echo '<option value="0"', selected($this->options->connection_type, 0, false), '>',  __('&mdash; Select &mdash;', 'rrze-multilang'), '</option>';
        }
        echo '<option value="1"', selected($this->options->connection_type, 1, false), '>',  __('Main Website', 'rrze-multilang'), '</option>';
        echo '<option value="2"', selected($this->options->connection_type, 2, false), '>',  __('Secondary Website', 'rrze-multilang'), '</option>';
        echo '</select>';
        echo '</fieldset>';

        if ($this->options->connection_type == 2) {
            $connections = $this->siteOptions->connections[$this->currentBlogId];
            $blogId = array_shift($connections);
            if ($blogId) {
                echo '<p>';
                echo __('Connected to the following main website:', 'rrze-multilang'), '<br>';
                $blogMeta = $this->getBlogMeta($blogId);
                printf(
                    '<a href="%1$s" title="%2$s">%3$s</a> &mdash; %4$s',
                    $blogMeta['admin_url'],
                    $blogMeta['site_name'],
                    $blogMeta['site_url'],
                    $blogMeta['site_lang']
                );
                echo '</p>';
            } else {
                echo '<p class="description">', __('There is no main website connected yet.', 'rrze-multilang'), '</p>';
            }
        }
    }

    public function postTypesField()
    {
        echo '<fieldset>';
        $allPostTypes = Functions::getPostTypes();
        foreach ($allPostTypes as $key => $label) {
            $checked = checked(in_array($key, $this->options->post_types), true, false);
            echo '<legend class="screen-reader-text">' . $label . '</legend>';
            printf(
                '<label><input type="checkbox" name="%1$s[post_types][]" id="rrze-multilang-post-types-%2$s" value="%2$s"%3$s>%4$s</label><br>',
                $this->optionName,
                $key,
                $checked,
                $label
            );
        }
        echo '</fieldset>';
    }

    public function copyPostMetaField()
    {
        echo '<fieldset>';
        foreach ($this->options->copy_post_meta as $key => $value) {
            $label = isset($this->copyPostMetaLabels[$key]) ? $this->copyPostMetaLabels[$key] : $key;
            $checked = checked($value, 1, false);
            echo '<legend class="screen-reader-text">' . $label . '</legend>';
            printf(
                '<label><input type="checkbox" name="%1$s[copy_post_meta][%2$s]" id="rrze-multilang-copy-post-meta-%2$s" value="1" %3$s>%4$s</label><br>',
                $this->optionName,
                $key,
                $checked,
                $label
            );
        }
        echo '</fieldset>';
    }

    public function connectionsField()
    {
        $currentUserId = get_current_user_id();
        $currentUserBlogs = get_blogs_of_user($currentUserId);
        $allBlogs = array_unique(
            array_merge(
                array_keys($currentUserBlogs),
                $this->siteOptions->connections[$this->currentBlogId]
            )
        );

        $availableBlogs = [];

        foreach ($allBlogs as $blogId) {
            if ($this->currentBlogId == $blogId) {
                continue;
            }

            if (!Functions::isBlogPublic($blogId)) {
                $this->deleteMainConnection($blogId);
                continue;
            }

            $availableBlogs[$blogId] = $this->getBlogMeta($blogId);
        }

        $note = __('Note: It is only possible to connect the websites that have activated the plugin in "Multiple Websites" mode and in which you are an administrator.', 'rrze-multilang');
        if (empty($availableBlogs)) {
            echo '<p class="description">', __('There are no websites available.', 'rrze-multilang'), '</p>';
            echo '<p class="description">', $note, '</p>';
            return;
        }

        foreach ($availableBlogs as $blogId => $meta) {
            $mainConnection = ($meta['connection_type'] == 1) ? ' &mdash; ' . __('Main Website', 'rrze-multilang') : '';
            $checked = checked(in_array($blogId, $this->siteOptions->connections[$this->currentBlogId]), true, false);
            if ($meta['connection_type'] == 1 || !isset($currentUserBlogs[$blogId]) || !isset($this->siteOptions->connections[$blogId])) {
                if ($checked && isset($this->siteOptions->connections[$blogId])) {
                    printf(
                        '<input type="hidden" name="%1$s[connections][%2$s]" value="%2$s">',
                        $this->optionName,
                        $blogId
                    );
                }
                $label = sprintf(
                    '<a href="%1$s" title="%2$s">%3$s</a> &mdash; %4$s',
                    $meta['admin_url'],
                    $meta['site_name'],
                    $meta['site_url'],
                    $meta['site_lang']
                );
                printf(
                    '<label><input type="checkbox" disabled="disabled" %1$s>%2$s%3$s</label> ',
                    $checked,
                    $label,
                    $mainConnection
                );
            } else {
                $label = sprintf(
                    '<a href="%1$s" title="%2$s">%3$s</a> &mdash; %4$s',
                    $meta['multilang_admin_url'],
                    $meta['site_name'],
                    $meta['site_url'],
                    $meta['site_lang']
                );
                printf(
                    '<label><input type="checkbox" name="%1$s[connections][%2$s]" id="rrze-multilang-connections-%2$s" value="%2$s" %3$s>%4$s</label> ',
                    $this->optionName,
                    $blogId,
                    $checked,
                    $label
                );
            }
            echo '<br><br>';
        }
        echo '<p class="description">', __('Select the websites that should be connected.', 'rrze-multilang'), '</p>';
        echo '<p class="description">', $note, '</p>';
    }

    public function languagesField()
    {
        $defaultLanguage = Locale::getDefaultLocale();
        $languages = Locale::getAvailableLanguages([
            'selected_only' => false
        ]);
        foreach ($languages as $code => $language) {
            $default = $code == $defaultLanguage;
            $checked = $default ? 'checked="checked"' : checked(in_array($code, $this->options->languages), true, false);
            if ($default) {
                printf(
                    '<input type="hidden" name="%1$s[languages][%2$s]" value="%2$s">',
                    $this->optionName,
                    $code
                );
                printf(
                    '<label><input type="checkbox" checked="checked" disabled="disabled"> %1$s %2$s %3$s</label> ',
                    $code,
                    $language,
                    '&mdash; ' . __('Website Default', 'rrze-multilang')
                );
            } else {
                printf(
                    '<label><input type="checkbox" name="%1$s[languages][%2$s]" id="rrze-multilang-languages-%2$s" value="%2$s" %3$s> %2$s %4$s</label> ',
                    $this->optionName,
                    $code,
                    $checked,
                    $language,
                );
            }
            echo '<br><br>';
        }
        echo '<p class="description">', __('Select the languages available for the website.', 'rrze-multilang'), '</p>';

        echo '<p>', sprintf('<a href="%1$s">%2$s</a>', admin_url('tools.php?page=rrze-multilang'), __('Terms Translations', 'rrze-multilang')), '</p>';
    }

    public function defaultPageField()
    {
        $args = [
            'show_option_none' => __('&mdash; None &mdash;', 'rrze-multilang'),
            'option_none_value' => 0,
            'selected' => $this->options->default_page,
            'depth' => 0,
            'hierarchical' => true,
            'post_type' => 'page',
            'post_status' => 'publish',
            'sort_column' => 'name',
            'name' => $this->optionName . '[default_page]'
        ];
        wp_dropdown_pages($args);
        echo '<p class="description">', __('The page to redirect to if the translation does not exist.', 'rrze-multilang'), '</p>';
    }

    public function optionsValidate($input)
    {
        $defaultOptions = Options::getDefaultOptions();

        // multilang_mode
        $multilangMode = !empty($input['multilang_mode']) ? absint($input['multilang_mode']) : 0;
        $input['multilang_mode'] = in_array($multilangMode, [0, 1, 2]) ? $multilangMode : 0;
        if ($input['multilang_mode'] == 0) {
            return $defaultOptions;
        }

        // connection_type
        $connectionType = !empty($input['connection_type']) ? absint($input['connection_type']) : 0;
        $input['connection_type'] = in_array($connectionType, [0, 1, 2]) ? $connectionType : 0;

        // post_types
        if (
            $this->options->multilang_mode == 1
            || ($this->options->multilang_mode == $input['multilang_mode']
                && $this->options->connection_type != 0)
        ) {
            $postTypes = [];
            $allPostTypes = Functions::getPostTypes();
            foreach (array_keys($allPostTypes) as $key) {
                if (in_array($key, $input['post_types'])) {
                    $postTypes[] = $key;
                }
            }
            $input['post_types'] = $postTypes;
        } else {
            $input['post_types'] = $defaultOptions['post_types'];
        }

        $input['default_page'] = !empty($input['default_page']) ? absint($input['default_page']) : 0;

        if ($input['multilang_mode'] == 2 && $input['connection_type'] == 1) {
            $this->deleteSecondaryConnections($this->currentBlogId);

            // connections
            $inputConnections = [];
            if (!empty($input['connections'])) {
                $inputConnections = (array) $input['connections'];
                unset($input['connections']);
            }
            $connections = [];
            foreach ($inputConnections as $key => $blogId) {
                if (!Functions::isBlogPublic($blogId)) {
                    $this->deleteMainConnection($blogId);
                    continue;
                }
                switch_to_blog($blogId);
                $options = (object) Options::getOptions();
                restore_current_blog();
                if (
                    isset($this->siteOptions->connections[$blogId])
                    && $options->connection_type == 2
                ) {
                    $connections[] = $blogId;
                    $this->siteOptions->connections[$blogId] = [$this->currentBlogId];
                }
            }
            $this->siteOptions->connections[$this->currentBlogId] = $connections;
            update_site_option($this->siteOptionName, (array) $this->siteOptions);

            // copy_post_meta
            $copyPostMeta = [];
            if ($this->options->connection_type != 1) {
                $input['copy_post_meta'] = $defaultOptions['copy_post_meta'];
            }
            foreach (array_keys($defaultOptions['copy_post_meta']) as $key) {
                $copyPostMeta[$key] = !empty($input['copy_post_meta'][$key]) ? 1 : 0;
            }
            $input['copy_post_meta'] = $copyPostMeta;
        } else {
            $input['copy_post_meta'] = $defaultOptions['copy_post_meta'];
        }

        return $input;
    }

    protected function addMainConnection(int $blogId)
    {
        if (!isset($this->siteOptions->connections[$blogId])) {
            $this->siteOptions->connections[$blogId] = [];
            update_site_option($this->siteOptionName, (array) $this->siteOptions);
        }
    }

    protected function deleteMainConnection($blogId)
    {
        if (isset($this->siteOptions->connections[$blogId])) {
            unset($this->siteOptions->connections[$blogId]);
            update_site_option($this->siteOptionName, (array) $this->siteOptions);
        }
    }

    protected function deleteSecondaryConnections($blogId)
    {
        $update = false;
        foreach ($this->siteOptions->connections as $connection => $secondaryConnections) {
            if ($connection == $blogId) {
                continue;
            }
            $key = array_search($blogId, $secondaryConnections);
            if ($key !== false) {
                unset($this->siteOptions->connections[$connection][$key]);
                $update = true;
            }
        }
        if ($update) {
            update_site_option($this->siteOptionName, (array) $this->siteOptions);
        }
    }

    protected function getBlogMeta(int $blogId): array
    {
        switch_to_blog($blogId);
        $options = (object) Options::getOptions();
        $connectionType = $options->connection_type;
        $siteName = get_bloginfo('name');
        $siteUrl = get_bloginfo('url');
        $language = Locale::getDefaultLocale();
        $adminUrl = admin_url();
        $multilangAdminUrl = admin_url('options-general.php?page=rrze-multilang');
        restore_current_blog();

        return [
            'connection_type' => $connectionType,
            'site_url' => $siteUrl,
            'site_name' => $siteName,
            'admin_url' => $adminUrl,
            'multilang_admin_url' => $multilangAdminUrl,
            'site_lang' => Locale::getLanguageNativeName($language)
        ];
    }
}
