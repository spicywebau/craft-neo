import Garnish from 'garnish'

const _defaults = {
	name: '',
	bodyHtml: '',
	footHtml: ''
}

export default Garnish.Base.extend({

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._name = settings.name
		this._bodyHtml = settings.bodyHtml
		this._footHtml = settings.footHtml
	},

	getName() { return this._name },

	getBodyHtml(blockId = null)
	{
		if(blockId !== null)
		{
			return this._bodyHtml.replace(/__NEOBLOCK__/g, blockId)
		}

		return this._bodyHtml
	},

	getFootHtml(blockId = null)
	{
		if(blockId !== null)
		{
			return this._footHtml.replace(/__NEOBLOCK__/g, blockId)
		}

		return this._footHtml
	}
})
