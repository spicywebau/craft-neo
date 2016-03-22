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

		this.$form.appendTo(Garnish.$bod)
		this.setContainer(this.$form)

		this._handleGenerator = new Craft.HandleGenerator(this.$nameInput, this.$handleInput)
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
	}
})
