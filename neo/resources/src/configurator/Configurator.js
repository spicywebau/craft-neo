import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import BlockType from './BlockType'
import BlockTypeSettings from './BlockTypeSettings'
import BlockTypeFieldLayout from './BlockTypeFieldLayout'

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

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		const inputIdPrefix = Craft.formatInputId(settings.namespace)
		const $field = $(`\#${inputIdPrefix}-neo-configurator`)
		const $input = $field.children('.field').children('.input')

		this._templateNs = NS.parse(settings.namespace)
		this._blockTypes = []

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

		this._blockTypeSort = new Garnish.DragSort(null, {
			container: this.$blockTypeItemsContainer,
			handle: '[data-neo-bt="button.move"]',
			axis: 'y',
			onSortChange: () => this._updateBlockTypeOrder()
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

		if(index >= 0 && index < this._blockTypes.length)
		{
			this._blockTypes.splice(index, 0, blockType)
			blockType.$container.insertAt(index, this.$blockTypesContainer)
		}
		else
		{
			this._blockTypes.push(blockType)
			this.$blockTypesContainer.append(blockType.$container)
		}

		this._blockTypeSort.addItems(blockType.$container);

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

		this._updateBlockTypeOrder()

		this.trigger('addBlockType', {
			blockType: blockType,
			index: index
		})
	},

	removeBlockType(blockType)
	{
		const settings = blockType.getSettings()
		const fieldLayout = blockType.getFieldLayout()

		this._blockTypes = this._blockTypes.filter(b => b !== blockType)

		this._blockTypeSort.removeItems(blockType.$container);

		blockType.$container.remove()
		if(settings) settings.$container.remove()
		if(fieldLayout) fieldLayout.$container.remove()

		if(this._blockTypes.length === 0)
		{
			this.$mainContainer.addClass('hidden')
		}

		this.removeListener(blockType.$container, 'click')
		blockType.off('delete.configurator')

		this._updateBlockTypeOrder()

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

	selectBlockType(blockType, focusFirstInput = true)
	{
		const settings = blockType.getSettings()

		for(let bt of this._blockTypes)
		{
			bt.toggleSelect(bt === blockType)
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

	_updateBlockTypeOrder()
	{
		const blockTypes = []

		this._blockTypeSort.$items.each((index, element) =>
		{
			const blockType = this.getBlockTypeByElement(element)
			const settings = blockType.getSettings()

			if(settings) settings.setSortOrder(index + 1)

			blockTypes.push(blockType)
		})

		this._blockTypes = blockTypes
	},

	'@newBlockType'()
	{
		const namespace = [...this._templateNs, 'blockTypes']
		const id = BlockTypeSettings.getNewId()

		const settings = new BlockTypeSettings({
			namespace: [...namespace, id],
			sortOrder: this._blockTypes.length,
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
		// TODO
	},

	'@selectBlockType'(e)
	{
		const blockType = this.getBlockTypeByElement(e.currentTarget)

		this.selectBlockType(blockType)
	}
})
