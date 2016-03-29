import $ from 'jquery'

import Garnish from 'garnish'

export default Garnish.Base.extend({

	_selected: false,

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
