import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import NS from '../namespace'

import Settings from './Settings'

import renderTemplate from './templates/group_settings.twig'
import '../twig-extensions'

const _defaults = {
	namespace: [],
	sortOrder: 0,
	name: ''
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

		this.setSortOrder(settings.sortOrder)
		this.setName(settings.name)

		NS.enter(this._templateNs)

		this.$container = $(renderTemplate({
			sortOrder: this.getSortOrder(),
			name:      this.getName()
		}))

		NS.leave()

		const $neo = this.$container.find('[data-neo-gs]')
		this.$sortOrderInput = $neo.filter('[data-neo-gs="input.sortOrder"]')
		this.$nameInput = $neo.filter('[data-neo-gs="input.name"]')
		this.$deleteButton = $neo.filter('[data-neo-gs="button.delete"]')

		this.addListener(this.$nameInput, 'keyup change', () => this.setName(this.$nameInput.val()))
		this.addListener(this.$deleteButton, 'click', () => this.destroy())
	},

	getFocusInput()
	{
		return this.$nameInput
	},

	setSortOrder(sortOrder)
	{
		this.base(sortOrder)

		this.$sortOrderInput.val(this.getSortOrder())
	},

	getName() { return this._name },
	setName(name)
	{
		if(name !== this._name)
		{
			const oldName = this._name
			this._name = name

			this.$nameInput.val(this._name)

			this.trigger('change', {
				property: 'name',
				oldValue: oldName,
				newValue: this._name
			})
		}
	}
})
