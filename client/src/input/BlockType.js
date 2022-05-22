import Garnish from 'garnish'

import Tab from './BlockTypeTab'

const _defaults = {
  id: -1,
  fieldLayoutId: -1,
  sortOrder: 0,
  name: '',
  handle: '',
  maxBlocks: 0,
  maxSiblingBlocks: 0,
  maxChildBlocks: 0,
  childBlocks: false,
  topLevel: true,
  tabs: [],
  hasChildBlocksUiElement: false
}

export default Garnish.Base.extend({

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    this._id = settings.id | 0
    this._fieldLayoutId = settings.fieldLayoutId | 0
    this._sortOrder = settings.sortOrder | 0
    this._name = settings.name
    this._handle = settings.handle
    this._description = settings.description
    this._icon = settings.icon
    this._maxBlocks = settings.maxBlocks | 0
    this._maxSiblingBlocks = settings.maxSiblingBlocks | 0
    this._maxChildBlocks = settings.maxChildBlocks | 0
    this._childBlocks = settings.childBlocks
    this._topLevel = settings.topLevel
    this._tabs = settings.tabs.tabNames?.map(tab => tab instanceof Tab ? tab : new Tab({ name: tab })) ?? []
    this._html = settings.tabs.html ?? ''
    this._js = settings.tabs.js ?? ''
    this._hasChildBlocksUiElement = settings.hasChildBlocksUiElement
  },

  getType () { return 'blockType' },
  getId () { return this._id },
  getFieldLayoutId () { return this._fieldLayoutId },
  getSortOrder () { return this._sortOrder },
  getName () { return this._name },
  getHandle () { return this._handle },
  getDescription () { return this._description },
  getIcon () { return this._icon },
  getMaxBlocks () { return this._maxBlocks },
  getMaxSiblingBlocks () { return this._maxSiblingBlocks },
  getMaxChildBlocks () { return this._maxChildBlocks },
  getChildBlocks () { return this._childBlocks },
  getTopLevel () { return this._topLevel },
  getTabs () { return Array.from(this._tabs) },

  getHtml (blockId = null) {
    return this._replaceBlockIdPlaceholder(this._html, blockId)
  },

  getJs (blockId = null) {
    return this._replaceBlockIdPlaceholder(this._js, blockId)
  },

  _replaceBlockIdPlaceholder (input, blockId = null) {
    return blockId !== null ? input.replace(/__NEOBLOCK__/g, blockId) : input
  },

  getChildBlockItems (items) {
    const firstPass = items.filter(item => item.getType() === 'group' || this.hasChildBlock(item.getHandle()))
    return firstPass.filter((item, i) => {
      if (item.getType() === 'group') {
        const nextItem = firstPass[i + 1]
        return nextItem && nextItem.getType() !== 'group'
      }

      return true
    })
  },

  isParent () {
    const cb = this.getChildBlocks()
    return cb === true || cb === '*' || (Array.isArray(cb) && cb.length > 0)
  },

  hasChildBlock (handle) {
    const cb = this.getChildBlocks()
    return cb === true || cb === '*' || (Array.isArray(cb) && cb.includes(handle))
  },

  isValidChildBlock (block) {
    return this.hasChildBlock(block.getBlockType().getHandle())
  },

  hasChildBlocksUiElement () {
    return this._hasChildBlocksUiElement
  }
})
