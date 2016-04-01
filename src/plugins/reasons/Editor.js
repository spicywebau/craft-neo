import Garnish from 'garnish'

let counter = 0

export default Editor => class extends Editor
{
	constructor($el, conditionals, blockId)
	{
		super($el, conditionals)

		this._blockId = blockId
		this.settings.formSelector = '.fieldlayoutform'

		this.patchInit()
	}

	patchInit()
	{
		if(counter === 0)
		{
			Garnish.$doc.on('click.neoReasons', '.menu a', e => this.patchOnFieldSettingsMenuItemClick(e))
		}

		this.onFieldSettingsMenuItemClick = function() {}

		super.init()

		this.$conditionalsInput.prop('name', `neo[reasons][${this._blockId}]`)
		this.$conditionalsIdInput.prop('name', `neo[reasonsId][${this._blockId}]`)

		counter++
	}

	destroy()
	{
		counter = Math.max(counter - 1, 0)

		if(counter === 0)
		{
			Garnish.$doc.off('.neoReasons')
		}
	}

	patchOnFieldSettingsMenuItemClick(e)
	{
		super.onFieldSettingsMenuItemClick(e)
	}
}
