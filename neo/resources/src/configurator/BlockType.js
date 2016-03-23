import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import SettingsModal from './BlockTypeSettingsModal'

import renderItemTemplate from './templates/block_type.twig'
import renderFieldsTemplate from './templates/block_type_fields.twig'
import '../twig-extensions'

export default Garnish.Base.extend({

	_totalNewBlockTypes: 0,
	_parsed: false,

	init(name, handle, id = null, errors = [])
	{
		this._errors = errors
		this._settingsModal = new SettingsModal()

		this._settingsModal.on('save', e =>
		{
			this.name = e.name
			this.handle = e.handle
		})

		this._settingsModal.on('delete', e => this.trigger('delete'))

		this.id = id
		this.name = name
		this.handle = handle

		const context = {
			id: this.id,
			name: this.name,
			handle: this.handle,
			errors: this._errors
		}

		this.$itemContainer = $(renderItemTemplate(context))
		this.$fieldsContainer = $(renderFieldsTemplate(context))

		this.$name = this.$itemContainer.children('.name')
		this.$nameInput = this.$itemContainer.children('.name-input')
		this.$handle = this.$itemContainer.children('.handle')
		this.$handleInput = this.$itemContainer.children('.handle-input')

		const $actions = this.$itemContainer.children('.actions')
		this.$moveBtn = $actions.children('.move')
		this.$settingsBtn = $actions.children('.settings')

		this.addListener(this.$settingsBtn, 'click', '@edit')

		this._parsed = true
	},

	get id()
	{
		return this._id
	},

	set id(id)
	{
		id = parseInt(id)
		this._id = (isNaN(id) ? `new${this._totalNewBlockTypes++}` : id)

		if(this._parsed)
		{
			this.$nameInput.prop(`blockType[${this._id}][name]`)
			this.$handleInput.prop(`blockType[${this._id}][handle]`)
		}
	},

	get name()
	{
		return this._name
	},

	set name(name)
	{
		this._name = name
		this._settingsModal.name = name

		if(this._parsed)
		{
			this.$name.text(this._name)
			this.$nameInput.val(this._name)
		}
	},

	get handle()
	{
		return this._handle
	},

	set handle(handle)
	{
		this._handle = handle
		this._settingsModal.handle = handle

		if(this._parsed)
		{
			this.$handle.text(this._handle)
			this.$handleInput.val(this._handle)
		}
	},

	getErrors()
	{
		return Array.from(this._errors)
	},

	getSettingsModal()
	{
		return this._settingsModal
	},

	'@edit'(e)
	{
		e.preventDefault()

		this._settingsModal.name = this._name
		this._settingsModal.handle = this._handle

		this._settingsModal.show()
	}
})
