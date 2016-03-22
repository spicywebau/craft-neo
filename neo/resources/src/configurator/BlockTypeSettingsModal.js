import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import renderTemplate from './templates/block_type_settings.twig'
import '../twig-extensions'

export default Garnish.Modal.extend({

	init()
	{
		this.base()

		this.$form = $(renderTemplate())
		this.$nameInput = this.$form.find('#new-block-type-name')
		this.$handleInput = this.$form.find('#new-block-type-handle')
		this.$cancelBtn = this.$form.find('#new-block-type-cancel')
		this.$deleteBtn = this.$form.find('#new-block-type-delete')

		this.$form.appendTo(Garnish.$bod)
		this.setContainer(this.$form)

		this._handleGenerator = new Craft.HandleGenerator(this.$nameInput, this.$handleInput)

		this.addListener(this.$cancelBtn, 'click', 'hide')
		this.addListener(this.$form, 'submit', '@onFormSubmit')
		this.addListener(this.$deleteBtn, 'click', '@onDeleteClick')
	},

	get name()
	{
		return this._name
	},

	set name(name)
	{
		this._name = name

		this.$nameInput.val(this._handle)
	},

	get handle()
	{
		return this._handle
	},

	set handle(handle)
	{
		this._handle = handle

		this.$handleInput.val(this._handle)
	},

	'@onFormSubmit': function(e)
	{
		e.preventDefault()

		// Prevent multi form submits with the return key
		if(!this.visible) return

		if(this._handleGenerator.listening)
		{
			// Give the handle a chance to catch up with the input
			this._handleGenerator.updateTarget()
		}

		// Basic validation
		const name = Craft.trim(this.$nameInput.val())
		const handle = Craft.trim(this.$handleInput.val())

		if(!name || !handle)
		{
			Garnish.shake(this.$form)
		}
		else
		{
			this.hide()

			this.trigger('save', {
				name: name,
				handle: handle
			})
		}
	}
})
