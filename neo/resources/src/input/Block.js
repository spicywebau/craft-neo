import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import renderTemplate from './templates/block.twig'
import '../twig-extensions'

const _defaults = {
	namespace: [],
	blockType: null,
	id: null
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

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			type: this._blockType
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-b]')
		this.$contentContainer = $neo.filter('[data-neo-b="container.content"]')
		this.$menuContainer = $neo.filter('[data-neo-b="container.menu"]')
		this.$settingsButton = $neo.filter('[data-neo-b="button.actions"]')
		this.$togglerButton = $neo.filter('[data-neo-b="button.toggler"]')
		this.$status = $neo.filter('[data-neo-b="status"]')

		this.$content = $(this._blockType.getBodyHtml(this._id)).appendTo(this.$contentContainer)

		this.addListener(this.$togglerButton, 'dblclick', '@doubleClickTitle')
	},

	initUi()
	{
		if(!this._initialised)
		{
			this.$foot = $(this._blockType.getFootHtml(this._id))

			Garnish.$bod.append(this.$foot)
			Craft.initUiElements(this.$contentContainer)

			this._settingsMenu = new Garnish.MenuBtn(this.$settingsButton);
			this._settingsMenu.on('optionSelect', e => this['@settingSelect'](e))

			this._initialised = true
		}
	},

	destroy()
	{
		if(this._initialised)
		{
			this.$container.remove()
			this.$foot.remove()
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

	collapse()
	{
		this.toggleExpansion(false)
	},

	expand()
	{
		this.toggleExpansion(true)
	},

	toggleExpansion(expand = !this._expanded)
	{
		this._expanded = expand

		const expandContainer = this.$menuContainer.find('[data-action="expand"]').parent()
		const collapseContainer = this.$menuContainer.find('[data-action="collapse"]').parent()

		this.$container
			.toggleClass('is-expanded', this._expanded)
			.toggleClass('is-contracted', !this._expanded)

		expandContainer.toggleClass('hidden', this._expanded)
		collapseContainer.toggleClass('hidden', !this._expanded)
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
		this._enabled = enable

		const enableContainer = this.$menuContainer.find('[data-action="enable"]').parent()
		const disableContainer = this.$menuContainer.find('[data-action="disable"]').parent()

		this.$container
			.toggleClass('is-enabled', this._enabled)
			.toggleClass('is-disabled', !this._enabled)

		this.$status.toggleClass('hidden', this._enabled)

		enableContainer.toggleClass('hidden', this._enabled)
		disableContainer.toggleClass('hidden', !this._enabled)
	},

	'@settingSelect'(e)
	{
		const $option = $(e.option)

		switch($option.attr('data-action'))
		{
			case 'collapse': this.collapse(); break
			case 'expand':   this.expand();   break
			case 'disable':  this.disable(); this.collapse(); break
			case 'enable':   this.enable();   break
			case 'delete':   this.destroy();  break
		}
	},

	'@doubleClickTitle'(e)
	{
		e.preventDefault()
		this.toggleExpansion()
	}
},
{
	_totalNewBlocks: 0,

	getNewId()
	{
		return `new${this._totalNewBlocks++}`
	}
})
