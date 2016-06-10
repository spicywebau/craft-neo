import $ from 'jquery'

export default Renderer => class extends Renderer
{
	addEventListeners()
	{
		const fieldSel = this.settings.fieldsSelector + '[data-toggle]'

		this.$el
			.on('click.neoReasons', fieldSel, e => this.onInputWrapperClick(e))
			.on('change.neoReasons keyup.neoReasons', fieldSel + ' :input', e => this.onFieldInputChange(e))
	}

	removeEventListeners()
	{
		this.$el.off('.neoReasons')
	}

	addLivePreviewListeners() {}
	removeLivePreviewListeners() {}

	initToggleFields()
	{
		// Get all current fields
		this.$fields = $(this.getFieldsSelector())

		if(this.$fields.length === 0)
		{
			return false
		}

		// Get toggle field IDs
		const toggleFieldIds = []
		for(let fieldId in this.conditionals)
		{
			for(let i = 0; i < this.conditionals[fieldId].length; i++)
			{
				toggleFieldIds.push(this.conditionals[fieldId][i][0].fieldId)
			}
		}

		// Loop over fields and add data-id attribute
		const self = this
		this.$fields.each(function()
		{
			const $field = $(this)

			if($field.attr('id') === undefined)
			{
				return
			}

			const fieldHandle = $field.attr('id').split('-').slice(-2, -1)[0] || false
			const fieldId = Craft.ReasonsPlugin.getFieldIdByHandle(fieldHandle)

			if(fieldId)
			{
				$field.attr('data-id', fieldId)
			}

			// Is this a target field?
			if(self.conditionals[fieldId])
			{
				$field.attr('data-target', 1)
			}

			// Is this a toggle field
			if(toggleFieldIds.indexOf(parseInt(fieldId)) > -1)
			{
				$field.attr('data-toggle', 1)
			}
		})

		return true
	}
}
