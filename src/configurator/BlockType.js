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
	fieldLayout: null
}

export default Item.extend({

	_templateNs: [],

	init(settings = {})
	{
		this.base(settings)

		const settingsObj = this.getSettings()
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._fieldLayout = settings.fieldLayout

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			settings:    settingsObj,
			fieldLayout: this._fieldLayout
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-bt]')
		this.$nameText = $neo.filter('[data-neo-bt="text.name"]')
		this.$moveButton = $neo.filter('[data-neo-bt="button.move"]')

		if(settingsObj)
		{
			settingsObj.on('change', () => this._updateTemplate())
			settingsObj.on('destroy', () => this.trigger('destroy'))

			this._updateTemplate()
		}

		this.deselect()
	},

	getFieldLayout()
	{
		return this._fieldLayout
	},

	toggleSelect: function(select)
	{
		this.base(select)

		const settings = this.getSettings()
		const fieldLayout = this.getFieldLayout()
		const selected = this.isSelected()

		if(settings)
		{
			settings.$container.toggleClass('hidden', !selected)
		}

		if(fieldLayout)
		{
			fieldLayout.$container.toggleClass('hidden', !selected)
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
			this.$container.toggleClass('is-child', !settings.getTopLevel())

			if(fieldLayout)
			{
				fieldLayout.setBlockName(settings.getName())
			}
		}
	}
})
