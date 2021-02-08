<?php

namespace RRZE\Multilang\Multiple;

defined('ABSPATH') || exit;

class SwitcherWidget extends \WP_Widget
{
    function __construct()
    {
        $widgetOps = [
            'description' => __('Language switcher widget.', 'rrze-multilang'),
        ];

        $controlOps = [];

        parent::__construct(
            'rrze_multilang_language_switcher',
            __('Language Switcher', 'rrze-multilang'),
            $widgetOps,
            $controlOps
        );
    }

    function widget($args, $instance)
    {
        $title = apply_filters(
            'rrze_multilang_language_switcher_widget_title',
            empty($instance['title'])
                ? ''
                : $instance['title'],
            $instance,
            $this->id_base
        );

        $defaultPage = absint($instance['default_page']);

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title'], esc_html(($title)), $args['after_title'];
        }

        echo Switcher::languageSwitcher();

        echo $args['after_widget'];
    }

    function form($instance)
    {
        $instance = wp_parse_args(
            (array) $instance,
            [
                'title' => ''
            ]
        );

        $title = strip_tags($instance['title']);

        echo '<p>';
        printf(
            '<label for="%1$s">%2$s</label> <input class="widefat" id="%1$s" name="%3$s" type="text" value="%4$s">',
            $this->get_field_id('title'),
            esc_html(__('Title:', 'rrze-multilang')),
            $this->get_field_name('title'),
            esc_attr($title)
        );
        echo '</p>';
    }

    function update($newInstance, $oldInstance)
    {
        $instance = $oldInstance;

        $newInstance = wp_parse_args(
            (array) $newInstance,
            [
                'title' => ''
            ]
        );

        $instance['title'] = strip_tags($newInstance['title']);

        return $instance;
    }
}
