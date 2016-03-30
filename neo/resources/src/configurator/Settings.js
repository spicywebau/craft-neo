import $ from 'jquery'

import Garnish from 'garnish'

export default Garnish.Base.extend({

	$container: new $,
	_sortOrder: 0,

	getSortOrder()
	{
		return this._sortOrder
	},

	setSortOrder(sortOrder)
	{
		const oldSortOrder = this._sortOrder
		this._sortOrder = sortOrder|0

		this.trigger('change', {
			property: 'sortOrder',
			oldValue: oldSortOrder,
			newValue: this._sortOrder
		})
	},

	destroy()
	{
		this.trigger('destroy')
	}
})
