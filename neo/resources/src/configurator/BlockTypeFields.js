import $ from 'jquery'

import Garnish from 'garnish'
import Craft from 'craft'

import renderTemplate from './templates/block_type_fields.twig'
import '../twig-extensions'

export default Garnish.Base.extend({

	init()
	{
		this.$container = $(renderTemplate())
	}
})
