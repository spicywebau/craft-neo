const path = require('path')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const TerserPlugin = require('terser-webpack-plugin')

module.exports = {
  entry: {
    main: path.resolve(__dirname, '../src/main.js'),
    configurator: path.resolve(__dirname, '../src/configurator.js'),
    converter: path.resolve(__dirname, '../src/converter.js')
  },
  output: {
    path: path.resolve(__dirname, '../../src/resources/'),
    filename: 'neo-[name].js'
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
          options: { presets: ['@babel/preset-env'] }
        },
        include: [path.resolve(__dirname, '../src')],
        test: /\.jsx?$/
      }
    ]
  },
  plugins: [new MiniCssExtractPlugin({
    filename: 'neo-[name].css'
  })]
}
