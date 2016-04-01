import Garnish from 'garnish'

import Tab from './BlockTypeTab'

const _defaults = {
	id: -1,
	sortOrder: 0,
	name: '',
	handle: '',
	maxBlocks: 0,
	tabs: []
}

export default Garnish.Base.extend({

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._id = settings.id|0
		this._sortOrder = settings.sortOrder|0
		this._name = settings.name
		this._handle = settings.handle
		this._maxBlocks = settings.maxBlocks|0
		this._tabs = settings.tabs.map(tab => new Tab(tab))
	},

	getType() { return 'blockType' },
	getId() { return this._id },
	getSortOrder() { return this._sortOrder },
	getName() { return this._name },
	getHandle() { return this._handle },
	getMaxBlocks() { return this._maxBlocks },
	getTabs() { return Array.from(this._tabs) }
})
