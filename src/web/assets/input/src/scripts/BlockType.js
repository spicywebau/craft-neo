import Garnish from 'garnish'
import Craft from 'craft'
import NS from './namespace'

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
   * @since 4.2.0
   */
  async newBlock (settings = {}) {
    NS.enter(this._field.getNamespace())
    const data = {
      namespace: NS.toFieldName(),
      fieldId: this._field?.getId(),
      siteId: this._field?.getSiteId(),
      unsavedIds: this._field?.getUnsavedIds(),
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

  getDefaultVisibleLayoutElements () {
    return {
      ...this._defaultVisibleLayoutElements
    }
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
