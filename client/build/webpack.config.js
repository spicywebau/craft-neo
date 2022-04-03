const webpack = require('webpack')
const path = require('path')

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
  plugins: [
    new webpack.optimize.UglifyJsPlugin({
      compress: {
        warnings: false
      }
    })
  ],
  module: {
    loaders: [
      {
        loader: 'style!css',
        test: /\.css$/
      },
      {
        loaders: ['style', 'css', 'sass'],
        test: /\.scss$/
      },
      {
        loader: 'babel-loader',
        test: /\.jsx?$/,
        include: [path.resolve(__dirname, '../src')],
        query: { presets: ['es2015'] }
      }
    ]
  }
}
