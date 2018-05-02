import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import ReasonsRenderer from '../plugins/reasons/Renderer'
import { addFieldLinks } from '../plugins/cpfieldlinks/main'

import renderTemplate from './templates/block.twig'
import '../twig-extensions'

const MutationObserver = (
	window.MutationObserver ||
	window.WebKitMutationObserver ||
	window.MozMutationObserver
)

const _defaults = {
	namespace: [],
	blockType: null,
	id: null,
	level: 0,
	buttons: null,
	enabled: true,
	collapsed: false,
	modified: true,
	'static': false,
}

const _resources = {}

function _resourceFilter()
{
	let url = this.href || this.src

	if(url)
	{
		const paramIndex = url.indexOf('?')

		url = (paramIndex < 0 ? url : url.substr(0, paramIndex))

		const isNew = !_resources.hasOwnProperty(url)
		_resources[url] = 1

		return isNew
	}

	return true
}

function _escapeHTML(str)
{
	return str ? str.replace(/[&<>"'\/]/g, s => ({
		'&': '&amp;',
		'<': '&lt;',
		'>': '&gt;',
		'"': '&quot;',
		"'": '&#39;',
		'/': '&#x2F;'
	})[s]) : ''
}

function _limit(s, l=40)
{
	s = s || ''
	return s.length > l ? s.slice(0, l - 3) + '...' : s
}

