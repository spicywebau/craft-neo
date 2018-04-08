import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import BlockType from './BlockType'
import BlockTypeSettings from './BlockTypeSettings'
import BlockTypeFieldLayout from './BlockTypeFieldLayout'
import Group from './Group'
import GroupSettings from './GroupSettings'

import renderTemplate from './templates/configurator.twig'
import '../twig-extensions'
import './styles/configurator.scss'

const _defaults = {
	namespace: [],
	blockTypes: [],
	groups: [],
	fieldLayoutHtml: ''
}

export default Garnish.Base.extend({

	_templateNs: [],
	_items: [],

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		const inputIdPrefix = Craft.formatInputId(settings.namespace)
		const $field = $(`\#${inputIdPrefix}-neo-configurator`)
		const $input = $field.children('.field').children('.input')

		this._templateNs = NS.parse(settings.namespace)
		this._fieldLayoutHtml = settings.fieldLayoutHtml
		this._items = []

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate())
		$input.append(this.$container)

		NS.leave()

		const $neo = this.$container.find('[data-neo]')
		this.$mainContainer = $neo.filter('[data-neo="container.main"]')
		this.$sidebarContainer = $neo.filter('[data-neo="container.sidebar"]')
		this.$blockTypesContainer = $neo.filter('[data-neo="container.blockTypes"]')
		this.$settingsContainer = $neo.filter('[data-neo="container.settings"]')
		this.$fieldLayoutContainer = $neo.filter('[data-neo="container.fieldLayout"]')
		this.$blockTypeButton = $neo.filter('[data-neo="button.blockType"]')
		this.$groupButton = $neo.filter('[data-neo="button.group"]')
		this.$settingsButton = $neo.filter('[data-neo="button.settings"]')
		this.$fieldLayoutButton = $neo.filter('[data-neo="button.fieldLayout"]')

		this._itemSort = new Garnish.DragSort(null, {
			container: this.$blockTypeItemsContainer,
			handle: '[data-neo-bt="button.move"], [data-neo-g="button.move"]',
			axis: 'y',
			onSortChange: () => this._updateItemOrder()
		})

		// Add the existing block types and groups
		const existingItems = []
		const btNamespace = [...this._templateNs, 'blockTypes']
		const gNamespace  = [...this._templateNs, 'groups']

		for(let btInfo of settings.blockTypes)
		{
			let btSettings = new BlockTypeSettings({
				namespace: [...btNamespace, btInfo.id],
				sortOrder: btInfo.sortOrder,
				id: btInfo.id,
				name: btInfo.name,
				handle: btInfo.handle,
				maxBlocks: btInfo.maxBlocks,
				maxChildBlocks: btInfo.maxChildBlocks,
				topLevel: btInfo.topLevel,
				errors: btInfo.errors,
				childBlockTypes: existingItems.filter(item => item instanceof BlockType)
			})

			let btFieldLayout = new BlockTypeFieldLayout({
				namespace: [...btNamespace, btInfo.id],
				html: this._fieldLayoutHtml,
				layout: btInfo.fieldLayout,
				id: btInfo.fieldLayoutId,
				blockId: btInfo.id
			})

			let blockType = new BlockType({
				namespace: btNamespace,
				settings: btSettings,
				fieldLayout: btFieldLayout
			})

			existingItems.push(blockType)
		}

		for(let gInfo of settings.groups)
		{
			let gSettings = new GroupSettings({
				namespace: gNamespace,
				sortOrder: gInfo.sortOrder,
				name: gInfo.name
			})

			let group = new Group({
				namespace: gNamespace,
				settings: gSettings
			})

			existingItems.push(group)
		}

		for(let item of existingItems.sort((a, b) => a.getSettings().getSortOrder() - b.getSettings().getSortOrder()))
		{
			this.addItem(item)
		}

		for(let blockType of this.getBlockTypes())
		{
			let btSettings = blockType.getSettings()
			let info = settings.blockTypes.find(i => i.handle === btSettings.getHandle())

			btSettings.setChildBlocks(info.childBlocks)
		}

		this.selectTab('settings')

		this.addListener(this.$blockTypeButton, 'click', '@newBlockType')
		this.addListener(this.$groupButton, 'click', '@newGroup')
		this.addListener(this.$settingsButton, 'click', () => this.selectTab('settings'))
		this.addListener(this.$fieldLayoutButton, 'click', () => this.selectTab('fieldLayout'))
	},

	addItem(item, index = -1)
	{
		const settings = item.getSettings()

		if(index >= 0 && index < this._items.length)
		{
			item.$container.insertAt(index, this.$blockTypesContainer)
		}
		else
		{
			this.$blockTypesContainer.append(item.$container)
		}

		this._itemSort.addItems(item.$container);

		if(settings) this.$settingsContainer.append(settings.$container)

		this.$mainContainer.removeClass('hidden')

		this.addListener(item.$container, 'click', '@selectItem')
		item.on('destroy.configurator', () => this.removeItem(item, (item instanceof BlockType)))

		if(item instanceof BlockType)
		{
			const fieldLayout = item.getFieldLayout()
			if(fieldLayout) this.$fieldLayoutContainer.append(fieldLayout.$container)
		}

		this._items.push(item)
		this._updateItemOrder()

		if(item instanceof BlockType)
		{
			for(let blockType of this.getBlockTypes())
			{
				const btSettings = blockType.getSettings()
				if(btSettings) btSettings.addChildBlockType(item)
			}
		}

		this.trigger('addItem', {
			item: item,
			index: index
		})
	},

	removeItem(item, showConfirm)
	{
		showConfirm = (typeof showConfirm === 'boolean' ? showConfirm : false)

		if(showConfirm)
		{
			const message = Craft.t('neo', 'Are you sure you want to delete this {type}?', {type:
				item instanceof BlockType ? 'block type' :
				item instanceof Group ? 'group' :
				'item'
			})

			if(confirm(message))
			{
				this.removeItem(item, false)
			}
		}
		else
		{
			const settings = item.getSettings()

			this._itemSort.removeItems(item.$container);

			item.$container.remove()
			if(settings) settings.$container.remove()

			if(item instanceof BlockType)
			{
				const fieldLayout = item.getFieldLayout()
				if(fieldLayout) fieldLayout.$container.remove()
			}

			this.removeListener(item.$container, 'click')
			item.off('.configurator')

			this._updateItemOrder()

			if(this._items.length === 0)
			{
				this.$mainContainer.addClass('hidden')
			}

			this.trigger('removeItem', {
				item: item
			})
		}
	},

	getItems()
	{
		return Array.from(this._items)
	},

	getItemByElement($element)
	{
		return this._items.find(item => item.$container.is($element))
	},

	getSelectedItem()
	{
		return this._items.find(item => item.isSelected())
	},

	selectItem(item, focusInput)
	{
		focusInput = (typeof focusInput === 'boolean' ? focusInput : true)

		const settings = item ? item.getSettings() : null

		for(let i of this._items)
		{
			i.toggleSelect(i === item)
		}

		if(focusInput && settings && !Garnish.isMobileBrowser())
		{
			setTimeout(() => settings.getFocusInput().focus(), 100)
		}
	},

	getBlockTypes()
	{
		return this._items.filter(item => item instanceof BlockType)
	},

	getGroups()
	{
		return this._items.filter(item => item instanceof Group)
	},

	selectTab(tab)
	{
		this.$settingsContainer.toggleClass('hidden', tab !== 'settings')
		this.$fieldLayoutContainer.toggleClass('hidden', tab !== 'fieldLayout')

		this.$settingsButton.toggleClass('is-selected', tab === 'settings')
		this.$fieldLayoutButton.toggleClass('is-selected', tab === 'fieldLayout')
	},

	_updateItemOrder()
	{
		const items = []

		this._itemSort.$items.each((index, element) =>
		{
			const item = this.getItemByElement(element)

			if(item)
			{
				const settings = item.getSettings()
				if(settings) settings.setSortOrder(index + 1)

				items.push(item)
			}
		})

		this._items = items
	},

	'@newBlockType'()
	{
		const namespace = [...this._templateNs, 'blockTypes']
		const id = BlockTypeSettings.getNewId()

		const settings = new BlockTypeSettings({
			namespace: [...namespace, id],
			sortOrder: this._items.length,
			id: id,
			childBlockTypes: this.getBlockTypes()
		})

		const fieldLayout = new BlockTypeFieldLayout({
			namespace: [...namespace, id],
			html: this._fieldLayoutHtml,
			blockId: id
		})

		const blockType = new BlockType({
			namespace: namespace,
			settings: settings,
			fieldLayout: fieldLayout
		})

		const selected = this.getSelectedItem()
		const index = selected ? selected.getSettings().getSortOrder() : -1

		this.addItem(blockType, index)
		this.selectItem(blockType)
	},

	'@newGroup'()
	{
		const namespace = [...this._templateNs, 'groups']

		const settings = new GroupSettings({
			namespace: namespace,
			sortOrder: this._items.length
		})

		const group = new Group({
			namespace: namespace,
			settings: settings
		})

		const selected = this.getSelectedItem()
		const index = selected ? selected.getSettings().getSortOrder() : -1

		this.addItem(group, index)
		this.selectItem(group)
	},

	'@selectItem'(e)
	{
		const item = this.getItemByElement(e.currentTarget)

		this.selectItem(item)
	}
})
