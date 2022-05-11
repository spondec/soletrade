const mix = require('laravel-mix');
const tailwindcss = require('tailwindcss')
const webpack = require('webpack');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel application. By default, we are compiling the Sass
 | file for the application as well as bundling up all the JS files.
 |
 */

mix.js('resources/js/app.js', 'public/js')
    .vue()
    .sass('resources/sass/app.scss', 'public/css')
    .options({
        processCssUrls: false,
        postCss: [tailwindcss('tailwind.config.js')],
    })
    .sourceMaps(false, 'source-map')
    .webpackConfig({
        plugins: [
            new webpack.DefinePlugin({
                __VUE_OPTIONS_API__: true,
                __VUE_PROD_DEVTOOLS__: false
            })
        ]
    });