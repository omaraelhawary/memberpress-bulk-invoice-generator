const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const FileManagerPlugin = require('filemanager-webpack-plugin');

module.exports = (env, argv) => {
  const isProduction = argv.mode === 'production';
  
  return {
    entry: {
      admin: './assets/js/admin.js',
      adminStyles: './assets/css/admin.css'
    },
    output: {
      path: path.resolve(__dirname, 'dist'),
      filename: 'js/[name].min.js',
      clean: true
    },
    module: {
      rules: [
        {
          test: /\.css$/,
          use: [
            MiniCssExtractPlugin.loader,
            'css-loader'
          ]
        },
        {
          test: /\.js$/,
          exclude: /node_modules/,
          use: {
            loader: 'babel-loader',
            options: {
              presets: ['@babel/preset-env']
            }
          }
        }
      ]
    },
    plugins: [
      new MiniCssExtractPlugin({
        filename: 'css/[name].min.css'
      }),
      new FileManagerPlugin({
        events: {
          onEnd: {
            copy: [
              {
                source: './dist/css/adminStyles.min.css',
                destination: './assets/css/admin.min.css'
              },
              {
                source: './dist/js/admin.min.js',
                destination: './assets/js/admin.min.js'
              }
            ],
            delete: ['./dist']
          }
        }
      })
    ],
    optimization: {
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            format: {
              comments: false,
            },
            compress: {
              drop_console: isProduction,
              drop_debugger: isProduction
            }
          },
          extractComments: false,
        }),
        new CssMinimizerPlugin({
          minimizerOptions: {
            preset: [
              'default',
              {
                discardComments: { removeAll: true },
                normalizeWhitespace: isProduction
              },
            ],
          },
        }),
      ],
    },
    devtool: isProduction ? false : 'source-map'
  };
};
