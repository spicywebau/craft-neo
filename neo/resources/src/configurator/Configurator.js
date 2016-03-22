import $ from 'jquery'
import Garnish from 'garnish'
import Craft from 'craft'

import BlockType from './BlockType'

import renderTemplate from './templates/configurator.twig'
import '../twig-extensions'
import './styles/configurator.scss'

export default Garnish.Base.extend({

	_defaults: {
		namespace: '',
		blockTypes: []
	},

	init(settings = {})
	{
		settings = Object.assign({}, this._defaults, settings)

		// Setup <input> field information
		this.inputNamePrefix = settings.namespace
		this.inputIdPrefix = Craft.formatInputId(this.inputNamePrefix)

		// Initialise the configurator template
		this.$field = $(`\#${this.inputIdPrefix}-neo-configurator`)
		this.$inputContainer = this.$field.children('.field').children('.input')
		this.$inputContainer.html(renderTemplate())

		this.$container = this.$field.find('.input').first()

		this.$blockTypesContainer = this.$container.children('.block-types').children()
		this.$fieldLayoutContainer = this.$container.children('.field-layout').children()

		this.$blockTypeItemsContainer = this.$blockTypesContainer.children('.items')
		this.$itemsContainer = this.$blockTypeItemsContainer.children('.blocktypes')
		this.$addItemButton = this.$blockTypeItemsContainer.children('.btn.add')

		this._setContainerHeight()

		this.addListener(this.$blockTypesContainer, 'resize', '@setContainerHeight')
		this.addListener(this.$fieldLayoutContainer, 'resize', '@setContainerHeight')
		this.addListener(this.$addItemButton, 'click', '@newBlockType')
	},

	addBlockType(blockType)
	{
		this.$itemsContainer.append(blockType.$itemContainer)
	},

	'@newBlockType'()
	{
		const blockType = new BlockType()
		const settingsModal = blockType.getSettingsModal()

		const onSave = (e) =>
		{
			blockType.name = e.name
			blockType.handle = e.handle

			this.addBlockType(blockType)

			settingsModal.off('save', onSave)
		}

		settingsModal.on('save', onSave)
		settingsModal.show()
	},

	'@setContainerHeight'()
	{
		setTimeout(() => this._setContainerHeight(), 1)
	},

	_setContainerHeight()
	{
		const maxColHeight = Math.max(400,
			this.$blockTypesContainer.height(),
			this.$fieldLayoutContainer.height())

		this.$container.height(maxColHeight)
	}
})
