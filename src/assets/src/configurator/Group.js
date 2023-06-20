import $ from 'jquery'
import Craft from 'craft'
import Item from './Item'
import NS from '../namespace'

const _defaults = {
  namespace: []
}

export default Item.extend({

  _templateNs: [],

  init (settings = {}) {
    this.base(settings)

    settings = Object.assign({}, _defaults, settings)

    const settingsObj = this.getSettings()
    this._templateNs = NS.parse(settings.namespace)
    const sidebarItem = this.getField()?.$sidebarContainer.find(`[data-neo-g="container.${this.getId()}`)

    if (sidebarItem?.length > 0) {
      this.$container = sidebarItem
    } else {
      this.$container = this._generateGroup(settingsObj)
    }

    const $neo = this.$container.find('[data-neo-g]')
    this.$nameText = $neo.filter('[data-neo-g="text.name"]')
    this.$moveButton = $neo.filter('[data-neo-g="button.move"]')

    if (settingsObj) {
      settingsObj.on('change', () => this._updateTemplate())
      settingsObj.on('destroy', () => this.trigger('destroy'))
    }

    this.deselect()
  },

  _generateGroup (settings) {
    const sortOrderNamespace = [...this._templateNs]
    sortOrderNamespace.pop()
    NS.enter(sortOrderNamespace)
    const sortOrderName = NS.fieldName('sortOrder')
    NS.leave()

    return $(`
      <div class="nc_sidebar_list_item type-heading" data-neo-g="container.${this.getId()}">
        <div class="label" data-neo-g="text.name">${settings.getName() ?? ''}</div>
        <a class="move icon" title="${Craft.t('neo', 'Reorder')}" role="button" data-neo-g="button.move"></a>
        <input type="hidden" name="${sortOrderName}[]" value="group:${this.getId()}" data-neo-g="input.sortOrder">
      </div>`)
  },

  getId () {
    return this.getSettings().getId()
  },

  toggleSelect: function (select) {
    this.base(select)

    const settings = this.getSettings()
    const selected = this.isSelected()

    if (settings) {
      settings.$container.toggleClass('hidden', !selected)
    }

    this.$container.toggleClass('is-selected', selected)
  },

  _updateTemplate () {
    const settings = this.getSettings()

    if (settings) {
      this.$nameText.text(settings.getName())
    }
  }
})
