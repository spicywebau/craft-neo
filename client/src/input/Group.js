import Garnish from 'garnish'

const _defaults = {
	sortOrder: 0,
	name: ''
}

export default Garnish.Base.extend({

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._sortOrder = settings.sortOrder|0
		this._name = settings.name
	},

	getType() { return 'group' },
	getSortOrder() { return this._sortOrder },
	getName() { return this._name },

	isBlank() { return !this._name }
})
