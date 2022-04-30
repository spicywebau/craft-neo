import $ from 'jquery'
import Craft from 'craft'
import Garnish from 'garnish'

const _fieldDefaults = {
  id: false,
  label: false,
  instructions: false,
  required: false,
  input: '',
  warning: false,
  errors: false
}

const _lightswitchDefaults = {
  attributes: {},
  name: '',
  checked: false
}

export default Garnish.Base.extend({

  $container: new $(),
  _sortOrder: 0,

  getSortOrder () {
    return this._sortOrder
  },

  setSortOrder (sortOrder) {
    const oldSortOrder = this._sortOrder
    this._sortOrder = sortOrder | 0

    if (oldSortOrder !== this._sortOrder) {
      this.trigger('change', {
        property: 'sortOrder',
        oldValue: oldSortOrder,
        newValue: this._sortOrder
      })
    }
  },

  getFocusElement () {
    return new $()
  },

  destroy () {
    this.trigger('destroy')
  },

  _field (settings = {}) {
    settings = Object.assign({}, _fieldDefaults, settings)

    const fieldHtml = []
    fieldHtml.push(`
      <div class="field">`)

    if (settings.label || settings.instructions) {
      if (settings.label) {
        fieldHtml.push(`
        <div class="heading">
          <label${settings.required ? ' class="required"' : ''}${settings.id ? ` for="${settings.id}"` : ''}>
            ${settings.label}
          </label>
        </div>`)
      }

      if (settings.instructions) {
        fieldHtml.push(`
        <div class="instructions">${settings.instructions}</div>`)
      }
    }

    fieldHtml.push(`
        <div class="input${settings.errors ? ' errors' : ''}">
          ${settings.input}
        </div>`)

    if (settings.warning) {
      fieldHtml.push(`
        <p class="warning">${settings.warning}</p>`)
    }

    if (settings.errors) {
      fieldHtml.push(`
        <ul class="errors">`)

      for (const error of settings.errors) {
        fieldHtml.push(`
          <li>${error}</li>`)
      }

      fieldHtml.push(`
        </ul>`)
    }

    fieldHtml.push(`
      </div>`)

    return fieldHtml.join('')
  },

  _lightswitch (settings = {}) {
    settings = Object.assign({}, _lightswitchDefaults, settings)

    const input = $(`
      <div class="lightswitch${settings.checked ? ' on' : ''}" tabindex="0"${this._attributes(settings.attributes)}>
        <div class="lightswitch-container">
          <div class="label on"></div>
          <div class="handle"></div>
          <div class="label off"></div>
        </div>
        <input type="hidden" name="${settings.name}" value="${settings.checked ? '1' : ''}">
      </div>`)

    return $('<div class="field"/>').append(Craft.ui.createField(input, settings)).html()
  },

  _attributes (attributes) {
    const attributesHtml = []

    for (const attribute in attributes) {
      attributesHtml.push(` ${attribute}="${attributes[attribute]}"`)
    }

    return attributesHtml.join('')
  },

  _refreshSetting ($container, showSetting, animate) {
    animate = !Garnish.prefersReducedMotion() && (typeof animate === 'boolean' ? animate : true)

    if (animate) {
      if (showSetting) {
        if ($container.hasClass('hidden')) {
          $container
            .removeClass('hidden')
            .css({
              opacity: 0,
              marginBottom: -($container.outerHeight())
            })
            .velocity({
              opacity: 1,
              marginBottom: 24
            }, 'fast')
        }
      } else if (!$container.hasClass('hidden')) {
        $container
          .css({
            opacity: 1,
            marginBottom: 24
          })
          .velocity({
            opacity: 0,
            marginBottom: -($container.outerHeight())
          }, 'fast', () => {
            $container.addClass('hidden')
          })
      }
    } else {
      $container
        .toggleClass('hidden', !showSetting)
        .css('margin-bottom', showSetting ? 24 : '')
    }
  }
})
