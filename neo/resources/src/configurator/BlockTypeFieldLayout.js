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

export default Craft.FieldLayoutDesigner.extend({

	_templateNs: [],
	_blockName: '',

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)

		this.setBlockName(settings.blockName)

		const $template = $('template[data-neo="template.fld"]')
		const $fld = $($template[0].content).children().clone()

		$fld.removeAttr('id')

		NS.enter(this._templateNs)

		this.base($fld, {
			customizableTabs: true,
			fieldInputName: NS.fieldName('fieldLayout[__TAB_NAME__][]'),
			requiredFieldInputName: NS.fieldName('requiredFields[]')
		})

		NS.leave()

		this.$instructions = this.$container.find('.instructions')

		this._updateInstructions()
	},

	getBlockName() { return this._blockName },
	setBlockName(name)
	{
		this._blockName = name

		this._updateInstructions()
	},

	_updateInstructions()
	{
		if(this.$instructions)
		{
			this.$instructions.html(Craft.t("For block type {blockType}", {blockType: this.getBlockName() || '&hellip;'}))
		}
	}
})
