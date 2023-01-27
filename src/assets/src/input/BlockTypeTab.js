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
  },

  getErrors () { return Array.from(this._errors) },

  getName () { return this._name }
})
