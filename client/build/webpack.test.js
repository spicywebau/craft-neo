const config = require('./webpack')

config.target = 'node'

const rules = config.module ? config.module.rules : []
for(let rule of rules)
{
	const use = rule.use || {}
	if(use.loader === 'babel-loader')
	{
		use.options = use.options || {}
		use.options.env = use.options.env || {}
		use.options.env.test = { plugins: ['istanbul'] }
	}
}

module.exports = config
