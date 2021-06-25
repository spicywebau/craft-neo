import $ from 'jquery'
import Craft from 'craft'
import Garnish from 'garnish'
import Item from './Item'
import NS from '../namespace'
import renderTemplate from './templates/blocktype.twig'
import '../twig-extensions'

const _defaults = {
  namespace: [],
  fieldLayout: null
}

export default Item.extend({

  _templateNs: [],

  init (settings = {}) {
    this.base(settings)

    const settingsObj = this.getSettings()
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._fieldLayout = settings.fieldLayout

    NS.enter(this._templateNs)

    this.$container = $(renderTemplate({
      settings: settingsObj,
      fieldLayout: this._fieldLayout
    }))

    NS.leave()

    const $neo = this.$container.find('[data-neo-bt]')
    this.$nameText = $neo.filter('[data-neo-bt="text.name"]')
    this.$moveButton = $neo.filter('[data-neo-bt="button.move"]')
    this.$actionsButton = $neo.filter('[data-neo-bt="button.actions"]')

    this._actionsMenu = new Garnish.MenuBtn(this.$actionsButton)
    this._actionsMenu.on('optionSelect', e => this['@actionSelect'](e))

    // Stop the actions button click from selecting the block type and closing the menu
    this.addListener(this.$actionsButton, 'click', e => e.stopPropagation())

    if (settingsObj) {
      settingsObj.on('change', () => this._updateTemplate())
      settingsObj.on('destroy', () => this.trigger('destroy'))

      this._updateTemplate()
    }

    this.deselect()
  },

  getFieldLayout () {
    return this._fieldLayout
  },

  toggleSelect: function (select) {
    this.base(select)

    const settings = this.getSettings()
    const fieldLayout = this.getFieldLayout()
    const selected = this.isSelected()

    if (settings) {
      settings.$container.toggleClass('hidden', !selected)
    }

    if (fieldLayout) {
      fieldLayout.$container.toggleClass('hidden', !selected)
    }

    this.$container.toggleClass('is-selected', selected)
  },

  _updateTemplate () {
    const settings = this.getSettings()
    const fieldLayout = this.getFieldLayout()

    if (settings) {
      this.$nameText.text(settings.getName())
      this.$container.toggleClass('is-child', !settings.getTopLevel())

      if (fieldLayout) {
        fieldLayout.setBlockName(settings.getName())
      }
    }
  },

  '@actionSelect' (e) {
    const $option = $(e.option)

    if ($option.hasClass('disabled')) {
      return
    }

    switch ($option.attr('data-action')) {
      case 'clone':
        this.trigger('clone')
        break
      case 'delete':
        if (window.confirm(Craft.t('neo', 'Are you sure you want to delete this block type?'))) {
          this.getSettings().destroy()
        }
    }
  }
})
