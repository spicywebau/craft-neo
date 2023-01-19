const path = require('path')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const TerserPlugin = require('terser-webpack-plugin')

module.exports = {
  entry: {
    main: path.resolve(__dirname, 'src/assets/src/main.js'),
    configurator: path.resolve(__dirname, 'src/assets/src/configurator.js'),
    converter: path.resolve(__dirname, 'src/assets/src/converter.js')
  },
  output: {
    path: path.resolve(__dirname, 'src/assets/dist/'),
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
        include: [path.resolve(__dirname, 'src/assets/src')],
        test: /\.jsx?$/
      }
    ]
  },
  devtool: 'source-map',
  plugins: [new MiniCssExtractPlugin({
    filename: 'neo-[name].css'
  })]
}
