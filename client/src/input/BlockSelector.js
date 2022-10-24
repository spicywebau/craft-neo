const _defaults = {
  $ownerContainer: null,
  blockTypes: [],
  groups: [],
  items: null
}

export default class {
  _blockTypes = []
  _blockTypeGroups = []

  constructor (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    if (settings.items) {
      this._items = Array.from(settings.items)
      this._blockTypes = this._items.filter(i => i.getType() === 'blockType')
      this._blockTypeGroups = this._items.filter(i => i.getType() === 'group')
    } else {
      this._blockTypes = Array.from(settings.blockTypes)
      this._blockTypeGroups = Array.from(settings.groups)
      this._items = [...this._blockTypes, ...this._blockTypeGroups].sort((a, b) => a.getSortOrder() - b.getSortOrder())
    }

    this.$ownerContainer = settings.$ownerContainer
    this._field = settings.field
  }

  getBlockTypes () {
    return Array.from(this._blockTypes)
  }

  getBlockTypeGroups () {
    return Array.from(this._blockTypeGroups)
  }
}
