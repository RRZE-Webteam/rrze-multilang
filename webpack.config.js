const defaults = require("@wordpress/scripts/config/webpack.config");
const webpack = require("webpack");

/**
 * WP-Scripts Webpack config.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-scripts/#provide-your-own-webpack-config
 */
module.exports = {
    ...defaults,
    entry: {
        "block-editor-multiple": "./src/block-editor/multiple/index.js",
        "block-editor-single": "./src/block-editor/single/index.js",
        "classic-editor-multiple": "./src/classic-editor/multiple/index.js",
        "classic-editor-single": "./src/classic-editor/single/index.js",
        "frontend": "./src/frontend/index.js",
    },
    plugins: [
        ...defaults.plugins,
        new webpack.ProvidePlugin({
            $: "jquery",
            jQuery: "jquery",
        }),
    ],
};
