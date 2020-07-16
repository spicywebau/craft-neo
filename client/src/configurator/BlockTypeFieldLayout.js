import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

const _defaults = {
	namespace: [],
	html: '',
	layout: [],
	id: -1,
	blockId: null,
	blockName: ''
}

export default Garnish.Base.extend({

	_templateNs: [],
	_blockName: '',

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._id = settings.id|0
		this._blockId = settings.blockId

		this.setBlockName(settings.blockName)

		this.$container = $(settings.html).find('.layoutdesigner')
		this.$container.removeAttr('id')

		NS.enter(this._templateNs)

		this._fld = new Craft.FieldLayoutDesigner(this.$container, {
			customizableTabs: true,
			customizableUi: true,
			elementPlacementInputName: NS.fieldName('elementPlacements[__TAB_NAME__][]'),
			elementConfigInputName: NS.fieldName('elementConfigs[__ELEMENT_KEY__]')
		})

		NS.leave()

		for(let tab of settings.layout)
		{
			let $tab = this.addTab(tab.name)

			for(let element of tab.elements)
			{
				this.addElementToTab($tab, element)
			}
		}

		this._hideNeoFields()
		this._patchFLD()
		this._setupBlankTabs()
	},

	getId()
	{
		return this._id
	},

	getBlockId()
	{
		return this._blockId
	},

	getBlockName() { return this._blockName },
	setBlockName(name)
	{
		this._blockName = name
	},

	/**
	 * @see Craft.FieldLayoutDesigner.addTab
	 */
	addTab(name = 'Tab' + (this._fld.tabGrid.$items.length + 1))
	{
		const fld = this._fld
		const $tab = $(`
			<div class="fld-tab">
				<div class="tabs">
					<div class="tab sel draggable">
						<span>${name}</span>
						<a class="settings icon" title="${Craft.t('neo', 'Rename')}"></a>
					</div>
				</div>
				<div class="fld-tabcontent"></div>
			</div>
		`).appendTo(fld.$tabContainer)

		fld.tabGrid.addItems($tab)
		fld.tabDrag.addItems($tab)

		this.$container.appendTo(document.body)

		fld.initTab($tab)

		this._setupBlankTab($tab)

		return $tab
	},

	/**
	 * @see Craft.FieldLayoutDesigner.ElementDrag.onDragStop
	 */
	addElementToTab($tab, element)
	{
		const $elementContainer = $tab.find('.fld-tabcontent')
		let $element = null;

		if (element.type === 'craft\\fieldlayoutelements\\CustomField') {
			const $unusedField = this._fld.$fields.filter(`[data-id="${element.id}"]`)
			$element = $unusedField.clone().toggleClass('fld-required', !!element.config.required)

			if (element.config.label) {
				$element.find('.field-name > h4').text(element.config.label)
			}

			$unusedField.addClass('hidden')
		} else {
			$element = this._fld.$uiLibraryElements.filter(function() {
				const $this = $(this)
				const type = $this.data('type')
				const style = $this.data('config').style

				return type === element.type && (!style || style === element.config.style)
			}).clone()
			let newLabel = null;

			switch (element.type) {
				case 'craft\\fieldlayoutelements\\Tip':
					newLabel = element.config.tip
					break

				case 'craft\\fieldlayoutelements\\Heading':
					newLabel = element.config.heading
					break

				case 'craft\\fieldlayoutelements\\Template':
					newLabel = element.config.template
					break
			}

			if (newLabel) {
				const $label = $element.find('.fld-element-label')
				$label.text(newLabel)
				$label.toggleClass('code', element.type === 'craft\\fieldlayoutelements\\Template')
			}
		}

		$element.removeClass('unused')
		$elementContainer.append($element)
		$element.data('config', element.config)
		$element.data('settings-html', element['settings-html'])
		this._fld.initElement($element)
		this._fld.elementDrag.addItems($element)
	},

	_hideNeoFields()
	{
		this._fld.$fields.each(function() {
			const $this = $(this)

			if ($this.find('.field-name > .smalltext').html() === 'Neo') {
				$this.addClass('hidden')
			}
		})
	},

	_patchFLD()
	{
		const patch = (method, callback) =>
		{
			const superMethod = this._fld[method]
			this._fld[method] = function()
			{
				const returnValue = superMethod.apply(this, arguments)
				callback.apply(this, arguments)
				return returnValue
			}
		}

		patch('initTab', $tab => this._setupBlankTab($tab))
		patch('renameTab', $tab => this._setupBlankTab($tab))
	},

	_setupBlankTab($tab)
	{
		$tab = $($tab)
		$tab.children('.nc_blanktab').remove()

		const tabName = $tab.find('.tab > span').text()
		let inputName = this._fld.getElementPlacementInputName(tabName)
		inputName = inputName.substr(0, inputName.length - 2) // Remove the "[]" array part

		$tab.prepend(`<input type="hidden" class="nc_blanktab" name="${inputName}">`)
	},

	_setupBlankTabs()
	{
		const $tabs = this._fld.$tabContainer.children('.fld-tab')
		const that = this

		$tabs.each(function()
		{
			that._setupBlankTab(this)
		})
	},
})
