import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import renderTemplate from './templates/buttons.twig'
import '../twig-extensions'

const _defaults = {
	blockTypes: [],
	groups: [],
	items: null,
	maxBlocks: 0,
	maxTopBlocks: 0,
	blocks: null
}

export default Garnish.Base.extend({

	_blockTypes: [],
	_groups: [],
	_maxBlocks: 0,
	_maxTopBlocks: 0,

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		if(settings.items)
		{
			this._items = Array.from(settings.items)
			this._blockTypes = this._items.filter(i => i.getType() === 'blockType')
			this._groups = this._items.filter(i => i.getType() === 'group')
		}
		else
		{
			this._blockTypes = Array.from(settings.blockTypes)
			this._groups = Array.from(settings.groups)
			this._items = [...this._blockTypes, ...this._groups].sort((a, b) => a.getSortOrder() - b.getSortOrder())
		}

		this._maxBlocks = settings.maxBlocks|0
		this._maxTopBlocks = settings.maxTopBlocks|0

		this.$container = $(renderTemplate({
			blockTypes: this._blockTypes,
			groups: this._groups,
			items: this._items,
			maxBlocks: this._maxBlocks,
			maxTopBlocks: this._maxTopBlocks
		}))

		const $neo = this.$container.find('[data-neo-bn]')
		this.$buttonsContainer = $neo.filter('[data-neo-bn="container.buttons"]')
		this.$menuContainer = $neo.filter('[data-neo-bn="container.menu"]')
		this.$blockButtons = $neo.filter('[data-neo-bn="button.addBlock"]')
		this.$groupButtons = $neo.filter('[data-neo-bn="button.group"]')

		if(settings.blocks)
		{
			this.updateButtonStates(settings.blocks)
		}

		this.addListener(this.$blockButtons, 'activate', '@newBlock')
	},

	initUi()
	{
		Craft.initUiElements(this.$container)
		this.updateResponsiveness()
	},

	getBlockTypes()
	{
		return Array.from(this._blockTypes)
	},

	getGroups()
	{
		return Array.from(this._groups)
	},

	getMaxBlocks()
	{
		return this._maxBlocks
	},

	updateButtonStates(blocks = [], additionalCheck = null)
	{
		additionalCheck = (typeof additionalCheck === 'boolean') ? additionalCheck : true

		const that = this
		let totalTopBlocks = 0;

		for(let block of blocks)
		{
			block.getLevel() > 0 || totalTopBlocks++
		}

		const maxBlocksMet = this._maxBlocks > 0 && blocks.length >= this._maxBlocks
		const maxTopBlocksMet = this._maxTopBlocks > 0 && totalTopBlocks >= this._maxTopBlocks

		const allDisabled = maxBlocksMet || maxTopBlocksMet || !additionalCheck

		this.$blockButtons.each(function()
		{
			const $button = $(this)
			let disabled = allDisabled

			if(!disabled)
			{
				const blockType = that.getBlockTypeByButton($button)
				const blocksOfType = blocks.filter(b => b.getBlockType().getHandle() === blockType.getHandle())
				const maxBlockTypes = blockType.getMaxBlocks()

				disabled = (maxBlockTypes > 0 && blocksOfType.length >= maxBlockTypes)
			}

			$button.toggleClass('disabled', disabled)
		})

		this.$groupButtons.each(function()
		{
			const $button = $(this)
			const menu = $button.data('menubtn')
			let disabled = allDisabled

			if(!disabled && menu)
			{
				const $menuButtons = menu.menu.$options
				disabled = ($menuButtons.length === $menuButtons.filter('.disabled').length)
			}

			$button.toggleClass('disabled', disabled)
		})
	},

	updateResponsiveness()
	{
		if(!this._buttonsContainerWidth)
		{
			this._buttonsContainerWidth = this.$buttonsContainer.width()
		}

		const isMobile = (this.$container.width() < this._buttonsContainerWidth)

		this.$buttonsContainer.toggleClass('hidden', isMobile)
		this.$menuContainer.toggleClass('hidden', !isMobile)
	},

	getBlockTypeByButton($button)
	{
		const btHandle = $button.attr('data-neo-bn-info')

		return this._blockTypes.find(bt => bt.getHandle() === btHandle)
	},

	'@newBlock'(e)
	{
		const $button = $(e.currentTarget)
		const blockTypeHandle = $button.attr('data-neo-bn-info')
		const blockType = this._blockTypes.find(bt => bt.getHandle() === blockTypeHandle)

		this.trigger('newBlock', {
			blockType: blockType
		})
	}
})
