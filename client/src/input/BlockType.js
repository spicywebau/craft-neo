import Garnish from 'garnish'

import Tab from './BlockTypeTab'

const _defaults = {
	id: -1,
	fieldLayoutId: -1,
	fieldTypes: {},
	sortOrder: 0,
	name: '',
	handle: '',
	maxBlocks: 0,
	maxChildBlocks: 0,
	childBlocks: false,
	topLevel: true,
	tabs: []
}

export default Garnish.Base.extend({

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._id = settings.id|0
		this._fieldLayoutId = settings.fieldLayoutId|0
		this._fieldTypes = settings.fieldTypes
		this._sortOrder = settings.sortOrder|0
		this._name = settings.name
		this._handle = settings.handle
		this._maxBlocks = settings.maxBlocks|0
		this._maxChildBlocks = settings.maxChildBlocks|0
		this._childBlocks = settings.childBlocks
		this._topLevel = settings.topLevel
		this._tabs = settings.tabs.map(tab => tab instanceof Tab ? tab : new Tab(tab))
	},

	getType() { return 'blockType' },
	getId() { return this._id },
	getFieldLayoutId() { return this._fieldLayoutId },
	getFieldTypes() { return this._fieldTypes },
	getSortOrder() { return this._sortOrder },
	getName() { return this._name },
	getHandle() { return this._handle },
	getMaxBlocks() { return this._maxBlocks },
	getMaxChildBlocks() { return this._maxChildBlocks },
	getChildBlocks() { return this._childBlocks },
	getTopLevel() { return this._topLevel },
	getTabs() { return Array.from(this._tabs) },

	getFieldType(handle)
	{
		return this._fieldTypes[handle]
	},

	getChildBlockItems(items)
	{
		const firstPass = items.filter(item => item.getType() === 'group' || this.hasChildBlock(item.getHandle()))
		return firstPass.filter((item, i) =>
		{
			if(item.getType() === 'group')
			{
				const nextItem = firstPass[i + 1]
				return nextItem && nextItem.getType() !== 'group'
			}

			return true
		})
	},

	isParent()
	{
		const cb = this.getChildBlocks()
		return cb === true || cb === '*' || (Array.isArray(cb) && cb.length > 0)
	},

	hasChildBlock(handle)
	{
		const cb = this.getChildBlocks()
		return cb === true || cb === '*' || (Array.isArray(cb) && cb.includes(handle))
	},

	isValidChildBlock(block)
	{
		const cb = this.getChildBlocks()

		if(cb === true || cb === '*')
		{
			return true
		}

		const typeHandle = block.getBlockType().getHandle()

		if(Array.isArray(cb))
		{
			for(let th of cb)
			{
				if(th == typeHandle)
				{
					return true
				}
			}
		}

		return false
	}
})
