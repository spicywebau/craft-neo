import $ from 'jquery'
import Craft from 'craft'
import NS from '../namespace'
import Settings from './Settings'

const _defaults = {
  namespace: [],
  id: null,
  sortOrder: 0,
  name: ''
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

    this.setSortOrder(settings.sortOrder)
    this.setName(settings.name)

    this.$container = this._generateGroupSettings()

    const $neo = this.$container.find('[data-neo-gs]')
    this.$sortOrderInput = $neo.filter('[data-neo-gs="input.sortOrder"]')
    this.$nameInput = $neo.filter('[data-neo-gs="input.name"]')
    this.$deleteButton = $neo.filter('[data-neo-gs="button.delete"]')

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
    NS.leave()

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

      this.trigger('change', {
        property: 'name',
        oldValue: oldName,
        newValue: this._name
      })
    }
  }
},
{
  _totalNewGroups: 0,

  getNewId () {
    return `new${this._totalNewGroups++}`
  }
})
