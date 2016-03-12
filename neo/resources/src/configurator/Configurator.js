import $ from 'jquery'
import Garnish from 'garnish'
import Craft from 'craft'
import '../twig-extensions'

import BlockType from './BlockType'

import renderTemplate from './templates/configurator.twig'
import './styles/configurator.scss'

export default Garnish.Base.extend({

	_defaults: {
		namespace: '',
		blockTypes: []
	},

	_totalNewBlockTypes: 0,

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

		this.$blockTypesContainer = this.$container.children('.block-types').children();
		this.$fieldLayoutContainer = this.$container.children('.field-layout').children();

		this.setContainerHeight()

		this.addListener(this.$blockTypesContainer, 'resize', '_setContainerHeight');
		this.addListener(this.$fieldLayoutContainer, 'resize', '_setContainerHeight');
	},

	addBlockType(blockType)
	{

	},

	_setContainerHeight()
	{
		setTimeout(() =>
		{
			var maxColHeight = Math.max(400,
				this.$blockTypesContainer.height(),
				this.$fieldLayoutContainer.height()
			);
			this.$container.height(maxColHeight);
		}, 1)
	}
})
