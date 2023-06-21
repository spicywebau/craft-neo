import $ from 'jquery'
import Craft from 'craft'
import Garnish from 'garnish'
import Item from './Item'
import NS from '../namespace'
import BlockTypeFieldLayout from './BlockTypeFieldLayout'

const _defaults = {
  namespace: [],
  fieldLayout: null
}

export default Item.extend({

  _templateNs: [],
  _loaded: false,

  init (settings = {}) {
    this.base(settings)

    const settingsObj = this.getSettings()
    settings = Object.assign({}, _defaults, settings)

    this._templateNs = NS.parse(settings.namespace)
    this._field = settings.field
    this._fieldLayout = settings.fieldLayout
    const sidebarItem = this.getField()?.$sidebarContainer.find(`[data-neo-bt="container.${this.getId()}`)

    if (sidebarItem?.length > 0) {
      this.$container = sidebarItem
    } else {
      this.$container = this._generateBlockType(settingsObj)
    }

    const $neo = this.$container.find('[data-neo-bt]')
    this.$nameText = $neo.filter('[data-neo-bt="text.name"]')
    this.$handleText = $neo.filter('[data-neo-bt="text.handle"]')
    this.$moveButton = $neo.filter('[data-neo-bt="button.move"]')
    this.$actionsButton = $neo.filter('[data-neo-bt="button.actions"]')
    this.$actionsMenu = $neo.filter('[data-neo-bt="container.menu"]')

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

  _generateBlockType (settings) {
    const sortOrderNamespace = [...this._templateNs]
    sortOrderNamespace.pop()
    NS.enter(sortOrderNamespace)
    const sortOrderName = NS.fieldName('sortOrder')
    NS.leave()
    const errors = settings.getErrors()
    const hasErrors = (Array.isArray(errors) ? errors : Object.keys(errors)).length > 0

    return $(`
      <div class="nc_sidebar_list_item${hasErrors ? ' has-errors' : ''}" data-neo-bt="container.${this.getId()}">
        <div class="label" data-neo-bt="text.name">${settings.getName()}</div>
        <div class="smalltext light code" data-neo-bt="text.handle">${settings.getHandle()}</div>
        <a class="move icon" title="${Craft.t('neo', 'Reorder')}" role="button" data-neo-bt="button.move"></a>
        <button class="settings icon menubtn" title="${Craft.t('neo', 'Actions')}" role="button" type="button" data-neo-bt="button.actions"></button>
        <div class="menu" data-neo-bt="container.menu">
          <ul class="padded">
            <li><a data-icon="field" data-action="copy">${Craft.t('neo', 'Copy')}</a></li>
            <li class="disabled"><a data-icon="brush" data-action="paste">${Craft.t('neo', 'Paste')}</a></li>
            <li><a data-icon="share" data-action="clone">${Craft.t('neo', 'Clone')}</a></li>
            <li><a class="error" data-icon="remove" data-action="delete">${Craft.t('neo', 'Delete')}</a></li>
          </ul>
        </div>
        <input type="hidden" name="${sortOrderName}[]" value="blocktype:${this.getId()}" data-neo-gs="input.sortOrder">
      </div>`)
  },

  getId () {
    return this.getSettings().getId()
  },

  getFieldLayout () {
    return this._fieldLayout
  },

  /**
   * @deprecated in 3.8.0, use load() instead
   */
  loadFieldLayout () {
    this.load()
  },

  /**
   * @since 3.8.0
   * @returns Promise
   */
  load () {
    if (this._loaded) {
      // Already loaded
      return Promise.resolve()
    }

    this.trigger('beforeLoad')
    this.trigger('beforeLoadFieldLayout') // TODO: remove in 4.0.0
    const settings = this.getSettings()
    const layout = settings.getFieldLayoutConfig()
    const layoutId = settings.getFieldLayoutId()
    const data = {
      blockTypeId: this.getId(),
      layout
    }

    return new Promise((resolve, reject) => {
      Craft.sendActionRequest('POST', 'neo/configurator/render-block-type', { data })
        .then(response => {
          this._fieldLayout = new BlockTypeFieldLayout({
            namespace: [...this._templateNs, this._id],
            html: response.data.layoutHtml,
            id: layoutId,
            blockTypeId: data.blockTypeId
          })
          this._settings.createContainer({
            html: response.data.settingsHtml.replace(/__NEOBLOCKTYPE_ID__/g, data.blockTypeId),
            js: response.data.settingsJs.replace(/__NEOBLOCKTYPE_ID__/g, data.blockTypeId)
          })
          this._field?.addItem(this)
          this._loaded = true

          this.trigger('afterLoad')
          this.trigger('afterLoadFieldLayout') // TODO: remove in 4.0.0
          resolve()
        })
        .catch(reject)
    })
  },

  toggleSelect: function (select) {
    this.base(select)

    const settings = this.getSettings()
    const fieldLayout = this.getFieldLayout()
    const selected = this.isSelected()

    if (settings?.$container ?? false) {
      settings.$container.toggleClass('hidden', !selected)
    }

    if (fieldLayout) {
      fieldLayout.$container.toggleClass('hidden', !selected)
    } else if (selected) {
      this.load()
    }

    this.$container.toggleClass('is-selected', selected)
  },

  _updateTemplate () {
    const settings = this.getSettings()

    if (settings) {
      this.$nameText.text(settings.getName())
      this.$handleText.text(settings.getHandle())
      this.$container.toggleClass('is-child', !settings.getTopLevel())
    }
  },

  '@actionSelect' (e) {
    const $option = $(e.option)

    if ($option.hasClass('disabled')) {
      return
    }

    switch ($option.attr('data-action')) {
      case 'copy':
        this.trigger('copy')
        break
      case 'paste':
        this.trigger('paste')
        break
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
