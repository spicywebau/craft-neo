import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import Settings from './BlockTypeSettings'
import FieldLayout from './BlockTypeFieldLayout'

import renderTemplate from './templates/blocktype.twig'
import '../twig-extensions'

const _defaults = {
	namespace: [],
	settings: null,
	fieldLayout: null
}

export default Garnish.Base.extend({

	_templateNs: [],
	_parsed: false,
	_selected: false,

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

	select()
	{
		this.toggleSelect(true)
	},

	deselect()
	{
		this.toggleSelect(false)
	},

	toggleSelect: function(select)
	{
		this._selected = (typeof select === 'boolean' ? select : !this._selected)

		if(this._settings)
		{
			this._settings.$container.toggleClass('hidden', !this._selected)
		}

		if(this._fieldLayout)
		{
			this._fieldLayout.$container.toggleClass('hidden', !this._selected)
		}

		this.$container.toggleClass('is-selected', this._selected)
	},

	isSelected()
	{
		return this._selected
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
