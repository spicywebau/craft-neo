var path = require('path')

module.exports = {
	devtool: 'source-map',
	entry: './neo/resources/src/main.js',
	output: {
		path: './neo/resources/dist/',
		filename: 'main.js'
	},
	externals: {
		jquery: 'jQuery',
		craft: 'Craft',
		garnish: 'Garnish'
	},
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
				include: [
					path.resolve(__dirname, 'neo/resources/src')
				],
				query: {
					presets: ['es2015']
				}
			},
			{
				loader: 'twig-loader',
				test: /\.twig$/
			}
		]
	},
	node: {
		// Warning suppression for Twig.js dependency on the fs module
		// @see https://github.com/josephsavona/valuable/issues/9
		fs: 'empty'
	}
}
