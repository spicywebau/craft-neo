import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import BlockSort from './BlockSort'
import BlockType from './BlockType'
import Group from './Group'
import Block from './Block'
import Buttons from './Buttons'

import renderTemplate from './templates/input.twig'
import '../twig-extensions'
import './styles/input.scss'

const _defaults = {
	name: null,
	namespace: [],
	blockTypes: [],
	groups: [],
	blocks: [],
	inputId: null,
	maxBlocks: 0,
	maxTopBlocks: 0,
	'static': false
}

export default Garnish.Base.extend({

	_templateNs: [],
	_name: null,
	_locale: null,

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._blockTypes = []
		this._groups = []
		this._blocks = []
		this._name = settings.name
		this._maxBlocks = settings.maxBlocks
		this._maxTopBlocks = settings.maxTopBlocks
		this._static = settings['static']

		NS.enter(this._templateNs)

		this.$container = $('#' + settings.inputId).append(renderTemplate({
			blockTypes: settings.blockTypes,
			'static': this._static
		}))

		NS.leave()

		for(let btInfo of settings.blockTypes)
		{
			let blockType = new BlockType(btInfo)

			this._blockTypes.push(blockType)
			this._blockTypes[blockType.getHandle()] = blockType
		}

		for(let gInfo of settings.groups)
		{
			let group = new Group(gInfo)

			this._groups.push(group)
		}

		const $form = this.$container.closest('form')
		this._locale = $form.find('input[name="siteId"]').val()

		const $neo = this.$container.find('[data-neo]')
		this.$blocksContainer = $neo.filter('[data-neo="container.blocks"]')
		this.$buttonsContainer = $neo.filter('[data-neo="container.buttons"]')

		this._buttons = new Buttons({
			blockTypes: this.getBlockTypes(true),
			groups: this.getGroups(),
			maxBlocks: this.getMaxBlocks(),
			maxTopBlocks: this.getMaxTopBlocks()
		})

		this.$buttonsContainer.append(this._buttons.$container)
		this._buttons.on('newBlock', e => this['@newBlock'](e))
		this._buttons.initUi()

		this._blockSort = new BlockSort({
			container: this.$blocksContainer,
			handle: '[data-neo-b="button.move"]',
			maxTopBlocks: this.getMaxTopBlocks(),
			filter: () =>
			{
				// Only return all the selected items if the target item is selected
				if(this._blockSort.$targetItem.hasClass('is-selected'))
				{
					return this._blockSelect.getSelectedItems()
				}

				return this._blockSort.$targetItem
			},
			collapseDraggees: true,
			magnetStrength: 4,
			helperLagBase: 1.5,
			helperOpacity: 0.9,
			onDragStop: () =>
			{
				this._updateBlockOrder()
				this._updateButtons()
			}
		})

		this._blockSelect = new Garnish.Select(this.$blocksContainer, null, {
			multi: true,
			vertical: true,
			handle: '[data-neo-b="select"]',
			checkboxMode: true,
			selectedClass: 'is-selected sel'
		});

		for(let bInfo of settings.blocks)
		{
			let blockType = this._blockTypes[bInfo.blockType]

			if(isNaN(parseInt(bInfo.id)))
			{
				bInfo.id = Block.getNewId()
			}

			bInfo.modified = !!bInfo.modified
			bInfo['static'] = this._static
			bInfo.namespace = [...this._templateNs, bInfo.id]
			bInfo.blockType = new BlockType({
				id: blockType.getId(),
				fieldLayoutId: blockType.getFieldLayoutId(),
				fieldTypes: blockType.getFieldTypes(),
				name: blockType.getName(),
				handle: blockType.getHandle(),
				maxBlocks: blockType.getMaxBlocks(),
				maxChildBlocks: blockType.getMaxChildBlocks(),
				childBlocks: blockType.getChildBlocks(),
				topLevel: blockType.getTopLevel(),
				tabs: bInfo.tabs,
			})
			bInfo.buttons = new Buttons({
				items: blockType.getChildBlockItems(this.getItems()),
				maxBlocks: this.getMaxBlocks()
			})

			let block = new Block(bInfo)
			this.addBlock(block, -1, bInfo.level|0, false)
		}

		this.addListener(this.$container, 'resize', () => this.updateResponsiveness())
	},

	updateResponsiveness()
	{
		for(let block of this._blocks)
		{
			block.updateResponsiveness()

			const buttons = block.getButtons()
			if(buttons)
			{
				buttons.updateResponsiveness()
			}
		}

		this._buttons.updateResponsiveness()

		if(this._tempButtons)
		{
			this._tempButtons.updateResponsiveness()
		}
	},

	addBlock(block, index = -1, level = 0, animate = null)
	{
		const blockCount = this._blocks.length
		index = (index >= 0 ? Math.max(0, Math.min(index, blockCount)) : blockCount)
		animate = (typeof animate === 'boolean' ? animate : true)

		const prevBlock = index > 0 ? this._blocks[index - 1] : false
		const nextBlock = index < blockCount ? this._blocks[index] : false

		if(!prevBlock)
		{
			this.$blocksContainer.prepend(block.$container)
		}
		else
		{
			const minLevel = nextBlock ? nextBlock.getLevel() : 0
			const maxLevel = prevBlock.getLevel() + (prevBlock.getBlockType().isParent() ? 1 : 0)

			level = Math.max(minLevel, Math.min(level, maxLevel))

			const prevBlockOnLevel = this._findPrevBlockOnLevel(index, level)

			if(prevBlockOnLevel)
			{
				prevBlockOnLevel.$container.after(block.$container)
			}
			else
			{
				prevBlock.$blocksContainer.prepend(block.$container)
			}
		}

		block.setLevel(level)

		this._blocks.push(block)
		this._blockSort.addBlock(block)
		this._blockSelect.addItems(block.$container)

		block.initUi()
		block.on('removeBlock.input', e =>
		{
			if(this.getSelectedBlocks().length > 1)
			{
				if(confirm(Craft.t('neo', "Are you sure you want to delete the selected blocks?")))
				{
					this._blockBatch(block, b => this.removeBlock(b))
				}
			}
			else
			{
				this.removeBlock(block)
			}
		})
		block.on('toggleEnabled.input', e => this._blockBatch(block, b => b.toggleEnabled(e.enabled)))
		block.on('toggleExpansion.input', e => this._blockBatch(block, b => b.toggleExpansion(e.expanded)))
		block.on('newBlock.input', e => this['@newBlock'](Object.assign(e, {index: this._getNextBlockIndex(block)})))
		block.on('addBlockAbove.input', e => this['@addBlockAbove'](e))
		block.on('copyBlock.input', e => this['@copyBlock'](e))
		block.on('pasteBlock.input', e => this['@pasteBlock'](e))
		block.on('duplicateBlock.input', e => this['@duplicateBlock'](e))

		this._destroyTempButtons()
		this._updateBlockOrder()
		this._updateBlockChildren()
		this._updateButtons()

		if(animate)
		{
			block.$container
				.css({
					opacity: 0,
					marginBottom: -(block.$container.outerHeight())
				})
				.velocity({
					opacity: 1,
					marginBottom: 10
				}, 'fast', e => Garnish.requestAnimationFrame(() => Garnish.scrollContainerToElement(block.$container)))
		}

		this.trigger('addBlock', {
			block: block,
			index: index
		})
	},

	removeBlock(block, animate = null, _delayAnimate = null)
	{
		animate = (typeof animate === 'boolean' ? animate : true)
		_delayAnimate = (typeof _delayAnimate === 'boolean' ? _delayAnimate : false)

		const childBlocks = this._findChildBlocks(this._blocks.indexOf(block))
		for(let childBlock of childBlocks)
		{
			this.removeBlock(childBlock, true, true)
		}

		block.off('.input')

		this._blocks = this._blocks.filter(b => b !== block)
		this._blockSort.removeItems(block.$container)
		this._blockSelect.removeItems(block.$container)

		this._destroyTempButtons()
		this._updateButtons()

		if(animate)
		{
			block.$container
				.css({
					opacity: 1,
					marginBottom: 10
				})
				.velocity({
					opacity: 0,
					marginBottom: _delayAnimate ? 10 : -(block.$container.outerHeight())
				}, 'fast', e =>
				{
					block.$container.remove()

					this._updateBlockChildren()
				})
		}
		else
		{
			block.$container.remove()

			this._updateBlockChildren()
		}

		block.destroy()

		this.trigger('removeBlock', {
			block: block
		})
	},

	getBlockByElement($block)
	{
		return this._blocks.find(block => block.$container.is($block))
	},

	getBlocks()
	{
		return Array.from(this._blocks)
	},

	getBlockTypeById(id)
	{
		return this._blockTypes.find(bt => bt.getId() == id)
	},

	getBlockTypeByHandle(handle)
	{
		return this._blockTypes.find(bt => bt.getHandle() == handle)
	},

	getBlockTypes(topLevelOnly)
	{
		topLevelOnly = (typeof topLevelOnly === 'boolean' ? topLevelOnly : false)

		return topLevelOnly ?
			this._blockTypes.filter(bt => bt.getTopLevel()) :
			Array.from(this._blockTypes)
	},

	getGroups()
	{
		return Array.from(this._groups)
	},

	getItems()
	{
		return [...this.getBlockTypes(), ...this.getGroups()].sort((a, b) => a.getSortOrder() - b.getSortOrder())
	},

	getMaxBlocks()
	{
		return this._maxBlocks
	},

	getMaxTopBlocks()
	{
		return this._maxTopBlocks
	},

	getSelectedBlocks()
	{
		const $selectedBlocks = this._blockSelect.getSelectedItems()
		return this._blocks.filter(block => block.$container.closest($selectedBlocks).length > 0)
	},

	_updateBlockOrder()
	{
		const blocks = []

		this.$blocksContainer.find('.ni_block').each((index, element) =>
		{
			const block = this.getBlockByElement(element)
			blocks.push(block)
		})

		this._blocks = blocks
	},

	_updateBlockChildren()
	{
		for(let block of this._blocks)
		{
			const children = block.$blocksContainer.children('.ni_block')
			const collapsedCount = Math.min(children.length, 8) // Any more than 8 and it's a little too big
			const collapsedChildren = []

			for(let i = 0; i < collapsedCount; i++)
			{
				collapsedChildren.push('<div class="ni_block_collapsed-children_child"></div>')
			}

			block.$collapsedChildrenContainer.html(collapsedChildren.join(''))
		}
	},

	_checkMaxChildren(block)
	{
		if(!block)
		{
			return true
		}

		const blockType = block.getBlockType()
		const maxChildren = blockType.getMaxChildBlocks()

		if(maxChildren > 0)
		{
			const children = this._findChildBlocks(block)

			return children.length < maxChildren
		}

		return true
	},

	_updateButtons()
	{
		const blocks = this.getBlocks()
		this._buttons.updateButtonStates(blocks)

		if(this._tempButtons)
		{
			this._tempButtons.updateButtonStates(blocks, this._checkMaxChildren(this._tempButtonsBlock))
		}

		for(let block of blocks)
		{
			const parentBlock = this._findParentBlock(block)
			const parentBlockType = parentBlock ? parentBlock.getBlockType() : null
			const buttons = block.getButtons()

			let allowedBlockTypes = parentBlockType ? parentBlockType.getChildBlocks() : this.getBlockTypes(true)

			if(allowedBlockTypes === true || allowedBlockTypes === '*')
			{
				allowedBlockTypes = this.getBlockTypes(false)
			}
			else if(Array.isArray(allowedBlockTypes))
			{
				allowedBlockTypes = allowedBlockTypes.map(bt => typeof bt === 'string' ? this.getBlockTypeByHandle(bt) : bt)
			}

			block.updateMenuStates(
				this._name,
				blocks,
				this.getMaxBlocks(),
				this._checkMaxChildren(parentBlock),
				allowedBlockTypes,
				block.getLevel() == 0 ? this.getMaxTopBlocks() : 0
			)

			if(buttons)
			{
				buttons.updateButtonStates(blocks, this._checkMaxChildren(block))
			}
		}
	},

	_blockBatch(block, callback)
	{
		const blocks = block.isSelected() ? this.getSelectedBlocks() : [block]

		for(let b of blocks)
		{
			callback(b)
		}
	},

	_destroyTempButtons(animate = null)
	{
		animate = (typeof animate === 'boolean' ? animate : true)

		if(this._tempButtons)
		{
			const buttons = this._tempButtons
			buttons.off('newBlock')

			if(animate)
			{
				buttons.$container
					.css({
						opacity: 1,
						marginBottom: 10
					})
					.velocity({
						opacity: 0,
						marginBottom: -(buttons.$container.outerHeight())
					}, 'fast', e => buttons.$container.remove())
			}
			else
			{
				buttons.$container.remove()
			}

			this._tempButtons = null
			this._tempButtonsBlock = null
		}
	},

	_findPrevBlockOnLevel(index, level)
	{
		if(index instanceof Block)
		{
			index = this._blocks.indexOf(index)
		}

		const blocks = this._blocks

		let block = blocks[--index]
		let lowestLevel = Number.MAX_VALUE

		while(block)
		{
			let blockLevel = block.getLevel()

			if(blockLevel < lowestLevel)
			{
				if(blockLevel === level)
				{
					return block
				}

				lowestLevel = blockLevel
			}

			block = this._blocks[--index]
		}

		return false
	},

	_findChildBlocks(index, descendants = null)
	{
		if(index instanceof Block)
		{
			index = this._blocks.indexOf(index)
		}

		descendants = (typeof descendants === 'boolean' ? descendants : false)

		const blocks = this._blocks
		const block = blocks[index]
		let childBlocks = []

		if(block)
		{
			childBlocks = block.getChildren(blocks, descendants)
		}

		return childBlocks
	},

	_findParentBlock(index)
	{
		if(index instanceof Block)
		{
			index = this._blocks.indexOf(index)
		}

		const blocks = this._blocks
		const block = blocks[index]

		if(block)
		{
			const level = block.getLevel()

			if(level > 0)
			{
				let i = index
				let currentBlock = block

				while(currentBlock && currentBlock.getLevel() >= level)
				{
					currentBlock = blocks[--i]
				}

				return currentBlock
			}
		}

		return null
	},

	_getNextBlockIndex(index)
	{
		if(index instanceof Block)
		{
			index = this._blocks.indexOf(index)
		}

		const descendants = this._findChildBlocks(index, true)
		const lastDescendant = descendants[descendants.length - 1]

		return (lastDescendant ? this._blocks.indexOf(lastDescendant) : index) + 1
	},

	_duplicate(data, block)
	{
		const $spinner = $('<div class="ni_spinner"><div class="spinner"></div></div>')

		block.$container.after($spinner)

		let spinnerComplete = false
		let spinnerCallback = function() {}

		$spinner
			.css({
				opacity: 0,
				marginBottom: -($spinner.outerHeight())
			})
			.velocity({
				opacity: 1,
				marginBottom: 10
			}, 'fast', () =>
			{
				spinnerComplete = true
				spinnerCallback()
			})

		Craft.postActionRequest('neo/input/render-blocks', data, e =>
		{
			if(e.success && e.blocks.length > 0)
			{
				const newBlocks = []

				for(let renderedBlock of e.blocks)
				{
					const newId = Block.getNewId()

					const blockType = this.getBlockTypeById(renderedBlock.type)
					const newBlockType = new BlockType({
						id: blockType.getId(),
						fieldLayoutId: blockType.getFieldLayoutId(),
						fieldTypes: blockType.getFieldTypes(),
						name: blockType.getName(),
						handle: blockType.getHandle(),
						maxBlocks: blockType.getMaxBlocks(),
						maxChildBlocks: blockType.getMaxChildBlocks(),
						childBlocks: blockType.getChildBlocks(),
						topLevel: blockType.getTopLevel(),
						tabs: renderedBlock.tabs
					})

					const newButtons = new Buttons({
						items: newBlockType.getChildBlockItems(this.getItems()),
						maxBlocks: this.getMaxBlocks()
					})

					const newBlock = new Block({
						namespace: [...this._templateNs, newId],
						blockType: newBlockType,
						id: newId,
						level: renderedBlock.level|0,
						buttons: newButtons,
						enabled: !!renderedBlock.enabled,
						collapsed: !!renderedBlock.collapsed
					})

					newBlocks.push(newBlock)
				}

				spinnerCallback = () =>
				{
					let newIndex = this._getNextBlockIndex(block)

					for(let newBlock of newBlocks)
					{
						this.addBlock(newBlock, newIndex++, newBlock.getLevel(), false)
					}

					const firstBlock = newBlocks[0]

					firstBlock.$container
						.css({
							opacity: 0,
							marginBottom: $spinner.outerHeight() - firstBlock.$container.outerHeight() + 10
						})
						.velocity({
							opacity: 1,
							marginBottom: 10
						}, 'fast', e => Garnish.requestAnimationFrame(() => Garnish.scrollContainerToElement(firstBlock.$container)))

					$spinner.remove()
				}

				if(spinnerComplete)
				{
					spinnerCallback()
				}
			}
		})
	},

	'@newBlock'(e)
	{
		const blockId = Block.getNewId()
		const block = new Block({
			namespace: [...this._templateNs, blockId],
			'static': this._static,
			blockType: e.blockType,
			id: blockId,
			buttons: new Buttons({
				items: e.blockType.getChildBlockItems(this.getItems()),
				maxBlocks: this.getMaxBlocks()
			})
		})

		this.addBlock(block, e.index, e.level)
	},

	'@addBlockAbove'(e)
	{
		this._destroyTempButtons()

		const block = e.block
		const index = this._blocks.indexOf(block)
		const parent = this._findParentBlock(index)
		const blocks = this.getBlocks()
		let buttons

		if(parent)
		{
			const parentType = parent.getBlockType()
			buttons = new Buttons({
				items: parentType.getChildBlockItems(this.getItems()),
				maxBlocks: this.getMaxBlocks(),
				blocks: blocks
			})
		}
		else
		{
			buttons = new Buttons({
				blockTypes: this.getBlockTypes(true),
				groups: this.getGroups(),
				maxBlocks: this.getMaxBlocks(),
				blocks: blocks
			})
		}

		block.$container.before(buttons.$container)

		buttons.on('newBlock', e =>
		{
			this['@newBlock']({
				blockType: e.blockType,
				index: this._blocks.indexOf(block),
				level: block.getLevel()
			})
		})

		buttons.initUi()

		if(e.animate !== false)
		{
			buttons.$container
				.css({
					opacity: 0,
					marginBottom: -(buttons.$container.outerHeight())
				})
				.velocity({
					opacity: 1,
					marginBottom: 10
				}, 'fast', e => Garnish.requestAnimationFrame(() => Garnish.scrollContainerToElement(buttons.$container)))
		}

		this._tempButtons = buttons
		this._tempButtonsBlock = this._findParentBlock(block)

		this._tempButtons.updateButtonStates(blocks, this._checkMaxChildren(this._tempButtonsBlock))
	},

	'@copyBlock'(e)
	{
		// Get the selected blocks and their descendants
		const blockGroups = []
		let blockCount = 0

		this._blockBatch(e.block, (block) =>
		{
			// To prevent block descendants from being copied multiple times, determine whether the current block has
			// been added to the most recently added group.
			const blockAdded = blockCount > 0 && blockGroups[blockGroups.length - 1].indexOf(block) !== -1

			if(!blockAdded)
			{
				const newGroup = []
				newGroup.push(block, ...this._findChildBlocks(block, true))
				blockGroups.push(newGroup)
				blockCount += newGroup.length
			}
		})

		// Collect the relevant data from those blocks to be stored for pasting
		const data = {
			field: this._name,
			blocks: []
		}

		for(let group of blockGroups)
		{
			const firstBlockLevel = group[0].getLevel()

			for(let block of group)
			{
				let blockData = {
					type: block.getBlockType().getId(),
					level: block.getLevel() - firstBlockLevel,
					content: block.getContent(),
				}

				if(block.isEnabled())
				{
					blockData.enabled = 1
				}

				if(!block.isExpanded())
				{
					blockData.collapsed = 1
				}

				data.blocks.push(blockData)
			}
		}

		localStorage.setItem('neo:copy', JSON.stringify(data))

		this._updateButtons()

		const notice = blockCount == 1 ? "1 block copied" : "{n} blocks copied"
		Craft.cp.displayNotice(Craft.t('neo', notice, { n: blockCount }))
	},

	'@pasteBlock'(e)
	{
		const block = e.block
		const baseLevel = block.getLevel()
		const copyData = localStorage.getItem('neo:copy')

		if(copyData)
		{
			const { field, blocks } = JSON.parse(copyData)

			for(let pasteBlock of blocks)
			{
				pasteBlock.level += baseLevel
			}

			NS.enter(this._templateNs)

			const data = {
				namespace: NS.toFieldName(),
				locale: this._locale,
				blocks,
			}

			NS.leave()

			this._duplicate(data, block)
		}
	},

	'@duplicateBlock'(e)
	{
		const block = e.block
		const blockIndex = this._blocks.indexOf(block)
		const subBlocks = this._findChildBlocks(blockIndex, true)

		NS.enter(this._templateNs)

		const data = {
			namespace: NS.toFieldName(),
			locale: this._locale,
			blocks: []
		}

		NS.leave()

		let blockData = {
			type: block.getBlockType().getId(),
			level: block.getLevel(),
			content: block.getContent()
		}

		if(block.isEnabled())
		{
			blockData.enabled = 1
		}

		if(!block.isExpanded())
		{
			blockData.collapsed = 1
		}

		data.blocks.push(blockData)

		for(let subBlock of subBlocks)
		{
			blockData = {
				type: subBlock.getBlockType().getId(),
				level: subBlock.getLevel(),
				content: subBlock.getContent()
			}

			if(subBlock.isEnabled())
			{
				blockData.enabled = 1
			}

			if(!subBlock.isExpanded())
			{
				blockData.collapsed = 1
			}

			data.blocks.push(blockData)
		}

		this._duplicate(data, block)
	}
})
