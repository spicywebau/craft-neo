import $ from 'jquery'

import Garnish from 'garnish'

const _defaults = {
	settings: null
}

export default Garnish.Base.extend({

	_selected: false,

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._settings = settings.settings
	},

	getSettings()
	{
		return this._settings
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

		this.trigger('toggleSelect', {
			selected: this._selected
		})
	},

	isSelected()
	{
		return this._selected
	}
})
