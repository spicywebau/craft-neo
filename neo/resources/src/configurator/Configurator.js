import $ from 'jquery'
import Garnish from 'garnish'
import Craft from 'craft'
import '../twigextensions'

import BlockType from './BlockType'

import renderTemplate from './templates/configurator.twig'
import './styles/configurator.scss'

console.log(renderTemplate())

export default Garnish.Base.extend({

	init()
	{
		const $template = $(renderTemplate())
	},

	setContainerHeight()
	{
		setTimeout(() =>
		{
			var maxColHeight = Math.max(400,
				this.$blockTypesColumnContainer.height(),
				this.$fieldsColumnContainer.height(),
				this.$fieldSettingsColumnContainer.height()
			);
			this.$container.height(maxColHeight);
		}, 1)
	},

	addBlockType()
	{

	}
})
