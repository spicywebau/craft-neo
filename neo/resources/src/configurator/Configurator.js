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
	blockTypes: []
}

export default Garnish.Base.extend({

	_templateNs: [],
	_blockTypes: [],
	_groups: [],

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		const inputIdPrefix = Craft.formatInputId(settings.namespace)
		const $field = $(`\#${inputIdPrefix}-neo-configurator`)
		const $input = $field.children('.field').children('.input')

		this._templateNs = NS.parse(settings.namespace)
		this._blockTypes = []
		this._groups = []

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

		// Add the existing block types
		for(let btInfo of settings.blockTypes)
		{
			let btNamespace = [...this._templateNs, 'blockTypes']

			let btSettings = new BlockTypeSettings({
				namespace: [...btNamespace, btInfo.id],
				sortOrder: btInfo.sortOrder,
				id: btInfo.id,
				name: btInfo.name,
				handle: btInfo.handle,
				maxBlocks: btInfo.maxBlocks,
				errors: btInfo.errors
			})

			let btFieldLayout = new BlockTypeFieldLayout({
				namespace: [...btNamespace, btInfo.id],
				layout: btInfo.fieldLayout
			})

			let blockType = new BlockType({
				namespace: btNamespace,
				settings: btSettings,
				fieldLayout: btFieldLayout
			})

			this.addBlockType(blockType, btSettings.getSortOrder() - 1)
		}

		this.selectTab('settings')

		this.addListener(this.$blockTypeButton, 'click', '@newBlockType')
		this.addListener(this.$groupButton, 'click', '@newGroup')
		this.addListener(this.$settingsButton, 'click', () => this.selectTab('settings'))
		this.addListener(this.$fieldLayoutButton, 'click', () => this.selectTab('fieldLayout'))
	},

	addBlockType(blockType, index = -1)
	{
		const settings = blockType.getSettings()
		const fieldLayout = blockType.getFieldLayout()

		if(index >= 0 && index < this._getItemCount())
		{
			blockType.$container.insertAt(index, this.$blockTypesContainer)
		}
		else
		{
			this.$blockTypesContainer.append(blockType.$container)
		}

		this._itemSort.addItems(blockType.$container);

		if(settings) this.$settingsContainer.append(settings.$container)
		if(fieldLayout) this.$fieldLayoutContainer.append(fieldLayout.$container)

		this.$mainContainer.removeClass('hidden')

		this.addListener(blockType.$container, 'click', '@selectBlockType')
		blockType.on('delete.configurator', () =>
		{
			if(confirm(Craft.t('Are you sure you want to delete this block type?')))
			{
				this.removeBlockType(blockType)
			}
		})

		this._blockTypes.push(blockType)
		this._updateItemOrder()

		this.trigger('addBlockType', {
			blockType: blockType,
			index: index
		})
	},

	removeBlockType(blockType)
	{
		const settings = blockType.getSettings()
		const fieldLayout = blockType.getFieldLayout()

		this._itemSort.removeItems(blockType.$container);

		blockType.$container.remove()
		if(settings) settings.$container.remove()
		if(fieldLayout) fieldLayout.$container.remove()

		this.removeListener(blockType.$container, 'click')
		blockType.off('delete.configurator')

		this._updateItemOrder()

		if(this._getItemCount() === 0)
		{
			this.$mainContainer.addClass('hidden')
		}

		this.trigger('removeBlockType', {
			blockType: blockType
		})
	},

	getBlockTypes()
	{
		return Array.from(this._blockTypes)
	},

	getBlockTypeByElement($element)
	{
		return this._blockTypes.find(blockType =>
		{
			return blockType.$container.is($element)
		})
	},

	selectBlockType(blockType, focusFirstInput)
	{
		focusFirstInput = typeof focusFirstInput === 'boolean' ? focusFirstInput : true

		const settings = blockType ? blockType.getSettings() : null

		for(let bt of this._blockTypes)
		{
			bt.toggleSelect(bt === blockType)
		}

		if(blockType)
		{
			this.selectGroup(null)
		}

		if(focusFirstInput && settings && !Garnish.isMobileBrowser())
		{
			setTimeout(() => settings.$nameInput.focus(), 100)
		}
	},

	addGroup(group, index = -1)
	{
		const settings = group.getSettings()

		if(index >= 0 && index < this._getItemCount())
		{
			group.$container.insertAt(index, this.$blockTypesContainer)
		}
		else
		{
			this.$blockTypesContainer.append(group.$container)
		}

		this._itemSort.addItems(group.$container);

		if(settings) this.$settingsContainer.append(settings.$container)

		this.$mainContainer.removeClass('hidden')

		this.addListener(group.$container, 'click', '@selectGroup')
		group.on('delete.configurator', () => this.removeGroup(group))

		this._groups.push(group)
		this._updateItemOrder()

		this.trigger('addGroup', {
			group: group,
			index: index
		})
	},

	removeGroup(group)
	{
		const settings = group.getSettings()

		this._itemSort.removeItems(group.$container);

		group.$container.remove()
		if(settings) settings.$container.remove()

		this.removeListener(group.$container, 'click')
		group.off('delete.configurator')

		this._updateItemOrder()

		if(this._getItemCount() === 0)
		{
			this.$mainContainer.addClass('hidden')
		}

		this.trigger('removeGroup', {
			group: group
		})
	},

	getGroups()
	{
		return Array.from(this._groups)
	},

	getGroupByElement($element)
	{
		return this._groups.find(group =>
		{
			return group.$container.is($element)
		})
	},

	selectGroup(group, focusFirstInput)
	{
		focusFirstInput = typeof focusFirstInput === 'boolean' ? focusFirstInput : true

		const settings = group ? group.getSettings() : null

		for(let g of this._groups)
		{
			g.toggleSelect(g === group)
		}

		if(group)
		{
			this.selectBlockType(null)
		}

		if(focusFirstInput && settings && !Garnish.isMobileBrowser())
		{
			setTimeout(() => settings.$nameInput.focus(), 100)
		}
	},

	selectTab(tab)
	{
		this.$settingsContainer.toggleClass('hidden', tab !== 'settings')
		this.$fieldLayoutContainer.toggleClass('hidden', tab !== 'fieldLayout')

		this.$settingsButton.toggleClass('is-selected', tab === 'settings')
		this.$fieldLayoutButton.toggleClass('is-selected', tab === 'fieldLayout')
	},

	_getItemCount()
	{
		return this._blockTypes.length + this._groups.length
	},

	_updateItemOrder()
	{
		const blockTypes = []
		const groups = []

		this._itemSort.$items.each((index, element) =>
		{
			const blockType = this.getBlockTypeByElement(element)
			const group = this.getGroupByElement(element)

			if(blockType)
			{
				const settings = blockType.getSettings()
				if(settings) settings.setSortOrder(index + 1)

				blockTypes.push(blockType)
			}
			else if(group)
			{
				const settings = group.getSettings()
				if(settings) settings.setSortOrder(index + 1)

				groups.push(group)
			}
		})

		this._blockTypes = blockTypes
		this._groups = groups
	},

	'@newBlockType'()
	{
		const namespace = [...this._templateNs, 'blockTypes']
		const id = BlockTypeSettings.getNewId()

		const settings = new BlockTypeSettings({
			namespace: [...namespace, id],
			sortOrder: this._getItemCount(),
			id: id
		})

		const fieldLayout = new BlockTypeFieldLayout({
			namespace: [...namespace, id]
		})

		const blockType = new BlockType({
			namespace: namespace,
			settings: settings,
			fieldLayout: fieldLayout
		})

		this.addBlockType(blockType)
		this.selectBlockType(blockType)
	},

	'@newGroup'()
	{
		const namespace = [...this._templateNs, 'groups']

		const settings = new GroupSettings({
			namespace: [...namespace, ''],
			sortOrder: this._getItemCount()
		})

		const group = new Group({
			namespace: namespace,
			settings: settings
		})

		this.addGroup(group)
		this.selectGroup(group)
	},

	'@selectBlockType'(e)
	{
		const blockType = this.getBlockTypeByElement(e.currentTarget)

		this.selectBlockType(blockType)
	},

	'@selectGroup'(e)
	{
		const group = this.getGroupByElement(e.currentTarget)

		this.selectGroup(group)
	}
})
