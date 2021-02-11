import { registerPlugin } from '@wordpress/plugins';

import render from './render';

registerPlugin('rrze-multilang-language-panel', {
    render,
    icon: 'translation'
});
