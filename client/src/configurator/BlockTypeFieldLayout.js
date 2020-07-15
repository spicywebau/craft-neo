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
			elementPlacementInputName: NS.fieldName('elementPlacements[__TAB_NAME__][]'),
			elementConfigInputName: NS.fieldName('elementConfigs[__ELEMENT_KEY__]')
		})

		NS.leave()

		for(let tab of settings.layout)
		{
			let $tab = this.addTab(tab.name)

			for(let field of tab.elements)
			{
				this.addFieldToTab($tab, field)
			}
		}

		this._hideNeoFields()
		this._patchFLD()
		this._setupBlankTabs()
		this._initFieldLabelsPlugin()
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
	 * @see Craft.FieldLayoutDesigner.FieldDrag.onDragStop
	 */
	addFieldToTab($tab, field)
	{
		const $unusedField = this._fld.$fields.filter(`[data-id="${field.id}"]`)
		const $field = $unusedField.clone().removeClass('unused')
		const $fieldContainer = $tab.find('.fld-tabcontent')
		$field.data('config', field.config)
		$field.data('settings-html', field['settings-html'])
		$field.toggleClass('fld-required', !!field.config.required)

		if (field.config.label) {
			$field.find('.field-name > h4').text(field.config.label)
		}

		$unusedField.addClass('hidden')
		$fieldContainer.append($field)
		this._fld.initElement($field)
		this._fld.elementDrag.addItems($field)
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

	_initFieldLabelsPlugin()
	{
		if(this._fld.fieldlabels)
		{
			const fieldlabels = this._fld.fieldlabels

			const id = this.getBlockId()
			fieldlabels.namespace = `neo[fieldlabels][${id}]`;
			fieldlabels.applyLabels(this.getId())

			this._fieldlabels = fieldlabels
		}
	},
})