export default Garnish.Base.extend({

	_templateNs: [],
	_blockType: null,
	_initialised: false,
	_expanded: true,
	_enabled: true,
	_modified: true,
	_static: false,
	_initialState: null,

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._blockType = settings.blockType
		this._id = settings.id
		this._buttons = settings.buttons
		this._modified = settings['static'] ? true : settings.modified

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			type: this._blockType,
			id: this._id,
			enabled: !!settings.enabled,
			collapsed: !!settings.collapsed,
			level: settings.level,
			modified: this._modified,
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-b]')
		this.$bodyContainer = $neo.filter('[data-neo-b="container.body"]')
		this.$contentContainer = $neo.filter('[data-neo-b="container.content"]')
		this.$childrenContainer = $neo.filter('[data-neo-b="container.children"]')
		this.$collapsedChildrenContainer = $neo.filter('[data-neo-b="container.collapsedChildren"]')
		this.$blocksContainer = $neo.filter('[data-neo-b="container.blocks"]')
		this.$buttonsContainer = $neo.filter('[data-neo-b="container.buttons"]')
		this.$topbarContainer = $neo.filter('[data-neo-b="container.topbar"]')
		this.$topbarLeftContainer = $neo.filter('[data-neo-b="container.topbarLeft"]')
		this.$topbarRightContainer = $neo.filter('[data-neo-b="container.topbarRight"]')
		this.$tabsContainer = $neo.filter('[data-neo-b="container.tabs"]')
		this.$tabContainer = $neo.filter('[data-neo-b="container.tab"]')
		this.$menuContainer = $neo.filter('[data-neo-b="container.menu"]')
		this.$previewContainer = $neo.filter('[data-neo-b="container.preview"]')
		this.$tabButton = $neo.filter('[data-neo-b="button.tab"]')
		this.$settingsButton = $neo.filter('[data-neo-b="button.actions"]')
		this.$togglerButton = $neo.filter('[data-neo-b="button.toggler"]')
		this.$tabsButton = $neo.filter('[data-neo-b="button.tabs"]')
		this.$enabledInput = $neo.filter('[data-neo-b="input.enabled"]')
		this.$collapsedInput = $neo.filter('[data-neo-b="input.collapsed"]')
		this.$levelInput = $neo.filter('[data-neo-b="input.level"]')
		this.$modifiedInput = $neo.filter('[data-neo-b="input.modified"]')
		this.$status = $neo.filter('[data-neo-b="status"]')

		if(this._buttons)
		{
			this._buttons.on('newBlock', e => this.trigger('newBlock', Object.assign(e, {level: this.getLevel() + 1})))
			this.$buttonsContainer.append(this._buttons.$container)
		}

		let hasErrors = false
		if(this._blockType)
		{
			for(let tab of this._blockType.getTabs())
			{
				if(tab.getErrors().length > 0)
				{
					hasErrors = true
					break
				}
			}
		}

		this.setLevel(settings.level)
		this.toggleEnabled(settings.enabled)
		this.toggleExpansion(hasErrors ? true : !settings.collapsed, false, false)

		this.addListener(this.$topbarContainer, 'dblclick', '@doubleClickTitle')
		this.addListener(this.$tabButton, 'click', '@setTab')
	},

	initUi()
	{
		if(!this._initialised)
		{
			const tabs = this._blockType.getTabs()

			let headList = tabs.map(tab => tab.getHeadHtml(this._id))
			let footList = tabs.map(tab => tab.getFootHtml(this._id))
			this.$head = $(headList.join('')).filter(_resourceFilter)
			this.$foot = $(footList.join('')).filter(_resourceFilter)

			Garnish.$bod.siblings('head').append(this.$head)
			Garnish.$bod.append(this.$foot)

			Craft.initUiElements(this.$contentContainer)
			
			this.$tabsButton.menubtn()

			this._settingsMenu = new Garnish.MenuBtn(this.$settingsButton);
			this._settingsMenu.on('optionSelect', e => this['@settingSelect'](e))

			this._initialised = true

			if(this._buttons)
			{
				this._buttons.initUi()
			}

			Garnish.requestAnimationFrame(() => this.updateResponsiveness())

			this._initReasonsPlugin()
			this._initRelabelPlugin()

			// For Matrix blocks inside a Neo block, this listener adds a class name to the block for Neo to style.
			// Neo applies it's own styles to Matrix blocks in an effort to improve the visibility of them, however
			// when dragging a Matrix block these styles get lost (since a dragged Matrix block loses it's context of
			// being inside a Neo block). Adding this class name to blocks before they are dragged means that the
			// dragged Matrix block can still have the Neo-specific styles.
			this.$container.on('mousedown', '.matrixblock', function(e)
			{
				$(this).addClass('neo-matrixblock')
			})

			// Setting up field and block property watching
			if(!this._modified && !this.isNew())
			{
				this._initialState = {
					enabled: this._enabled,
					level: this._level,
					content: Garnish.getPostData(this.$contentContainer)
				}

				if(MutationObserver)
				{
					const detectChange = () => this._detectChange()
					const observer = new MutationObserver(() => setTimeout(detectChange, 20))

					observer.observe(this.$container[0], {
						attributes: true,
						childList: true,
						characterData: true,
						subtree: true,
						attributeFilter: ['name', 'value'],
					})

					this.$contentContainer.on('propertychange change click', 'input, textarea, select', detectChange)
					this.$contentContainer.on('paste input keyup', 'input:not([type="hidden"]), textarea', detectChange)

					this._detectChangeObserver = observer
				}
				else
				{
					this._detectChangeInterval = setInterval(() => this._detectChange(), 300)
				}
			}

			addFieldLinks(this.$contentContainer)

			this.trigger('initUi')
		}
	},

	destroy()
	{
		if(this._initialised)
		{
			this.$head.remove()
			this.$foot.remove()

			clearInterval(this._detectChangeInterval)

			if(this._detectChangeObserver)
			{
				this._detectChangeObserver.disconnect()
			}

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

	getLocale()
	{
		if(!this._locale)
		{
			const $form = this.$container.closest('form')
			const $locale = $form.find('input[name="siteId"]')

			this._locale = $locale.val()
		}

		return this._locale
	},

	getContent()
	{
		const rawContent = Garnish.getPostData(this.$contentContainer)
		const content = {}

		const setValue = (keys, value) =>
		{
			let currentSet = content

			for(let i = 0; i < keys.length - 1; i++)
			{
				let key = keys[i]

				if(!$.isPlainObject(currentSet[key]) && !$.isArray(currentSet[key]))
				{
					currentSet[key] = {}
				}

				currentSet = currentSet[key]
			}

			let key = keys[keys.length - 1]
			currentSet[key] = value
		}

		for(let rawName of Object.keys(rawContent))
		{
			let fullName = NS.parse(rawName)
			let name = fullName.slice(this._templateNs.length + 1) // Adding 1 because content is NS'd under [fields]
			let value = rawContent[rawName]

			setValue(name, value)
		}

		return content
	},

	updatePreview(condensed=null)
	{
		condensed = typeof condensed === 'boolean' ? condensed : false

		const $fields = this.$contentContainer.find('.field')
		const blockType = this.getBlockType()
		const fieldTypes = blockType.getFieldTypes()
		const previewText = []

		$fields.each(function()
		{
			const $field = $(this)
			const $input = $field.children('.input')
			const id = $field.prop('id')
			const label = $field.children('.heading').children('label').text()
			const handle = id.match(/-([a-z0-9_]+)-field$/i)[1]
			const fieldType = fieldTypes[handle]
			let value = false

			switch(fieldType)
			{
				case 'Assets':
				{
					const values = []
					const $assets = $input.find('.element')

					$assets.each(function()
					{
						const $asset = $(this)
						const $thumb = $asset.find('.elementthumb > img')
						const srcset = $thumb.prop('srcset')

						values.push(`<img sizes="30px" srcset="${srcset}">`)

						if(!condensed && $assets.length === 1)
						{
							const title = $asset.find('.title').text()

							values.push(_escapeHTML(_limit(title)))
						}
					})

					value = values.join(' ')
				}
				break
				case 'Categories':
				case 'Entries':
				case 'Tags':
				case 'Users':
				{
					const values = []
					const $elements = $input.find('.element')

					$elements.each(function()
					{
						const $element = $(this)
						const title = $element.find('.title, .label').eq(0).text()

						values.push(_escapeHTML(_limit(title)))
					})

					value = values.join(', ')
				}
				break
				case 'Checkboxes':
				{
					const values = []
					const $checkboxes = $input.find('input[type="checkbox"]')

					$checkboxes.each(function()
					{
						if(this.checked)
						{
							const $checkbox = $(this)
							const id = $checkbox.prop('id')
							const $label = $input.find(`label[for="${id}"]`)
							const label = $label.text()

							values.push(_escapeHTML(_limit(label)))
						}
					})

					value = values.join(', ')
				}
				break
				case 'Color':
				{
					const color = $input.find('input[type="color"]').val()

					value = `<div class="preview_color" style="background-color: ${color}"></div>`
				}
				break
				case 'Date':
				{
					const date = _escapeHTML($input.find('.datewrapper input').val())
					const time = _escapeHTML($input.find('.timewrapper input').val())

					value = date && time ? (date + ' ' + time) : (date || time)
				}
				break
				case 'Dropdown':
				{
					const $selected = $input.find('select').children(':selected')

					value = _escapeHTML(_limit($selected.text()))
				}
				break
				case 'Lightswitch':
				{
					const enabled = !!$input.find('input').val()

					value = `<span class="status${enabled ? ' live' : ''}"></span>` + _escapeHTML(_limit(label))
				}
				break
				case 'MultiSelect':
				{
					const values = []
					const $selected = $input.find('select').children(':selected')

					$selected.each(function()
					{
						values.push($(this).text())
					})

					value = _escapeHTML(_limit(values.join(', ')))
				}
				break
				case 'Number':
				case 'PlainText':
				{
					value = _escapeHTML(_limit($input.children('input').val()))
				}
				break
				case 'PositionSelect':
				{
					const $selected = $input.find('.btn.active')

					value = _escapeHTML($selected.prop('title'))
				}
				break
				case 'RadioButtons':
				{
					const $checked = $input.find('input[type="g"]:checked')

					value = _escapeHTML(_limit($checked.val()))
				}
				break
				case 'RichText':
				{
					value = _escapeHTML(_limit(Craft.getText($input.find('textarea').val())))
				}
				break
				case 'Matrix':
				case 'SuperTable':
				{
					const $subFields = $field.find('.field');
					const $subInputs = $subFields.find('input[type!="hidden"], select, textarea, .label')

					let values = []

					$subInputs.each(function()
					{
						const $subInput = $(this)
						let subValue = null

						if($subInput.is('input, textarea'))
						{
							subValue = Craft.getText(Garnish.getInputPostVal($subInput))
						}
						else if($subInput.is('select'))
						{
							subValue = $subInput.find('option:selected').text()
						}
						else if($subInput.hasClass('label'))
						{
							// TODO check for lightswitch maybe?
							subValue = $subInput.text()
						}

						if(subValue)
						{
							values.push(_limit(subValue))
						}
					})

					value = _escapeHTML(values.join(', '))
				}
			}

			if(value && previewText.length < 10)
			{
				previewText.push('<span class="preview_section">', value, '</span>')
			}
		})

		this.$previewContainer.html(previewText.join(''))
	},

	isNew()
	{
		return /^new/.test(this.getId())
	},

	isSelected()
	{
		return this.$container.hasClass('is-selected')
	},

	collapse(save, animate)
	{
		this.toggleExpansion(false, save, animate)
	},

	expand(save, animate)
	{
		this.toggleExpansion(true, save, animate)
	},

	toggleExpansion(expand, save, animate)
	{
		expand  = (typeof expand  === 'boolean' ? expand  : !this._expanded)
		save    = (typeof save    === 'boolean' ? save    : true)
		animate = (typeof animate === 'boolean' ? animate : true)

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

			if(!this._expanded)
			{
				this.updatePreview()
			}

			const contentHeight = this.$contentContainer.outerHeight()|0
			const childrenHeight = this.$childrenContainer.outerHeight()|0

			const expandedCss = {
				opacity: 1,
				height: contentHeight + childrenHeight,
			}
			const collapsedCss = {
				opacity: 0,
				height: 0,
			}
			const clearCss = {
				opacity: '',
				height: '',
			}

			if(animate)
			{
				this.$bodyContainer
					.css(this._expanded ? collapsedCss : expandedCss)
					.velocity(this._expanded ? expandedCss : collapsedCss, 'fast', e =>
					{
						if(this._expanded)
						{
							this.$bodyContainer.css(clearCss)
						}
					})
			}
			else
			{
				this.$bodyContainer.css(this._expanded ? clearCss : collapsedCss)
			}

			this.$collapsedInput.val(this._expanded ? 0 : 1)

			if(save)
			{
				this.saveExpansion()
			}

			this.trigger('toggleExpansion', {
				expanded: this._expanded,
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
			Craft.queueActionRequest('neo/input/save-expansion', {
				expanded: this.isExpanded() ? 1 : 0,
				blockId: this.getId(),
				locale: this.getLocale(),
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

		this.$tabsButton.text(name)

		this.trigger('selectTab', {
			tabName: name,
			$tabButton: $tab.filter('[data-neo-b="button.tab"]'),
			$tabContainer: $tab.filter('[data-neo-b="container.tab"]')
		})
	},

	updateResponsiveness()
	{
		this._topbarLeftWidth = this._topbarLeftWidth || this.$topbarLeftContainer.width()
		this._topbarRightWidth = this._topbarRightWidth || this.$topbarRightContainer.width()

		const isMobile = (this.$topbarContainer.width() < this._topbarLeftWidth + this._topbarRightWidth)

		this.$tabsContainer.toggleClass('invisible', isMobile)
		this.$tabsButton.toggleClass('invisible', !isMobile)
	},

	updateMenuStates(field, blocks = [], maxBlocks = 0, additionalCheck = null, allowedBlockTypes = false)
	{
		additionalCheck = (typeof additionalCheck === 'boolean') ? additionalCheck : true

		const allDisabled = (maxBlocks > 0 && blocks.length >= maxBlocks) || !additionalCheck

		const pasteData = JSON.parse(localStorage.getItem('neo:copy') || '{}')
		let pasteDisabled = (!pasteData.blocks || !pasteData.field || pasteData.field !== field)

		if(!pasteDisabled)
		{
			const currentBlockTypesById = blocks.reduce((m, b) =>
			{
				const bt = b.getBlockType()
				const id = bt.getId()
				const v = m[id] || { blockType: bt, count: 0 }

				v.count++
				m[id] = v

				return m
			})

			for(let pasteBlock of pasteData.blocks)
			{
				const pasteBlockTypeObj = currentBlockTypesById[pasteBlock.type]

				// Test to see if any max block types properties will be violated
				if(pasteBlockTypeObj)
				{
					const pasteBlockType = pasteBlockTypeObj.blockType
					const currentBlocksOfTypeCount = pasteBlockTypeObj.count
					const maxPasteBlockTypes = pasteBlockType.getMaxBlocks()
					const pasteTypeDisabled = (maxPasteBlockTypes > 0 && currentBlocksOfTypeCount >= maxPasteBlockTypes)

					pasteDisabled = pasteDisabled || pasteTypeDisabled
				}

				// Test to see if the top level paste blocks have a block type that is allowed to be pasted here
				if(pasteBlock.level === 0)
				{
					const allowedBlockType = allowedBlockTypes.find(bt => bt.getId() == pasteBlock.type);

					pasteDisabled = pasteDisabled || !allowedBlockType
				}
			}
		}

		this.$menuContainer.find('[data-action="add"]').toggleClass('disabled', allDisabled)
		this.$menuContainer.find('[data-action="paste"]').toggleClass('disabled', pasteDisabled)
	},

	_initReasonsPlugin()
	{
		const Reasons = Craft.ReasonsPlugin

		if(Reasons)
		{
			const Renderer = ReasonsRenderer(Reasons.ConditionalsRenderer)

			const type = this.getBlockType()
			const typeId = type.getId()
			const conditionals = Reasons.Neo.conditionals[typeId] || {}

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

	_initRelabelPlugin()
	{
		const Relabel = window.Relabel

		if(Relabel)
		{
			NS.enter(this._templateNs)

			const blockType = this.getBlockType()
			Relabel.applyLabels(this.$contentContainer, blockType.getFieldLayoutId(), NS.value())

			NS.leave()
		}
	},

	_detectChange()
	{
		const initial = this._initialState
		const content = Garnish.getPostData(this.$contentContainer)

		const modified = !Craft.compare(content, initial.content, false) ||
			initial.enabled !== this._enabled ||
			initial.level !== this._level

		if(modified !== this._modified)
		{
			this.$modifiedInput.val(modified ? 1 : 0)
			this._modified = modified
		}
	},

	'@settingSelect'(e)
	{
		const $option = $(e.option)

		if(!$option.hasClass('disabled'))
		{
			switch($option.attr('data-action'))
			{
				case 'collapse':
				{
					this.collapse()
				}
				break
				case 'expand':
				{
					this.expand()
				}
				break
				case 'disable':
				{
					this.disable()
					this.collapse()
				}
				break
				case 'enable':
				{
					this.enable()
					this.expand()
				}
				break
				case 'delete':
				{
					this.trigger('removeBlock', { block: this })
				}
				break
				case 'add':
				{
					this.trigger('addBlockAbove', { block: this })
				}
				break
				case 'copy':
				{
					this.trigger('copyBlock', { block: this })
				}
				break
				case 'paste':
				{
					this.trigger('pasteBlock', { block: this })
				}
				break
			}
		}
	},

	'@doubleClickTitle'(e)
	{
		e.preventDefault()

		const $target = $(e.target)
		const $checkFrom = $target.parent()
		const isLeft = ($checkFrom.closest(this.$topbarLeftContainer).length > 0)
		const isRight = ($checkFrom.closest(this.$topbarRightContainer).length > 0)

		if(!isLeft && !isRight)
		{
			this.toggleExpansion()
		}
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
