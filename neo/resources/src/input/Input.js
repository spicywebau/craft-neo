import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import BlockType from './BlockType'
import Block from './Block'
import Buttons from './Buttons'

import renderTemplate from './templates/input.twig'
import '../twig-extensions'
import './styles/input.scss'

const _defaults = {
	namespace: [],
	blockTypes: [],
	blocks: [],
	inputId: null,
	maxBlocks: 0
}

export default Garnish.Base.extend({

	_templateNs: [],
	_blockTypes: [],
	_blocks: [],

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._blockTypes = []
		this._blocks = []
		this._maxBlocks = settings.maxBlocks

		NS.enter(this._templateNs)

		this.$container = $('#' + settings.inputId).append(renderTemplate({
			blockTypes: settings.blockTypes
		}))

		NS.leave()

		for(let btInfo of settings.blockTypes)
		{
			let blockType = new BlockType(btInfo)

			this._blockTypes.push(blockType)
			this._blockTypes[blockType.getHandle()] = blockType
		}

		const $neo = this.$container.find('[data-neo]')
		this.$blocksContainer = $neo.filter('[data-neo="container.blocks"]')
		this.$buttonsContainer = $neo.filter('[data-neo="container.buttons"]')

		this._buttons = new Buttons({
			blockTypes: this.getBlockTypes(),
			maxBlocks: this.getMaxBlocks()
		})

		this.$buttonsContainer.append(this._buttons.$container)
		this._buttons.on('newBlock', e => this['@newBlock'](e))

		this._blockSort = new Garnish.DragSort(null, {
			container: this.$blocksContainer,
			handle: '[data-neo-b="button.move"]',
			axis: 'y',
			filter: () =>
			{
				// Only return all the selected items if the target item is selected
				if(this._blockSort.$targetItem.hasClass('is-selected'))
				{
					return this._blockSelect.getSelectedItems()
				}
				else
				{
					return this._blockSort.$targetItem
				}
			},
			collapseDraggees: true,
			magnetStrength: 4,
			helperLagBase: 1.5,
			helperOpacity: 0.9,
			onSortChange: () => this._updateBlockOrder()
		})

		this._blockSelect = new Garnish.Select(this.$blocksContainer, null, {
			multi: true,
			vertical: true,
			handle: '[data-neo-b="select"], [data-neo-b="button.toggler"]',
			checkboxMode: true,
			selectedClass: 'is-selected sel'
		});

		for(let bInfo of settings.blocks)
		{
			let blockType = this._blockTypes[bInfo.blockType]

			bInfo.namespace = [...this._templateNs, bInfo.id]
			bInfo.blockType = new BlockType({
				name:      blockType.getName(),
				handle:    blockType.getHandle(),
				maxBlocks: blockType.getMaxBlocks(),
				tabs:      bInfo.tabs
			})

			let block = new Block(bInfo)
			this.addBlock(block)
		}
	},

	addBlock(block, index = -1)
	{
		if(index >= 0 && index < this._blocks.length)
		{
			this._blocks[index].$container.before(block.$container)
			this._blocks.splice(index, 0, block)
		}
		else
		{
			this.$blocksContainer.append(block.$container)
			this._blocks.push(block)
		}

		this._blockSort.addItems(block.$container)
		this._blockSelect.addItems(block.$container)

		block.initUi()
		block.on('destroy.input',         e => this._blockBatch(block, b => this.removeBlock(b)))
		block.on('toggleEnabled.input',   e => this._blockBatch(block, b => b.toggleEnabled(e.enabled)))
		block.on('toggleExpansion.input', e => this._blockBatch(block, b => b.toggleExpansion(e.expanded)))
		block.on('addBlockAbove.input',   e => this['@addBlockAbove'](e))

		this._updateButtons()
		this._updateBlockOrder()
	},

	removeBlock(block)
	{
		block.$container.remove()
		block.off('.input')

		this._blocks = this._blocks.filter(b => b !== block)
		this._blockSort.removeItems(block.$container)
		this._blockSelect.removeItems(block.$container)

		this._updateButtons()
	},

	getBlockByElement($block)
	{
		return this._blocks.find(block => block.$container.is($block))
	},

	getBlocks()
	{
		return Array.from(this._blocks)
	},

	getBlockTypes()
	{
		return Array.from(this._blockTypes)
	},

	getMaxBlocks()
	{
		return this._maxBlocks
	},

	getSelectedBlocks()
	{
		const $selectedBlocks = this._blockSelect.getSelectedItems()

		return this._blocks.filter(block => block.$container.is($selectedBlocks))
	},

	_updateBlockOrder()
	{
		const blocks = []

		this._blockSort.$items.each((index, element) =>
		{
			const block = this.getBlockByElement(element)

			blocks.push(block)
		})

		this._blocks = blocks
	},

	_updateButtons()
	{
		const blocks = this.getBlocks()

		this._buttons.update(blocks)
	},

	_blockBatch(block, callback)
	{
		const blocks = block.isSelected() ? this.getSelectedBlocks() : [block]

		for(let b of blocks)
		{
			callback(b)
		}
	},

	'@newBlock'(e)
	{
		const blockId = Block.getNewId()
		const block = new Block({
			namespace: [...this._templateNs, blockId],
			blockType: e.blockType,
			id: blockId
		})

		this.addBlock(block, e.index)
	},

	'@addBlockAbove'(e)
	{
		const block = e.block
		const buttons = new Buttons({
			blockTypes: this.getBlockTypes(),
			maxBlocks: this.getMaxBlocks()
		})

		block.$container.before(buttons.$container)
		buttons.on('newBlock', e =>
		{
			this['@newBlock']({
				blockType: e.blockType,
				index: this._blocks.indexOf(block)
			})

			buttons.off('newBlock')
			buttons.$container.remove()
		})
	}
})
