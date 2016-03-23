import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import SettingsModal from './BlockTypeSettingsModal'
import FieldLayoutDesigner from './BlockTypeFLD'

import renderTemplate from './templates/blocktype.twig'
import '../twig-extensions'

const _defaults = {
	namespace: [],
	name: '',
	handle: '',
	id: null,
	errors: []
}

let _totalNewBlockTypes = 0

export default Garnish.Base.extend({

	_templateNs: [],
	_parsed: false,

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._errors = settings.errors

		this.id = settings.id
		this.name = settings.name
		this.handle = settings.handle

		this._settingsModal = new SettingsModal({
			name: this.name,
			handle: this.handle
		})

		this._settingsModal.on('save', e =>
		{
			this.name = e.name
			this.handle = e.handle
		})

		this._settingsModal.on('delete', e => this.trigger('delete'))

		this._fieldLayout = new FieldLayoutDesigner({
			namespace: this._templateNs,
			blockId: this.id,
			blockName: this.name
		})

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			id: this.id,
			name: this.name,
			handle: this.handle,
			errors: this._errors
		}))

		NS.leave()

		const $itemNeo = this.$container.find('[data-neo]')

		this.$name = $itemNeo.filter('[data-neo="text.name"]')
		this.$nameInput = $itemNeo.filter('[data-neo="input.name"]')
		this.$handle = $itemNeo.filter('[data-neo="text.handle"]')
		this.$handleInput = $itemNeo.filter('[data-neo="input.handle"]')
		this.$moveBtn = $itemNeo.filter('[data-neo="button.move"]')
		this.$settingsBtn = $itemNeo.filter('[data-neo="button.settings"]')

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
		this._id = (isNaN(id) ? `new${_totalNewBlockTypes++}` : id)

		if(this._fieldLayout)
		{
			this._fieldLayout.blockId = this._id
		}

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

		if(this._settingsModal)
		{
			this._settingsModal.name = this._name
		}

		if(this._fieldLayout)
		{
			this._fieldLayout.blockName = this._name
		}

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

		if(this._settingsModal)
		{
			this._settingsModal.handle = this._handle
		}

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

	getFieldLayout()
	{
		return this._fieldLayout
	},

	'@edit'(e)
	{
		e.preventDefault()

		this._settingsModal.name = this._name
		this._settingsModal.handle = this._handle

		this._settingsModal.show()
	}
})
