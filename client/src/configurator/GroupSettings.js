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

    this.$container = this._generateGroupSettings()

    const $neo = this.$container.find('[data-neo-gs]')
    this.$sortOrderInput = $neo.filter('[data-neo-gs="input.sortOrder"]')
    this.$nameInput = $neo.filter('[data-neo-gs="input.name"]')
    this.$deleteButton = $neo.filter('[data-neo-gs="button.delete"]')
    this.$alwaysShowDropdownContainer = $neo.filter('[data-neo-gs="container.alwaysShowDropdown"]')

    this.setSortOrder(settings.sortOrder)
    this.setName(settings.name)

    this.addListener(this.$nameInput, 'keyup change', () => this.setName(this.$nameInput.val()))
    this.addListener(this.$deleteButton, 'click', () => {
      if (window.confirm(Craft.t('neo', 'Are you sure you want to delete this group?'))) {
        this.destroy()
      }
    })
  },

  _generateGroupSettings () {
    NS.enter(this._templateNs)
    const sortOrderName = NS.fieldName('sortOrder')
    const inputId = NS.value('name', '-')
    const inputName = NS.fieldName('name')
    const alwaysShowDropdownId = NS.value('alwaysShowDropdown', '-')
    const alwaysShowDropdownName = NS.fieldName('alwaysShowDropdown')
    NS.leave()
    const alwaysShowDropdownOptions = [
      {
        value: 'show',
        label: Craft.t('neo', 'Show')
      },
      {
        value: 'hide',
        label: Craft.t('neo', 'Hide')
      },
      {
        value: 'global',
        label: this._defaultAlwaysShowGroupDropdowns ? Craft.t('neo', 'Use global setting (Show)') : Craft.t('neo', 'Use global setting (Hide)')
      }
    ]

    return $(`
      <div>
      <input type="hidden" name="${sortOrderName}" value="${this.getSortOrder()}" data-neo-gs="input.sortOrder">
      <div>
        ${this._input({
            type: 'text',
            id: inputId,
            name: inputName,
            label: Craft.t('neo', 'Name'),
            instructions: Craft.t('neo', 'This can be left blank if you just want an unlabeled separator.'),
            value: this.getName(),
            attributes: {
                'data-neo-gs': 'input.name'
            }
        })}
        <div data-neo-gs="container.alwaysShowDropdown">
          <div class="field">
            ${Craft.ui.createSelectField({
              label: Craft.t('neo', 'Always Show Dropdown?'),
              instructions: Craft.t('neo', 'Whether to show the dropdown for this group if it only has one available block type.'),
              id: alwaysShowDropdownId,
              name: alwaysShowDropdownName,
              options: alwaysShowDropdownOptions,
              value: this._alwaysShowDropdown ? 'show' : (this._alwaysShowDropdown === false ? 'hide' : 'global')
            }).html()}
          </div>
        </div>
      </div>
      <hr>
      <a class="error delete" data-neo-gs="button.delete">${Craft.t('neo', 'Delete group')}</a>
    </div>`)
  },

  getFocusInput () {
    return this.$nameInput
  },

  getId () {
    return this._id
  },

  setSortOrder (sortOrder) {
    this.base(sortOrder)

    this.$sortOrderInput.val(this.getSortOrder())
  },

  getName () { return this._name },
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

  getAlwaysShowDropdown () { return this._alwaysShowDropdown },

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
