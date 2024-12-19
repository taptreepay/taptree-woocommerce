const path = require('path');
const {CleanWebpackPlugin} = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const webpack = require('webpack');

const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
  mode: isProduction ? 'production' : 'development',

  entry: {
    modal: ['./client/js/modal/modal.js', './client/scss/modal/modal.scss'],
  },

  output: {
    filename: '[name].bundle.js',
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
    ],
  },

  plugins: [
    new CleanWebpackPlugin(),
    new MiniCssExtractPlugin({
      filename: '../css/modal.css', // Outputs modal.css for the modal bundle
    }),
    new webpack.ProvidePlugin({
      $: 'jquery',
      jQuery: 'jquery',
    }),
  ],

  externals: {
    jquery: 'jQuery',
  },

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
