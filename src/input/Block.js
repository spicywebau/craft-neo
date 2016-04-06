import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import Buttons from './Buttons'

import ReasonsRenderer from '../plugins/reasons/Renderer'

import renderTemplate from './templates/block.twig'
import '../twig-extensions'

const _defaults = {
	namespace: [],
	blockType: null,
	id: null,
	level: 0,
	buttons: null,
	enabled: true,
	collapsed: false
}

export default Garnish.Base.extend({

	_templateNs: [],
	_blockType: null,
	_initialised: false,
	_expanded: true,
	_enabled: true,

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._blockType = settings.blockType
		this._id = settings.id
		this._buttons = settings.buttons

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			type: this._blockType,
			id: this._id,
			enabled: !!settings.enabled,
			collapsed: !!settings.collapsed,
			level: settings.level
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-b]')
		this.$contentContainer = $neo.filter('[data-neo-b="container.content"]')
		this.$childrenContainer = $neo.filter('[data-neo-b="container.children"]')
		this.$blocksContainer = $neo.filter('[data-neo-b="container.blocks"]')
		this.$buttonsContainer = $neo.filter('[data-neo-b="container.buttons"]')
		this.$tabContainer = $neo.filter('[data-neo-b="container.tab"]')
		this.$menuContainer = $neo.filter('[data-neo-b="container.menu"]')
		this.$tabButton = $neo.filter('[data-neo-b="button.tab"]')
		this.$settingsButton = $neo.filter('[data-neo-b="button.actions"]')
		this.$togglerButton = $neo.filter('[data-neo-b="button.toggler"]')
		this.$enabledInput = $neo.filter('[data-neo-b="input.enabled"]')
		this.$collapsedInput = $neo.filter('[data-neo-b="input.collapsed"]')
		this.$levelInput = $neo.filter('[data-neo-b="input.level"]')
		this.$status = $neo.filter('[data-neo-b="status"]')

		if(this._buttons)
		{
			this._buttons.on('newBlock', e => this.trigger('newBlock', Object.assign(e, {level: this.getLevel() + 1})))
			this.$buttonsContainer.append(this._buttons.$container)
		}

		this.setLevel(settings.level)
		this.toggleEnabled(settings.enabled)
		this.toggleExpansion(!settings.collapsed)

		this.addListener(this.$togglerButton, 'dblclick', '@doubleClickTitle')
		this.addListener(this.$tabButton, 'click', '@setTab')
	},

	initUi()
	{
		if(!this._initialised)
		{
			const tabs = this._blockType.getTabs()

			let footList = tabs.map(tab => tab.getFootHtml(this._id))
			this.$foot = $(footList.join(''))

			Garnish.$bod.append(this.$foot)
			Craft.initUiElements(this.$contentContainer)

			this._settingsMenu = new Garnish.MenuBtn(this.$settingsButton);
			this._settingsMenu.on('optionSelect', e => this['@settingSelect'](e))

			this._initialised = true

			if(this._buttons)
			{
				this._buttons.initUi()
			}

			this._initReasonsPlugin()

			this.trigger('initUi')
		}
	},

	destroy()
	{
		if(this._initialised)
		{
			this.$foot.remove()

			this._destroyReasonsPlugin()

			this.trigger('destroy')
		}
	},

	getBlockType()
	{
		return this._blockType
	},

	getId()
	{
		return this._id
	},

	getLevel()
	{
		return this._level
	},

	setLevel(level)
	{
		this._level = level|0

		this.$levelInput.val(this._level)
		this.$container.toggleClass('is-level-odd', !!(this._level % 2))
		this.$container.toggleClass('is-level-even', !(this._level % 2))
	},

	getButtons()
	{
		return this._buttons
	},

	isNew()
	{
		return /^new/.test(this.getId())
	},

	isSelected()
	{
		return this.$container.hasClass('is-selected')
	},

	collapse(save = true)
	{
		this.toggleExpansion(false, save)
	},

	expand(save = true)
	{
		this.toggleExpansion(true, save)
	},

	toggleExpansion(expand = !this._expanded, save = true)
	{
		if(expand !== this._expanded)
		{
			this._expanded = expand

			const expandContainer = this.$menuContainer.find('[data-action="expand"]').parent()
			const collapseContainer = this.$menuContainer.find('[data-action="collapse"]').parent()

			this.$container
				.toggleClass('is-expanded', this._expanded)
				.toggleClass('is-contracted', !this._expanded)

			expandContainer.toggleClass('hidden', this._expanded)
			collapseContainer.toggleClass('hidden', !this._expanded)

			this.$collapsedInput.val(this._expanded ? 0 : 1)

			if(save)
			{
				this.saveExpansion()
			}

			this.trigger('toggleExpansion', {
				expanded: this._expanded
			})
		}
	},

	isExpanded()
	{
		return this._expanded
	},

	saveExpansion()
	{
		if(!this.isNew())
		{
			Craft.queueActionRequest('neo/saveExpansion', {
				expanded: this.isExpanded(),
				blockId: this.getId()
			})
		}
	},

	disable()
	{
		this.toggleEnabled(false)
	},

	enable()
	{
		this.toggleEnabled(true)
	},

	toggleEnabled(enable = !this._enabled)
	{
		if(enable !== this._enabled)
		{
			this._enabled = enable

			const enableContainer = this.$menuContainer.find('[data-action="enable"]').parent()
			const disableContainer = this.$menuContainer.find('[data-action="disable"]').parent()

			this.$container
				.toggleClass('is-enabled', this._enabled)
				.toggleClass('is-disabled', !this._enabled)

			this.$status.toggleClass('hidden', this._enabled)

			enableContainer.toggleClass('hidden', this._enabled)
			disableContainer.toggleClass('hidden', !this._enabled)

			this.$enabledInput.val(this._enabled ? 1 : 0)

			this.trigger('toggleEnabled', {
				enabled: this._enabled
			})
		}
	},

	isEnabled()
	{
		return this._enabled
	},

	selectTab(name)
	{
		const $tabs = $()
			.add(this.$tabButton)
			.add(this.$tabContainer)

		$tabs.removeClass('is-selected')

		const $tab = $tabs.filter(`[data-neo-b-info="${name}"]`).addClass('is-selected')

		this.trigger('selectTab', {
			tabName: name,
			$tabButton: $tab.filter('[data-neo-b="button.tab"]'),
			$tabContainer: $tab.filter('[data-neo-b="container.tab"]')
		})
	},

	_initReasonsPlugin()
	{
		const Reasons = Craft.ReasonsPlugin

		if(Reasons)
		{
			const Renderer = ReasonsRenderer(Reasons.ConditionalsRenderer)

			const type = this.getBlockType()
			const typeId = type.getId()
			const conditionals = Reasons.Neo.conditionals[typeId]

			this._reasons = new Renderer(this.$contentContainer, conditionals)
		}
	},

	_destroyReasonsPlugin()
	{
		if(this._reasons)
		{
			this._reasons.destroy()
		}
	},

	'@settingSelect'(e)
	{
		const $option = $(e.option)

		switch($option.attr('data-action'))
		{
			case 'collapse': this.collapse() ; break
			case 'expand':   this.expand()   ; break
			case 'disable':  this.disable()
			                 this.collapse() ; break
			case 'enable':   this.enable()   ; break
			case 'delete':   this.destroy()  ; break

			case 'add':
				this.trigger('addBlockAbove', {
					block: this
				})
				break
		}
	},

	'@doubleClickTitle'(e)
	{
		e.preventDefault()

		this.toggleExpansion()
	},

	'@setTab'(e)
	{
		e.preventDefault()

		const $tab = $(e.currentTarget)
		const tabName = $tab.attr('data-neo-b-info')

		this.selectTab(tabName)
	}
},
{
	_totalNewBlocks: 0,

	getNewId()
	{
		return `new${this._totalNewBlocks++}`
	}
})
