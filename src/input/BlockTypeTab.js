import Garnish from 'garnish'

const _defaults = {
	name: '',
	headHtml: '',
	bodyHtml: '',
	footHtml: '',
	errors: []
}

export default Garnish.Base.extend({

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._name = settings.name
		this._headHtml = settings.headHtml || ''
		this._bodyHtml = settings.bodyHtml || ''
		this._footHtml = settings.footHtml || ''
		this._errors = settings.errors
	},

	getErrors() { return Array.from(this._errors) },

	getName() { return this._name },

	getHeadHtml(blockId = null)
	{
		return this._getHtml(this._headHtml, blockId)
	},

	getBodyHtml(blockId = null)
	{
		return this._getHtml(this._bodyHtml, blockId)
	},

	getFootHtml(blockId = null)
	{
		return this._getHtml(this._footHtml, blockId)
	},

	isBlank()
	{
		return !this._bodyHtml.trim()
	},

	_getHtml(html, blockId = null)
	{
		if(blockId !== null)
		{
			return html.replace(/__NEOBLOCK__/g, blockId)
		}

		return html
	}
})
