import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import Item from './Item'
import Settings from './GroupSettings'

import renderTemplate from './templates/group.twig'
import '../twig-extensions'

const _defaults = {
	namespace: [],
	settings: null
}

export default Item.extend({

	_templateNs: [],

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._settings = settings.settings

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			settings: this._settings
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-g]')
		this.$nameText = $neo.filter('[data-neo-g="text.name"]')
		this.$moveButton = $neo.filter('[data-neo-g="button.move"]')

		if(this._settings)
		{
			this._settings.on('change', () => this._updateTemplate())
			this._settings.on('delete', () => this.trigger('delete'))
		}
	},

	getSettings()
	{
		return this._settings
	},

	toggleSelect: function(select)
	{
		this.base(select)

		const selected = this.isSelected()

		if(this._settings)
		{
			this._settings.$container.toggleClass('hidden', !selected)
		}

		this.$container.toggleClass('is-selected', selected)
	},

	_updateTemplate()
	{
		const settings = this.getSettings()

		if(settings)
		{
			this.$nameText.text(settings.getName())
		}
	}
})
