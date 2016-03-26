import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

const _defaults = {
	namespace: [],
	layout: [],
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

		this.setBlockName(settings.blockName)

		const $template = $('template[data-neo="template.fld"]')
		this.$container = $($template[0].content).children().clone()
		this.$container.removeAttr('id')

		NS.enter(this._templateNs)

		this._fld = new Craft.FieldLayoutDesigner(this.$container, {
			customizableTabs: true,
			fieldInputName: NS.fieldName('fieldLayout[__TAB_NAME__][]'),
			requiredFieldInputName: NS.fieldName('requiredFields[]')
		})

		NS.leave()

		this.$instructions = this.$container.find('.instructions')

		console.log(settings.layout)

		for(let tab of settings.layout)
		{
			this.addTab(tab.name)
		}


		this._updateInstructions()
	},

	getBlockName() { return this._blockName },
	setBlockName(name)
	{
		this._blockName = name

		this._updateInstructions()
	},

	addTab(name = 'Tab' + (this._fld.tabGrid.$items.length + 1))
	{
		const fld = this._fld
		const $tab = $(`
			<div class="fld-tab">
				<div class="tabs">
					<div class="tab sel draggable">
						<span>${name}</span>
						<a class="settings icon" title="${Craft.t('Rename')}"></a>
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

		return $tab
	},

	addFieldToTab($tab, fieldId)
	{
		
	},

	_updateInstructions()
	{
		if(this.$instructions)
		{
			this.$instructions.html(Craft.t("For block type {blockType}", {blockType: this.getBlockName() || '&hellip;'}))
		}
	}
})
