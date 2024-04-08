import $ from 'jquery'
import Craft from 'craft'
import Garnish from 'garnish'

const $fieldType = $('#type')
const $fieldId = $('input[name="fieldId"]')

if ($fieldType.data('value') === 'benf\\neo\\Field' && $fieldId.length > 0) {
  const $form = Craft.cp.$primaryForm
  const $formButton = $form.find('input[type="submit"]')
  let $convert = $('#Matrix-convert_button')
  let $spinner = $('#Matrix-convert_spinner')
  let enabled = true

  const toggleState = state => {
    enabled = !!state

    $convert.toggleClass('disabled', !enabled)
    $formButton.toggleClass('disabled', !enabled)

    if (enabled) {
      $form.off('submit.neo')
    } else {
      $form.on('submit.neo', e => e.preventDefault())
    }
  }

  const perform = () => {
    toggleState(false)

    $spinner.removeClass('hidden')

    Craft.postActionRequest('neo/conversion/convert-to-matrix', { fieldId: $fieldId.val() }, (response, textStatus) => {
      if (response.success) {
        // Prevent the "Do you want to reload this site?" prompt from showing
        Craft.cp.removeListener(Garnish.$win, 'beforeunload')

        window.location.reload()
      } else {
        toggleState(true)

        Craft.cp.displayError(Craft.t('neo', 'Could not convert Neo field to Matrix'))

        if (response.errors && response.errors.length > 0) {
          for (const error of response.errors) {
            Craft.cp.displayError(error)
          }
        }
      }
    })
  }

  const applyHtml = () => {
    const $matrixSettings = $('#craft-fields-Matrix')

    if ($matrixSettings.find('#conversion-prompt').length > 0) {
      return
    }

    $matrixSettings.prepend(`
      <div id="conversion-prompt">
        <div class="field">
          <div class="heading">
            <label>${Craft.t('neo', 'Convert from Neo')}</label>
            <div class="instructions"><p>${Craft.t('neo', 'This field is currently of the Neo type. You may automatically convert it to Matrix along with all of its content.')}</p></div>
          </div>
          <div class="input ltr">
            <input id="Matrix-convert_button" type="button" class="btn submit" value="${Craft.t('neo', 'Convert')}">
            <span id="Matrix-convert_spinner" class="spinner hidden"></span>
          </div>
          <p class="warning">${Craft.t('neo', 'By converting to Matrix, structural information will be lost.')}</p>
        </div>
      </div>
      <hr>
    `)

    $convert = $('#Matrix-convert_button')
    $spinner = $('#Matrix-convert_spinner')

    $convert.on('click', e => {
      e.preventDefault()

      if (enabled && window.confirm(Craft.t('neo', 'Are you sure? This is a one way operation. You cannot undo conversion from Neo to Matrix.'))) {
        perform()
      }
    })
  }

  const settingsObserver = new window.MutationObserver(applyHtml)
  settingsObserver.observe(document.getElementById('settings'), { childList: true, subtree: true })
}
