import Garnish from 'garnish'

import Tab from './BlockTypeTab'

const _defaults = {
	name: '',
	handle: '',
	maxBlocks: 0,
	tabs: []
}

export default Garnish.Base.extend({

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._name = settings.name
		this._handle = settings.handle
		this._maxBlocks = settings.maxBlocks|0
		this._tabs = settings.tabs.map(tab => new Tab(tab))
	},

	getName() { return this._name },
	getHandle() { return this._handle },
	getMaxBlocks() { return this._maxBlocks },
	getTabs() { return Array.from(this._tabs) }
})
