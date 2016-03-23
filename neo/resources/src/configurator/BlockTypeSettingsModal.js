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
		this.addListener(this.$form, 'submit', '@save')
		this.addListener(this.$deleteBtn, 'click', '@delete')
	},

	get name()
	{
		return this._name
	},

	set name(name)
	{
		this._name = name

		this.$nameInput.val(this._name)
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

	show()
	{
		if(!Garnish.isMobileBrowser())
		{
			setTimeout(() => this.$nameInput.focus(), 100);
		}

		this.base()
	},

	enableDeleteButton()
	{
		this.$deleteBtn.removeClass('hidden')
	},

	_destroy()
	{
		this.$container.remove();
		this.$shade.remove();
	},

	'@save'(e)
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
		let name = Craft.trim(this.$nameInput.val())
		let handle = Craft.trim(this.$handleInput.val())

		if(!name)
		{
			Garnish.shake(this.$form)
		}
		else
		{
			if(!handle)
			{
				handle = this._handleGenerator.generateTargetValue(name)

				this.$handleInput.val(handle)
			}

			this.hide()

			this.trigger('save', {
				name: name,
				handle: handle
			})
		}
	},

	'@delete'(e)
	{
		e.preventDefault()

		if(confirm(Craft.t('Are you sure you want to delete this block type?')))
		{
			this.on('fadeOut', e => this._destroy())

			this.hide()

			this.trigger('delete')
		}
	}
})
