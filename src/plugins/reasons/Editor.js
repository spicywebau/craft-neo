import Garnish from 'garnish'

let editors = []

function refresh()
{
	editors.forEach(editor => editor.refresh())
}

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
		const onFSMIClick = this.onFieldSettingsMenuItemClick
		this.onFieldSettingsMenuItemClick = function() {}

		if(editors.length === 0)
		{
			Garnish.$doc.on('click.ncr', '.menu a', e => onFSMIClick.call({refresh, templates: this.templates}, e))
		}

		super.init()

		this.$conditionalsInput.prop('name', `neo[reasons][${this._blockId}]`)
		this.$conditionalsIdInput.prop('name', `neo[reasonsId][${this._blockId}]`)

		editors.push(this)
	}

	destroy()
	{
		editors = editors.filter(editor => editor !== this)

		if(editors.length === 0)
		{
			Garnish.$doc.off('.ncr')
		}
	}
}
