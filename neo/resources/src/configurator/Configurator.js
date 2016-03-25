import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import BlockType from './BlockType'

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

		this._templateNs = NS.parse(settings.namespace)

		const inputIdPrefix = Craft.formatInputId(settings.namespace)
		this.$field = $(`\#${inputIdPrefix}-neo-configurator`)
		this.$inputContainer = this.$field.children('.field').children('.input')
		this.$inputContainer.html(renderTemplate())

		this.$container = this.$field.find('.input').first()

		this.$blockTypesContainer = this.$container.children('.block-types').children()
		this.$fieldLayoutContainer = this.$container.children('.field-layout').children()

		const $blockTypeItemsContainer = this.$blockTypesContainer.children('.items')
		this.$itemsContainer = $blockTypeItemsContainer.children('.blocktypes')
		this.$addItemButton = $blockTypeItemsContainer.children('.btn.add')

		this.$fieldsContainer = this.$fieldLayoutContainer.children('.items')

		this._blockTypeSort = new Garnish.DragSort(null, {
			handle: '[data-neo="button.move"]',
			axis: 'y',
			magnetStrength: 4,
			helperLagBase: 1.5,
			onSortChange: () => this._updateBlockTypeOrder()
		})

		// Add the existing block types
		for(let blockTypeInfo of settings.blockTypes)
		{
			let blockType = new BlockType({
				namespace: [...this._templateNs, 'blockTypes'],
				name: blockTypeInfo.name,
				handle: blockTypeInfo.handle,
				id: blockTypeInfo.id,
				errors: blockTypeInfo.errors,
				fieldLayout: blockTypeInfo.fieldLayout
			})

			this.addBlockType(blockType)
		}

		this._setContainerHeight()

		this.addListener(this.$blockTypesContainer, 'resize', '@setContainerHeight')
		this.addListener(this.$fieldLayoutContainer, 'resize', '@setContainerHeight')
		this.addListener(this.$addItemButton, 'click', '@newBlockType')
	},

	addBlockType(blockType, index = -1)
	{
		if(index >= 0 && index < this._blockTypes.length)
		{
			this._blockTypes = this._blockTypes.splice(index, 0, blockType)
			blockType.$container.insertAt(index, this.$itemsContainer)
		}
		else
		{
			this._blockTypes.push(blockType)
			this.$itemsContainer.append(blockType.$container)
		}

		this._blockTypeSort.addItems(blockType.$container);

		this.$fieldsContainer.append(blockType.getFieldLayout().$container)
		this.$fieldLayoutContainer.removeClass('hidden')

		this.addListener(blockType.$container, 'click', '@selectBlockType')

		this.trigger('addBlockType', {
			blockType: blockType,
			index: index
		})

		this._setContainerHeight()
	},

	removeBlockType(blockType)
	{
		this._blockTypes = this._blockTypes.filter(b => b !== blockType)

		this._blockTypeSort.removeItems(blockType.$container);

		blockType.$container.remove()
		blockType.getFieldLayout().$container.remove()

		if(this._blockTypes.length === 0)
		{
			this.$fieldLayoutContainer.addClass('hidden')
		}

		this.removeListener(blockType.$container, 'click')

		this.trigger('removeBlockType', {
			blockType: blockType
		})

		this._setContainerHeight()
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

	selectBlockType(blockType)
	{
		for(let bt of this._blockTypes)
		{
			bt.toggleSelect(bt === blockType)
		}
	},

	_updateBlockTypeOrder()
	{
		const blockTypes = []

		this._blockTypeSort.$items.each((index, element) =>
		{
			const blockType = this.getBlockTypeByElement(element)
			blockTypes.push(blockType)
		})

		this._blockTypes = blockTypes
	},

	_setContainerHeight()
	{
		const maxColHeight = Math.max(400,
			this.$blockTypesContainer.height(),
			this.$fieldLayoutContainer.height())

		this.$container.height(maxColHeight)
	},

	'@newBlockType'()
	{
		const blockType = new BlockType({
			namespace: [...this._templateNs, 'blockTypes']
		})

		const settingsModal = blockType.getSettingsModal()

		blockType.on('delete', e => this.removeBlockType(blockType))

		const onSave = (e) =>
		{
			this.addBlockType(blockType)
			this.selectBlockType(blockType)

			settingsModal.off('save', onSave)
		}

		const onFadeOut = (e) =>
		{
			settingsModal.enableDeleteButton()
			settingsModal.off('fadeOut', onFadeOut)
		}

		settingsModal.on('save', onSave)
		settingsModal.on('fadeOut', onFadeOut)

		settingsModal.show()
	},

	'@setContainerHeight'()
	{
		setTimeout(() => this._setContainerHeight(), 1)
	},

	'@selectBlockType'(e)
	{
		const blockType = this.getBlockTypeByElement(e.currentTarget)

		this.selectBlockType(blockType)
	}
})
