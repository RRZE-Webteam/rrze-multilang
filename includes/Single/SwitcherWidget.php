<?php

namespace RRZE\Multilang\Single;

defined('ABSPATH') || exit;

class SwitcherWidget extends \WP_Widget
{
    function __construct()
    {
        $widget_ops = [
            'description' => __('Language switcher widget.', 'rrze-multilang'),
        ];

        $control_ops = [];

        parent::__construct(
            'rrze_multilang_language_switcher',
            __('Language Switcher', 'rrze-multilang'),
            $widget_ops,
            $control_ops
        );
    }

    function widget($args, $instance)
    {
        $title = apply_filters(
            'widget_title',
            empty($instance['title'])
                ? __('Language', 'rrze-multilang')
                : $instance['title'],
            $instance,
            $this->id_base
        );

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title'] . $title . $args['after_title'];
        }

        echo Switcher::languageSwitcher();

        echo $args['after_widget'];
    }

    function form($instance)
    {
        $instance = wp_parse_args((array) $instance, ['title' => '']);
        $title = strip_tags($instance['title']);

        echo '<p><label for="' . $this->get_field_id('title') . '">' . esc_html(__('Title:', 'rrze-multilang')) . '</label> <input class="widefat" id="' . $this->get_field_id('title') . '" name="' . $this->get_field_name('title') . '" type="text" value="' . esc_attr($title) . '" /></p>';
    }

    function update($new_instance, $old_instance)
    {
        $instance = $old_instance;

        $new_instance = wp_parse_args(
            (array) $new_instance,
            ['title' => '']
        );

        $instance['title'] = strip_tags($new_instance['title']);

        return $instance;
    }
}
