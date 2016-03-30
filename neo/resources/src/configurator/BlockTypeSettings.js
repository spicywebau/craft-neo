import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import Settings from './Settings'

import renderTemplate from './templates/blocktype_settings.twig'
import '../twig-extensions'

const _defaults = {
	namespace: [],
	id: null,
	sortOrder: 0,
	name: '',
	handle: '',
	maxBlocks: 0,
	childBlocks: null,
	errors: {}
}

export default Settings.extend({

	_templateNs: [],

	$sortOrderInput: new $,
	$nameInput: new $,
	$handleInput: new $,
	$maxBlocksInput: new $,

	init(settings = {})
	{
		settings = Object.assign({}, _defaults, settings)

		this._templateNs = NS.parse(settings.namespace)
		this._id = settings.id
		this._errors = settings.errors

		this.setSortOrder(settings.sortOrder)
		this.setName(settings.name)
		this.setHandle(settings.handle)
		this.setMaxBlocks(settings.maxBlocks)

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			id:        this.getId(),
			sortOrder: this.getSortOrder(),
			name:      this.getName(),
			handle:    this.getHandle(),
			maxBlocks: this.getMaxBlocks(),
			errors:    this.getErrors()
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-bts]')
		this.$sortOrderInput = $neo.filter('[data-neo-bts="input.sortOrder"]')
		this.$nameInput = $neo.filter('[data-neo-bts="input.name"]')
		this.$handleInput = $neo.filter('[data-neo-bts="input.handle"]')
		this.$maxBlocksInput = $neo.filter('[data-neo-bts="input.maxBlocks"]')
		this.$deleteButton = $neo.filter('[data-neo-bts="button.delete"]')

		this._handleGenerator = new Craft.HandleGenerator(this.$nameInput, this.$handleInput)

		this.addListener(this.$nameInput, 'keyup change', () => this.setName(this.$nameInput.val()))
		this.addListener(this.$handleInput, 'keyup change', () => this.setHandle(this.$handleInput.val()))
		this.addListener(this.$maxBlocksInput, 'keyup change', () => this.setMaxBlocks(this.$maxBlocksInput.val()))
		this.addListener(this.$deleteButton, 'click', () => this.destroy())
	},

	getFocusInput()
	{
		return this.$nameInput
	},

	getId()
	{
		return this._id
	},

	isNew()
	{
		return /^new/.test(this.getId())
	},

	getErrors()
	{
		return this._errors
	},

	setSortOrder(sortOrder)
	{
		this.base(sortOrder)

		this.$sortOrderInput.val(this.getSortOrder())
	},

	getName() { return this._name },
	setName(name)
	{
		const oldName = this._name
		this._name = name

		this.$nameInput.val(this._name)

		this.trigger('change', {
			property: 'name',
			oldValue: oldName,
			newValue: this._name
		})
	},

	getHandle() { return this._handle },
	setHandle(handle)
	{
		const oldHandle = this._handle
		this._handle = handle

		this.$handleInput.val(this._handle)

		this.trigger('change', {
			property: 'handle',
			oldValue: oldHandle,
			newValue: this._handle
		})
	},

	getMaxBlocks() { return this._maxBlocks },
	setMaxBlocks(maxBlocks)
	{
		const oldMaxBlocks = this._maxBlocks
		this._maxBlocks = Math.max(0, maxBlocks|0)

		this.$maxBlocksInput.val(this._maxBlocks > 0 ? this._maxBlocks : null)

		this.trigger('change', {
			property: 'maxBlocks',
			oldValue: oldMaxBlocks,
			newValue: this._maxBlocks
		})
	}
},
{
	_totalNewBlockTypes: 0,

	getNewId()
	{
		return `new${this._totalNewBlockTypes++}`
	}
})
