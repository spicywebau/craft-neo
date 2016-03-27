import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

const _defaults = {
	name: '',
	handle: '',
	maxBlocks: 0,
	bodyHtml: '',
	footHtml: ''
}

export default Garnish.Base.extend({

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._name = settings.name
		this._handle = settings.handle
		this._maxBlocks = settings.maxBlocks|0
		this._bodyHtml = settings.bodyHtml
		this._footHtml = settings.footHtml
	},

	getName() { return this._name },
	getHandle() { return this._handle },
	getMaxBlocks() { return this._maxBlocks },

	getBodyHtml(blockId = null)
	{
		if(blockId !== null)
		{
			return this._bodyHtml.replace(/__BLOCK__/g, blockId)
		}

		return this._bodyHtml
	},

	getFootHtml(blockId = null)
	{
		if(blockId !== null)
		{
			return this._footHtml.replace(/__BLOCK__/g, blockId)
		}

		return this._footHtml
	}
})
