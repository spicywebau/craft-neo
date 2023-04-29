import Garnish from 'garnish'

const _defaults = {
  name: '',
  errors: []
}

export default Garnish.Base.extend({

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    this._name = settings.name
    this._errors = settings.errors
    this._uid = settings.uid
  },

  getErrors () { return Array.from(this._errors) },

  getName () { return this._name },

  /**
   * @public
   * @since 3.7.0
   * @returns this tab's UID
   */
  getUid () { return this._uid }
})
