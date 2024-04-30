const path = require('path')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const TerserPlugin = require('terser-webpack-plugin')

module.exports = {
  entry: {
    'configurator/dist/scripts/configurator': path.resolve(__dirname, 'src/web/assets/configurator/src/scripts/main.js'),
    'configurator/dist/styles/configurator': path.resolve(__dirname, 'src/web/assets/configurator/src/styles/main.scss'),
    'converter/dist/scripts/converter': path.resolve(__dirname, 'src/web/assets/converter/src/scripts/main.js'),
    'input/dist/scripts/input': path.resolve(__dirname, 'src/web/assets/input/src/scripts/main.js'),
    'input/dist/styles/input': path.resolve(__dirname, 'src/web/assets/input/src/styles/main.scss')
  },
  output: {
    path: path.resolve(__dirname, 'src/web/assets'),
    filename: '[name].js'
  },
  externals: {
    jquery: 'jQuery',
    craft: 'Craft',
    garnish: 'Garnish'
  },
  optimization: {
    minimize: true,
    minimizer: [new TerserPlugin()]
  },
  module: {
    rules: [
      {
        use: [MiniCssExtractPlugin.loader, 'css-loader'],
        test: /\.css$/
      },
      {
        use: [MiniCssExtractPlugin.loader, 'css-loader', 'sass-loader'],
        test: /\.scss$/
      },
      {
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              [
                '@babel/preset-env',
                {
                  targets: {
                    firefox: '67',
                    chrome: '63',
                    safari: '11',
                    edge: '79'
                  }
                }
              ]
            ]
          }
        },
        include: [path.resolve(__dirname, 'src/web/assets/src')],
        test: /\.jsx?$/
      }
    ]
  },
  devtool: 'source-map',
  plugins: [new MiniCssExtractPlugin({
    filename: '[name].css'
  })]
}
