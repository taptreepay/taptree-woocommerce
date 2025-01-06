const path = require('path');
const {CleanWebpackPlugin} = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const RemoveEmptyScriptsPlugin = require('webpack-remove-empty-scripts');
const webpack = require('webpack');

const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
  mode: isProduction ? 'production' : 'development',

  entry: {
    common: ['./client/scss/common.scss'],
    modal: ['./client/js/modal/modal.js', './client/scss/modal.scss'],
  },

  output: {
    filename: (pathData) => {
      if (pathData.chunk.name === 'common') {
        return '[name].js';
      }
      return '[name].bundle.js';
    },
    path: path.resolve(__dirname, 'assets/js'),
    publicPath:
      '/wp-content/plugins/taptree-payments-for-woocommerce/assets/js/',
  },

  module: {
    rules: [
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
        },
      },
      {
        test: /\.scss$/,
        use: [
          MiniCssExtractPlugin.loader, // Always extract CSS
          'css-loader', // Turns CSS into CommonJS
          'sass-loader', // Compiles SCSS to CSS
        ],
      },
      {
        test: /\.scss$/,
        issuer: /\.scss$/, // Only apply this for SCSS-only entries
        use: 'ignore-loader', // Ignore JS generation for SCSS-only entries
      },
    ],
  },

  plugins: [
    new RemoveEmptyScriptsPlugin(),
    new CleanWebpackPlugin(),
    new MiniCssExtractPlugin({
      filename: (pathData) => {
        return '../css/[name].css';
      },
    }),
    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
    }),
  ],

  externals: {
    jquery: 'jQuery',
  },

  // Sourcemaps have no performance impact in production.
  // They are only downloaded if the browser's DevTools are open.
  devtool: isProduction ? 'source-map' : 'inline-source-map',

  watchOptions: {
    ignored: /node_modules/,
    aggregateTimeout: 300,
    poll: 1000,
  },

  resolve: {
    extensions: ['.js', '.scss'],
  },
};
