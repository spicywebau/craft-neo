import $ from 'jquery'
import Garnish from 'garnish'
import Craft from 'craft'

import SettingsModal from './BlockTypeSettingsModal'

import renderTemplate from './templates/block-type.twig'

export default Garnish.Base.extend({

	init(name, handle, id = null)
	{
		id = (isNaN(id) ? `new${this._totalNewBlockTypes++}` : id)
	}
})
