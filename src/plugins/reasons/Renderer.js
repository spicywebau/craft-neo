export default Renderer => class extends Renderer
{
	addEventListeners()
	{
		const fieldSel = this.settings.fieldsSelector + '[data-toggle]'

		this.$el
			.on('click.neoReasons', fieldSel, e => this.onInputWrapperClick(e))
			.on('change.neoReasons keyup.neoReasons', fieldSel + ' :input', e => this.onFieldInputChange(e))
	}

	removeEventListeners()
	{
		this.$el.off('.neoReasons')
	}

	addLivePreviewListeners() {}
	removeLivePreviewListeners() {}
}
