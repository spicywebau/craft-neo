import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import Settings from './Settings'

import renderTemplate from './templates/blocktype_settings.twig'
import renderCheckbox from './templates/blocktype_settings_checkbox.twig'
import '../twig-extensions'

const _defaults = {
	namespace: [],
	id: null,
	sortOrder: 0,
	name: '',
	handle: '',
	maxBlocks: 0,
	maxChildBlocks: 0,
	topLevel: true,
	childBlocks: null,
	childBlockTypes: [],
	errors: {}
}

export default Settings.extend({

	_templateNs: [],
	_childBlockTypes: [],

	$sortOrderInput: new $,
	$nameInput: new $,
	$handleInput: new $,
	$maxBlocksInput: new $,
	$maxChildBlocksInput: new $,

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._childBlockTypes = []
		this._id = settings.id
		this._errors = settings.errors

		this.setSortOrder(settings.sortOrder)
		this.setName(settings.name)
		this.setHandle(settings.handle)
		this.setMaxBlocks(settings.maxBlocks)
		this.setMaxChildBlocks(settings.maxChildBlocks)
		this.setTopLevel(settings.topLevel)

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			id: this.getId(),
			sortOrder: this.getSortOrder(),
			name: this.getName(),
			handle: this.getHandle(),
			maxBlocks: this.getMaxBlocks(),
			maxChildBlocks: this.getMaxChildBlocks(),
			topLevel: this.getTopLevel(),
			errors: this.getErrors()
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-bts]')
		this.$sortOrderInput = $neo.filter('[data-neo-bts="input.sortOrder"]')
		this.$nameInput = $neo.filter('[data-neo-bts="input.name"]')
		this.$handleInput = $neo.filter('[data-neo-bts="input.handle"]')
		this.$maxBlocksInput = $neo.filter('[data-neo-bts="input.maxBlocks"]')
		this.$maxChildBlocksInput = $neo.filter('[data-neo-bts="input.maxChildBlocks"]')
		this.$maxChildBlocksContainer = $neo.filter('[data-neo-bts="container.maxChildBlocks"]')
		this.$topLevelInput = $neo.filter('[data-neo-bts="input.topLevel"]')
		this.$topLevelContainer = $neo.filter('[data-neo-bts="container.topLevel"]')
		this.$childBlocksInput = $neo.filter('[data-neo-bts="input.childBlocks"]')
		this.$childBlocksContainer = $neo.filter('[data-neo-bts="container.childBlocks"]')
		this.$deleteButton = $neo.filter('[data-neo-bts="button.delete"]')

		Craft.initUiElements(this.$container)

		this._childBlocksSelect = this.$childBlocksInput.data('checkboxSelect')
		this._topLevelLightswitch = this.$topLevelInput.data('lightswitch')
		this._handleGenerator = new Craft.HandleGenerator(this.$nameInput, this.$handleInput)

		for(let blockType of settings.childBlockTypes)
		{
			this.addChildBlockType(blockType)
		}

		this.setChildBlocks(settings.childBlocks)

		// LightSwitch accidentally overrides the `on()` method by using `on` as a property...
		Garnish.Base.prototype.on.call(this._topLevelLightswitch, 'change', () => this.setTopLevel(this._topLevelLightswitch.on))

		this.addListener(this.$nameInput, 'keyup change', () => this.setName(this.$nameInput.val()))
		this.addListener(this.$handleInput, 'keyup change textchange', () => this.setHandle(this.$handleInput.val()))
		this.addListener(this.$maxBlocksInput, 'keyup change', () => this.setMaxBlocks(this.$maxBlocksInput.val()))
		this.addListener(this.$maxChildBlocksInput, 'keyup change', () => this.setMaxChildBlocks(this.$maxChildBlocksInput.val()))
		this.addListener(this.$deleteButton, 'click', () => this.destroy())

		this.$childBlocksInput.on('change', 'input', () => this._refreshMaxChildBlocks())
	},

	getFocusInput()
	{
		return this.$nameInput
	},

	getId()
	{
		return this._id
	},

	isNew()
	{
		return /^new/.test(this.getId())
	},

	getErrors()
	{
		return this._errors
	},

	setSortOrder(sortOrder)
	{
		this.base(sortOrder)

		this.$sortOrderInput.val(this.getSortOrder())
	},

	getName() { return this._name },
	setName(name)
	{
		if(name !== this._name)
		{
			const oldName = this._name
			this._name = name

			if(this.$nameInput.val() != this._name)
			{
				this.$nameInput.val(this._name)
			}

			this.trigger('change', {
				property: 'name',
				oldValue: oldName,
				newValue: this._name
			})
		}
	},

	getHandle() { return this._handle },
	setHandle(handle)
	{
		if(handle !== this._handle)
		{
			const oldHandle = this._handle
			this._handle = handle

			if(this.$handleInput.val() != this._handle)
			{
				this.$handleInput.val(this._handle)
			}

			this.trigger('change', {
				property: 'handle',
				oldValue: oldHandle,
				newValue: this._handle
			})
		}
	},

	getMaxBlocks() { return this._maxBlocks },
	setMaxBlocks(maxBlocks)
	{
		const oldMaxBlocks = this._maxBlocks
		const newMaxBlocks = Math.max(0, maxBlocks|0)

		if(newMaxBlocks === 0)
		{
			this.$maxBlocksInput.val(null)
		}

		if(oldMaxBlocks !== newMaxBlocks)
		{
			this._maxBlocks = newMaxBlocks

			if(this._maxBlocks > 0 && this.$maxBlocksInput.val() != this._maxBlocks)
			{
				this.$maxBlocksInput.val(this._maxBlocks)
			}

			this.trigger('change', {
				property: 'maxBlocks',
				oldValue: oldMaxBlocks,
				newValue: this._maxBlocks
			})
		}
	},

	getMaxChildBlocks() { return this._maxChildBlocks },
	setMaxChildBlocks(maxChildBlocks)
	{
		const oldMaxChildBlocks = this._maxChildBlocks
		const newMaxChildBlocks = Math.max(0, maxChildBlocks|0)

		if(newMaxChildBlocks === 0)
		{
			this.$maxChildBlocksInput.val(null)
		}

		if(oldMaxChildBlocks !== newMaxChildBlocks)
		{
			this._maxChildBlocks = newMaxChildBlocks

			if(this._maxChildBlocks > 0 && this.$maxChildBlocksInput.val() != this._maxChildBlocks)
			{
				this.$maxChildBlocksInput.val(this._maxChildBlocks)
			}

			this.trigger('change', {
				property: 'maxChildBlocks',
				oldValue: oldMaxChildBlocks,
				newValue: this._maxChildBlocks
			})
		}
	},

	getTopLevel() { return this._topLevel },
	setTopLevel(topLevel)
	{
		const oldTopLevel = this._topLevel
		const newTopLevel = !!topLevel

		if(oldTopLevel !== newTopLevel)
		{
			this._topLevel = newTopLevel

			if(this._topLevelLightswitch && this._topLevelLightswitch.on !== this._topLevel)
			{
				this._topLevelLightswitch.on = this._topLevel
				this._topLevelLightswitch.toggle()
			}

			this.trigger('change', {
				property: 'topLevel',
				oldValue: oldTopLevel,
				newValue: this._topLevel
			})
		}
	},

	getChildBlocks()
	{
		const select = this._childBlocksSelect
		const childBlocks = []

		if(select.$all.prop('checked'))
		{
			return true
		}

		select.$options.each(function(index)
		{
			const $option = $(this)

			if($option.prop('checked'))
			{
				childBlocks.push($option.val())
			}
		})

		return childBlocks.length > 0 ? childBlocks : false
	},

	setChildBlocks(childBlocks)
	{
		const select = this._childBlocksSelect

		if(childBlocks === true || childBlocks === '*')
		{
			select.$all.prop('checked', true)
			select.onAllChange()
		}
		else if(Array.isArray(childBlocks))
		{
			select.$all.prop('checked', false)

			for(let handle of childBlocks)
			{
				select.$options.filter(`[value="${handle}"]`).prop('checked', true)
			}
		}
		else
		{
			select.$all.prop('checked', false)
			select.$options.prop('checked', false)
		}

		this._refreshMaxChildBlocks(false)
	},

	addChildBlockType(blockType)
	{
		if(!this._childBlockTypes.includes(blockType))
		{
			NS.enter(this._templateNs)

			const settings = blockType.getSettings()
			const $checkbox = $(renderCheckbox({
				id: 'childBlock-' + settings.getId(),
				name: 'childBlocks',
				value: settings.getHandle(),
				label: settings.getName()
			}))

			NS.leave()
			
			this._childBlockTypes.push(blockType)
			this.$childBlocksContainer.append($checkbox)

			this._refreshChildBlocks()

			const select = this._childBlocksSelect
			const allChecked = select.$all.prop('checked')
			select.$options = select.$options.add($checkbox.find('input'))
			if(allChecked) select.onAllChange()

			const eventNs = '.childBlock' + this.getId()
			settings.on('change' + eventNs, e => this['@onChildBlockTypeChange'](e, blockType, $checkbox))
			settings.on('destroy' + eventNs, e => this.removeChildBlockType(blockType))
		}
	},

	removeChildBlockType(blockType)
	{
		const index = this._childBlockTypes.indexOf(blockType)
		if(index >= 0)
		{
			this._childBlockTypes.splice(index, 1)

			const settings = blockType.getSettings()
			const $checkbox = this.$childBlocksContainer.children().eq(index)

			$checkbox.remove()

			const select = this._childBlocksSelect
			select.$options = select.$options.remove($checkbox.find('input'))

			const eventNs = '.childBlock' + this.getId()
			settings.off(eventNs)

			this._refreshChildBlocks()
		}
	},

	_refreshChildBlocks()
	{
		const blockTypes = Array.from(this._childBlockTypes)
		const $options = this.$childBlocksContainer.children()

		const getOption = blockType => $options.get(blockTypes.indexOf(blockType))

		this._childBlockTypes = this._childBlockTypes.sort((a, b) => a.getSettings().getSortOrder() - b.getSettings().getSortOrder())
		$options.remove()

		for(let blockType of this._childBlockTypes)
		{
			let $option = getOption(blockType)
			this.$childBlocksContainer.append($option)
		}
	},

	_refreshMaxChildBlocks(animate)
	{
		animate = (typeof animate === 'boolean') ? animate : true

		const showSetting = !!this.getChildBlocks()

		if(animate)
		{
			if(showSetting)
			{
				if(this.$maxChildBlocksContainer.hasClass('hidden'))
				{
					this.$maxChildBlocksContainer
						.removeClass('hidden')
						.css({
							opacity: 0,
							marginBottom: -(this.$maxChildBlocksContainer.outerHeight())
						})
						.velocity({
							opacity: 1,
							marginBottom: 24
						}, 'fast')
				}
			}
			else
			{
				if(!this.$maxChildBlocksContainer.hasClass('hidden'))
				{
					this.$maxChildBlocksContainer
						.css({
							opacity: 1,
							marginBottom: 24
						})
						.velocity({
							opacity: 0,
							marginBottom: -(this.$maxChildBlocksContainer.outerHeight())
						}, 'fast', () =>
						{
							this.$maxChildBlocksContainer.addClass('hidden')
						})
				}
			}
		}
		else
		{
			this.$maxChildBlocksContainer
				.toggleClass('hidden', !showSetting)
				.css('margin-bottom', showSetting ? 24 : '')
		}
	},

	'@onChildBlockTypeChange'(e, blockType, $checkbox)
	{
		const $neo = $checkbox.find('[data-neo-btsc]')
		const $input = $neo.filter('[data-neo-btsc="input"]')
		const $labelText = $neo.filter('[data-neo-btsc="text.label"]')

		switch(e.property)
		{
			case 'name':
				$labelText.text(e.newValue)
				break

			case 'handle':
				$input.val(e.newValue)
				break

			case 'sortOrder':
				this._refreshChildBlocks()
				break
		}
	}
},
{
	_totalNewBlockTypes: 0,

	getNewId()
	{
		return `new${this._totalNewBlockTypes++}`
	}
})
