const webpack = require('webpack')
const path = require('path')

module.exports = {
	entry: {
		main: path.resolve(__dirname, '../src/main.js'),
		converter: path.resolve(__dirname, '../src/converter.js'),
		polyfill: path.resolve(__dirname, '../src/polyfill.js'),
	},
	output: {
		path: path.resolve(__dirname, '../../src/resources/'),
		filename: '[name].js',
	},
	externals: {
		jquery: 'jQuery',
		craft: 'Craft',
		garnish: 'Garnish',
	},
	plugins: [
		new webpack.optimize.UglifyJsPlugin({
			compress: {
				warnings: false,
			},
		}),
	],
	module: {
		loaders: [
			{
				loader: 'style!css',
				test: /\.css$/,
			},
			{
				loaders: ['style', 'css', 'sass'],
				test: /\.scss$/,
			},
			{
				loader: 'babel-loader',
				test: /\.jsx?$/,
				include: [ path.resolve(__dirname, '../src') ],
				query: { presets: ['es2015'] },
			},
			{
				loader: 'twig-loader',
				test: /\.twig$/,
			}
		]
	},
	node: {
		// Warning suppression for Twig.js dependency on the fs module
		// @see https://github.com/josephsavona/valuable/issues/9
		fs: 'empty',
	}
}
