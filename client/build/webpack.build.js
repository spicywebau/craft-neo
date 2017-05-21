const webpack = require('webpack')
const path = require('path')
const config = require('./webpack')

const absPath = (dir) => path.resolve(__dirname, dir)

module.exports = (env={}, args={}) => Object.assign(config, {
	devtool: 'source-map',
	entry: {
		configurator: absPath('../src/configurator.js'),
		input: absPath('../src/input.js'),
	},
	output: {
		path: absPath('../../src/assets/resources/'),
		filename: '[name].js',
	},
	plugins: args.p || args.production ? [
		new webpack.optimize.UglifyJsPlugin({
			compress: { warnings: false },
			output: { comments: false },
			sourceMap: true,
		}),
	] : [],
})
