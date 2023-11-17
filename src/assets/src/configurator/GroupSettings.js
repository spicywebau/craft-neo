import $ from 'jquery'
import Craft from 'craft'
import NS from '../namespace'
import Settings from './Settings'

const _defaults = {
  namespace: [],
  id: null,
  sortOrder: 0,
  name: '',
  alwaysShowDropdown: null,
  defaultAlwaysShowGroupDropdowns: true
}

export default Settings.extend({

  _templateNs: [],

  $container: null,
  $sortOrderInput: new $(),
  $nameInput: new $(),
  $handleInput: new $(),
  $maxBlocksInput: new $(),

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._id = settings.id
    this._alwaysShowDropdown = settings.alwaysShowDropdown
    this._defaultAlwaysShowGroupDropdowns = settings.defaultAlwaysShowGroupDropdowns
    this._originalSettings = settings

    if (typeof settings.html !== 'undefined' && settings.html !== null) {
      this.createContainer({
        html: settings.html,
        js: settings.js
      })
    }
  },

  createContainer (containerData) {
    // Only create it if it doesn't already exist
    if (this.$container !== null) {
      return
    }

    this.$container = $(containerData.html)
    this._js = containerData.js ?? ''

    const $neo = this.$container.find('[data-neo-gs]')
    this.$nameInput = $neo.filter('[data-neo-gs="input.name"]')
    this.$deleteButton = $neo.filter('[data-neo-gs="button.delete"]')
    this.$alwaysShowDropdownContainer = $neo.filter('[data-neo-gs="container.alwaysShowDropdown"]')

    this.setName(this._originalSettings.name)

    this.addListener(this.$nameInput, 'keyup change', () => this.setName(this.$nameInput.val()))
    this.addListener(this.$deleteButton, 'click', () => {
      if (window.confirm(Craft.t('neo', 'Are you sure you want to delete this group?'))) {
        this.destroy()
      }
    })
  },

  getFocusInput () {
    return this.$nameInput
  },

  getId () {
    return this._id
  },

  getName () { return this._name ?? this._originalSettings.name },
  setName (name) {
    if (name !== this._name) {
      const oldName = this._name
      this._name = name

      this.$nameInput.val(this._name)
      this._refreshAlwaysShowDropdown()

      this.trigger('change', {
        property: 'name',
        oldValue: oldName,
        newValue: this._name
      })
    }
  },

  getAlwaysShowDropdown () { return this._alwaysShowDropdown ?? this._originalSettings.alwaysShowDropdown },

  _refreshAlwaysShowDropdown (animate) {
    this._refreshSetting(this.$alwaysShowDropdownContainer, !!this._name, animate)
  }
},
{
  _totalNewGroups: 0,

  getNewId () {
    return `new${this._totalNewGroups++}`
  }
})
