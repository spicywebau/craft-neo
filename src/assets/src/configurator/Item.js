import Garnish from 'garnish'

const _defaults = {
  settings: null
}

export default Garnish.Base.extend({

  $container: null,
  _field: null,
  _selected: false,

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)
    this._field = settings.field
    this._settings = settings.settings
  },

  /**
   * @since 3.8.0
   * @returns Promise
   */
  load () {
    return Promise.resolve()
  },

  /**
   * @since 3.8.0
   * @returns the Neo field this item belongs to
   */
  getField () {
    return this._field
  },

  getSettings () {
    return this._settings
  },

  /**
   * @since 3.8.0
   */
  getSortOrder () {
    return this.$container.index() + 1
  },

  select () {
    this.toggleSelect(true)
  },

  deselect () {
    this.toggleSelect(false)
  },

  toggleSelect: function (select) {
    this._selected = (typeof select === 'boolean' ? select : !this._selected)

    this.trigger('toggleSelect', {
      selected: this._selected
    })
  },

  isSelected () {
    return this._selected
  }
})
