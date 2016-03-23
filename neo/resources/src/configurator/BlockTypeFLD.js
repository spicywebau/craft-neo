import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

const _defaults = {
	namespace: [],
	blockId: null,
	blockName: ''
}

export default Craft.FieldLayoutDesigner.extend({

	_templateNs: [],
	_blockId: null,
	_blockName: '',

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._templateNs.push(null) // So block ID can override

		this.blockId = settings.blockId
		this.blockName = settings.blockName

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

	get blockId()
	{
		return this._blockId
	},

	set blockId(id)
	{
		this._blockId = id

		this._templateNs.pop()
		this._templateNs.push(id)

		this._updateNamespace()
	},

	get blockName()
	{
		return this._blockName
	},

	set blockName(name)
	{
		this._blockName = name

		this._updateInstructions()
	},

	_updateNamespace()
	{
		if(this.$container)
		{
			// TODO Go through all inputs and change their names
		}
	},

	_updateInstructions()
	{
		if(this.$instructions)
		{
			this.$instructions.text(Craft.t("For block type {blockType}", {blockType: this.blockName}))
		}
	}
})
