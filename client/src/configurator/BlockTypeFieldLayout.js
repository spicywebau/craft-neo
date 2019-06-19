import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import ReasonsEditor from '../plugins/reasons/Editor'
import QuickField from '../plugins/quickfield/QuickField'

const _defaults = {
	namespace: [],
	html: '',
	layout: [],
	id: -1,
	blockId: null,
	blockName: ''
}

let _reasonsInitialised = false

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

		this.$container = $(settings.html)
		this.$container.removeAttr('id')

		NS.enter(this._templateNs)

		this._fld = new Craft.FieldLayoutDesigner(this.$container, {
			customizableTabs: true,
			fieldInputName: NS.fieldName('fieldLayout[__TAB_NAME__][]'),
			requiredFieldInputName: NS.fieldName('requiredFields[]')
		})

		NS.leave()

		this.$instructions = this.$container.find('.instructions')

		for(let tab of settings.layout)
		{
			let $tab = this.addTab(tab.name)

			for(let field of tab.fields)
			{
				this.addFieldToTab($tab, field.id, field.required == 1)
			}
		}

		this._patchFLD()
		this._updateInstructions()
		this._setupBlankTabs()
		this._initReasonsPlugin()
		this._initFieldLabelsPlugin()
		this._initQuickFieldPlugin()
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

		this._updateInstructions()
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

		// In order for tabs to be added to the FLD, the FLD must be visible in the DOM.
		// To ensure this, the FLD is momentarily placed in the root body element, then after the tab has been added,
		// it is placed back in the same position it was.

		const $containerNext = this.$container.next()
		const $containerParent = this.$container.parent()

		this.$container.appendTo(document.body)

		fld.initTab($tab)

		if($containerNext.length > 0)
		{
			$containerNext.before(this.$container)
		}
		else
		{
			$containerParent.append(this.$container)
		}

		this._setupBlankTab($tab)

		return $tab
	},

	/**
	 * @see Craft.FieldLayoutDesigner.FieldDrag.onDragStop
	 */
	addFieldToTab($tab, fieldId, required = null)
	{
		required = !!required

		const $unusedField = this._fld.$allFields.filter(`[data-id="${fieldId}"]`)
		const $unusedGroup = $unusedField.closest('.fld-tab')
		const $field = $unusedField.clone().removeClass('unused')
		const $fieldContainer = $tab.find('.fld-tabcontent')

		$unusedField.addClass('hidden')
		if($unusedField.siblings(':not(.hidden)').length === 0)
		{
			$unusedGroup.addClass('hidden')
			this._fld.unusedFieldGrid.removeItems($unusedGroup)
		}

		let $fieldInput = $field.find('.id-input')
		if($fieldInput.length === 0)
		{
			let tabName = $tab.find('.tab > span').text()
			let inputName = this._fld.getFieldInputName(tabName)

			$fieldInput = $(`<input class="id-input" type="hidden" name="${inputName}" value="${fieldId}">`)
			$field.append($fieldInput)
		}

		$field.prepend(`<a class="settings icon" title="${Craft.t('neo', 'Edit')}"></a>`);
		$fieldContainer.append($field)
		this._fld.initField($field)
		this._fld.fieldDrag.addItems($field)

		this.toggleFieldRequire(fieldId, required)
	},

	toggleFieldRequire(fieldId, required = null)
	{
		const $field = this._fld.$tabContainer.find(`[data-id="${fieldId}"]`)
		const isRequired = $field.hasClass('fld-required')

		if(required === null || required !== isRequired)
		{
			const $editButton = $field.find('.settings')
			const menuButton = $editButton.data('menubtn')
			const menu = menuButton.menu
			const $options = menu.$options
			const $requiredOption = $options.filter('.toggle-required')

			this._fld.toggleRequiredField($field, $requiredOption)
		}
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

	_updateInstructions()
	{
		if(this.$instructions)
		{
			this.$instructions.html(Craft.t('neo', "For block type {blockType}", {blockType: this.getBlockName() || '&hellip;'}))
		}
	},

	_initReasonsPlugin()
	{
		const Reasons = Craft.ReasonsPlugin

		if(Reasons)
		{
			const Editor = ReasonsEditor(Reasons.FieldLayoutDesigner)

			const id = this.getBlockId()
			const conditionals = Reasons.Neo.conditionals[id]

			this._reasons = new Editor(this.$container, conditionals, id)
		}
	},

	_destroyReasonsPlugin()
	{
		if(this._reasons)
		{
			this._reasons.destroy()
		}
	},

	_setupBlankTab($tab)
	{
		$tab = $($tab)
		$tab.children('.nc_blanktab').remove()

		const tabName = $tab.find('.tab > span').text()
		let inputName = this._fld.getFieldInputName(tabName)
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

	_initQuickFieldPlugin()
	{
		if(QuickField)
		{
			const quickField = new QuickField(this._fld)

			const newGroups = QuickField.getNewGroups()
			const newFields = QuickField.getNewFields()

			for(let id of Object.keys(newGroups))
			{
				let group = newGroups[id]
				quickField.addGroup(id, group.name)
			}

			for(let id of Object.keys(newFields))
			{
				let field = newFields[id]
				quickField.addField(id, field.name, field.groupName)
			}

			this._quickField = quickField
		}
	}
})
