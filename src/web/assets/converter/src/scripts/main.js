// import Craft from 'craft'
import Garnish from 'garnish'

const fieldType = document.getElementById('type')
const fieldId = document.querySelector('input[name="fieldId"]')

if (fieldType.dataset.value === 'benf\\neo\\Field' && fieldId !== null) {
  const $form = window.Craft.cp.$primaryForm
  const $formButton = $form.find('input[type="submit"]')
  let convertButton = document.getElementById('Matrix-convert_button')
  let spinner = document.getElementById('Matrix-convert_spinner')
  let enabled = true

  const toggleState = (state) => {
    enabled = !!state

    convertButton.classList.toggle('disabled', !enabled)
    $formButton.toggleClass('disabled', !enabled)

    if (enabled) {
      $form.off('submit.neo')
    } else {
      $form.on('submit.neo', (e) => e.preventDefault())
    }
  }

  const perform = () => {
    toggleState(false)
    spinner.classList.remove('hidden')

    window.Craft.postActionRequest('neo/conversion/convert-to-matrix', { fieldId: fieldId.value }, (response, textStatus) => {
      if (response.success) {
        // Prevent the "Do you want to reload this site?" prompt from showing before page reload
        window.Craft.cp.removeListener(Garnish.$win, 'beforeunload')
        window.location.reload()
      } else {
        toggleState(true)
        window.Craft.cp.displayError(window.Craft.t('neo', 'Could not convert Neo field to Matrix'))
        response.errors?.forEach((error) => window.Craft.cp.displayError(error))
      }
    })
  }

  const applyHtml = () => {
    const matrixSettings = document.getElementById('craft-fields-Matrix')

    if (matrixSettings === null || matrixSettings.querySelector('#conversion-prompt') !== null) {
      return
    }

    matrixSettings.insertAdjacentHTML('afterbegin', `
      <div id="conversion-prompt">
        <div class="field">
          <div class="heading">
            <label>${window.Craft.t('neo', 'Convert from Neo')}</label>
            <div class="instructions"><p>${window.Craft.t('neo', 'This field is currently of the Neo type. You may automatically convert it to Matrix along with all of its content.')}</p></div>
          </div>
          <div class="input ltr">
            <input id="Matrix-convert_button" type="button" class="btn submit" value="${window.Craft.t('neo', 'Convert')}">
            <span id="Matrix-convert_spinner" class="spinner hidden"></span>
          </div>
          <p class="warning">${window.Craft.t('neo', 'By converting to Matrix, structural information will be lost.')}</p>
        </div>
      </div>
      <hr>
    `)

    convertButton = document.getElementById('Matrix-convert_button')
    spinner = document.getElementById('Matrix-convert_spinner')

    convertButton.addEventListener('click', (event) => {
      event.preventDefault()

      if (enabled && window.confirm(window.Craft.t('neo', 'Are you sure? This is a one way operation. You cannot undo conversion from Neo to Matrix.'))) {
        perform()
      }
    })
  }

  const settingsObserver = new window.MutationObserver(applyHtml)
  settingsObserver.observe(
    document.getElementById('settings'),
    {
      childList: true,
      subtree: true
    }
  )
}
