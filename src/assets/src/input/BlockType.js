import Garnish from 'garnish'
import Craft from 'craft'
import NS from '../namespace'
import Tab from './BlockTypeTab'

const _defaults = {
  id: -1,
  field: null,
  fieldLayoutId: -1,
  sortOrder: 0,
  name: '',
  handle: '',
  maxBlocks: 0,
  maxSiblingBlocks: 0,
  maxChildBlocks: 0,
  groupChildBlockTypes: true,
  childBlocks: false,
  topLevel: true,
  tabs: null,
  tabNames: [],
  hasChildBlocksUiElement: false,
  creatableByUser: true,
  deletableByUser: true,
  editableByUser: true
}

export default Garnish.Base.extend({

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    this._id = settings.id | 0
    this._field = settings.field
    this._fieldLayoutId = settings.fieldLayoutId | 0
    this._sortOrder = settings.sortOrder | 0
    this._name = settings.name
    this._handle = settings.handle
    this._description = settings.description
    this._enabled = settings.enabled
    this._minBlocks = settings.minBlocks | 0
    this._maxBlocks = settings.maxBlocks | 0
    this._minSiblingBlocks = settings.maxSiblingBlocks | 0
    this._maxSiblingBlocks = settings.maxSiblingBlocks | 0
    this._minChildBlocks = settings.minChildBlocks | 0
    this._maxChildBlocks = settings.maxChildBlocks | 0
    this._groupChildBlockTypes = settings.groupChildBlockTypes
    this._childBlocks = settings.childBlocks
    this._topLevel = settings.topLevel
    this._tabNames = settings.tabNames
    if (settings.tabs !== null) {
      this._tabs = settings.tabs.tabNames?.map(
        tab => tab instanceof Tab
          ? tab
          : new Tab({
            name: tab,
            uid: settings.tabs.tabUids[tab]
          })
      ) ?? []
    } else {
      this._tabs = null
    }
    this._html = settings.tabs?.html ?? ''
    this._js = settings.tabs?.js ?? ''
    this._defaultVisibleLayoutElements = settings.tabs?.visibleLayoutElements ?? {}
    this._hasChildBlocksUiElement = settings.hasChildBlocksUiElement
    this._creatableByUser = settings.creatableByUser
    this._deletableByUser = settings.deletableByUser
    this._editableByUser = settings.editableByUser
  },

  getType () { return 'blockType' },
  getId () { return this._id },
  getFieldLayoutId () { return this._fieldLayoutId },
  getSortOrder () { return this._sortOrder },
  getName () { return this._name },
  getHandle () { return this._handle },
  getDescription () { return this._description },
  getEnabled () { return this._enabled },
  getMinBlocks () { return this._minBlocks },
  getMaxBlocks () { return this._maxBlocks },
  getMinSiblingBlocks () { return this._minSiblingBlocks },
  getMaxSiblingBlocks () { return this._maxSiblingBlocks },
  getMinChildBlocks () { return this._minChildBlocks },
  getMaxChildBlocks () { return this._maxChildBlocks },
  getGroupChildBlockTypes () { return this._groupChildBlockTypes },
  getChildBlocks () { return this._childBlocks },
  getTopLevel () { return this._topLevel },
  getTabNames () { return this._tabNames },

  /**
   * @deprecated in 4.2.0
   */
  getTabs () { return this._tabs !== null ? Array.from(this._tabs) : null },

  /**
   * @deprecated in 4.2.0
   */
  async loadTabs () {
    if (this._tabs !== null) {
      return
    }

    NS.enter(this._field.getNamespace())
    const data = {
      namespace: NS.toFieldName(),
      siteId: this._field?.getSiteId(),
      blocks: [{
        collapsed: false,
        enabled: true,
        level: 1,
        ownerId: this._field?.getOwnerId(),
        type: this._id
      }]
    }
    NS.leave()

    const renderedBlocks = await Craft.sendActionRequest('POST', 'neo/input/render-blocks', { data })
    if (renderedBlocks.data.success) {
      if (renderedBlocks.data.headHtml) {
        Craft.appendHeadHtml(renderedBlocks.data.headHtml)
      }

      if (renderedBlocks.data.bodyHtml) {
        Craft.appendBodyHtml(renderedBlocks.data.bodyHtml)
      }

      const tabs = renderedBlocks.data.blocks[0].tabs
      this._tabs = tabs.tabNames?.map(
        tab => new Tab({
          name: tab,
          uid: tabs.tabUids[tab]
        })
      ) ?? []
      this._html = tabs.html
      this._js = tabs.js
    }
  },

  /**
   * @since 4.2.0
   */
  async newBlock (settings = {}) {
    NS.enter(this._field.getNamespace())
    const data = {
      namespace: NS.toFieldName(),
      fieldId: this._field?.getId(),
      siteId: this._field?.getSiteId(),
      blocks: [Object.assign({
        collapsed: false,
        enabled: true,
        level: 1,
        ownerId: this._field?.getOwnerId(),
        type: this._id
      }, settings)]
    }
    NS.leave()
    const response = await Craft.sendActionRequest('POST', 'neo/input/render-blocks', { data })

    return response.data.blocks[0]
  },

  getHtml (blockId = null) {
    return this._replaceBlockIdPlaceholder(this._html, blockId)
  },

  getJs (blockId = null) {
    return this._replaceBlockIdPlaceholder(this._js, blockId)
  },

  getDefaultVisibleLayoutElements () {
    return {
      ...this._defaultVisibleLayoutElements
    }
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
  },

  isCreatableByUser () {
    return this._creatableByUser
  },

  isDeletableByUser () {
    return this._deletableByUser
  },

  isEditableByUser () {
    return this._editableByUser
  }
})
