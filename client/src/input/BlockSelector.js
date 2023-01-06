import $ from 'jquery'
import Garnish from 'garnish'

const _defaults = {
  $ownerContainer: null,
  blockTypes: [],
  groups: [],
  items: null
}

class BlockSelector {
  static BUTTON_INFO = 'data-neo-bn-info'
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

  getField () {
    return this._field
  }

  getBlockTypes () {
    return Array.from(this._blockTypes)
  }

  getBlockTypeGroups () {
    return Array.from(this._blockTypeGroups)
  }

  getBlockTypeByButton ($button) {
    const btHandle = $button.attr(BlockSelector.BUTTON_INFO)

    return this._blockTypes.find(bt => bt.getHandle() === btHandle)
  }

  updateResponsiveness () {}
}

const GarnishBlockSelector = Garnish.Base.extend({

  init (buttons) {
    this._buttons = buttons
    this.$container = this._buttons.$container
    this.addListener(this._buttons.$blockButtons, 'activate', '@newBlock')
  },

  initUi () {
    this._buttons.initUi()
  },

  getBlockTypes () {
    return this._buttons.getBlockTypes()
  },

  getGroups () {
    return this._buttons.getBlockTypeGroups()
  },

  getMaxBlocks () {
    return this._maxBlocks
  },

  updateButtonStates (blocks = [], additionalCheck = null, block = null) {
    this._buttons.updateState(blocks, additionalCheck, block)
  },

  updateResponsiveness () {
    this._buttons.updateResponsiveness()
  },

  getBlockTypeByButton ($button) {
    return this._buttons.getBlockTypeByButton($button)
  },

  '@newBlock' (e) {
    const $button = $(e.currentTarget)
    const blockTypeHandle = $button.attr(BlockSelector.BUTTON_INFO)
    const blockType = this._buttons.getBlockTypes().find(bt => bt.getHandle() === blockTypeHandle)

    this.trigger('newBlock', {
      blockType
    })
  }
})

export { BlockSelector, GarnishBlockSelector }
