import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import renderTemplate from './fieldlayout.twig'
import '../../twig-extensions'

export default Garnish.Base.extend({

	init()
	{
		this.$container = $(renderTemplate())
	},

	addFieldGroup(group)
	{

	},

	removeFieldGroup(group)
	{

	},

	addField(field, group)
	{

	},

	removeField(field)
	{

	},

	addTab(tab)
	{

	},

	removeTab(tab)
	{

	},

	useField(field, tabId)
	{

	}
})
