import $ from 'jquery'

const $fieldType = $('#type')
const $matrixSettings = $('#Matrix')

if($fieldType.val() === 'Neo')
{
	$matrixSettings.prepend(`
		<hr>
		<div class="field">
			<div class="heading">
				<label>${ Craft.t("Convert from Neo") }</label>
				<div class="instructions"><p>${ Craft.t("This field is currently of the Neo type. You may automatically convert it to Matrix along with all of it's content.") }</p></div>
			</div>
			<div class="input ltr">
				<a href="#" class="btn submit">${ Craft.t("Convert") }</a>
			</div>
			<p class="warning">${ Craft.t("By converting to Matrix, structural information will be lost.") }</p>
		</div>
		<hr>
	`)
}
