import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import renderTemplate from './templates/block.twig'
import '../twig-extensions'

const _defaults = {
	namespace: [],
	blockType: null,
	id: null
}

export default Garnish.Base.extend({

	_templateNs: [],
	_blockType: null,
	_initialised: false,

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._blockType = settings.blockType
		this._id = settings.id

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			type: this._blockType
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-b]')
		this.$contentContainer = $neo.filter('[data-neo-b="container.content"]')
		this.$content = $(this._blockType.getBodyHtml(this._id)).appendTo(this.$contentContainer)
	},

	initUi()
	{
		if(!this._initialised)
		{
			this.$foot = $(this._blockType.getFootHtml(this._id))

			Garnish.$bod.append(this.$foot)
			Craft.initUiElements(this.$contentContainer)

			this._initialised = true
		}
	},

	destroy()
	{
		if(this._initialised)
		{
			this.$foot.remove()
		}
	},

	getBlockType()
	{
		return this._blockType
	},

	getId()
	{
		return this._id
	}
},
{
	_totalNewBlocks: 0,

	getNewId()
	{
		return `new${this._totalNewBlocks++}`
	}
})
