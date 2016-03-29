import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import Item from './Item'
import Settings from './BlockTypeSettings'
import FieldLayout from './BlockTypeFieldLayout'

import renderTemplate from './templates/blocktype.twig'
import '../twig-extensions'

const _defaults = {
	namespace: [],
	settings: null,
	fieldLayout: null
}

export default Item.extend({

	_templateNs: [],

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._settings = settings.settings
		this._fieldLayout = settings.fieldLayout

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			settings:    this._settings,
			fieldLayout: this._fieldLayout
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-bt]')
		this.$nameText = $neo.filter('[data-neo-bt="text.name"]')
		this.$moveButton = $neo.filter('[data-neo-bt="button.move"]')

		if(this._settings)
		{
			this._settings.on('change', () => this._updateTemplate())
			this._settings.on('delete', () => this.trigger('delete'))
		}

		this.deselect()
	},

	getSettings()
	{
		return this._settings
	},

	getFieldLayout()
	{
		return this._fieldLayout
	},

	toggleSelect: function(select)
	{
		this.base(select)

		const selected = this.isSelected()

		if(this._settings)
		{
			this._settings.$container.toggleClass('hidden', !selected)
		}

		if(this._fieldLayout)
		{
			this._fieldLayout.$container.toggleClass('hidden', !selected)
		}

		this.$container.toggleClass('is-selected', selected)
	},

	_updateTemplate()
	{
		const settings = this.getSettings()
		const fieldLayout = this.getFieldLayout()

		if(settings)
		{
			this.$nameText.text(settings.getName())

			if(fieldLayout)
			{
				fieldLayout.setBlockName(settings.getName())
			}
		}
	}
})
