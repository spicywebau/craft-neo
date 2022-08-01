import $ from 'jquery'
import '../jquery-extensions'

import Garnish from 'garnish'
import Craft from 'craft'
import { v4 as uuidv4 } from 'uuid'
import NS from '../namespace'

import BlockType from './BlockType'
import BlockTypeSettings from './BlockTypeSettings'
import BlockTypeFieldLayout from './BlockTypeFieldLayout'
import Group from './Group'
import GroupSettings from './GroupSettings'
import './styles/configurator.scss'

const _defaults = {
  namespace: [],
  blockTypes: [],
  groups: [],
  blockTypeSettingsHtml: '',
  blockTypeSettingsJs: '',
  fieldLayoutHtml: ''
}

export default Garnish.Base.extend({

  _templateNs: [],
  _items: [],

  init (settings = {}) {
    settings = Object.assign({}, _defaults, settings)

    const inputIdPrefix = Craft.formatInputId(settings.namespace)
    const $field = $(`#${inputIdPrefix}-neo-configurator`)
    this.$container = $field.children('.field').children('.input')

    this._templateNs = NS.parse(settings.namespace)
    this._blockTypeSettingsHtml = settings.blockTypeSettingsHtml
    this._blockTypeSettingsJs = settings.blockTypeSettingsJs
    this._fieldLayoutHtml = settings.fieldLayoutHtml
    this._items = []

    const $neo = this.$container.find('[data-neo]')
    this.$mainContainer = $neo.filter('[data-neo="container.main"]')
    this.$sidebarContainer = $neo.filter('[data-neo="container.sidebar"]')
    this.$blockTypesContainer = $neo.filter('[data-neo="container.blockTypes"]')
    this.$settingsContainer = $neo.filter('[data-neo="container.settings"]')
    this.$fieldLayoutContainer = $neo.filter('[data-neo="container.fieldLayout"]')
    this.$blockTypeButton = $neo.filter('[data-neo="button.blockType"]')
    this.$groupButton = $neo.filter('[data-neo="button.group"]')
    this.$settingsButton = $neo.filter('[data-neo="button.settings"]')
    this.$fieldLayoutButton = $neo.filter('[data-neo="button.fieldLayout"]')

    this._itemSort = new Garnish.DragSort(null, {
      container: this.$blockTypeItemsContainer,
      handle: '[data-neo-bt="button.move"], [data-neo-g="button.move"]',
      axis: 'y',
      onSortChange: () => this._updateItemOrder()
    })

    // Add the existing block types and groups
    const existingItems = []
    const btNamespace = [...this._templateNs, 'blockTypes']
    const gNamespace = [...this._templateNs, 'groups']

    for (const btInfo of settings.blockTypes) {
      const btSettings = new BlockTypeSettings({
        namespace: [...btNamespace, btInfo.id],
        sortOrder: btInfo.sortOrder,
        id: btInfo.id,
        name: btInfo.name,
        handle: btInfo.handle,
        description: btInfo.description,
        maxBlocks: btInfo.maxBlocks,
        maxSiblingBlocks: btInfo.maxSiblingBlocks,
        minChildBlocks: btInfo.minChildBlocks,
        maxChildBlocks: btInfo.maxChildBlocks,
        topLevel: btInfo.topLevel,
        html: btInfo.settingsHtml,
        js: btInfo.settingsJs,
        errors: btInfo.errors,
        fieldLayoutId: btInfo.fieldLayoutId,
        fieldLayoutConfig: btInfo.fieldLayoutConfig,
        childBlockTypes: existingItems.filter(item => item instanceof BlockType)
      })

      const blockType = new BlockType({
        namespace: btNamespace,
        settings: btSettings
      })

      blockType.on('copy.configurator', () => this._copyBlockType(blockType))
      blockType.on('paste.configurator', () => this._pasteBlockType())
      blockType.on('clone.configurator', () => this._createBlockTypeFrom(blockType))
      blockType.on('beforeLoadFieldLayout.configurator', () => this.$fieldLayoutContainer.append(
        $('<span class="spinner"/></span>')
      ))
      blockType.on('afterLoadFieldLayout.configurator', () => {
        this.$fieldLayoutContainer.children('.spinner').remove()
        this._addFieldLayout(blockType.getFieldLayout())
      })
      existingItems.push(blockType)
    }

    for (const gInfo of settings.groups) {
      const gSettings = new GroupSettings({
        namespace: [...gNamespace, gInfo.id],
        sortOrder: gInfo.sortOrder,
        id: gInfo.id,
        name: gInfo.name,
        alwaysShowDropdown: gInfo.alwaysShowDropdown,
        defaultAlwaysShowGroupDropdowns: settings.defaultAlwaysShowGroupDropdowns
      })

      const group = new Group({
        namespace: gNamespace,
        settings: gSettings
      })

      existingItems.push(group)
    }

    for (const item of existingItems.sort((a, b) => a.getSettings().getSortOrder() - b.getSettings().getSortOrder())) {
      this.addItem(item)
    }

    for (const blockType of this.getBlockTypes()) {
      const btSettings = blockType.getSettings()
      const info = settings.blockTypes.find(i => i.handle === btSettings.getHandle())

      btSettings.setChildBlocks(info.childBlocks)
    }

    // Make sure menu states (for pasting block types) are updated when changing tabs
    const refreshPasteOptions = () => {
      const noPasteData = !window.localStorage.getItem('neo:copyBlockType')

      for (const blockType of this.getBlockTypes()) {
        blockType.$actionsMenu.find('[data-action="paste"]').parent().toggleClass('disabled', noPasteData)
      }
    }

    refreshPasteOptions()
    this.addListener(document, 'visibilitychange.configurator', refreshPasteOptions)

    this.selectTab('settings')

    this.addListener(this.$blockTypeButton, 'click', '@newBlockType')
    this.addListener(this.$groupButton, 'click', '@newGroup')
    this.addListener(this.$settingsButton, 'click', () => this.selectTab('settings'))
    this.addListener(this.$fieldLayoutButton, 'click', () => this.selectTab('fieldLayout'))
  },

  addItem (item, index = -1) {
    const settings = item.getSettings()

    this._insertAt(item.$container, index)
    this._itemSort.addItems(item.$container)

    if (settings) {
      this.$settingsContainer.append(settings.$container)

      if (item instanceof BlockType) {
        settings.initUi()
      }
    }

    this.$mainContainer.removeClass('hidden')

    this.addListener(item.$container, 'click', '@selectItem')
    item.on('destroy.configurator', () => this.removeItem(item, false))

    if (item instanceof BlockType) {
      this._addFieldLayout(item.getFieldLayout())
    }

    this._items.push(item)
    this._updateItemOrder()

    if (item instanceof BlockType) {
      for (const blockType of this.getBlockTypes()) {
        const btSettings = blockType.getSettings()
        if (btSettings) btSettings.addChildBlockType(item)
      }
    }

    this.trigger('addItem', {
      item,
      index
    })
  },

  _addFieldLayout (fieldLayout) {
    if (fieldLayout) {
      this.$fieldLayoutContainer.append(fieldLayout.$container)
    }
  },

  removeItem (item, showConfirm) {
    showConfirm = (typeof showConfirm === 'boolean' ? showConfirm : false)

    if (showConfirm) {
      const message = Craft.t('neo', 'Are you sure you want to delete this {type}?', {
        type:
        item instanceof BlockType
          ? 'block type'
          : item instanceof Group
            ? 'group'
            : 'item'
      })

      if (window.confirm(message)) {
        this.removeItem(item, false)
      }
    } else {
      const settings = item.getSettings()

      this._itemSort.removeItems(item.$container)

      item.$container.remove()
      if (settings) settings.$container.remove()

      if (item instanceof BlockType) {
        const fieldLayout = item.getFieldLayout()
        if (fieldLayout) fieldLayout.$container.remove()
      }

      this.removeListener(item.$container, 'click')
      item.off('.configurator')

      this._updateItemOrder()

      if (this._items.length === 0) {
        this.$mainContainer.addClass('hidden')
      }

      this.trigger('removeItem', {
        item
      })
    }
  },

  getItems () {
    return Array.from(this._items)
  },

  getItemByElement ($element) {
    return this._items.find(item => item.$container.is($element))
  },

  getSelectedItem () {
    return this._items.find(item => item.isSelected())
  },

  selectItem (item, focusInput) {
    focusInput = (typeof focusInput === 'boolean' ? focusInput : true)

    const settings = item ? item.getSettings() : null

    for (const i of this._items) {
      const thisIsTheItem = i === item
      i.toggleSelect(thisIsTheItem)

      if (thisIsTheItem) {
        const itemIsGroup = !(i instanceof BlockType)
        this.$fieldLayoutButton.toggleClass('hidden', itemIsGroup)

        if (itemIsGroup) {
          this.selectTab('settings')
        }
      }
    }

    if (focusInput && settings && !Garnish.isMobileBrowser()) {
      setTimeout(() => settings.getFocusInput().focus(), 100)
    }
  },

  getBlockTypes () {
    return this._items.filter(item => item instanceof BlockType)
  },

  getGroups () {
    return this._items.filter(item => item instanceof Group)
  },

  selectTab (tab) {
    this.$settingsContainer.toggleClass('hidden', tab !== 'settings')
    this.$fieldLayoutContainer.toggleClass('hidden', tab !== 'fieldLayout')

    this.$settingsButton.toggleClass('is-selected', tab === 'settings')
    this.$fieldLayoutButton.toggleClass('is-selected', tab === 'fieldLayout')
  },

  _getNewBlockTypeSettingsHtml (blockTypeId, sortOrder) {
    return this._blockTypeSettingsHtml
      .replace(/__NEOBLOCKTYPE_ID__/g, blockTypeId)
      .replace(/__NEOBLOCKTYPE_SORTORDER__/, sortOrder)
  },

  _getNewBlockTypeSettingsJs (blockTypeId) {
    return this._blockTypeSettingsJs.replace(/__NEOBLOCKTYPE_ID__/g, blockTypeId)
  },

  _getNewFieldLayoutHtml () {
    return this._fieldLayoutHtml.replace(
      /&quot;uid&quot;:&quot;([a-f0-9-]+)&quot;/,
      `&quot;uid&quot;:&quot;${uuidv4()}&quot;`
    )
  },

  _updateItemOrder () {
    const items = []

    this._itemSort.$items.each((index, element) => {
      const item = this.getItemByElement(element)

      if (item) {
        const settings = item.getSettings()
        if (settings) settings.setSortOrder(index + 1)

        items.push(item)
      }
    })

    this._items = items
  },

  _createBlockTypeFrom (oldBlockType) {
    const namespace = [...this._templateNs, 'blockTypes']
    const id = BlockTypeSettings.getNewId()
    const selectedItem = this.getSelectedItem()
    const selectedIndex = selectedItem ? selectedItem.getSettings().getSortOrder() : -1

    if (oldBlockType === null) {
      const settings = new BlockTypeSettings({
        childBlockTypes: this.getBlockTypes(),
        id,
        namespace: [...namespace, id],
        sortOrder: this._items.length,
        html: this._getNewBlockTypeSettingsHtml(id, selectedIndex),
        js: this._getNewBlockTypeSettingsJs(id)
      })
      const fieldLayout = new BlockTypeFieldLayout({
        blockTypeId: id,
        html: this._getNewFieldLayoutHtml(),
        namespace: [...namespace, id]
      })

      this._initBlockType(namespace, settings, fieldLayout, selectedIndex)
    } else {
      const oldSettings = oldBlockType.getSettings()
      const settings = new BlockTypeSettings({
        childBlocks: oldSettings.getChildBlocks(),
        childBlockTypes: this.getBlockTypes(),
        // Set a timestamp on the handle so it doesn't clash with the old one
        handle: `${oldSettings.getHandle()}_${Date.now()}`,
        id,
        maxBlocks: oldSettings.getMaxBlocks(),
        minChildBlocks: oldSettings.getMinChildBlocks(),
        maxChildBlocks: oldSettings.getMaxChildBlocks(),
        maxSiblingBlocks: oldSettings.getMaxSiblingBlocks(),
        name: oldSettings.getName(),
        description: oldSettings.getDescription(),
        namespace: [...namespace, id],
        sortOrder: this._items.length,
        topLevel: oldSettings.getTopLevel(),
        html: this._getNewBlockTypeSettingsHtml(id, selectedIndex),
        js: this._getNewBlockTypeSettingsJs(id)
      })
      const $spinner = $('<div class="nc_sidebar_list_item type-spinner"><span class="spinner"></span></div>')
      this._insertAt($spinner, selectedIndex)

      oldBlockType.loadFieldLayout()
        .then(() => {
          const layout = oldBlockType.getFieldLayout().getConfig()

          if (layout.tabs.length > 0) {
            const data = { layout }

            Craft.queue.push(() => new Promise((resolve, reject) => {
              Craft.sendActionRequest('POST', 'neo/configurator/render-field-layout', { data })
                .then(response => {
                  const fieldLayout = new BlockTypeFieldLayout({
                    blockTypeId: id,
                    html: response.data.html,
                    namespace: [...namespace, id]
                  })

                  this.$blockTypesContainer.find('.type-spinner').remove()
                  this._initBlockType(namespace, settings, fieldLayout, selectedIndex)
                  resolve()
                })
                .catch(reject)
            }))
          } else {
            const fieldLayout = new BlockTypeFieldLayout({
              blockTypeId: id,
              html: this._getNewFieldLayoutHtml(),
              namespace: [...namespace, id]
            })

            this.$blockTypesContainer.find('.type-spinner').remove()
            this._initBlockType(namespace, settings, fieldLayout, selectedIndex)
          }
        })
        .catch(() => Craft.cp.displayError(Craft.t('neo', 'Couldn’t create new block type.')))
    }
  },

  _initBlockType (namespace, settings, fieldLayout, index) {
    const blockType = new BlockType({ namespace, settings, fieldLayout })

    this.addItem(blockType, index)
    this.selectItem(blockType)
    this.selectTab('settings')

    blockType.on('copy.configurator', () => this._copyBlockType(blockType))
    blockType.on('paste.configurator', () => this._pasteBlockType())
    blockType.on('clone.configurator', () => this._createBlockTypeFrom(blockType))
  },

  _copyBlockType (blockType) {
    blockType.loadFieldLayout()
      .then(() => {
        const settings = blockType.getSettings()
        const data = {
          childBlocks: settings.getChildBlocks(),
          handle: settings.getHandle(),
          layout: blockType.getFieldLayout().getConfig(),
          maxBlocks: settings.getMaxBlocks(),
          minChildBlocks: settings.getMinChildBlocks(),
          maxChildBlocks: settings.getMaxChildBlocks(),
          maxSiblingBlocks: settings.getMaxSiblingBlocks(),
          name: settings.getName(),
          topLevel: settings.getTopLevel()
        }

        window.localStorage.setItem('neo:copyBlockType', JSON.stringify(data))
        this.getBlockTypes().forEach(bt => bt.$actionsMenu.find('[data-action="paste"]').parent().removeClass('disabled'))
      })
      .catch(() => Craft.cp.displayError(Craft.t('neo', 'Couldn’t copy block type.')))
  },

  _pasteBlockType () {
    const encodedData = window.localStorage.getItem('neo:copyBlockType')

    if (!encodedData) {
      return
    }

    const data = JSON.parse(encodedData)
    const blockTypeHandles = this.getBlockTypes().map(bt => bt.getSettings().getHandle())
    const childBlocks = Array.isArray(data.childBlocks)
      ? data.childBlocks.filter(cb => blockTypeHandles.includes(cb))
      : (data.childBlocks ? true : [])
    const settings = new BlockTypeSettings({
      childBlocks,
      childBlockTypes: this.getBlockTypes(),
      handle: data.handle,
      maxBlocks: data.maxBlocks,
      minChildBlocks: data.minChildBlocks,
      maxChildBlocks: data.maxChildBlocks,
      maxSiblingBlocks: data.maxSiblingBlocks,
      name: data.name,
      topLevel: data.topLevel
    })

    const fieldLayout = new BlockTypeFieldLayout({
      html: this._getNewFieldLayoutHtml(),
      layout: data.layout
    })

    const blockType = new BlockType({
      settings,
      fieldLayout
    })

    this._createBlockTypeFrom(blockType)
  },

  _insertAt (element, index) {
    const $element = $(element)

    if (index >= 0 && index < this._items.length) {
      $element.insertAt(index, this.$blockTypesContainer)
    } else {
      this.$blockTypesContainer.append($element)
    }
  },

  '@newBlockType' () {
    this._createBlockTypeFrom(null)
  },

  '@newGroup' () {
    const namespace = [...this._templateNs, 'groups']
    const id = GroupSettings.getNewId()

    const settings = new GroupSettings({
      namespace: [...namespace, id],
      sortOrder: this._items.length,
      id
    })

    const group = new Group({
      namespace,
      settings
    })

    const selected = this.getSelectedItem()
    const index = selected ? selected.getSettings().getSortOrder() : -1

    this.addItem(group, index)
    this.selectItem(group)
  },

  '@selectItem' (e) {
    const item = this.getItemByElement(e.currentTarget)

    this.selectItem(item)
  }
})
