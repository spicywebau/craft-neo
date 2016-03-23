import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

const _defaults = {
	namespace: [],
	blockId: null
}

export default Craft.FieldLayoutDesigner.extend({

	_templateNs: [],
	_blockId: null,

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._templateNs.push(null) // So block ID can override

		this.blockId = settings.blockId

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

	_updateNamespace()
	{
		if(this.$container)
		{
			// TODO Go through all inputs and change their names
		}
	}
})
