import $ from 'jquery'

const $fieldType = $('#type')
const $fieldId = $('input[name="fieldId"]')
const $matrixSettings = $('#Matrix')

if($fieldType.val() === 'Neo' && $fieldId.length > 0)
{
	$matrixSettings.prepend(`
		<hr>
		<div class="field">
			<div class="heading">
				<label>${ Craft.t('neo', "Convert from Neo") }</label>
				<div class="instructions"><p>${ Craft.t('neo', "This field is currently of the Neo type. You may automatically convert it to Matrix along with all of it's content.") }</p></div>
			</div>
			<div class="input ltr">
				<input id="Matrix-convert_button" type="button" class="btn submit" value="${ Craft.t('neo', "Convert") }">
				<span id="Matrix-convert_spinner" class="spinner hidden"></span>
			</div>
			<p class="warning">${ Craft.t('neo', "By converting to Matrix, structural information will be lost.") }</p>
		</div>
		<hr>
	`)

	const $convert = $('#Matrix-convert_button')
	const $spinner = $('#Matrix-convert_spinner')
	const $form = Craft.cp.$primaryForm
	const $formButton = $form.find('input[type="submit"]')
	let enabled = true

	function toggleState(state)
	{
		enabled = !!state

		$convert.toggleClass('disabled', !enabled)
		$formButton.toggleClass('disabled', !enabled)

		if(enabled)
		{
			$form.off('submit.neo')
		}
		else
		{
			$form.on('submit.neo', e => e.preventDefault())
		}
	}

	function perform()
	{
		toggleState(false)

		$spinner.removeClass('hidden')

		Craft.postActionRequest('neo/convertToMatrix', { fieldId: $fieldId.val() }, (response, textStatus) =>
		{
			if(response.success)
			{
				// Prevent the "Do you want to reload this site?" prompt from showing
				Craft.cp.removeListener(Garnish.$win, 'beforeunload')

				window.location.reload()
			}
			else
			{
				toggleState(true)

				Craft.cp.displayError(Craft.t('neo', "Could not convert Neo field to Matrix"))

				if(response.errors && response.errors.length > 0)
				{
					for(let error of response.errors)
					{
						Craft.cp.displayError(error)
					}
				}
			}
		})
	}

	$convert.on('click', e =>
	{
		e.preventDefault()

		if(enabled && confirm(Craft.t('neo', "Are you sure? This is a one way operation. You cannot undo conversion from Neo to Matrix.")))
		{
			perform()
		}
	})
}
