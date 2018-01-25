import $ from 'jquery'

export default Renderer => class extends Renderer
{
	addEventListeners()
	{
		const that = this

		this.$el.on('click.nir', '[data-nir-toggle]', e => this.onInputWrapperClick(e))
		this.$el.on('click.nir', '[data-nir-buttonbox-value]', e => this.onFieldInputChange(e))
		this.$el.on('change.nir keyup.nir', '[data-nir-toggle] :input', e => this.onFieldInputChange(e));

		this.$el.find('.field .elementselect, .field .categoriesfield').each(function()
		{
			const $this = $(this)

			if(!$this.hasAttr('data-nir-elementselect'))
			{
				const elementSelect = $this.data('elementSelect')

				if(elementSelect)
				{
					elementSelect.on('selectElements.nir, removeElements.nir', e => that.onElementSelectChange(e))
					$this.attr('data-nir-elementselect', '')

					that.onElementSelectChange()
				}
			}
		})

	}

	removeEventListeners()
	{
		this.$el.off('.nir')

		this.$el.find('[data-nir-elementselect]').each(function()
		{
			const $this = $(this)
			const elementSelect = $this.data('elementSelect')

			$this.removeAttr('data-nir-elementselect')

			if(elementSelect)
			{
				elementSelect.off('.nir')
			}
		})
	}

	// No need for live preview listeners as Reasons is initialised per block
	addLivePreviewListeners() {}
	removeLivePreviewListeners() {}

	initToggleFields()
	{
		// Get all current fields
		this.$fields = this.$el.find('.field')

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
		const that = this
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
			if(that.conditionals[fieldId])
			{
				$field.attr('data-nir-target', 1)
			}

			// Is this a toggle field
			if(toggleFieldIds.indexOf(parseInt(fieldId)) > -1)
			{
				$field.attr('data-nir-toggle', 1)
			}
		})

		return true
	}

	evaluateConditionals()
	{
		const that = this
		const $el = this.$el
		const $targetFields = this.$el.find('[data-nir-target]')

		$targetFields
			.removeClass('reasonsHide')
			.removeAttr('aria-hidden')
			.removeAttr('tabindex')

		$targetFields.each(function()
		{
			const $targetField = $(this)
			const statements = that.conditionals[$targetField.data('id')]

			if(!statements)
			{
				return
			}

			let numValidStatements = statements.length

			for(let i = 0; i < statements.length; i++)
			{
				const rules = statements[i]
				let statementValid = true

				for(var j = 0; j < rules.length; j++)
				{
					const rule = rules[j]
					const $toggleField = $el.find('[data-id="' + rule.fieldId + '"]')

					if($toggleField.length === 0)
					{
						continue
					}

					const toggleFieldData = Craft.ReasonsPlugin.getToggleFieldById(rule.fieldId)
					let toggleFieldValue = null

					switch(toggleFieldData.type)
					{
						case 'Lightswitch':
						{
							const $input = $toggleField.find('*:input:first')

							if($input.length > 0)
							{
								toggleFieldValue = $input.val() === '1' ? 'true': 'false'
							}
						}
						break
						case 'Checkboxes':
						case 'RadioButtons':
						case 'ButtonBox_Buttons':
						case 'ButtonBox_Stars':
						case 'ButtonBox_Width':
						{
							toggleFieldValue = $toggleField.find('input:checkbox:checked, input:radio:checked')
								.map(function()
								{
									return $(this).val()
								})
								.get()
						}
						break
						case 'Entries':
						case 'Categories':
						case 'Tags':
						case 'Assets':
						case 'Users':
						case 'Calendar_Event':
						{
							const elementSelect = $toggleField.find('[data-nir-elementselect]').data('elementSelect') || null
							toggleFieldValue = elementSelect && elementSelect.totalSelected ? 'notnull' : 'null'
						}
						break
						default:
						{
							toggleFieldValue = $toggleField.find('*:input:first').val()
						}
					}

					// Flatten array values for easier comparisons

					if($.isArray(toggleFieldValue))
					{
						toggleFieldValue = toggleFieldValue.join('')
					}

					if($.isArray(rule.value))
					{
						rule.value = rule.value.join('')
					}

					// Compare trigger field value to expected value
					switch(rule.compare)
					{
						case '!=':
						{
							if(toggleFieldValue == rule.value)
							{
								statementValid = false
							}
						}
						break
						// case '==':
						default:
						{
							if(toggleFieldValue != rule.value)
							{
								statementValid = false
							}
						}
					}

					if(!statementValid)
					{
						numValidStatements--
						break
					}
				}

			}

			if(numValidStatements <= 0)
			{
				$targetField
					.addClass('reasonsHide')
					.attr('aria-hidden', 'true')
					.attr('tabindex', '-1')
			}
		})
	}
}
