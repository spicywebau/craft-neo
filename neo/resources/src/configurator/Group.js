import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import Item from './Item'
import Settings from './GroupSettings'

import renderTemplate from './templates/group.twig'
import '../twig-extensions'

const _defaults = {
	namespace: []
}

export default Item.extend({

	_templateNs: [],

	init(settings = {})
	{
		this.base(settings)

		settings = Object.assign({}, _defaults, settings)

		const settingsObj = this.getSettings()
		this._templateNs = NS.parse(settings.namespace)

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			settings: settingsObj
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-g]')
		this.$nameText = $neo.filter('[data-neo-g="text.name"]')
		this.$moveButton = $neo.filter('[data-neo-g="button.move"]')

		if(settingsObj)
		{
			settingsObj.on('change', () => this._updateTemplate())
			settingsObj.on('destroy', () => this.trigger('destroy'))
		}

		this.deselect()
	},

	toggleSelect: function(select)
	{
		this.base(select)

		const settings = this.getSettings()
		const selected = this.isSelected()

		if(settings)
		{
			settings.$container.toggleClass('hidden', !selected)
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
